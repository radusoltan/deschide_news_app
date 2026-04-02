<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Service\Editorial\WeeklySummaryService;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class WeeklySummaryServiceTest extends TestCase
{
    public function testSaveSummaryWritesCorrectFrontmatter(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-weekly-test-' . uniqid();

        $em = $this->createMock(EntityManagerInterface::class);
        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new WeeklySummaryService(
            geminiCliPath: '/usr/bin/gemini',
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        $weekEnd = new \DateTimeImmutable('2026-04-05');
        $filePath = $service->saveSummary(
            '# Test Summary Content',
            $weekEnd,
            42,
            $tmpDir,
        );

        self::assertNotEmpty($filePath);
        self::assertFileExists($filePath);

        $content = file_get_contents($filePath);
        self::assertStringContainsString('type: weekly-summary', $content);
        self::assertStringContainsString('article_count: 42', $content);
        self::assertStringContainsString('auto_generated: true', $content);
        self::assertStringContainsString('reviewed: false', $content);
        self::assertStringContainsString('# Test Summary Content', $content);

        $this->removeDir($tmpDir);
    }

    public function testSaveSummaryWithAudioPath(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-weekly-test-' . uniqid();

        $em = $this->createMock(EntityManagerInterface::class);
        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new WeeklySummaryService(
            geminiCliPath: '/usr/bin/gemini',
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        $weekEnd = new \DateTimeImmutable('2026-04-05');
        $filePath = $service->saveSummary(
            'Summary text',
            $weekEnd,
            10,
            $tmpDir,
            '/path/to/audio.mp3',
        );

        $content = file_get_contents($filePath);
        self::assertStringContainsString('audio_path: /path/to/audio.mp3', $content);

        $this->removeDir($tmpDir);
    }

    public function testSaveSummaryReturnsEmptyStringForInvalidPath(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new WeeklySummaryService(
            geminiCliPath: '/usr/bin/gemini',
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        // Use a non-writable path
        $filePath = $service->saveSummary('test', new \DateTimeImmutable(), 0, '');

        self::assertSame('', $filePath);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
