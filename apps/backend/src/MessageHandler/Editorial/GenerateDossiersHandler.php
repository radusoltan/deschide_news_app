<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Entity\Topic;
use App\Message\Editorial\GenerateDossiersMessage;
use App\Service\Editorial\DossierGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateDossiersHandler
{
    public function __construct(
        private DossierGenerationService $dossierService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateDossiersMessage $message): void
    {
        $this->logger->info('GenerateDossiersHandler: detecting topics needing dossiers', [
            'threshold' => $message->threshold,
        ]);

        // Get topics from DB
        $topics = $this->em->getRepository(Topic::class)->findAll();

        if ($topics === []) {
            $this->logger->info('GenerateDossiersHandler: no topics found');

            return;
        }

        $generated = 0;

        foreach ($topics as $topic) {
            $topicName = $topic->getName();
            $articles = $this->dossierService->getRecentArticlesForTopic($topicName, $message->days);

            if (\count($articles) < $message->threshold) {
                continue;
            }

            $result = $this->dossierService->generateDossier($topicName, $articles);

            if ($result !== null) {
                ++$generated;
            }
        }

        $this->logger->info('GenerateDossiersHandler: dossier generation complete', [
            'topicsProcessed' => \count($topics),
            'dossiersGenerated' => $generated,
        ]);
    }
}
