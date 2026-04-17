<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Dto\NotebookLM\FactCheckResult;
use App\Entity\AppSetting;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Topic;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Enum\TopicStatus;
use App\Service\NotebookLM\NotebookLmFactCheckService;
use App\Service\NotebookLM\NotebookLMService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for POST /api/admin/articles/{id}/factcheck.
 *
 * Covers every documented response envelope in the controller:
 *   - 200 fresh / 200 cached (success paths, NotebookLM mocked)
 *   - 422 validation (question too short)
 *   - 422 no_topics (article has no topics attached)
 *   - 404 not_found
 *   - 401 unauthenticated / 403 forbidden (non-editor)
 *   - 503 disabled / 503 unavailable / 503 no_notebook / 503 failed
 */
class ArticleFactCheckControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private ?User $adminUser = null;
    private ?User $regularUser = null;

    /** @var list<int> */
    private array $createdArticleIds = [];

    /** @var list<int> */
    private array $createdTopicIds = [];

    /** @var list<int> */
    private array $createdCategoryIds = [];

    /** @var list<int> */
    private array $createdAuthorIds = [];

    /** @var list<string> */
    private array $createdSettingKeys = [];

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        // Belt-and-braces: scrub any notebooklm.* AppSettings left over from
        // previous test runs so each test starts from a known baseline.
        $this->purgeFactCheckSettings();

        $this->createTestUsers();
    }

    private function purgeFactCheckSettings(): void
    {
        $keys = [
            'notebooklm.enabled',
            'notebooklm.factcheck.enabled',
            'notebooklm.factcheck.cache_ttl',
        ];
        $repo = $this->entityManager->getRepository(AppSetting::class);
        foreach ($keys as $key) {
            $existing = $repo->find($key);
            if ($existing !== null) {
                $this->entityManager->remove($existing);
            }
        }
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $this->cleanup();

        if (isset($this->entityManager)) {
            $this->entityManager->close();
            unset($this->entityManager);
        }

        parent::tearDown();
    }

    // ================
    // Authentication / authorization
    // ================

    public function testRequires401WhenMissingToken(): void
    {
        $this->client->request('POST', '/api/admin/articles/1/factcheck', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['question' => 'Ceva întrebare validă pentru fact check.']));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testReturns403ForNonEditorUser(): void
    {
        $token = $this->getAuthToken($this->regularUser);

        $this->client->request('POST', '/api/admin/articles/1/factcheck', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['question' => 'Ceva întrebare validă pentru fact check.']));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ================
    // 404 / 422
    // ================

    public function testReturns404WhenArticleDoesNotExist(): void
    {
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', '/api/admin/articles/999999999/factcheck', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['question' => 'Ceva întrebare validă pentru fact check.']));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = $this->decodeResponse();
        $this->assertSame('not_found', $data['status']);
    }

    public function testReturns422WhenQuestionTooShort(): void
    {
        $article = $this->createArticleWithTopic(notebookLmId: 'nb-42');
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'prea']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $this->decodeResponse();
        $this->assertSame('validation', $data['status']);
        $this->assertArrayHasKey('violations', $data);
        $this->assertSame('question', $data['violations'][0]['field']);
    }

    public function testReturns422WhenArticleHasNoTopics(): void
    {
        $article = $this->createArticleWithoutTopic();
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'Verifică afirmația despre ceva.']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $this->decodeResponse();
        $this->assertSame('no_topics', $data['status']);
    }

    // ================
    // 503 family
    // ================

    public function testReturns503DisabledWhenFactCheckNotEnabled(): void
    {
        // No AppSettings key seeded → factcheck.enabled defaults to false → disabled path.
        $article = $this->createArticleWithTopic(notebookLmId: 'nb-42');
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'Verifică afirmația despre context.']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $data = $this->decodeResponse();
        $this->assertSame('disabled', $data['status']);
    }

    public function testReturns503UnavailableWhenNotebookLmNotReachable(): void
    {
        $this->setAppSetting('notebooklm.factcheck.enabled', 'true');

        $notebookLmMock = $this->createMock(NotebookLMService::class);
        $notebookLmMock->method('isAvailable')->willReturn(false);
        static::getContainer()->set(NotebookLMService::class, $notebookLmMock);

        $article = $this->createArticleWithTopic(notebookLmId: 'nb-42');
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'Verifică afirmația despre context.']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $data = $this->decodeResponse();
        $this->assertSame('unavailable', $data['status']);
    }

    public function testReturns503NoNotebookWhenTopicMissingNotebookId(): void
    {
        $this->setAppSetting('notebooklm.factcheck.enabled', 'true');

        $notebookLmMock = $this->createMock(NotebookLMService::class);
        $notebookLmMock->method('isAvailable')->willReturn(true);
        static::getContainer()->set(NotebookLMService::class, $notebookLmMock);

        $article = $this->createArticleWithTopic(notebookLmId: null);
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'Verifică afirmația despre context.']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $data = $this->decodeResponse();
        $this->assertSame('no_notebook', $data['status']);
    }

    public function testReturns503FailedWhenFactCheckServiceReturnsNull(): void
    {
        $this->setAppSetting('notebooklm.factcheck.enabled', 'true');

        $notebookLmMock = $this->createMock(NotebookLMService::class);
        $notebookLmMock->method('isAvailable')->willReturn(true);
        static::getContainer()->set(NotebookLMService::class, $notebookLmMock);

        $factCheckMock = $this->createMock(NotebookLmFactCheckService::class);
        $factCheckMock->method('factCheck')->willReturn(null);
        static::getContainer()->set(NotebookLmFactCheckService::class, $factCheckMock);

        $article = $this->createArticleWithTopic(notebookLmId: 'nb-42');
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'Verifică afirmația despre context.']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $data = $this->decodeResponse();
        $this->assertSame('failed', $data['status']);
    }

    // ================
    // 200 success (fresh + cached)
    // ================

    public function testReturns200FreshWhenFactCheckSucceeds(): void
    {
        $this->setAppSetting('notebooklm.factcheck.enabled', 'true');

        $notebookLmMock = $this->createMock(NotebookLMService::class);
        $notebookLmMock->method('isAvailable')->willReturn(true);
        static::getContainer()->set(NotebookLMService::class, $notebookLmMock);

        $result = new FactCheckResult(
            answer: 'Afirmația este confirmată de 3 surse independente.',
            question: 'Este această afirmație verificată?',
            topicId: 42,
            notebookId: 'nb-42',
            cached: false,
            checkedAt: new DateTimeImmutable('2026-04-17T10:00:00+00:00'),
        );

        $factCheckMock = $this->createMock(NotebookLmFactCheckService::class);
        $factCheckMock->method('factCheck')->willReturn($result);
        static::getContainer()->set(NotebookLmFactCheckService::class, $factCheckMock);

        $article = $this->createArticleWithTopic(notebookLmId: 'nb-42');
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'Este această afirmație verificată?']),
        );

        $this->assertResponseIsSuccessful();
        $data = $this->decodeResponse();
        $this->assertTrue($data['success']);
        $this->assertSame('fresh', $data['status']);
        $this->assertSame('Afirmația este confirmată de 3 surse independente.', $data['data']['answer']);
        $this->assertFalse($data['data']['cached']);
    }

    public function testReturns200CachedWhenFactCheckResultIsCached(): void
    {
        $this->setAppSetting('notebooklm.factcheck.enabled', 'true');

        $notebookLmMock = $this->createMock(NotebookLMService::class);
        $notebookLmMock->method('isAvailable')->willReturn(true);
        static::getContainer()->set(NotebookLMService::class, $notebookLmMock);

        $cached = new FactCheckResult(
            answer: 'Cached answer.',
            question: 'Is X true?',
            topicId: 42,
            notebookId: 'nb-42',
            cached: true,
            checkedAt: new DateTimeImmutable('2026-04-17T09:00:00+00:00'),
        );

        $factCheckMock = $this->createMock(NotebookLmFactCheckService::class);
        $factCheckMock->method('factCheck')->willReturn($cached);
        static::getContainer()->set(NotebookLmFactCheckService::class, $factCheckMock);

        $article = $this->createArticleWithTopic(notebookLmId: 'nb-42');
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request(
            'POST',
            sprintf('/api/admin/articles/%d/factcheck', $article->getId()),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['question' => 'Is X true?']),
        );

        $this->assertResponseIsSuccessful();
        $data = $this->decodeResponse();
        $this->assertSame('cached', $data['status']);
        $this->assertTrue($data['data']['cached']);
    }

    // ================
    // Helpers
    // ================

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        $data = json_decode((string) $content, true);
        $this->assertIsArray($data);

        return $data;
    }

    private function createTestUsers(): void
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $this->adminUser = new User();
        $this->adminUser->setUsername('factcheck_admin_' . uniqid());
        $this->adminUser->setEmail('factcheck_admin_' . uniqid() . '@example.com');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->adminUser->setPassword($passwordHasher->hashPassword($this->adminUser, 'password123'));
        $this->entityManager->persist($this->adminUser);

        $this->regularUser = new User();
        $this->regularUser->setUsername('factcheck_user_' . uniqid());
        $this->regularUser->setEmail('factcheck_user_' . uniqid() . '@example.com');
        $this->regularUser->setRoles(['ROLE_USER']);
        $this->regularUser->setPassword($passwordHasher->hashPassword($this->regularUser, 'password123'));
        $this->entityManager->persist($this->regularUser);

        $this->entityManager->flush();
    }

    private function getAuthToken(?User $user): string
    {
        $user ??= $this->adminUser;

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');

        return $jwtManager->create($user);
    }

    private function createArticleWithTopic(?string $notebookLmId): Article
    {
        $topic = new Topic();
        $topic->setTitle('FactCheck Topic ' . uniqid());
        $topic->setSlug('factcheck-topic-' . uniqid());
        $topic->setStatus(TopicStatus::ACTIVE);
        $topic->setIsActive(true);
        if ($notebookLmId !== null) {
            $topic->setNotebookLmId($notebookLmId);
        }
        $this->entityManager->persist($topic);

        $article = $this->createArticleWithoutTopic();
        $article->addTopic($topic);
        $this->entityManager->flush();

        $this->createdTopicIds[] = $topic->getId();

        return $article;
    }

    private function createArticleWithoutTopic(): Article
    {
        $category = new Category();
        $category->setTitle('FactCheck Cat ' . uniqid());
        $category->setSlug('factcheck-cat-' . uniqid());
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->createdCategoryIds[] = $category->getId();

        $author = new Author();
        $author->setFirstName('FC');
        $author->setLastName('Author');
        $author->setEmail('fc-' . uniqid() . '@example.com');
        $author->setSlug('fc-author-' . uniqid());
        $this->entityManager->persist($author);
        $this->entityManager->flush();
        $this->createdAuthorIds[] = $author->getId();

        $article = new Article();
        $article->setTitle('FactCheck Article ' . uniqid());
        $article->setSlug('factcheck-article-' . uniqid());
        $article->setLead('Lead');
        $article->setContent('Content');
        $article->setStatus(ArticleStatus::NEW);
        $article->setPublishedAt(new DateTimeImmutable());
        $article->setCategory($category);
        $article->addAuthor($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $article->setStatus(ArticleStatus::PUBLISHED);
        $this->entityManager->flush();

        $this->createdArticleIds[] = $article->getId();

        return $article;
    }

    private function setAppSetting(string $key, string $value): void
    {
        $repo = $this->entityManager->getRepository(AppSetting::class);
        $existing = $repo->find($key);
        if ($existing !== null) {
            $existing->setValue($value);
        } else {
            $this->entityManager->persist(new AppSetting($key, $value));
            $this->createdSettingKeys[] = $key;
        }
        $this->entityManager->flush();
    }

    private function cleanup(): void
    {
        if (!isset($this->entityManager) || !$this->entityManager->isOpen()) {
            return;
        }

        foreach ($this->createdArticleIds as $id) {
            $article = $this->entityManager->find(Article::class, $id);
            if ($article !== null) {
                $this->entityManager->remove($article);
            }
        }
        $this->entityManager->flush();

        foreach ($this->createdTopicIds as $id) {
            $topic = $this->entityManager->find(Topic::class, $id);
            if ($topic !== null) {
                $this->entityManager->remove($topic);
            }
        }
        foreach ($this->createdAuthorIds as $id) {
            $author = $this->entityManager->find(Author::class, $id);
            if ($author !== null) {
                $this->entityManager->remove($author);
            }
        }
        foreach ($this->createdCategoryIds as $id) {
            $category = $this->entityManager->find(Category::class, $id);
            if ($category !== null) {
                $this->entityManager->remove($category);
            }
        }

        if ($this->adminUser !== null && $this->entityManager->contains($this->adminUser)) {
            $this->entityManager->remove($this->adminUser);
        }
        if ($this->regularUser !== null && $this->entityManager->contains($this->regularUser)) {
            $this->entityManager->remove($this->regularUser);
        }

        foreach ($this->createdSettingKeys as $key) {
            $setting = $this->entityManager->find(AppSetting::class, $key);
            if ($setting !== null) {
                $this->entityManager->remove($setting);
            }
        }

        $this->entityManager->flush();
    }
}
