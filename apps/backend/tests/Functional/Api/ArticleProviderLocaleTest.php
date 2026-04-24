<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\DataFixtures\TestFixtures;
use App\Entity\Article;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Regression tests for the ArticleProvider per-locale publishing gate scope.
 *
 * Hotfix v1.4.1 / ADR-027 — see docs/adr/ADR-027-article-provider-locale-gate-scope.md
 *
 * The gate at ArticleProvider::provide() returns null (→ 404) when the
 * requested article is not published in the current Accept-Language locale.
 * Before the hotfix, the same gate also blocked API Platform's IriConverter
 * lookups during denormalization of write payloads (e.g. POST /api/article_images
 * with "article": "/api/articles/{id}"), breaking admin image-attach in any
 * non-RO browser locale.
 *
 * Matrix (all four rows must be green after the hotfix):
 * | # | Article state          | Request                                 | Accept-Language | Expected |
 * |---|------------------------|-----------------------------------------|-----------------|----------|
 * | 1 | Published in ro only   | GET  /api/articles/{id}                 | ro              | 200      |
 * | 2 | Published in ro only   | GET  /api/articles/{id}                 | en              | 404      |
 * | 3 | Published in ro only   | POST /api/article_images (IRI = article) | en              | 201      |
 * | 4 | Draft (no locales)     | POST /api/article_images (IRI = article) | en              | 201      |
 */
class ArticleProviderLocaleTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    /** @var int[] */
    private array $createdArticleIds = [];
    /** @var int[] */
    private array $createdImageIds = [];
    /** @var int[] */
    private array $createdArticleImageIds = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        // Use raw DBAL for cleanup — Doctrine ORM remove() triggers VichUploader
        // lifecycle listeners that try to delete physical files and setOriginalFilename(null),
        // which breaks on fixture entities that were never uploaded via the HTTP pipeline.
        if (isset($this->em)) {
            $conn = $this->em->getConnection();
            foreach ($this->createdArticleImageIds as $id) {
                $conn->executeStatement('DELETE FROM article_image WHERE id = ?', [$id]);
            }
            foreach ($this->createdArticleIds as $id) {
                $conn->executeStatement('DELETE FROM article_image WHERE article_id = ?', [$id]);
                $conn->executeStatement('DELETE FROM articles WHERE id = ?', [$id]);
            }
            foreach ($this->createdImageIds as $id) {
                $conn->executeStatement('DELETE FROM article_image WHERE image_id = ?', [$id]);
                $conn->executeStatement('DELETE FROM images WHERE id = ?', [$id]);
            }
        }

        $this->createdArticleImageIds = [];
        $this->createdArticleIds = [];
        $this->createdImageIds = [];

        parent::tearDown();
    }

    // ======================
    // Matrix row #1: public GET, published in requested locale → 200
    // ======================
    public function testPublicGetReturns200WhenArticleIsPublishedInRequestedLocale(): void
    {
        $article = $this->createArticle(['ro'], ArticleStatus::PUBLISHED);
        $token = $this->authenticateAdmin();

        $this->client->request('GET', "/api/articles/{$article->getId()}", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    // ======================
    // Matrix row #2: public GET, not published in requested locale → 404
    // ======================
    public function testPublicGetReturns404WhenArticleNotPublishedInRequestedLocale(): void
    {
        $article = $this->createArticle(['ro'], ArticleStatus::PUBLISHED);
        $token = $this->authenticateAdmin();

        $this->client->request('GET', "/api/articles/{$article->getId()}", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ======================
    // Matrix row #3: POST /api/article_images with IRI to RO-only article,
    // Accept-Language: en → must succeed (201). Pre-hotfix: 400/500.
    // ======================
    public function testAttachImageToRoOnlyArticleWithEnglishAcceptLanguageReturns201(): void
    {
        $article = $this->createArticle(['ro'], ArticleStatus::PUBLISHED);
        $image = $this->createImage();
        $token = $this->authenticateAdmin();

        $payload = [
            'article' => "/api/articles/{$article->getId()}",
            'image' => "/api/images/{$image->getId()}",
            'position' => 0,
            'isFeatured' => false,
        ];

        $this->client->request(
            'POST',
            '/api/article_images',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => "Bearer {$token}",
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(
            Response::HTTP_CREATED,
            'Attaching image to RO-only article with Accept-Language: en must return 201 '
            . '(regression: hotfix v1.4.1 / ADR-027). Body: '
            . $this->client->getResponse()->getContent()
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);
        if (isset($data['id'])) {
            $this->createdArticleImageIds[] = (int) $data['id'];
        }
    }

    // ======================
    // Matrix row #4: draft article (no published locales) with Accept-Language: en → 201
    // ======================
    public function testAttachImageToDraftArticleWithEnglishAcceptLanguageReturns201(): void
    {
        $article = $this->createArticle([], ArticleStatus::NEW);
        $image = $this->createImage();
        $token = $this->authenticateAdmin();

        $payload = [
            'article' => "/api/articles/{$article->getId()}",
            'image' => "/api/images/{$image->getId()}",
            'position' => 0,
            'isFeatured' => false,
        ];

        $this->client->request(
            'POST',
            '/api/article_images',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => "Bearer {$token}",
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(
            Response::HTTP_CREATED,
            'Attaching image to draft article (no published locales) with Accept-Language: en '
            . 'must return 201 (regression: hotfix v1.4.1 / ADR-027). Body: '
            . $this->client->getResponse()->getContent()
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);
        if (isset($data['id'])) {
            $this->createdArticleImageIds[] = (int) $data['id'];
        }
    }

    // ======================
    // Helpers
    // ======================

    /**
     * Create an Article directly in the DB with the given publishedLocales and status.
     *
     * @param string[] $publishedLocales
     */
    private function createArticle(array $publishedLocales, ArticleStatus $status): Article
    {
        $article = new Article();
        $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        $article->setTitle("Hotfix test article {$uniq}");
        $article->setLead('Regression fixture for ADR-027');
        $article->setContent('<p>Body</p>');
        $article->setStatus($status);
        $article->setPublishedLocales($publishedLocales);

        if ($status === ArticleStatus::PUBLISHED) {
            $article->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->persist($article);
        $this->em->flush();

        $this->createdArticleIds[] = $article->getId();

        return $article;
    }

    private function createImage(): Image
    {
        $image = new Image();
        $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        $image->setFilename("hotfix-{$uniq}.jpg");
        $image->setOriginalFilename("hotfix-{$uniq}.jpg");
        $image->setPath("images/hotfix-{$uniq}.jpg");
        $image->setMimeType('image/jpeg');
        $image->setSize(1024);
        $image->setWidth(100);
        $image->setHeight(100);

        $this->em->persist($image);
        $this->em->flush();

        $this->createdImageIds[] = $image->getId();

        return $image;
    }

    private function authenticateAdmin(): string
    {
        $container = static::getContainer();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $userRepo = $this->em->getRepository(User::class);
        if (!$userRepo->findOneBy(['username' => TestFixtures::ADMIN_USER_USERNAME])) {
            $user = new User();
            $user->setUsername(TestFixtures::ADMIN_USER_USERNAME);
            $user->setEmail(TestFixtures::ADMIN_USER_EMAIL);
            $user->setFirstName('Test');
            $user->setLastName('Admin');
            $user->setRoles(['ROLE_ADMIN']);
            $user->setPassword($hasher->hashPassword($user, TestFixtures::ADMIN_USER_PASSWORD));
            $this->em->persist($user);
            $this->em->flush();
        }

        $this->client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => TestFixtures::ADMIN_USER_USERNAME,
            'password' => TestFixtures::ADMIN_USER_PASSWORD,
        ], JSON_THROW_ON_ERROR));

        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'] ?? throw new \RuntimeException('JWT login failed: ' . $this->client->getResponse()->getContent());
    }
}
