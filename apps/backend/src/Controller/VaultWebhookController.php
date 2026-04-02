<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Editorial\VaultSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VaultWebhookController extends AbstractController
{
    public function __construct(
        private readonly VaultSyncService $syncService,
        #[Autowire('%editorial.vault_webhook_secret%')]
        private readonly string $webhookSecret,
        #[Autowire('%editorial.vault_path%')]
        private readonly string $vaultPath,
    ) {}

    #[Route('/api/webhook/vault-sync', name: 'api_vault_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        // Validate webhook secret
        $secret = $request->headers->get('X-Webhook-Secret');
        if ($secret !== $this->webhookSecret) {
            return $this->json(['error' => 'Invalid webhook secret'], Response::HTTP_UNAUTHORIZED);
        }

        // Parse JSON body
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['action'])) {
            return $this->json(['error' => 'Invalid JSON payload, expected {"action": "sync"|"sync_all", "files": [...]}'], Response::HTTP_BAD_REQUEST);
        }

        $action = $payload['action'];
        $results = [];

        if ($action === 'sync_all') {
            $files = $this->scanVaultArticles();
        } elseif ($action === 'sync' && isset($payload['files']) && is_array($payload['files'])) {
            $files = $payload['files'];
        } else {
            return $this->json(['error' => 'Invalid action. Use "sync" with "files" array or "sync_all"'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($files as $relativePath) {
            $fullPath = rtrim($this->vaultPath, '/') . '/' . ltrim($relativePath, '/');
            $result = $this->syncService->syncFromFile($fullPath);
            $results[] = [
                'file' => $relativePath,
                'status' => $result->status,
                'article_id' => $result->articleId,
                'errors' => $result->errors,
            ];
        }

        $statusCode = Response::HTTP_OK;
        foreach ($results as $r) {
            if ($r['status'] === 'validation_error') {
                $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
                break;
            }
        }

        return $this->json(['results' => $results], $statusCode);
    }

    /**
     * @return list<string>
     */
    private function scanVaultArticles(): array
    {
        $articlesDir = rtrim($this->vaultPath, '/') . '/articles';
        if (!is_dir($articlesDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($articlesDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                // Return path relative to vault root
                $files[] = str_replace(rtrim($this->vaultPath, '/') . '/', '', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }
}
