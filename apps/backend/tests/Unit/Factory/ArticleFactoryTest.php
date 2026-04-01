<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Entity\Article;
use App\Factory\ArticleFactory;
use PHPUnit\Framework\TestCase;

class ArticleFactoryTest extends TestCase
{
    public function testClassReturnsArticleClass(): void
    {
        $this->assertSame(Article::class, ArticleFactory::class());
    }

    public function testFactoryCanBeInstantiated(): void
    {
        $factory = new ArticleFactory();
        $this->assertInstanceOf(ArticleFactory::class, $factory);
    }
}
