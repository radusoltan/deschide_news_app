<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use PHPUnit\Framework\TestCase;

class ArticlePublishedLocalesTest extends TestCase
{
    public function testDefaultPublishedLocales(): void
    {
        $article = new Article();
        $this->assertSame(['ro'], $article->getPublishedLocales());
    }

    public function testSetPublishedLocales(): void
    {
        $article = new Article();
        $result = $article->setPublishedLocales(['ro', 'en', 'ru']);

        $this->assertSame($article, $result);
        $this->assertSame(['ro', 'en', 'ru'], $article->getPublishedLocales());
    }

    public function testSetPublishedLocalesDeduplicates(): void
    {
        $article = new Article();
        $article->setPublishedLocales(['ro', 'en', 'ro']);

        $this->assertSame(['ro', 'en'], $article->getPublishedLocales());
    }

    public function testAddPublishedLocale(): void
    {
        $article = new Article();
        $result = $article->addPublishedLocale('en');

        $this->assertSame($article, $result);
        $this->assertSame(['ro', 'en'], $article->getPublishedLocales());
    }

    public function testAddPublishedLocaleIdempotent(): void
    {
        $article = new Article();
        $article->addPublishedLocale('ro');

        $this->assertSame(['ro'], $article->getPublishedLocales());
    }

    public function testAddMultipleLocales(): void
    {
        $article = new Article();
        $article->addPublishedLocale('en');
        $article->addPublishedLocale('ru');

        $this->assertSame(['ro', 'en', 'ru'], $article->getPublishedLocales());
    }

    public function testRemovePublishedLocale(): void
    {
        $article = new Article();
        $article->setPublishedLocales(['ro', 'en', 'ru']);
        $result = $article->removePublishedLocale('en');

        $this->assertSame($article, $result);
        $this->assertSame(['ro', 'ru'], $article->getPublishedLocales());
    }

    public function testRemoveNonExistentLocale(): void
    {
        $article = new Article();
        $article->removePublishedLocale('fr');

        $this->assertSame(['ro'], $article->getPublishedLocales());
    }

    public function testIsPublishedInLocaleDefault(): void
    {
        $article = new Article();

        $this->assertTrue($article->isPublishedInLocale('ro'));
        $this->assertFalse($article->isPublishedInLocale('en'));
        $this->assertFalse($article->isPublishedInLocale('ru'));
    }

    public function testIsPublishedInLocaleAllLocales(): void
    {
        $article = new Article();
        $article->setPublishedLocales(['ro', 'en', 'ru']);

        $this->assertTrue($article->isPublishedInLocale('ro'));
        $this->assertTrue($article->isPublishedInLocale('en'));
        $this->assertTrue($article->isPublishedInLocale('ru'));
        $this->assertFalse($article->isPublishedInLocale('fr'));
    }

    public function testRemoveLastLocale(): void
    {
        $article = new Article();
        $article->removePublishedLocale('ro');

        $this->assertSame([], $article->getPublishedLocales());
        $this->assertFalse($article->isPublishedInLocale('ro'));
    }
}
