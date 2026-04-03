<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Message\Editorial\GenerateDossiersMessage;
use App\Service\Editorial\DossierGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateDossiersHandler
{
    public function __construct(
        private DossierGenerationService $dossierService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        #[Autowire('%env(default::VAULT_PATH)%')] private string $vaultPath = '',
    ) {}

    public function __invoke(GenerateDossiersMessage $message): void
    {
        if ($this->vaultPath === '') {
            $this->logger->warning('GenerateDossiersHandler: VAULT_PATH not configured, skipping');

            return;
        }

        $this->logger->info('GenerateDossiersHandler: detecting MOCs needing dossiers', [
            'threshold' => $message->threshold,
        ]);

        $mocs = $this->dossierService->detectMOCsNeedingDossier($this->vaultPath, $message->threshold);

        if ($mocs === []) {
            $this->logger->info('GenerateDossiersHandler: no MOCs above threshold');

            return;
        }

        $since = new \DateTimeImmutable("-{$message->days} days");
        $generated = 0;

        foreach ($mocs as $moc) {
            $articles = $this->em->getRepository(Article::class)->createQueryBuilder('a')
                ->where('a.status = :status')
                ->andWhere('a.publishedAt >= :since')
                ->setParameter('status', ArticleStatus::PUBLISHED)
                ->setParameter('since', $since)
                ->orderBy('a.publishedAt', 'DESC')
                ->setMaxResults(50)
                ->getQuery()
                ->getResult();

            $result = $this->dossierService->generateDossier($moc['path'], $articles, $this->vaultPath);

            if ($result !== null) {
                ++$generated;
            }
        }

        $this->logger->info('GenerateDossiersHandler: dossier generation complete', [
            'mocsProcessed' => \count($mocs),
            'dossiersGenerated' => $generated,
        ]);
    }
}
