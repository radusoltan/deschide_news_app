<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Editorial\Monitor;

use App\Enum\EditorialAlignment;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\Editorial\VerifiedSourceRepository;
use App\Service\ContentHasher;
use App\Service\Editorial\Monitor\AbstractRssMonitor;
use App\Service\Editorial\Monitor\UrlNormalizer;
use App\Service\Scraping\RssFeedParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Test-only concrete subclass of AbstractRssMonitor — exposes a configurable
 * alignment filter so the generic pipeline can be exercised without depending
 * on the Sprint 53 production subclasses (WireSourceMonitor, MediaRoSourceMonitor).
 */
final class InTestRssMonitor extends AbstractRssMonitor
{
    /**
     * @param list<EditorialAlignment> $alignments
     */
    public function __construct(
        private readonly array $alignments,
        HttpClientInterface $httpClient,
        RssFeedParser $rssFeedParser,
        ContentHasher $contentHasher,
        UrlNormalizer $urlNormalizer,
        MessageBusInterface $messageBus,
        EntityManagerInterface $entityManager,
        VerifiedSourceRepository $verifiedSourceRepository,
        SourceSignalRepository $sourceSignalRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct(
            $httpClient,
            $rssFeedParser,
            $contentHasher,
            $urlNormalizer,
            $messageBus,
            $entityManager,
            $verifiedSourceRepository,
            $sourceSignalRepository,
            $logger,
        );
    }

    protected function getAlignmentFilters(): array
    {
        return $this->alignments;
    }
}
