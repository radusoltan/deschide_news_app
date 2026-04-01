<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\ImportantArticlesList;
use PHPUnit\Framework\TestCase;

class ImportantArticlesListTest extends TestCase
{
    private ImportantArticlesList $list;

    protected function setUp(): void
    {
        $this->list = new ImportantArticlesList();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->list->getId());
        $this->assertNull($this->list->getArticle());
        $this->assertNull($this->list->getPosition());
        $this->assertNull($this->list->getCreatedAt());
    }

    public function testSetGetArticle(): void
    {
        $article = $this->createStub(Article::class);
        $result = $this->list->setArticle($article);
        $this->assertSame($article, $this->list->getArticle());
        $this->assertSame($this->list, $result);
    }

    public function testSetGetArticleNull(): void
    {
        $article = $this->createStub(Article::class);
        $this->list->setArticle($article);
        $this->list->setArticle(null);
        $this->assertNull($this->list->getArticle());
    }

    public function testSetGetPosition(): void
    {
        $result = $this->list->setPosition(5);
        $this->assertSame(5, $this->list->getPosition());
        $this->assertSame($this->list, $result);
    }

    public function testSetGetPositionMinValue(): void
    {
        $this->list->setPosition(1);
        $this->assertSame(1, $this->list->getPosition());
    }

    public function testSetGetPositionMaxValue(): void
    {
        $this->list->setPosition(25);
        $this->assertSame(25, $this->list->getPosition());
    }
}
