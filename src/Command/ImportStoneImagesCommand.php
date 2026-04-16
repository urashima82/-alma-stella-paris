<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\StoneRepository;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-stone-images',
    description: 'Process and import stone images from docs/design/stones/ into public/uploads/stones/.',
)]
final class ImportStoneImagesCommand extends Command
{
    private const MAX_WIDTH = 800;
    private const MAX_HEIGHT = 800;

    /**
     * Map source filenames (without extension) to Stone.nameFr.
     * Keys = filename in docs/design/stones/, Values = nameFr in database.
     *
     * @var array<string, string>
     */
    private const FILENAME_TO_NAME_FR = [
        'Onyx noir' => 'Onyx noir',
        'Lapis Lazuli' => 'Lapis Lazuli',
        'Turquoise' => 'Turquoise',
        'Malachite' => 'Malachite',
        'Cornaline' => 'Cornaline',
        'Grenat' => 'Grenat',
        'Labradorite' => 'Labradorite',
        'Amazonite' => 'Amazonite',
        'Rhodonite' => 'Rhodonite',
        'Pierre de Lune' => 'Pierre de Lune',
        'Péridot' => 'Péridot',
        'Quartz Rose' => 'Quartz Rose',
        'Quartz Fumé' => 'Quartz Fumé',
        'Aventurine verte' => 'Aventurine verte',
        'Sodalite' => 'Sodalite',
        'Jaspe Dalmatien' => 'Jaspe Dalmatien',
        'Opale Rose' => 'Opale Rose',
        'Quartz Vert' => 'Quartz Vert',
        'Nacre' => 'Nacre',
        'Perle' => 'Perle',
        'Abalone' => 'Abalone',
    ];

    public function __construct(
        private readonly StoneRepository $stoneRepository,
        private readonly ImageProcessor $imageProcessor,
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceDir = $this->projectDir.'/docs/design/stones';
        $targetDir = $this->projectDir.'/public/uploads/stones';

        if (!\is_dir($sourceDir)) {
            $io->error(\sprintf('Source directory not found: %s', $sourceDir));

            return Command::FAILURE;
        }

        if (!\is_dir($targetDir)) {
            \mkdir($targetDir, 0o755, true);
        }

        // Index stones by nameFr for quick lookup
        $stones = $this->stoneRepository->findAll();
        $stonesByNameFr = [];
        foreach ($stones as $stone) {
            $stonesByNameFr[$stone->getNameFr()] = $stone;
        }

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach (self::FILENAME_TO_NAME_FR as $filename => $nameFr) {
            $sourcePath = $sourceDir.'/'.$filename.'.png';

            if (!\file_exists($sourcePath)) {
                $io->warning(\sprintf('Source file not found: %s.png', $filename));
                ++$skipped;

                continue;
            }

            $stone = $stonesByNameFr[$nameFr] ?? null;
            if ($stone === null) {
                $io->warning(\sprintf('Stone not found in database: "%s"', $nameFr));
                ++$skipped;

                continue;
            }

            // Use the stone slug for a clean filename
            $webpFilename = $stone->getSlug().'.webp';
            $targetPath = $targetDir.'/'.$webpFilename;

            try {
                $this->imageProcessor->process($sourcePath, $targetPath, self::MAX_WIDTH, self::MAX_HEIGHT);
                $stone->setImageName($webpFilename);
                ++$processed;
                $io->text(\sprintf('  ✓ %s → %s', $filename.'.png', $webpFilename));
            } catch (\RuntimeException $e) {
                $io->error(\sprintf('Failed to process %s: %s', $filename, $e->getMessage()));
                ++$errors;
            }
        }

        $this->em->flush();

        $io->newLine();
        $io->success(\sprintf('%d images processed, %d skipped, %d errors.', $processed, $skipped, $errors));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
