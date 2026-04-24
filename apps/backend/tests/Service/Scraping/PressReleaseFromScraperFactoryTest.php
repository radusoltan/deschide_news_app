<?php

declare(strict_types=1);

namespace App\Tests\Service\Scraping;

use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Service\ContentHasher;
use App\Service\Scraping\PressReleaseFromScraperFactory;
use PHPUnit\Framework\TestCase;

class PressReleaseFromScraperFactoryTest extends TestCase
{
    private PressReleaseFromScraperFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new PressReleaseFromScraperFactory(new ContentHasher());
    }

    public function testCreateSetsBasicFields(): void
    {
        $item = $this->makeItem();

        $pr = $this->factory->create($item);

        self::assertSame('Test Government Decision', $pr->getTitle());
        self::assertSame('Full content of the press release here.', $pr->getContent());
        self::assertSame('https://gov.md/article/123', $pr->getSourceUrl());
        self::assertSame('python:gov.md', $pr->getSourceName());
        self::assertSame('ro', $pr->getOriginalLanguage());
    }

    public function testCreateSetsStatusPending(): void
    {
        $pr = $this->factory->create($this->makeItem());

        self::assertSame(PressReleaseStatus::PENDING, $pr->getStatus());
    }

    public function testCreateSetsSourceTypeScrape(): void
    {
        $pr = $this->factory->create($this->makeItem());

        self::assertSame(SourceType::SCRAPE, $pr->getSourceType());
    }

    public function testCreateGeneratesContentHash(): void
    {
        $pr = $this->factory->create($this->makeItem());

        self::assertNotNull($pr->getContentHash());
        self::assertSame(64, \strlen($pr->getContentHash())); // SHA-256 hex
    }

    public function testCreateSetsLeadFromExcerpt(): void
    {
        $item = $this->makeItem(['excerpt' => 'Short summary of the article']);

        $pr = $this->factory->create($item);

        self::assertSame('Short summary of the article', $pr->getLead());
    }

    public function testCreateLeadNullWhenNoExcerpt(): void
    {
        $item = $this->makeItem(['excerpt' => null]);

        $pr = $this->factory->create($item);

        self::assertNull($pr->getLead());
    }

    public function testCreateSetsReceivedAtFromPublishedAt(): void
    {
        $item = $this->makeItem(['published_at' => '2026-04-15T10:30:00+00:00']);

        $pr = $this->factory->create($item);

        self::assertSame('2026-04-15', $pr->getReceivedAt()->format('Y-m-d'));
    }

    public function testCreateHandlesInvalidPublishedAt(): void
    {
        $item = $this->makeItem(['published_at' => 'not-a-date']);

        $pr = $this->factory->create($item);

        // Should fallback to default (now)
        self::assertSame(date('Y-m-d'), $pr->getReceivedAt()->format('Y-m-d'));
    }

    public function testCreateTruncatesLongTitle(): void
    {
        $longTitle = str_repeat('A', 300);
        $item = $this->makeItem(['title' => $longTitle]);

        $pr = $this->factory->create($item);

        self::assertSame(255, mb_strlen($pr->getTitle()));
    }

    public function testSourceNamePrefixedWithPython(): void
    {
        $item = $this->makeItem(['source_name' => 'cancelaria.gov.md']);

        $pr = $this->factory->create($item);

        self::assertSame('python:cancelaria.gov.md', $pr->getSourceName());
    }

    public function testCreateSetsCategorySlugGeneral(): void
    {
        $pr = $this->factory->create($this->makeItem());

        self::assertSame('general', $pr->getCategorySlug());
    }

    public function testSameContentProducesSameHash(): void
    {
        $item1 = $this->makeItem(['content' => 'Identical content for both items.']);
        $item2 = $this->makeItem(['content' => 'Identical content for both items.', 'source_url' => 'https://other.md/1']);

        $pr1 = $this->factory->create($item1);
        $pr2 = $this->factory->create($item2);

        self::assertSame($pr1->getContentHash(), $pr2->getContentHash());
    }

    public function testDifferentContentProducesDifferentHash(): void
    {
        $item1 = $this->makeItem(['content' => 'First article content.']);
        $item2 = $this->makeItem(['content' => 'Completely different content.']);

        $pr1 = $this->factory->create($item1);
        $pr2 = $this->factory->create($item2);

        self::assertNotSame($pr1->getContentHash(), $pr2->getContentHash());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{title: string, content: string, source_url: string, source_name: string, published_at: ?string, language: string, attachments: list<string>, excerpt: ?string}
     */
    private function makeItem(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Government Decision',
            'content' => 'Full content of the press release here.',
            'source_url' => 'https://gov.md/article/123',
            'source_name' => 'gov.md',
            'published_at' => null,
            'language' => 'ro',
            'attachments' => [],
            'excerpt' => null,
        ], $overrides);
    }
}
