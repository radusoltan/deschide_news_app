<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\Scraping;

use App\Entity\PressRelease;
use App\Message\Scraping\PythonScrapeMessage;
use App\MessageHandler\Scraping\PythonScrapeHandler;
use App\Repository\PressReleaseRepository;
use App\Service\ContentHasher;
use App\Service\Scraping\PressReleaseFromScraperFactory;
use App\Service\Scraping\PythonScraperException;
use App\Service\Scraping\PythonScraperService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PythonScrapeHandlerTest extends TestCase
{
    private PythonScraperService&MockObject $scraperService;
    private PressReleaseFromScraperFactory&MockObject $factory;
    private PressReleaseRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private ContentHasher $contentHasher;
    private PythonScrapeHandler $handler;

    protected function setUp(): void
    {
        $this->scraperService = $this->createMock(PythonScraperService::class);
        $this->factory = $this->createMock(PressReleaseFromScraperFactory::class);
        $this->repository = $this->createMock(PressReleaseRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->contentHasher = new ContentHasher();

        $this->handler = new PythonScrapeHandler(
            $this->scraperService,
            $this->factory,
            $this->repository,
            $this->contentHasher,
            $this->em,
            new NullLogger(),
        );
    }

    public function testHandlerCreatesNewPressRelease(): void
    {
        $message = new PythonScrapeMessage('gov_md', 'https://gov.md/ro/rss.xml', 'gov-rss', 5);

        $this->scraperService->method('fetch')->willReturn([
            'source_type' => 'gov-rss',
            'items' => [$this->makeItem()],
            'errors' => [],
            'scraped_at' => '2026-04-15T10:00:00Z',
        ]);

        $this->repository->method('findBySourceUrl')->willReturn(null);
        $this->repository->method('findByContentHash')->willReturn(null);

        $this->factory->method('create')->willReturn(new PressRelease());

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        ($this->handler)($message);
    }

    public function testHandlerSkipsDuplicateUrl(): void
    {
        $message = new PythonScrapeMessage('gov_md', 'https://gov.md/ro/rss.xml', 'gov-rss');

        $this->scraperService->method('fetch')->willReturn([
            'source_type' => 'gov-rss',
            'items' => [$this->makeItem()],
            'errors' => [],
            'scraped_at' => '2026-04-15T10:00:00Z',
        ]);

        // URL already exists
        $this->repository->method('findBySourceUrl')->willReturn(new PressRelease());

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');

        ($this->handler)($message);
    }

    public function testHandlerSkipsDuplicateHash(): void
    {
        $message = new PythonScrapeMessage('gov_md', 'https://gov.md/ro/rss.xml', 'gov-rss');

        $this->scraperService->method('fetch')->willReturn([
            'source_type' => 'gov-rss',
            'items' => [$this->makeItem()],
            'errors' => [],
            'scraped_at' => '2026-04-15T10:00:00Z',
        ]);

        $this->repository->method('findBySourceUrl')->willReturn(null);
        // Hash already exists
        $this->repository->method('findByContentHash')->willReturn(new PressRelease());

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');

        ($this->handler)($message);
    }

    public function testHandlerRethrowsScraperException(): void
    {
        $message = new PythonScrapeMessage('gov_md', 'https://gov.md/ro/rss.xml', 'gov-rss');

        $this->scraperService->method('fetch')->willThrowException(
            new PythonScraperException('Timeout', isTimeout: true),
        );

        $this->expectException(PythonScraperException::class);

        ($this->handler)($message);
    }

    public function testHandlerHandlesEmptyResults(): void
    {
        $message = new PythonScrapeMessage('gov_md', 'https://gov.md/ro/rss.xml', 'gov-rss');

        $this->scraperService->method('fetch')->willReturn([
            'source_type' => 'gov-rss',
            'items' => [],
            'errors' => [],
            'scraped_at' => '2026-04-15T10:00:00Z',
        ]);

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');

        ($this->handler)($message);
    }

    /**
     * @return array{title: string, content: string, source_url: string, source_name: string, published_at: ?string, language: string, attachments: list<string>, excerpt: ?string}
     */
    private function makeItem(): array
    {
        return [
            'title' => 'Test Government Decision',
            'content' => 'Full content of the press release here.',
            'source_url' => 'https://gov.md/article/123',
            'source_name' => 'gov.md',
            'published_at' => null,
            'language' => 'ro',
            'attachments' => [],
            'excerpt' => null,
        ];
    }
}
