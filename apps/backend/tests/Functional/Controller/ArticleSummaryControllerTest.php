<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Article Summary Controller.
 *
 * Sprint 20 — Agent Sinteză
 */
class ArticleSummaryControllerTest extends WebTestCase
{
    public function testGetSummaryReturnsExistingSummary(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();

        // Find an existing article or create one
        $article = $em->getRepository(Article::class)->findOneBy([]) ?? $this->createArticle($em);
        $article->setInternalSummary("• Punct 1: test sumă.\n• Punct 2: test context.");
        $em->flush();
        $articleId = $article->getId();

        $client->request('GET', "/api/articles/{$articleId}/summary", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertTrue($data['has_summary']);
        self::assertStringContainsString('Punct 1', $data['summary']);

        // Cleanup
        $article->setInternalSummary(null);
        $em->flush();
    }

    public function testGetSummaryReturnsNullWhenNoSummary(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();

        // Find an article without summary
        $article = $em->getRepository(Article::class)->findOneBy(['internalSummary' => null]);
        if ($article === null) {
            $article = $em->getRepository(Article::class)->findOneBy([]);
            $article?->setInternalSummary(null);
            $em->flush();
        }

        self::assertNotNull($article, 'Need at least one article in DB');
        $articleId = $article->getId();

        $client->request('GET', "/api/articles/{$articleId}/summary", [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertFalse($data['has_summary']);
        self::assertNull($data['summary']);
    }

    public function testGetSummaryReturns404ForNonExistentArticle(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/articles/999999/summary', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    private function createArticle(EntityManagerInterface $em): Article
    {
        $article = new Article();
        $article->setTitle('Test Summary Article');
        $article->setContent('Lorem ipsum dolor sit amet, content text here for testing.');
        $article->setStatus(ArticleStatus::PUBLISHED);

        $em->persist($article);
        $em->flush();

        return $article;
    }
}
