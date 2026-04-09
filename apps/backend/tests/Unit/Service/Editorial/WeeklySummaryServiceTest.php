<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\GeneratedContent;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\WeeklySummaryService;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class WeeklySummaryServiceTest extends TestCase
{
    public function testSaveSummaryPersistsGeneratedContent(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(GeneratedContent::class));
        $em->expects($this->once())->method('flush');

        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new WeeklySummaryService(
            geminiCli: new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()),
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        $weekStart = new \DateTimeImmutable('2026-03-30');
        $weekEnd = new \DateTimeImmutable('2026-04-05');

        $gc = $service->saveSummary(
            '# Test Summary Content',
            $weekStart,
            $weekEnd,
            42,
        );

        self::assertInstanceOf(GeneratedContent::class, $gc);
        self::assertSame('weekly_summary', $gc->getType());
        self::assertStringContainsString('30.03', $gc->getTitle());
        self::assertStringContainsString('05.04.2026', $gc->getTitle());
        self::assertSame('# Test Summary Content', $gc->getContent());
        self::assertSame(42, $gc->getMetadata()['article_count']);
        self::assertSame('2026-W14', $gc->getMetadata()['week']);
    }

    public function testSaveSummaryReturnsGeneratedContent(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new WeeklySummaryService(
            geminiCli: new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()),
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        $weekStart = new \DateTimeImmutable('2026-03-30');
        $weekEnd = new \DateTimeImmutable('2026-04-05');

        $gc = $service->saveSummary('Summary text', $weekStart, $weekEnd, 10);

        self::assertSame('weekly_summary', $gc->getType());
        self::assertSame('Summary text', $gc->getContent());
    }
}
