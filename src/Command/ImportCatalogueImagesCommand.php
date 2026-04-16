<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ProductRepository;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-catalogue-images',
    description: 'Import product images from docs/Catalogue/photos/ into public/uploads/products/.',
)]
final class ImportCatalogueImagesCommand extends Command
{
    /** wornPhoto: 4:5 ratio, max 800×1000 */
    private const WORN_MAX_WIDTH = 800;
    private const WORN_MAX_HEIGHT = 1000;

    /** thumbnail: 4:5 ratio, max 600×750 */
    private const THUMB_MAX_WIDTH = 600;
    private const THUMB_MAX_HEIGHT = 750;

    /** contextPhoto: 4:5 ratio, max 800×1000 */
    private const CONTEXT_MAX_WIDTH = 800;
    private const CONTEXT_MAX_HEIGHT = 1000;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ImageProcessor $imageProcessor,
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
        $targetDir = $this->projectDir.'/public/uploads/products';

        if (!\file_exists($csvPath)) {
            $io->error('Catalogue CSV not found: '.$csvPath);

            return Command::FAILURE;
        }

        if (!\is_dir($photosDir)) {
            $io->error('Photos directory not found: '.$photosDir);

            return Command::FAILURE;
        }

        if (!$dryRun && !\is_dir($targetDir)) {
            \mkdir($targetDir, 0o755, true);
        }

        // Index products by nameFr
        $products = $this->productRepository->findAll();
        $productsByNameFr = [];
        foreach ($products as $product) {
            $productsByNameFr[$product->getNameFr()] = $product;
        }

        // Read CSV to build product → photo directory mapping
        $catalogue = $this->parseCsv($csvPath);

        $processed = 0;
        $skipped = 0;
        $errors = 0;

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

            // List photos sorted by filename (timestamp-based, oldest first)
            $photos = $this->getPhotosSorted($photoDir);

            if ($photos === []) {
                $io->warning(\sprintf('No photos found for: "%s"', $nameFr));
                ++$skipped;

                continue;
            }

            $slug = $product->getSlug();

            // Photo assignment: 1st → wornPhoto, 2nd → thumbnail, 3rd → contextPhoto
            $assignments = [
                ['index' => 0, 'field' => 'wornPhoto', 'setter' => 'setWornPhoto', 'maxW' => self::WORN_MAX_WIDTH, 'maxH' => self::WORN_MAX_HEIGHT],
                ['index' => 1, 'field' => 'thumbnail', 'setter' => 'setThumbnail', 'maxW' => self::THUMB_MAX_WIDTH, 'maxH' => self::THUMB_MAX_HEIGHT],
                ['index' => 2, 'field' => 'contextPhoto', 'setter' => 'setContextPhoto', 'maxW' => self::CONTEXT_MAX_WIDTH, 'maxH' => self::CONTEXT_MAX_HEIGHT],
            ];

            foreach ($assignments as $assignment) {
                if (!isset($photos[$assignment['index']])) {
                    continue;
                }

                $sourcePath = $photos[$assignment['index']];
                $webpFilename = \sprintf('%s-%s.webp', $slug, $assignment['field']);
                $targetPath = $targetDir.'/'.$webpFilename;

                if ($dryRun) {
                    $io->text(\sprintf('  [DRY-RUN] %s → %s (%s)', \basename($sourcePath), $webpFilename, $assignment['field']));

                    continue;
                }

                try {
                    $this->imageProcessor->processWithCrop(
                        $sourcePath,
                        $targetPath,
                        $assignment['maxW'],
                        $assignment['maxH'],
                    );

                    $product->{$assignment['setter']}($webpFilename);
                    $io->text(\sprintf('  ✓ %s → %s (%s)', \basename($sourcePath), $webpFilename, $assignment['field']));
                } catch (\RuntimeException $e) {
                    $io->error(\sprintf('Failed: %s — %s', $nameFr, $e->getMessage()));
                    ++$errors;
                }
            }

            ++$processed;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->newLine();
        $io->success(\sprintf(
            '%s%d products processed, %d skipped, %d errors.',
            $dryRun ? '[DRY-RUN] ' : '',
            $processed,
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
