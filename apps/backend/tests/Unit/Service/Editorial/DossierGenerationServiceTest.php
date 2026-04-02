<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Service\Editorial\DossierGenerationService;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DossierGenerationServiceTest extends TestCase
{
    public function testDetectMOCsNeedingDossierWithThreshold(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-dossier-test-' . uniqid();
        mkdir($tmpDir . '/mocs', 0o755, true);

        // MOC with 6 article references (above default threshold of 5)
        $content = "---\ntype: moc\n---\n\n# MOC Test\n\n## Cronologie\n\n"
            . "- [[art-2026-04-01-article1]]\n"
            . "- [[art-2026-04-02-article2]]\n"
            . "- [[art-2026-04-03-article3]]\n"
            . "- [[art-2026-04-04-article4]]\n"
            . "- [[art-2026-04-05-article5]]\n"
            . "- [[art-2026-04-06-article6]]\n";
        file_put_contents($tmpDir . '/mocs/MOC-Test-Topic.md', $content);

        // MOC with 2 article references (below threshold)
        $contentSmall = "---\ntype: moc\n---\n\n# MOC Small\n\n## Cronologie\n\n"
            . "- [[art-2026-04-01-article1]]\n"
            . "- [[art-2026-04-02-article2]]\n";
        file_put_contents($tmpDir . '/mocs/MOC-Small.md', $contentSmall);

        $em = $this->createMock(EntityManagerInterface::class);
        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new DossierGenerationService(
            geminiCliPath: '/usr/bin/gemini',
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        $results = $service->detectMOCsNeedingDossier($tmpDir, 5);

        self::assertCount(1, $results);
        self::assertSame('MOC-Test-Topic.md', $results[0]['moc']);
        self::assertSame(6, $results[0]['articleCount']);

        $this->removeDir($tmpDir);
    }

    public function testDetectMOCsReturnsEmptyForNonExistentDir(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new DossierGenerationService(
            geminiCliPath: '/usr/bin/gemini',
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        $results = $service->detectMOCsNeedingDossier('/nonexistent/vault/path', 5);

        self::assertSame([], $results);
    }

    public function testDetectMOCsWithHigherThreshold(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-dossier-test-' . uniqid();
        mkdir($tmpDir . '/mocs', 0o755, true);

        $content = "---\ntype: moc\n---\n\n# MOC\n\n"
            . "- [[art-1]] [[art-2]] [[art-3]] [[art-4]] [[art-5]]\n";
        file_put_contents($tmpDir . '/mocs/MOC-Five.md', $content);

        $em = $this->createMock(EntityManagerInterface::class);
        $nlm = new NotebookLMService(enabled: false, cliPath: '/nonexistent', logger: new NullLogger());

        $service = new DossierGenerationService(
            geminiCliPath: '/usr/bin/gemini',
            em: $em,
            notebookLMService: $nlm,
            logger: new NullLogger(),
        );

        // Threshold 10 — should exclude our MOC with 5 refs
        $results = $service->detectMOCsNeedingDossier($tmpDir, 10);
        self::assertSame([], $results);

        // Threshold 3 — should include it
        $results = $service->detectMOCsNeedingDossier($tmpDir, 3);
        self::assertCount(1, $results);

        $this->removeDir($tmpDir);
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
