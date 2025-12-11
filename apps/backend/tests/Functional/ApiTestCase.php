<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Trait\AuthenticatedClientTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base test case for API functional tests.
 *
 * Provides common functionality for testing API endpoints:
 * - JWT authentication helpers
 * - Pre-configured HTTP client
 * - Common assertions
 *
 * All API functional tests should extend this class instead of WebTestCase.
 *
 * Note: Test fixtures (TestFixtures.php) are automatically loaded by the trait
 * on first authentication request. DAMA DoctrineTestBundle handles transaction
 * rollback between tests, so fixtures remain available without database pollution.
 *
 * Example:
 * ```php
 * class MyApiTest extends ApiTestCase
 * {
 *     public function testProtectedEndpoint(): void
 *     {
 *         $client = $this->getAuthenticatedClient('ROLE_ADMIN');
 *         $client->request('GET', '/api/articles');
 *         $this->assertResponseIsSuccessful();
 *     }
 * }
 * ```
 */
abstract class ApiTestCase extends WebTestCase
{
    use AuthenticatedClientTrait;

    /**
     * Assert that the response is a valid JSON response.
     *
     * @param string|null $expectedContentType Expected content-type header (default: 'application/json')
     */
    protected function assertJsonResponse(?string $expectedContentType = 'application/json'): void
    {
        $response = static::getClient()->getResponse();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', $expectedContentType);

        $content = $response->getContent();
        $this->assertNotEmpty($content, 'Response content is empty');

        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'Response is not valid JSON: ' . json_last_error_msg());
    }

    /**
     * Assert that the response contains the expected JSON structure.
     *
     * @param array<string> $expectedKeys Expected top-level keys in the JSON response
     */
    protected function assertJsonHasKeys(array $expectedKeys): void
    {
        $response = static::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $data,
                "Expected key '{$key}' not found in JSON response"
            );
        }
    }

    /**
     * Get the decoded JSON response data.
     *
     * @return array<string, mixed> Decoded JSON response
     */
    protected function getJsonResponseData(): array
    {
        $response = static::getClient()->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data, 'Response is not a valid JSON object/array');

        return $data;
    }
}
