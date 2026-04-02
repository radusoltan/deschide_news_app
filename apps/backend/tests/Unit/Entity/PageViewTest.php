<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\PageView;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class PageViewTest extends TestCase
{
    private PageView $pageView;

    protected function setUp(): void
    {
        $this->pageView = new PageView();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->pageView->getId());
        $this->assertNull($this->pageView->getArticle());
        $this->assertNull($this->pageView->getIpAddress());
        $this->assertNull($this->pageView->getUserAgent());
        $this->assertNull($this->pageView->getReferrer());
        $this->assertNull($this->pageView->getCategory());
        $this->assertNull($this->pageView->getSessionDuration());
    }

    public function testConstructorSetsViewedAt(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->pageView->getViewedAt());
    }

    public function testSetGetArticle(): void
    {
        $article = $this->createStub(Article::class);
        $result = $this->pageView->setArticle($article);
        $this->assertSame($article, $this->pageView->getArticle());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetArticleNull(): void
    {
        $article = $this->createStub(Article::class);
        $this->pageView->setArticle($article);
        $this->pageView->setArticle(null);
        $this->assertNull($this->pageView->getArticle());
    }

    public function testSetGetVisitorId(): void
    {
        $result = $this->pageView->setVisitorId('visitor_abc123');
        $this->assertSame('visitor_abc123', $this->pageView->getVisitorId());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetIpAddress(): void
    {
        $result = $this->pageView->setIpAddress('192.168.1.100');
        $this->assertSame('192.168.1.100', $this->pageView->getIpAddress());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetIpAddressNull(): void
    {
        $this->pageView->setIpAddress('1.2.3.4');
        $this->pageView->setIpAddress(null);
        $this->assertNull($this->pageView->getIpAddress());
    }

    public function testSetGetUserAgent(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $result = $this->pageView->setUserAgent($ua);
        $this->assertSame($ua, $this->pageView->getUserAgent());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetReferrer(): void
    {
        $result = $this->pageView->setReferrer('https://google.com');
        $this->assertSame('https://google.com', $this->pageView->getReferrer());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetCategory(): void
    {
        $category = new Category();
        $result = $this->pageView->setCategory($category);
        $this->assertSame($category, $this->pageView->getCategory());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetViewedAt(): void
    {
        $date = new DateTime('2024-06-15 10:00:00');
        $result = $this->pageView->setViewedAt($date);
        $this->assertSame($date, $this->pageView->getViewedAt());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetSessionDuration(): void
    {
        $result = $this->pageView->setSessionDuration(120);
        $this->assertSame(120, $this->pageView->getSessionDuration());
        $this->assertSame($this->pageView, $result);
    }

    public function testSetGetSessionDurationNull(): void
    {
        $this->pageView->setSessionDuration(60);
        $this->pageView->setSessionDuration(null);
        $this->assertNull($this->pageView->getSessionDuration());
    }
}
