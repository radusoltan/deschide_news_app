<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\VaultSyncResult;
use App\Entity\Article;
use App\Message\Editorial\SyncArticleToVaultMessage;
use App\MessageHandler\Editorial\SyncArticleToVaultHandler;
use App\Service\Editorial\DbToVaultSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SyncArticleToVaultHandlerTest extends TestCase
{
    private DbToVaultSyncService $syncService;
    private EntityManagerInterface $em;
    private SyncArticleToVaultHandler $handler;

    protected function setUp(): void
    {
        $this->syncService = $this->createMock(DbToVaultSyncService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->handler = new SyncArticleToVaultHandler(
            $this->syncService,
            $this->em,
            new NullLogger(),
        );
    }

    public function testSyncActionCallsSyncService(): void
    {
        $article = $this->createStub(Article::class);
        $this->em->method('find')->with(Article::class, 42)->willReturn($article);

        $this->syncService->expects($this->once())
            ->method('syncArticleToVault')
            ->with($article)
            ->willReturn(new VaultSyncResult(42, VaultSyncResult::ACTION_CREATED, 'articles/2026/04/test.md'));

        $message = new SyncArticleToVaultMessage(articleId: 42, action: 'sync');
        ($this->handler)($message);
    }

    public function testArchiveActionCallsArchiveService(): void
    {
        $this->syncService->expects($this->once())
            ->method('archiveArticleFromVault')
            ->with(77, 'my-slug', $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(new VaultSyncResult(77, VaultSyncResult::ACTION_ARCHIVED, '_archived/2026/04/my-slug-123.md'));

        $message = new SyncArticleToVaultMessage(
            articleId: 77,
            action: 'archive',
            slug: 'my-slug',
            createdAt: '2026-04-01T10:00:00+00:00',
        );
        ($this->handler)($message);
    }

    public function testSyncSkipsGracefullyWhenArticleNotFound(): void
    {
        $this->em->method('find')->with(Article::class, 999)->willReturn(null);

        $this->syncService->expects($this->never())
            ->method('syncArticleToVault');

        $message = new SyncArticleToVaultMessage(articleId: 999, action: 'sync');
        ($this->handler)($message);
    }
}
