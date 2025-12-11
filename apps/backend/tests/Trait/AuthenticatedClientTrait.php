<?php

declare(strict_types=1);

namespace App\Tests\Trait;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Trait for authenticating test clients with JWT tokens.
 *
 * Provides helper methods for obtaining JWT tokens and creating authenticated HTTP clients
 * for functional/integration tests.
 *
 * Automatically loads test fixtures (User entities) on first use. Uses DAMA DoctrineTestBundle
 * for transaction rollback between tests.
 *
 * Usage:
 * ```php
 * class MyControllerTest extends WebTestCase
 * {
 *     use AuthenticatedClientTrait;
 *
 *     public function testProtectedEndpoint(): void
 *     {
 *         $client = $this->getAuthenticatedClient();
 *         $client->request('GET', '/api/protected-resource');
 *         $this->assertResponseIsSuccessful();
 *     }
 * }
 * ```
 */
trait AuthenticatedClientTrait
{
    private static bool $fixturesLoaded = false;

    /**
     * Ensure test fixtures are loaded before authentication.
     */
    private function ensureFixturesLoaded(): void
    {
        if (self::$fixturesLoaded) {
            return;
        }

        // Get container from existing kernel or boot a new one
        $kernel = static::$kernel ?? static::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Check if admin user already exists
        $userRepo = $em->getRepository(User::class);
        $existingAdmin = $userRepo->findOneBy(['username' => TestFixtures::ADMIN_USER_USERNAME]);

        if ($existingAdmin) {
            // Fixtures already loaded (possibly by another test)
            self::$fixturesLoaded = true;
            return;
        }

        // Load fixtures
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Create admin user
        $adminUser = new User();
        $adminUser->setUsername(TestFixtures::ADMIN_USER_USERNAME);
        $adminUser->setEmail(TestFixtures::ADMIN_USER_EMAIL);
        $adminUser->setFirstName('Test');
        $adminUser->setLastName('Admin');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $adminUser->setPassword(
            $passwordHasher->hashPassword($adminUser, TestFixtures::ADMIN_USER_PASSWORD)
        );
        $em->persist($adminUser);

        // Create editor user
        $editorUser = new User();
        $editorUser->setUsername(TestFixtures::EDITOR_USER_USERNAME);
        $editorUser->setEmail(TestFixtures::EDITOR_USER_EMAIL);
        $editorUser->setFirstName('Test');
        $editorUser->setLastName('Editor');
        $editorUser->setRoles(['ROLE_EDITOR']);
        $editorUser->setPassword(
            $passwordHasher->hashPassword($editorUser, TestFixtures::EDITOR_USER_PASSWORD)
        );
        $em->persist($editorUser);

        // Create regular user
        $regularUser = new User();
        $regularUser->setUsername(TestFixtures::REGULAR_USER_USERNAME);
        $regularUser->setEmail(TestFixtures::REGULAR_USER_EMAIL);
        $regularUser->setFirstName('Test');
        $regularUser->setLastName('User');
        $regularUser->setRoles([]); // Will get ROLE_USER automatically
        $regularUser->setPassword(
            $passwordHasher->hashPassword($regularUser, TestFixtures::REGULAR_USER_PASSWORD)
        );
        $em->persist($regularUser);

        $em->flush();

        self::$fixturesLoaded = true;
    }
    /**
     * Get an authenticated client with a valid JWT token.
     *
     * @param string $role Role to authenticate as (ROLE_ADMIN, ROLE_EDITOR, or ROLE_USER)
     * @return KernelBrowser Authenticated client
     */
    protected function getAuthenticatedClient(string $role = 'ROLE_ADMIN'): KernelBrowser
    {
        // Create client first (this boots the kernel)
        $client = static::createClient();

        // Now we can safely load fixtures since kernel is booted
        $this->ensureFixturesLoaded();

        $token = match ($role) {
            'ROLE_ADMIN' => $this->getJwtToken(
                TestFixtures::ADMIN_USER_USERNAME,
                TestFixtures::ADMIN_USER_PASSWORD
            ),
            'ROLE_EDITOR' => $this->getJwtToken(
                TestFixtures::EDITOR_USER_USERNAME,
                TestFixtures::EDITOR_USER_PASSWORD
            ),
            'ROLE_USER' => $this->getJwtToken(
                TestFixtures::REGULAR_USER_USERNAME,
                TestFixtures::REGULAR_USER_PASSWORD
            ),
            default => throw new \InvalidArgumentException("Unknown role: {$role}"),
        };

        // Set JWT token in Authorization header for all subsequent requests
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }

