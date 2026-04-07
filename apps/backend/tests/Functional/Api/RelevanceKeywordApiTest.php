<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\DataFixtures\TestFixtures;
use App\Entity\RelevanceKeyword;
use App\Entity\User;
use App\Enum\KeywordAddedBy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for RelevanceKeyword API endpoints.
 *
 * Tests CRUD operations, filters, security (ROLE_EDITOR for reads,
 * ROLE_ADMIN for writes), and validation for the /api/relevance-keywords resource.
 */
class RelevanceKeywordApiTest extends WebTestCase
{
    private static bool $schemaEnsured = false;

    /**
     * Ensure the relevance_keywords table exists in the test database.
     */
    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $connection = $em->getConnection();

        try {
            $connection->executeQuery('SELECT 1 FROM relevance_keywords LIMIT 1');
        } catch (\Exception) {
            // Table doesn't exist, create it using SchemaTool (single entity only)
            $schemaTool = new SchemaTool($em);
            $metadata = [$em->getClassMetadata(RelevanceKeyword::class)];
            $sqls = $schemaTool->getCreateSchemaSql($metadata);

            foreach ($sqls as $sql) {
                try {
                    $connection->executeStatement($sql);
                } catch (\Exception) {
                    // Ignore errors for already-existing objects (sequences, etc.)
                }
            }
        }

