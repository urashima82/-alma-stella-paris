<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SourcePhoto;
use App\Enum\PhotoAngle;
use App\Enum\VisualWorkflowStatus;
use App\Repository\ProductRepository;
use App\Service\Visual\ImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-catalogue-images',
    description: 'Import product images from docs/Catalogue/photos/ as SourcePhoto entities via Flysystem.',
)]
final class ImportCatalogueImagesCommand extends Command
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ImageStorage $imageStorage,
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be imported without actually processing images');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $csvPath = $this->projectDir.'/docs/Catalogue/catalogue.csv';
        $photosDir = $this->projectDir.'/docs/Catalogue/photos';

        if (!\file_exists($csvPath)) {
            $io->error('Catalogue CSV not found: '.$csvPath);

            return Command::FAILURE;
        }

        if (!\is_dir($photosDir)) {
            $io->error('Photos directory not found: '.$photosDir);

            return Command::FAILURE;
        }

        // Index products by nameFr
        $products = $this->productRepository->findAll();
        $productsByNameFr = [];
        foreach ($products as $product) {
            $productsByNameFr[$product->getNameFr()] = $product;
        }

        // Read CSV to build product -> photo directory mapping
        $catalogue = $this->parseCsv($csvPath);

        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $totalPhotos = 0;

        foreach ($catalogue as $entry) {
            $nameFr = $entry['name'];
            $category = $entry['category'];
            $subcategory = $entry['subcategory'];

            $product = $productsByNameFr[$nameFr] ?? null;
            if ($product === null) {
                $io->warning(\sprintf('Product not found in database: "%s"', $nameFr));
                ++$skipped;

                continue;
            }

            // Find the photo directory: photos/{category}/{subcategory}/{productName}/
            $photoDir = \sprintf('%s/%s/%s/%s', $photosDir, $category, $subcategory, $nameFr);

            if (!\is_dir($photoDir)) {
                $io->warning(\sprintf('No photo directory for: "%s" (expected: %s)', $nameFr, $photoDir));
                ++$skipped;

                continue;
            }

            // List all photos sorted by filename (timestamp-based, oldest first)
            $photos = $this->getPhotosSorted($photoDir);

            if ($photos === []) {
                $io->warning(\sprintf('No photos found for: "%s"', $nameFr));
                ++$skipped;

                continue;
            }

            $io->text(\sprintf('<info>%s</info> — %d photo(s)', $nameFr, \count($photos)));

            // Import each photo as a SourcePhoto
            foreach ($photos as $position => $sourcePath) {
                $angle = $position === 0 ? PhotoAngle::Front : PhotoAngle::Other;

                if ($dryRun) {
                    $io->text(\sprintf('  [DRY-RUN] #%d %s → %s', $position + 1, \basename($sourcePath), $angle->label()));
                    ++$totalPhotos;

                    continue;
                }

                try {
                    $flysystemPath = $this->imageStorage->storeSourcePhotoFromPath(
                        $sourcePath,
                        $product,
                        $position + 1,
                    );

                    $sourcePhoto = new SourcePhoto();
                    $sourcePhoto->setProduct($product);
                    $sourcePhoto->setPath($flysystemPath);
                    $sourcePhoto->setPosition($position + 1);
                    $sourcePhoto->setAngle($angle);

                    $this->em->persist($sourcePhoto);
                    $product->addSourcePhoto($sourcePhoto);

                    $io->text(\sprintf('  + #%d %s → %s (%s)', $position + 1, \basename($sourcePath), $flysystemPath, $angle->label()));
                    ++$totalPhotos;
                } catch (\RuntimeException $e) {
                    $io->error(\sprintf('Failed: %s photo #%d — %s', $nameFr, $position + 1, $e->getMessage()));
                    ++$errors;
                }
            }

            // Update visual workflow status
            if (!$dryRun && $product->getSourcePhotos()->count() > 0) {
                $product->setVisualStatus(VisualWorkflowStatus::PendingVisuals);
            }

            ++$processed;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->newLine();
        $io->success(\sprintf(
            '%s%d products processed (%d source photos), %d skipped, %d errors.',
            $dryRun ? '[DRY-RUN] ' : '',
            $processed,
            $totalPhotos,
            $skipped,
            $errors,
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Parse the catalogue CSV into an array of entries.
     *
     * @return list<array{name: string, category: string, subcategory: string}>
     */
    private function parseCsv(string $csvPath): array
    {
        $handle = \fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open CSV: '.$csvPath);
        }

        // Skip header
        \fgetcsv($handle, 0, ';');

        $entries = [];
        while (($row = \fgetcsv($handle, 0, ';')) !== false) {
            if (\count($row) < 8) {
                continue;
            }

            $entries[] = [
                'name' => $row[3],
                'category' => $row[1],
                'subcategory' => $row[2],
            ];
        }

        \fclose($handle);

        return $entries;
    }

    /**
     * Get photo file paths sorted by filename (oldest timestamp first).
     *
     * @return list<string>
     */
    private function getPhotosSorted(string $directory): array
    {
        $photos = [];

        /** @var \DirectoryIterator $file */
        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            $ext = \strtolower($file->getExtension());
            if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            $photos[] = $file->getRealPath();
        }

        // Sort by filename (timestamp-based names like 20260409_122601.jpg)
        \sort($photos);

        return $photos;
    }
}
