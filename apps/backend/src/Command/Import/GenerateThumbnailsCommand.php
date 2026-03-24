<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Entity\Image;
use App\Entity\ThumbnailProfile;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:generate-thumbnails',
    description: 'Generate thumbnails for all imported images'
)]
class GenerateThumbnailsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageService $imageService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of images to process', null)
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset for pagination', 0)
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Batch size for processing', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $offset = (int) $input->getOption('offset');
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('FAZA 3b: Generare Thumbnails pentru Imagini');

        $io->section('Configuration');
        $io->definitionList(
            ['Limit' => $limit ?? 'ALL'],
            ['Offset' => $offset],
            ['Batch Size' => $batchSize],
        );

        // Step 1: Get all thumbnail profiles
        $io->section('Step 1: Load Thumbnail Profiles');
        $profiles = $this->entityManager->getRepository(ThumbnailProfile::class)->findAll();

        if (\count($profiles) === 0) {
            $io->error('No thumbnail profiles found! Please load fixtures first.');

            return Command::FAILURE;
        }

        $io->writeln(\sprintf('Found %d thumbnail profiles', \count($profiles)));
        foreach ($profiles as $profile) {
            $io->writeln(\sprintf(
                '  • %s (%dx%d, mode: %s)',
                $profile->getName(),
                $profile->getWidth(),
                $profile->getHeight(),
                $profile->getMode()->value
            ));
        }

        // Step 2: Get images without thumbnails
        $io->section('Step 2: Find Images Without Thumbnails');

        $qb = $this->entityManager->getRepository(Image::class)->createQueryBuilder('i');
        $qb->leftJoin('i.thumbnails', 't')
            ->where('t.id IS NULL')
            ->orderBy('i.id', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }
        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        $images = $qb->getQuery()->getResult();
        $totalImages = \count($images);

        $io->writeln(\sprintf('Found %d images without thumbnails (offset: %d)', $totalImages, $offset));

        if ($totalImages === 0) {
            $io->success('All images already have thumbnails!');

            return Command::SUCCESS;
        }

        // Step 3: Generate thumbnails
        $io->section('Step 3: Generate Thumbnails');

        $stats = [
            'total' => $totalImages,
            'success' => 0,
            'error' => 0,
            'thumbnails_generated' => 0,
        ];

        $io->progressStart($totalImages);

        foreach ($images as $index => $image) {
            try {
                $generatedCount = 0;

                // Generate thumbnail for each profile (webp format)
                foreach ($profiles as $profile) {
                    try {
                        $this->imageService->generateThumbnail($image, $profile, 'webp', null);
                        ++$generatedCount;
                    } catch (Exception $e) {
                        $io->writeln(
                            \sprintf('  [ERROR] Image %d, Profile %s: %s', $image->getId(), $profile->getName(), $e->getMessage()),
                            OutputInterface::VERBOSITY_VERBOSE
                        );
                    }
                }

                if ($generatedCount > 0) {
                    ++$stats['success'];
                    $stats['thumbnails_generated'] += $generatedCount;

                    $io->writeln(
                        \sprintf('  [OK] Image %d: generated %d thumbnails', $image->getId(), $generatedCount),
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                } else {
                    ++$stats['error'];
                }

                // Periodic GC to manage memory (do NOT call em->clear() because
                // ImageService::generateThumbnail() already flushes per thumbnail,
                // and clear() would detach Image/ThumbnailProfile entities still in use)
                if (($index + 1) % $batchSize === 0) {
                    gc_collect_cycles();
                }

            } catch (Exception $e) {
                $io->writeln(
                    \sprintf('  [ERROR] Image %d: %s', $image->getId(), $e->getMessage()),
                    OutputInterface::VERBOSITY_VERBOSE
                );
                ++$stats['error'];
            }

            $io->progressAdvance();
        }

        // Final flush (ImageService already flushes per thumbnail, but ensure nothing is left)
        $this->entityManager->flush();

        $io->progressFinish();

        // Step 4: Display statistics
        $io->section('Step 4: Generation Statistics');

        $io->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total Images', $stats['total'], '100%'],
                ['Success', $stats['success'], \sprintf('%.1f%%', ($stats['success'] / $stats['total']) * 100)],
                ['Error', $stats['error'], \sprintf('%.1f%%', ($stats['error'] / $stats['total']) * 100)],
                ['Total Thumbnails Generated', $stats['thumbnails_generated'], \sprintf('%.1f per image', $stats['thumbnails_generated'] / $stats['total'])],
            ]
        );

        if ($stats['error'] > 0) {
            $io->warning(\sprintf('%d images failed to generate thumbnails', $stats['error']));
        }

        if ($stats['success'] > 0) {
            $io->success(\sprintf('Successfully generated thumbnails for %d images!', $stats['success']));
        }

        // Check if there are more images to process
        $lastProcessedId = isset($image) ? $image->getId() : 0;
        $remainingImages = $this->entityManager->getRepository(Image::class)
            ->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->leftJoin('i.thumbnails', 't')
            ->where('t.id IS NULL')
            ->andWhere('i.id > :lastId')
            ->setParameter('lastId', $lastProcessedId)
            ->getQuery()
            ->getSingleScalarResult();

        if ($remainingImages > 0) {
            $io->note([
                \sprintf('There are still %d images without thumbnails.', $remainingImages),
                'To continue processing, use:',
                \sprintf('  symfony console app:import:generate-thumbnails --offset=%d', $offset + $totalImages),
            ]);
        }

        return Command::SUCCESS;
    }
}
