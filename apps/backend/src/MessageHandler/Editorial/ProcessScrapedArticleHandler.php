<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Entity\PressRelease;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Message\Editorial\ProcessScrapedArticleMessage;
use App\Service\CategoryDetectorService;
use App\Service\ContentDeduplicator;
use App\Service\ContentHasher;
use App\Service\NotificationService;
use App\Service\ScrapedContentCleaner;
use App\Service\SourceAuthorResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessScrapedArticleHandler
{
    public function __construct(
        private ScrapedContentCleaner $contentCleaner,
        private ContentHasher $contentHasher,
        private ContentDeduplicator $deduplicator,
        private CategoryDetectorService $categoryDetector,
        private SourceAuthorResolver $authorResolver,
        private NotificationService $notificationService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessScrapedArticleMessage $message): void
    {
        // 1. Clean HTML content
        $cleanHtml = $this->contentCleaner->clean($message->bodyMarkdown);

        // 2. Hash for deduplication
        $hash = $this->contentHasher->hash($cleanHtml);

        // 3. Check for duplicates across PressRelease + Article tables
        $dupCheck = $this->deduplicator->isDuplicate($hash);
        if ($dupCheck->isDuplicate) {
            $this->logger->debug('ProcessScrapedArticleHandler: duplicate content, skipping', [
                'hash' => $hash,
                'existingEntity' => $dupCheck->existingEntityType,
                'existingId' => $dupCheck->existingEntityId,
            ]);

            return;
        }

        // 4. Extract lead from cleaned HTML
        $lead = $this->contentCleaner->extractLead($cleanHtml);

        // 5. Detect category
        $categorySlug = $this->categoryDetector->detectSlug($message->title . ' ' . $lead);

        // 6. Build source name slug
        $sourceName = 'scrape:' . $this->toSourceSlug($message->sourceName);

        // 7. Create PressRelease (NOT Article)
        $pr = new PressRelease();
        $pr->setTitle(mb_substr($message->title, 0, 255));
        $pr->setContent($cleanHtml);
        $pr->setLead(mb_substr($lead, 0, 300) ?: null);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setSourceType(SourceType::SCRAPE);
        $pr->setSourceName($sourceName);
        $pr->setSourceUrl($message->sourceUrl);
        $pr->setContentHash($hash);
        $pr->setOriginalLanguage($message->originalLanguage);
        $pr->setCategorySlug($categorySlug);

        if ($message->publishedAt !== null) {
            $pr->setReceivedAt($message->publishedAt);
        }

        // 8. Persist
        $this->em->persist($pr);
        $this->em->flush();

        $this->logger->info('ProcessScrapedArticleHandler: PressRelease created (pending review)', [
            'pressReleaseId' => $pr->getId(),
            'title' => mb_substr($message->title, 0, 80),
            'source' => $sourceName,
            'sourceType' => 'scrape',
        ]);

        // 9. Notify editors
        try {
            $this->notificationService->notify(
                type: NotificationType::PRESS_QUEUE_NEW,
                title: 'Articol scrapat nou în coadă',
                message: sprintf('"%s" de la %s', mb_substr($message->title, 0, 80), $message->sourceName),
                importance: NotificationImportance::MEDIUM,
                relatedEntityType: 'PressRelease',
                relatedEntityId: $pr->getId(),
                actionUrl: '/admin/press-queue',
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to send scrape notification', ['error' => $e->getMessage()]);
        }
    }

    private function toSourceSlug(string $sourceName): string
    {
        $slug = mb_strtolower($sourceName);
        $slug = str_replace([' ', '.', '-'], '_', $slug);
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug) ?? $slug;

        return mb_substr($slug, 0, 50);
    }
}