        self::$schemaEnsured = true;
    }

    /**
     * Create an authenticated client using a single client instance.
     */
    private function createAuthenticatedClient(string $role = 'ROLE_EDITOR'): KernelBrowser
    {
        $client = static::createClient();

        $this->ensureSchema();

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $username = match ($role) {
            'ROLE_ADMIN' => TestFixtures::ADMIN_USER_USERNAME,
            'ROLE_EDITOR' => TestFixtures::EDITOR_USER_USERNAME,
            default => TestFixtures::REGULAR_USER_USERNAME,
        };
        $password = match ($role) {
            'ROLE_ADMIN' => TestFixtures::ADMIN_USER_PASSWORD,
            'ROLE_EDITOR' => TestFixtures::EDITOR_USER_PASSWORD,
            default => TestFixtures::REGULAR_USER_PASSWORD,
        };
        $roles = match ($role) {
            'ROLE_ADMIN' => ['ROLE_ADMIN'],
            'ROLE_EDITOR' => ['ROLE_EDITOR'],
            default => [],
        };

        // Ensure user exists
        $userRepo = $em->getRepository(User::class);
        if (!$userRepo->findOneBy(['username' => $username])) {
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($username . '@test.local');
            $user->setFirstName('Test');
            $user->setLastName('User');
            $user->setRoles($roles);
            $user->setPassword($hasher->hashPassword($user, $password));
            $em->persist($user);
            $em->flush();
        }

        // Login to get JWT token using the same client
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);
        $token = $data['token'] ?? throw new \RuntimeException('Failed to get JWT token');

        // Set token for subsequent requests
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }

    /**
     * Helper: create a RelevanceKeyword entity directly in the database.
     */
    private function createKeyword(
        string $keyword,
        int $tier = 1,
        string $language = 'ro',
        bool $isActive = true,
        KeywordAddedBy $addedBy = KeywordAddedBy::MANUAL,
    ): RelevanceKeyword {
        $em = static::getContainer()->get('doctrine')->getManager();

        $entity = new RelevanceKeyword();
        $entity->setKeyword($keyword);
        $entity->setTier($tier);
        $entity->setLanguage($language);
        $entity->setIsActive($isActive);
        $entity->setAddedBy($addedBy);
        $em->persist($entity);
        $em->flush();

        return $entity;
    }

    // ==============================
    // GET Collection Tests
    // ==============================

    public function testGetCollectionRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/relevance-keywords', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetCollectionAsEditorReturnsSuccess(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $client->request('GET', '/api/relevance-keywords', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('member', $data);
        $this->assertArrayHasKey('totalItems', $data);
    }

    public function testGetCollectionAsAdminReturnsSuccess(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $client->request('GET', '/api/relevance-keywords', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('member', $data);
    }

    public function testGetCollectionReturnsHydraFormat(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createKeyword('test-hydra');

        $client->request('GET', '/api/relevance-keywords', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    // ==============================
    // GET Single Item Tests
    // ==============================

    public function testGetSingleKeywordReturnsSuccess(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $keyword = $this->createKeyword('single-test', 2, 'en', true, KeywordAddedBy::AI);
        $id = $keyword->getId();

        $client->request('GET', "/api/relevance-keywords/{$id}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($id, $data['id']);
        $this->assertEquals('single-test', $data['keyword']);
        $this->assertEquals(2, $data['tier']);
        $this->assertEquals('en', $data['language']);
        $this->assertTrue($data['isActive']);
        $this->assertEquals('ai', $data['addedBy']);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testGetNonExistentKeywordReturns404(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $client->request('GET', '/api/relevance-keywords/99999', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ==============================
    // Filter Tests
    // ==============================

    public function testFilterByKeywordPartial(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createKeyword('moldova-politics', 1);
        $this->createKeyword('economy-news', 2);

        $client->request('GET', '/api/relevance-keywords?keyword=moldova', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        foreach ($data['member'] as $member) {
            $this->assertStringContainsStringIgnoringCase('moldova', $member['keyword']);
        }
    }

    public function testFilterByLanguageExact(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createKeyword('filter-lang-ro', 1, 'ro');
        $this->createKeyword('filter-lang-en', 1, 'en');

        $client->request('GET', '/api/relevance-keywords?language=en', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        foreach ($data['member'] as $member) {
            $this->assertEquals('en', $member['language']);
        }
    }

    public function testFilterByTierRange(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createKeyword('tier-range-1', 1);
        $this->createKeyword('tier-range-3', 3);
        $this->createKeyword('tier-range-4', 4);

        $client->request('GET', '/api/relevance-keywords?tier[gte]=2&tier[lte]=3', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        foreach ($data['member'] as $member) {
            $this->assertGreaterThanOrEqual(2, $member['tier']);
            $this->assertLessThanOrEqual(3, $member['tier']);
        }
    }

    public function testFilterByIsActive(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $this->createKeyword('active-keyword', 1, 'ro', true);
        $this->createKeyword('inactive-keyword', 2, 'ro', false);

        $client->request('GET', '/api/relevance-keywords?isActive=false', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        foreach ($data['member'] as $member) {
            $this->assertFalse($member['isActive']);
        }
    }

    // ==============================
    // Security Tests
    // ==============================

    public function testPostRequiresAdminRole(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $client->request('POST', '/api/relevance-keywords', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => 'editor-attempt',
            'tier' => 1,
            'language' => 'ro',
            'isActive' => true,
            'addedBy' => 'manual',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPutRequiresAdminRole(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $keyword = $this->createKeyword('put-security-test', 1);
        $id = $keyword->getId();

        $client->request('PUT', "/api/relevance-keywords/{$id}", [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => 'put-security-changed',
            'tier' => 2,
            'language' => 'en',
            'isActive' => false,
            'addedBy' => 'ai',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteRequiresAdminRole(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_EDITOR');

        $keyword = $this->createKeyword('delete-security-test', 1);
        $id = $keyword->getId();

        $client->request('DELETE', "/api/relevance-keywords/{$id}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ==============================
    // POST (Create) Tests
    // ==============================

    public function testPostWithValidDataCreatesKeyword(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $client->request('POST', '/api/relevance-keywords', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => 'new-keyword-test',
            'tier' => 2,
            'language' => 'en',
            'isActive' => true,
            'addedBy' => 'ai',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('new-keyword-test', $data['keyword']);
        $this->assertEquals(2, $data['tier']);
        $this->assertEquals('en', $data['language']);
        $this->assertTrue($data['isActive']);
        $this->assertEquals('ai', $data['addedBy']);
        $this->assertArrayHasKey('createdAt', $data);
    }

    // ==============================
    // Validation Tests
    // ==============================

    public function testPostWithBlankKeywordReturnsValidationError(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $client->request('POST', '/api/relevance-keywords', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => '',
            'tier' => 1,
            'language' => 'ro',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPostWithTierOutOfRangeReturnsValidationError(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $client->request('POST', '/api/relevance-keywords', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => 'bad-tier',
            'tier' => 5,
            'language' => 'ro',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPostWithTierZeroReturnsValidationError(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $client->request('POST', '/api/relevance-keywords', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => 'zero-tier',
            'tier' => 0,
            'language' => 'ro',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPostWithInvalidLanguageReturnsValidationError(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $client->request('POST', '/api/relevance-keywords', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => 'bad-language',
            'tier' => 1,
            'language' => 'fr',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ==============================
    // PUT (Update) Tests
    // ==============================

    public function testPutUpdatesKeyword(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $keyword = $this->createKeyword('update-me', 1, 'ro', true, KeywordAddedBy::MANUAL);
        $id = $keyword->getId();

        $client->request('PUT', "/api/relevance-keywords/{$id}", [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'keyword' => 'updated-keyword',
            'tier' => 3,
            'language' => 'en',
            'isActive' => false,
            'addedBy' => 'trend',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('updated-keyword', $data['keyword']);
        $this->assertEquals(3, $data['tier']);
        $this->assertEquals('en', $data['language']);
        $this->assertFalse($data['isActive']);
        $this->assertEquals('trend', $data['addedBy']);
    }

    // ==============================
    // DELETE Tests
    // ==============================

    public function testDeleteAsAdminSucceeds(): void
    {
        $client = $this->createAuthenticatedClient('ROLE_ADMIN');

        $keyword = $this->createKeyword('delete-me', 4, 'ru');
        $id = $keyword->getId();

        $client->request('DELETE', "/api/relevance-keywords/{$id}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify it's gone
        $client->request('GET', "/api/relevance-keywords/{$id}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
