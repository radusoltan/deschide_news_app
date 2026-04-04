<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\GeneratedContent;
use PHPUnit\Framework\TestCase;

class GeneratedContentTest extends TestCase
{
    public function testConstructor_setsGeneratedAt(): void
    {
        $before = new \DateTimeImmutable();
        $gc = new GeneratedContent();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($gc->getGeneratedAt());
        $this->assertGreaterThanOrEqual($before, $gc->getGeneratedAt());
        $this->assertLessThanOrEqual($after, $gc->getGeneratedAt());
    }

    public function testConstructor_idIsNull(): void
    {
        $gc = new GeneratedContent();

        $this->assertNull($gc->getId());
    }

    public function testDefaultLocale_isRo(): void
    {
        $gc = new GeneratedContent();

        $this->assertSame('ro', $gc->getLocale());
    }

    public function testDefaultReviewedAt_isNull(): void
    {
        $gc = new GeneratedContent();

        $this->assertNull($gc->getReviewedAt());
    }

    public function testDefaultMetadata_isNull(): void
    {
        $gc = new GeneratedContent();

        $this->assertNull($gc->getMetadata());
    }

    public function testSetType(): void
    {
        $gc = new GeneratedContent();
        $result = $gc->setType('daily_briefing');

        $this->assertSame('daily_briefing', $gc->getType());
        $this->assertSame($gc, $result, 'setType should return $this for fluent API');
    }

    public function testSetTitle(): void
    {
        $gc = new GeneratedContent();
        $result = $gc->setTitle('Daily Briefing 2025-01-15');

        $this->assertSame('Daily Briefing 2025-01-15', $gc->getTitle());
        $this->assertSame($gc, $result);
    }

    public function testSetContent(): void
    {
        $gc = new GeneratedContent();
        $markdown = "# Briefing\n\nParagraph content here.";
        $result = $gc->setContent($markdown);

        $this->assertSame($markdown, $gc->getContent());
        $this->assertSame($gc, $result);
    }

    public function testSetMetadata(): void
    {
        $gc = new GeneratedContent();
        $meta = ['article_ids' => [1, 2, 3], 'score' => 0.95];
        $result = $gc->setMetadata($meta);

        $this->assertSame($meta, $gc->getMetadata());
        $this->assertSame($gc, $result);
    }

    public function testSetMetadata_null(): void
    {
        $gc = new GeneratedContent();
        $gc->setMetadata(['key' => 'value']);
        $gc->setMetadata(null);

        $this->assertNull($gc->getMetadata());
    }

    public function testSetGeneratedAt(): void
    {
        $gc = new GeneratedContent();
        $date = new \DateTimeImmutable('2025-06-01 10:00:00');
        $result = $gc->setGeneratedAt($date);

        $this->assertSame($date, $gc->getGeneratedAt());
        $this->assertSame($gc, $result);
    }

    public function testSetReviewedAt(): void
    {
        $gc = new GeneratedContent();
        $date = new \DateTimeImmutable('2025-06-02 14:30:00');
        $result = $gc->setReviewedAt($date);

        $this->assertSame($date, $gc->getReviewedAt());
        $this->assertSame($gc, $result);
    }

    public function testSetReviewedAt_null(): void
    {
        $gc = new GeneratedContent();
        $gc->setReviewedAt(new \DateTimeImmutable());
        $gc->setReviewedAt(null);

        $this->assertNull($gc->getReviewedAt());
    }

    public function testSetLocale(): void
    {
        $gc = new GeneratedContent();
        $result = $gc->setLocale('en');

        $this->assertSame('en', $gc->getLocale());
        $this->assertSame($gc, $result);
    }

    public function testFluentApi_fullChain(): void
    {
        $gc = (new GeneratedContent())
            ->setType('dossier')
            ->setTitle('Dosar: Integrare UE')
            ->setContent('# Dosar\n\nContent here.')
            ->setMetadata(['topic_slug' => 'integrare-ue'])
            ->setLocale('ro');

        $this->assertSame('dossier', $gc->getType());
        $this->assertSame('Dosar: Integrare UE', $gc->getTitle());
        $this->assertSame('ro', $gc->getLocale());
        $this->assertSame('integrare-ue', $gc->getMetadata()['topic_slug']);
    }

    public function testAllContentTypes(): void
    {
        $types = ['daily_briefing', 'weekly_summary', 'dossier', 'background', 'connection_alert', 'entity_extraction'];

        foreach ($types as $type) {
            $gc = new GeneratedContent();
            $gc->setType($type);
            $this->assertSame($type, $gc->getType());
        }
    }
}