    /**
     * Get a JWT token by logging in with username and password.
     *
     * Note: Ensure ensureFixturesLoaded() is called before this method.
     *
     * @param string $username Username to authenticate
     * @param string $password Password to authenticate
     * @return string JWT access token
     */
    protected function getJwtToken(string $username, string $password): string
    {
        $client = static::createClient();

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        $response = $client->getResponse();

        if (!$response->isSuccessful()) {
            throw new \RuntimeException(
                "Failed to authenticate user '{$username}'. " .
                "Status: {$response->getStatusCode()}, " .
                "Response: {$response->getContent()}"
            );
        }

        $data = json_decode($response->getContent(), true);

        if (!isset($data['token'])) {
            throw new \RuntimeException(
                "JWT token not found in login response. Response: " . $response->getContent()
            );
        }

        return $data['token'];
    }

    /**
     * Get admin JWT token using test credentials.
     *
     * @return string JWT access token for admin user
     */
    protected function getAdminToken(): string
    {
        // Ensure fixtures are loaded before getting token
        if (!self::$fixturesLoaded) {
            static::createClient(); // Boot kernel
            $this->ensureFixturesLoaded();
        }

        return $this->getJwtToken(
            TestFixtures::ADMIN_USER_USERNAME,
            TestFixtures::ADMIN_USER_PASSWORD
        );
    }

    /**
     * Get editor JWT token using test credentials.
     *
     * @return string JWT access token for editor user
     */
    protected function getEditorToken(): string
    {
        // Ensure fixtures are loaded before getting token
        if (!self::$fixturesLoaded) {
            static::createClient(); // Boot kernel
            $this->ensureFixturesLoaded();
        }

        return $this->getJwtToken(
            TestFixtures::EDITOR_USER_USERNAME,
            TestFixtures::EDITOR_USER_PASSWORD
        );
    }

    /**
     * Get regular user JWT token using test credentials.
     *
     * @return string JWT access token for regular user
     */
    protected function getUserToken(): string
    {
        // Ensure fixtures are loaded before getting token
        if (!self::$fixturesLoaded) {
            static::createClient(); // Boot kernel
            $this->ensureFixturesLoaded();
        }

        return $this->getJwtToken(
            TestFixtures::REGULAR_USER_USERNAME,
            TestFixtures::REGULAR_USER_PASSWORD
        );
    }

    /**
     * Make an authenticated API request.
     *
     * @param KernelBrowser $client HTTP client
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $uri URI to request
     * @param array<string, mixed> $data Request data (will be JSON encoded)
     * @param string $role Role to authenticate as
     * @return void
     */
    protected function makeAuthenticatedRequest(
        KernelBrowser $client,
        string $method,
        string $uri,
        array $data = [],
        string $role = 'ROLE_ADMIN'
    ): void {
        $token = match ($role) {
            'ROLE_ADMIN' => $this->getAdminToken(),
            'ROLE_EDITOR' => $this->getEditorToken(),
            'ROLE_USER' => $this->getUserToken(),
            default => throw new \InvalidArgumentException("Unknown role: {$role}"),
        };

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $content = empty($data) ? null : json_encode($data);

        $client->request($method, $uri, [], [], $headers, $content);
    }
}
