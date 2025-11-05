<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use App\Service\ImageElasticService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:index-images',
    description: 'Index all images in Elasticsearch'
)]
class ElasticsearchIndexImagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageElasticService $imageElasticService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->imageElasticService->isEnabled()) {
            $io->error('Elasticsearch is not enabled. Check your configuration.');

            return Command::FAILURE;
        }

        $io->title('Indexing Images in Elasticsearch');

        try {
            $repository = $this->entityManager->getRepository(Image::class);
            $images = $repository->findAll();

            $io->info(\sprintf('Found %d images to index', \count($images)));

            $indexed = 0;
            $io->progressStart(\count($images));

            foreach ($images as $image) {
                // Build suggest input from various fields
                $suggestInput = array_filter([
                    $image->getOriginalFilename(),
                    $image->getFilename(),
                    $image->getAlt(),
                ]);

                $document = [
                    'id' => $image->getId(),
                    'filename' => $image->getFilename(),
                    'originalFilename' => $image->getOriginalFilename(),
                    'alt' => $image->getAlt() ?? '',
                    'caption' => $image->getCaption() ?? '',
                    'description' => $image->getDescription() ?? '',
                    'mimeType' => $image->getMimeType(),
                    'size' => $image->getSize(),
                    'width' => $image->getWidth(),
                    'height' => $image->getHeight(),
                    'uploadedAt' => $image->getCreatedAt()?->format('Y-m-d\TH:i:s\Z'),
                    'suggest' => [
                        'input' => $suggestInput,
                        'weight' => 1,
                    ],
                ];

                $this->imageElasticService->indexDocument($document);
                ++$indexed;
                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success(\sprintf('Successfully indexed %d images!', $indexed));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Failed to index images: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
