<?php

declare(strict_types=1);

namespace App\MessageHandler\Scraping;

use App\Message\Scraping\PythonScrapeMessage;
use App\Message\Topic\DetectTopicsForPressReleaseMessage;
use App\Repository\PressReleaseRepository;
use App\Service\ContentHasher;
use App\Service\Scraping\PressReleaseFromScraperFactory;
use App\Service\Scraping\PythonScraperException;
use App\Service\Scraping\PythonScraperService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles PythonScrapeMessage: runs the Python scraper for a single source
 * and creates PressRelease entities with deduplication.
 */
#[AsMessageHandler]
class PythonScrapeHandler
{
    public function __construct(
        private readonly PythonScraperService $scraperService,
        private readonly PressReleaseFromScraperFactory $factory,
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly ContentHasher $contentHasher,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(PythonScrapeMessage $message): void
    {
        $this->logger->info('PythonScrapeHandler: processing source', [
            'source' => $message->sourceName,
            'url' => $message->sourceUrl,
            'type' => $message->sourceType,
        ]);

        try {
            $result = $this->scraperService->fetch(
                $message->sourceUrl,
                $message->sourceType,
                $message->limit,
            );
        } catch (PythonScraperException $e) {
            $this->logger->error('PythonScrapeHandler: scraper failed for {source}', [
                'source' => $message->sourceName,
                'error' => $e->getMessage(),
                'isTimeout' => $e->isTimeout(),
            ]);

            throw $e; // Let Messenger retry handle this
        }

        $items = $result['items'];
        $created = 0;
        $skippedUrl = 0;
        $skippedHash = 0;
        $newPressReleases = [];

        foreach ($items as $item) {
            // Dedup: sourceUrl
            if (!empty($item['source_url']) && $this->pressReleaseRepository->findBySourceUrl($item['source_url']) !== null) {
                ++$skippedUrl;
                continue;
            }

            // Dedup: contentHash
            $hash = $this->contentHasher->hash($item['content']);
            if ($this->pressReleaseRepository->findByContentHash($hash) !== null) {
                ++$skippedHash;
                continue;
            }

            $pr = $this->factory->create($item);
            $this->em->persist($pr);
            $newPressReleases[] = $pr;
            ++$created;
        }

        if ($created > 0) {
            try {
                $this->em->flush();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $this->logger->warning('PythonScrapeHandler: race condition dedup on flush', [
                    'source' => $message->sourceName,
                    'error' => $e->getMessage(),
                ]);
                $this->em->clear();

                return;
            }

            // Dispatch async topic detection for each new PR (ADR-015)
            foreach ($newPressReleases as $pr) {
                if ($pr->getId() !== null) {
                    $this->messageBus->dispatch(new DetectTopicsForPressReleaseMessage($pr->getId()));
                }
            }
        }

        $this->logger->info('PythonScrapeHandler: completed', [
            'source' => $message->sourceName,
            'scraped' => \count($items),
            'created' => $created,
            'skippedUrl' => $skippedUrl,
            'skippedHash' => $skippedHash,
        ]);
    }
}
