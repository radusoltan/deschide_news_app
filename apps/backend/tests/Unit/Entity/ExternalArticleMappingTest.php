<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\ExternalArticleMapping;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ExternalArticleMappingTest extends TestCase
{
    private ExternalArticleMapping $mapping;

    protected function setUp(): void
    {
        $this->mapping = new ExternalArticleMapping();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->mapping->getId());
        $this->assertNull($this->mapping->getArticle());
        $this->assertNull($this->mapping->getSource());
        $this->assertNull($this->mapping->getExternalId());
        $this->assertNull($this->mapping->getMetadata());
        $this->assertNull($this->mapping->getCreatedAt());
        $this->assertNull($this->mapping->getUpdatedAt());
    }

    public function testSetGetArticle(): void
    {
        $article = $this->createStub(Article::class);
        $result = $this->mapping->setArticle($article);
        $this->assertSame($article, $this->mapping->getArticle());
        $this->assertSame($this->mapping, $result);
    }

    public function testSetGetArticleNull(): void
    {
        $article = $this->createStub(Article::class);
        $this->mapping->setArticle($article);
        $this->mapping->setArticle(null);
        $this->assertNull($this->mapping->getArticle());
    }

    public function testSetGetSource(): void
    {
        $result = $this->mapping->setSource('newscoop');
        $this->assertSame('newscoop', $this->mapping->getSource());
        $this->assertSame($this->mapping, $result);
    }

    public function testSetGetExternalId(): void
    {
        $result = $this->mapping->setExternalId('12345');
        $this->assertSame('12345', $this->mapping->getExternalId());
        $this->assertSame($this->mapping, $result);
    }

    public function testSetGetMetadata(): void
    {
        $metadata = ['original_url' => 'https://old-site.com/article/12345', 'imported_at' => '2024-01-01'];
        $result = $this->mapping->setMetadata($metadata);
        $this->assertSame($metadata, $this->mapping->getMetadata());
        $this->assertSame($this->mapping, $result);
    }

    public function testSetGetMetadataNull(): void
    {
        $this->mapping->setMetadata(['test' => 1]);
        $this->mapping->setMetadata(null);
        $this->assertNull($this->mapping->getMetadata());
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTimeImmutable('2024-06-15');
        $result = $this->mapping->setCreatedAt($date);
        $this->assertSame($date, $this->mapping->getCreatedAt());
        $this->assertSame($this->mapping, $result);
    }

    public function testSetGetUpdatedAt(): void
    {
        $date = new DateTimeImmutable('2024-06-16');
        $result = $this->mapping->setUpdatedAt($date);
        $this->assertSame($date, $this->mapping->getUpdatedAt());
        $this->assertSame($this->mapping, $result);
    }
}
