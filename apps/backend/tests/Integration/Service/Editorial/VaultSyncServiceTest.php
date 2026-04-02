<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Editorial;

use App\Entity\Article;
use App\Service\Editorial\VaultSyncResult;
use App\Service\Editorial\VaultSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VaultSyncServiceTest extends KernelTestCase
{
    private VaultSyncService $syncService;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->syncService = $container->get(VaultSyncService::class);
        $this->em = $container->get(EntityManagerInterface::class);
    }

    public function testIngestCompleteArticle(): void
    {
        $vaultId = 'art-test-ingest-' . uniqid();
        $content = $this->getValidMarkdownContent($vaultId);

        $result = $this->syncService->syncFromContent($content);

        $this->assertSame(VaultSyncResult::STATUS_CREATED, $result->status, 'Errors: ' . implode(', ', $result->errors));
        $this->assertNotNull($result->articleId);
        $this->assertSame([], $result->errors);

        // Verify article in DB
        $article = $this->em->getRepository(Article::class)->find($result->articleId);
        $this->assertNotNull($article);
        $this->assertSame('Guvernul aprobă noul plan de reformă energetică', $article->getTitle());
    }

    public function testIngestCreatesGedmoTranslations(): void
    {
        $vaultId = 'art-test-trans-' . uniqid();
        $content = $this->getValidMarkdownContent($vaultId);
        $result = $this->syncService->syncFromContent($content);

        $this->assertSame(VaultSyncResult::STATUS_CREATED, $result->status);

        // Check ext_translations for EN and RU
        $conn = $this->em->getConnection();
        $translations = $conn->fetchAllAssociative(
            "SELECT locale, field, content FROM ext_translations WHERE object_class = :class AND foreign_key = :id ORDER BY locale, field",
            ['class' => Article::class, 'id' => (string) $result->articleId],
        );

        $translationMap = [];
        foreach ($translations as $row) {
            $translationMap[$row['locale']][$row['field']] = $row['content'];
        }

        $this->assertArrayHasKey('en', $translationMap);
        $this->assertSame('Government Approves Energy Reform Plan', $translationMap['en']['title']);
        $this->assertArrayHasKey('ru', $translationMap);
        $this->assertSame('Правительство утвердило план', $translationMap['ru']['title']);
    }

    public function testValidationErrorReturnedForInvalidFrontmatter(): void
    {
        $content = <<<'MD'
---
id: "no-art-prefix"
type: "invalid-type"
---

Body text.
MD;

        $result = $this->syncService->syncFromContent($content);

        $this->assertSame(VaultSyncResult::STATUS_VALIDATION_ERROR, $result->status);
        $this->assertNull($result->articleId);
        $this->assertNotEmpty($result->errors);
    }

    public function testUpdateExistingArticle(): void
    {
        $vaultId = 'art-test-upd-' . uniqid();
        $content = $this->getValidMarkdownContent($vaultId);

        // First sync — creates
        $result1 = $this->syncService->syncFromContent($content);
        $this->assertSame(VaultSyncResult::STATUS_CREATED, $result1->status);
        $articleId = $result1->articleId;

        // Second sync — updates (same vault ID)
        $result2 = $this->syncService->syncFromContent($content);
        $this->assertSame(VaultSyncResult::STATUS_UPDATED, $result2->status);
        $this->assertSame($articleId, $result2->articleId);

        // Verify only one article exists with this vault ID
        $articles = $this->em->getRepository(Article::class)->findBy(['sourceEmail' => $vaultId]);
        $this->assertCount(1, $articles);
    }

    public function testParseErrorForEmptyContent(): void
    {
        $result = $this->syncService->syncFromContent('');

        $this->assertSame(VaultSyncResult::STATUS_PARSE_ERROR, $result->status);
        $this->assertNotEmpty($result->errors);
    }

    public function testSyncFromFileNotFound(): void
    {
        $result = $this->syncService->syncFromFile('/nonexistent/path.md');

        $this->assertSame(VaultSyncResult::STATUS_PARSE_ERROR, $result->status);
        $this->assertStringContainsString('File not found', $result->errors[0]);
    }

    private function getValidMarkdownContent(string $vaultId): string
    {
        $slug = 'test-' . substr(md5($vaultId), 0, 8);
        return <<<MD
---
id: "{$vaultId}"
type: "news"
language: "ro"
title:
  ro: "Guvernul aprobă noul plan de reformă energetică"
  en: "Government Approves Energy Reform Plan"
  ru: "Правительство утвердило план"
description:
  ro: "Cabinetul a aprobat planul de reformă."
  en: "The cabinet approved the reform plan."
slug:
  ro: "{$slug}-ro"
  en: "{$slug}-en"
  ru: "{$slug}-ru"
status: "draft"
date_created: "2026-04-02T06:30:00Z"
author: "ion-popescu"
categories: []
tags: []
---

# Reforma energetică

Corpul articolului de test.
MD;
    }
}
