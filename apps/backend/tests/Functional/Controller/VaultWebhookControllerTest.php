<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VaultWebhookControllerTest extends WebTestCase
{
    public function testSyncWithValidSecret(): void
    {
        $client = static::createClient();

        // Create a temp file to sync
        $vaultPath = static::getContainer()->getParameter('editorial.vault_path');
        $articlesDir = $vaultPath . '/articles/2026/04';
        @mkdir($articlesDir, 0o755, true);

        $uniqueId = 'art-webhook-' . uniqid();
        $testFile = $articlesDir . '/test-webhook-' . uniqid() . '.md';
        $testFileName = basename($testFile);
        file_put_contents($testFile, <<<MD
---
id: "{$uniqueId}"
type: "news"
language: "ro"
title:
  ro: "Test Webhook Article"
slug:
  ro: "test-webhook-{$uniqueId}"
status: "draft"
date_created: "2026-04-02T10:00:00Z"
---

Body content.
MD);

        $secret = static::getContainer()->getParameter('editorial.vault_webhook_secret');

        $client->request(
            'POST',
            '/api/webhook/vault-sync',
            [],
            [],
            [
                'HTTP_X_Webhook_Secret' => $secret,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'action' => 'sync',
                'files' => ['articles/2026/04/' . $testFileName],
            ]),
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('results', $response);
        $this->assertCount(1, $response['results']);
        $this->assertSame('created', $response['results'][0]['status']);

        // Cleanup
        @unlink($testFile);
    }

    public function testSyncWithoutSecretReturns401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/webhook/vault-sync',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => 'sync_all']),
        );

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testSyncWithInvalidSecretReturns401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/webhook/vault-sync',
            [],
            [],
            [
                'HTTP_X_Webhook_Secret' => 'wrong-secret',
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['action' => 'sync_all']),
        );

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testSyncWithInvalidJsonReturns400(): void
    {
        $client = static::createClient();
        $secret = static::getContainer()->getParameter('editorial.vault_webhook_secret');

        $client->request(
            'POST',
            '/api/webhook/vault-sync',
            [],
            [],
            [
                'HTTP_X_Webhook_Secret' => $secret,
                'CONTENT_TYPE' => 'application/json',
            ],
            'not-valid-json',
        );

        $this->assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testSyncWithInvalidActionReturns400(): void
    {
        $client = static::createClient();
        $secret = static::getContainer()->getParameter('editorial.vault_webhook_secret');

        $client->request(
            'POST',
            '/api/webhook/vault-sync',
            [],
            [],
            [
                'HTTP_X_Webhook_Secret' => $secret,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['action' => 'invalid']),
        );

        $this->assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testSyncAllProcessesArticles(): void
    {
        $client = static::createClient();

        $vaultPath = static::getContainer()->getParameter('editorial.vault_path');
        $articlesDir = $vaultPath . '/articles';
        @mkdir($articlesDir, 0o755, true);

        $secret = static::getContainer()->getParameter('editorial.vault_webhook_secret');

        $client->request(
            'POST',
            '/api/webhook/vault-sync',
            [],
            [],
            [
                'HTTP_X_Webhook_Secret' => $secret,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['action' => 'sync_all']),
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('results', $response);
    }
}
