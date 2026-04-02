<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\Scraping\ContentDeduplicator;
use PHPUnit\Framework\TestCase;

class ContentDeduplicatorTest extends TestCase
{
    public function testIdenticalContentReturnsSameHash(): void
    {
        $repo = $this->createMock(ArticleRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $dedup = new ContentDeduplicator($repo);

        $hash1 = $dedup->computeHash('Guvernul a aprobat noul plan.');
        $hash2 = $dedup->computeHash('Guvernul a aprobat noul plan.');

        $this->assertSame($hash1, $hash2);
    }

    public function testDifferentContentReturnsDifferentHash(): void
    {
        $repo = $this->createMock(ArticleRepository::class);
        $dedup = new ContentDeduplicator($repo);

        $hash1 = $dedup->computeHash('Articol despre economie.');
        $hash2 = $dedup->computeHash('Articol despre politică.');

        $this->assertNotSame($hash1, $hash2);
    }

    public function testNormalizationIgnoresCase(): void
    {
        $repo = $this->createMock(ArticleRepository::class);
        $dedup = new ContentDeduplicator($repo);

        $hash1 = $dedup->computeHash('Moldova aprobă REFORMĂ.');
        $hash2 = $dedup->computeHash('moldova aprobă reformă.');

        $this->assertSame($hash1, $hash2);
    }

    public function testNormalizationIgnoresPunctuation(): void
    {
        $repo = $this->createMock(ArticleRepository::class);
        $dedup = new ContentDeduplicator($repo);

        $hash1 = $dedup->computeHash('Guvernul a aprobat planul!');
        $hash2 = $dedup->computeHash('Guvernul a aprobat planul');

        $this->assertSame($hash1, $hash2);
    }

    public function testNormalizationIgnoresExtraWhitespace(): void
    {
        $repo = $this->createMock(ArticleRepository::class);
        $dedup = new ContentDeduplicator($repo);

        $hash1 = $dedup->computeHash("Text  cu    spații\n\nmulte.");
        $hash2 = $dedup->computeHash('Text cu spații multe.');

        $this->assertSame($hash1, $hash2);
    }

    public function testIsDuplicateReturnsTrueWhenExists(): void
    {
        $article = $this->createMock(Article::class);
        $repo = $this->createMock(ArticleRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->willReturn($article);

        $dedup = new ContentDeduplicator($repo);

        $this->assertTrue($dedup->isDuplicate('Conținut existent'));
    }

    public function testIsDuplicateReturnsFalseWhenNew(): void
    {
        $repo = $this->createMock(ArticleRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $dedup = new ContentDeduplicator($repo);

        $this->assertFalse($dedup->isDuplicate('Conținut nou'));
    }

    public function testHashIsSha256(): void
    {
        $repo = $this->createMock(ArticleRepository::class);
        $dedup = new ContentDeduplicator($repo);

        $hash = $dedup->computeHash('test');

        // SHA-256 produces 64 hex characters
        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }
}
