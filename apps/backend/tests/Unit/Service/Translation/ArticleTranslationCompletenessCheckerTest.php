<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Translation;

use App\Entity\Article;
use App\Service\Translation\ArticleTranslationCompletenessChecker;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ArticleTranslationCompletenessCheckerTest extends TestCase
{
    private ArticleTranslationCompletenessChecker $checker;
    private EntityManagerInterface $entityManager;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->checker = new ArticleTranslationCompletenessChecker($this->entityManager);
    }

    public function testIsCompleteForDefaultLocaleWithAllFields(): void
    {
        $article = new Article();
        $article->setTitle('Test Title');
        $article->setLead('Test Lead');
        $article->setContent('Test Content');

        $this->assertTrue($this->checker->isComplete($article, 'ro'));
    }

    public function testIsIncompleteForDefaultLocaleWithMissingContent(): void
    {
        $article = new Article();
        $article->setTitle('Test Title');
        $article->setLead('Test Lead');
        // content not set

        $this->assertFalse($this->checker->isComplete($article, 'ro'));
    }

    public function testGetMissingFieldsForDefaultLocale(): void
    {
        $article = new Article();
        $article->setTitle('Test Title');
        // lead and content not set

        $missing = $this->checker->getMissingFields($article, 'ro');

        $this->assertContains('lead', $missing);
        $this->assertContains('content', $missing);
        $this->assertNotContains('title', $missing);
    }

    public function testIsCompleteForNonDefaultLocaleAllFields(): void
    {
        $article = $this->createArticleWithId(42);

        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn(['title', 'lead', 'content']);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->assertTrue($this->checker->isComplete($article, 'en'));
    }

    public function testIsIncompleteForNonDefaultLocaleMissingField(): void
    {
        $article = $this->createArticleWithId(42);

        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn(['title', 'lead']);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->assertFalse($this->checker->isComplete($article, 'en'));
    }

    public function testGetMissingFieldsForNonDefaultLocale(): void
    {
        $article = $this->createArticleWithId(42);

        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn(['title']);

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $missing = $this->checker->getMissingFields($article, 'ru');

        $this->assertSame(['lead', 'content'], $missing);
    }

    public function testGetCompleteLocales(): void
    {
        $article = $this->createArticleWithId(42);
        $article->setTitle('Test');
        $article->setLead('Lead');
        $article->setContent('Content');

        // EN: complete
        $resultEn = $this->createMock(Result::class);
        $resultEn->method('fetchFirstColumn')->willReturn(['title', 'lead', 'content']);

        // RU: incomplete
        $resultRu = $this->createMock(Result::class);
        $resultRu->method('fetchFirstColumn')->willReturn(['title']);

        $this->connection->expects($this->exactly(2))
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($resultEn, $resultRu);

        $locales = $this->checker->getCompleteLocales($article);

        $this->assertSame(['ro', 'en'], $locales);
    }

    public function testIsIncompleteForArticleWithoutId(): void
    {
        $article = new Article();

        $this->assertFalse($this->checker->isComplete($article, 'en'));
        $this->assertSame(['title', 'lead', 'content'], $this->checker->getMissingFields($article, 'en'));
    }

    private function createArticleWithId(int $id): Article
    {
        $article = new Article();
        $reflection = new \ReflectionClass($article);
        $prop = $reflection->getProperty('id');
        $prop->setValue($article, $id);

        return $article;
    }
}
