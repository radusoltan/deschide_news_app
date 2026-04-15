<?php

declare(strict_types=1);

namespace App\MessageHandler\Scraping;

use App\Message\Scraping\ScrapeFullContentMessage;
use App\Repository\PressReleaseRepository;
use App\Service\Aggregator\RemoteContentFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ScrapeFullContentHandler
{
    public function __construct(
        private PressReleaseRepository $repository,
        private RemoteContentFetcher $contentFetcher,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ScrapeFullContentMessage $message): void
    {
        $pr = $this->repository->find($message->pressReleaseId);
        if ($pr === null) {
            $this->logger->warning('ScrapeFullContentHandler: PressRelease not found', [
                'id' => $message->pressReleaseId,
            ]);

            return;
        }

        // Skip if already enriched
        if ($pr->getEnrichedAt() !== null) {
            $this->logger->debug('ScrapeFullContentHandler: already enriched, skipping', [
                'id' => $pr->getId(),
            ]);

            return;
        }

        $result = $this->contentFetcher->fetchAndExtract($message->sourceUrl);
        if ($result === null) {
            $this->logger->info('ScrapeFullContentHandler: fetch failed', [
                'id' => $pr->getId(),
                'url' => mb_substr($message->sourceUrl, 0, 100),
            ]);

            return;
        }

        // Only update if extracted content is longer than current
        $currentLength = $pr->getContentLength();
        $newLength = mb_strlen(strip_tags($result->htmlContent));

        if ($newLength <= $currentLength) {
            $this->logger->debug('ScrapeFullContentHandler: extracted content not longer ({new} <= {current})', [
                'id' => $pr->getId(),
                'new' => $newLength,
                'current' => $currentLength,
            ]);

            return;
        }

        $pr->setContent($result->htmlContent);
        $pr->setEnrichedAt(new \DateTimeImmutable());

        if ($result->excerpt !== null && $result->excerpt !== '' && $pr->getLead() === null) {
            $pr->setLead(mb_substr($result->excerpt, 0, 500));
        }

        if ($result->imageUrl !== null && $pr->getSourceImageUrl() === null) {
            $pr->setSourceImageUrl($result->imageUrl);
        }

        $this->em->flush();

        $this->logger->info('ScrapeFullContentHandler: enriched PR #{id} ({old} → {new} chars, {words} words)', [
            'id' => $pr->getId(),
            'old' => $currentLength,
            'new' => $pr->getContentLength(),
            'words' => $result->wordCount,
        ]);
    }
}
