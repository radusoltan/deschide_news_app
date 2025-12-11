<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Authentication Smoke Tests.
 *
 * Verifies that authentication endpoints respond correctly to invalid requests.
 * Tests proper handling of missing credentials, invalid tokens, etc.
 */
#[Group('smoke')]
class AuthenticationSmokeTest extends WebTestCase
{
    public function testLoginCheckWithoutCredentialsReturns400Or401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        // Could be 400 (bad request) or 401 (unauthorized) depending on validation
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [400, 401], 'Expected 400 or 401 status code');
    }

    public function testLoginCheckWithEmptyBodyReturns400Or401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        // Either 400 (bad request) or 401 (unauthorized) is acceptable
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [400, 401]);
    }

    public function testLoginCheckWithInvalidCredentialsReturns401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'invalid_user_' . uniqid(),
            'password' => 'invalid_password_' . uniqid(),
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateArticleWithoutJwtReturns401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/articles', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], json_encode([
            'title' => 'Test Article',
            'content' => 'Test content',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateArticleWithoutJwtReturns401(): void
    {
        $client = static::createClient();

        // Try to update a non-existent article (ID 999999)
        $client->request('PUT', '/api/articles/999999', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], json_encode([
            'title' => 'Updated Title',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteArticleWithoutJwtReturns401(): void
    {
        $client = static::createClient();

        // Try to delete a non-existent article (ID 999999)
        $client->request('DELETE', '/api/articles/999999');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenWithoutTokenReturns400Or401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/token/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        // Either 400 (bad request) or 401 (unauthorized) is acceptable
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [400, 401]);
    }

    public function testRefreshTokenWithInvalidTokenReturns401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/token/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refresh_token' => 'invalid_token_' . uniqid(),
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointsRequireAuthentication(): void
    {
        $client = static::createClient();

        $protectedEndpoints = [
            ['POST', '/api/articles'],
            ['POST', '/api/categories'],
            ['POST', '/api/authors'],
            ['POST', '/api/images'],
        ];

        foreach ($protectedEndpoints as [$method, $endpoint]) {
            $client->request($method, $endpoint, [], [], [
                'CONTENT_TYPE' => 'application/ld+json',
            ], '{}');

            $this->assertResponseStatusCodeSame(
                401,
                sprintf('Expected %s %s to return 401 without authentication', $method, $endpoint)
            );
        }
    }

    public function testLoginCheckEndpointIsAccessible(): void
    {
        $client = static::createClient();

        // Even with invalid credentials, the endpoint should be accessible (not 404)
        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'test',
            'password' => 'test',
        ]));

        // Should return 401 (not 404)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Login endpoint should not return 404');
    }

    public function testTokenRefreshEndpointIsAccessible(): void
    {
        $client = static::createClient();

        // Even with invalid token, the endpoint should be accessible (not 404)
        $client->request('POST', '/api/token/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refresh_token' => 'invalid',
        ]));

        // Should return 400 or 401 (not 404)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Token refresh endpoint should not return 404');
    }
}
