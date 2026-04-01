<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\ShortLink;
use App\Entity\ShortLinkInteraction;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class ShortLinkTest extends TestCase
{
    private ShortLink $shortLink;

    protected function setUp(): void
    {
        $this->shortLink = new ShortLink();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->shortLink->getId());
        $this->assertNull($this->shortLink->getCode());
        $this->assertNull($this->shortLink->getOriginalUrl());
        $this->assertNull($this->shortLink->getTitle());
        $this->assertSame(0, $this->shortLink->getClickCount());
        $this->assertNull($this->shortLink->getArticle());
        $this->assertNull($this->shortLink->getCreatedAt());
        $this->assertNull($this->shortLink->getCreatedBy());
        $this->assertInstanceOf(Collection::class, $this->shortLink->getInteractions());
        $this->assertCount(0, $this->shortLink->getInteractions());
    }

    public function testSetGetCode(): void
    {
        $result = $this->shortLink->setCode('abc123');
        $this->assertSame('abc123', $this->shortLink->getCode());
        $this->assertSame($this->shortLink, $result);
    }

    public function testSetGetOriginalUrl(): void
    {
        $result = $this->shortLink->setOriginalUrl('https://example.com/long-article-url');
        $this->assertSame('https://example.com/long-article-url', $this->shortLink->getOriginalUrl());
        $this->assertSame($this->shortLink, $result);
    }

    public function testSetGetTitle(): void
    {
        $result = $this->shortLink->setTitle('My Short Link');
        $this->assertSame('My Short Link', $this->shortLink->getTitle());
        $this->assertSame($this->shortLink, $result);
    }

    public function testSetGetTitleNull(): void
    {
        $this->shortLink->setTitle('test');
        $this->shortLink->setTitle(null);
        $this->assertNull($this->shortLink->getTitle());
    }

    public function testSetGetClickCount(): void
    {
        $result = $this->shortLink->setClickCount(42);
        $this->assertSame(42, $this->shortLink->getClickCount());
        $this->assertSame($this->shortLink, $result);
    }

    public function testIncrementClickCount(): void
    {
        $this->shortLink->setClickCount(10);
        $result = $this->shortLink->incrementClickCount();
        $this->assertSame(11, $this->shortLink->getClickCount());
        $this->assertSame($this->shortLink, $result);
    }

    public function testIncrementClickCountFromZero(): void
    {
        $this->shortLink->incrementClickCount();
        $this->assertSame(1, $this->shortLink->getClickCount());
    }

    public function testSetGetArticle(): void
    {
        $article = $this->createStub(Article::class);
        $result = $this->shortLink->setArticle($article);
        $this->assertSame($article, $this->shortLink->getArticle());
        $this->assertSame($this->shortLink, $result);
    }

    public function testSetGetArticleNull(): void
    {
        $article = $this->createStub(Article::class);
        $this->shortLink->setArticle($article);
        $this->shortLink->setArticle(null);
        $this->assertNull($this->shortLink->getArticle());
    }

    public function testSetGetCreatedBy(): void
    {
        $user = new User();
        $result = $this->shortLink->setCreatedBy($user);
        $this->assertSame($user, $this->shortLink->getCreatedBy());
        $this->assertSame($this->shortLink, $result);
    }

    public function testAddInteraction(): void
    {
        $interaction = new ShortLinkInteraction();
        $result = $this->shortLink->addInteraction($interaction);
        $this->assertCount(1, $this->shortLink->getInteractions());
        $this->assertTrue($this->shortLink->getInteractions()->contains($interaction));
        $this->assertSame($this->shortLink, $interaction->getShortLink());
        $this->assertSame($this->shortLink, $result);
    }

    public function testAddInteractionDoesNotDuplicate(): void
    {
        $interaction = new ShortLinkInteraction();
        $this->shortLink->addInteraction($interaction);
        $this->shortLink->addInteraction($interaction);
        $this->assertCount(1, $this->shortLink->getInteractions());
    }

    public function testRemoveInteraction(): void
    {
        $interaction = new ShortLinkInteraction();
        $this->shortLink->addInteraction($interaction);
        $result = $this->shortLink->removeInteraction($interaction);
        $this->assertCount(0, $this->shortLink->getInteractions());
        $this->assertNull($interaction->getShortLink());
        $this->assertSame($this->shortLink, $result);
    }

    public function testGetShortUrl(): void
    {
        $this->shortLink->setCode('abc123');
        $this->assertSame('/s/abc123', $this->shortLink->getShortUrl());
    }

    public function testGetShortUrlWithNullCode(): void
    {
        $this->assertSame('/s/', $this->shortLink->getShortUrl());
    }
}
