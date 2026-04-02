<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\NotebookLM;

use App\Service\NotebookLM\NotebookLMService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class NotebookLMServiceTest extends TestCase
{
    public function testIsAvailableReturnsFalseWhenDisabled(): void
    {
        $service = new NotebookLMService(
            enabled: false,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertFalse($service->isAvailable());
    }

    public function testAddSourceReturnsFalseWhenDisabled(): void
    {
        $service = new NotebookLMService(
            enabled: false,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertFalse($service->addSource('notebook-123', 'https://example.com/article'));
    }

    public function testAddSourceReturnsFalseWithEmptyNotebookId(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertFalse($service->addSource('', 'https://example.com/article'));
    }

    public function testAddTextSourceReturnsFalseWhenDisabled(): void
    {
        $service = new NotebookLMService(
            enabled: false,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertFalse($service->addTextSource('notebook-123', 'Title', 'Content'));
    }

    public function testAskReturnsNullWhenDisabled(): void
    {
        $service = new NotebookLMService(
            enabled: false,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertNull($service->ask('notebook-123', 'What is this about?'));
    }

    public function testGenerateAudioReturnsNullWhenDisabled(): void
    {
        $service = new NotebookLMService(
            enabled: false,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertNull($service->generateAudio('notebook-123', 'instructions', 'brief', 'ro'));
    }

    public function testGenerateMindMapReturnsNullWhenDisabled(): void
    {
        $service = new NotebookLMService(
            enabled: false,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertNull($service->generateMindMap('notebook-123'));
    }

    public function testResolveNotebookIdDirectMatch(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        $notebooks = [
            'politica' => 'nb-001',
            'economie' => 'nb-002',
        ];

        self::assertSame('nb-001', $service->resolveNotebookId('politica', $notebooks));
        self::assertSame('nb-002', $service->resolveNotebookId('economie', $notebooks));
    }

    public function testResolveNotebookIdFuzzyMatch(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        $notebooks = [
            'politica' => 'nb-001',
            'integrare_ue' => 'nb-003',
        ];

        self::assertSame('nb-001', $service->resolveNotebookId('politică', $notebooks));
        self::assertSame('nb-003', $service->resolveNotebookId('integrare-ue', $notebooks));
    }

    public function testResolveNotebookIdReturnsNullForUnknown(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertNull($service->resolveNotebookId('unknown-category', ['politica' => 'nb-001']));
    }

    public function testResolveNotebookIdSkipsEmptyIds(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        self::assertNull($service->resolveNotebookId('politica', ['politica' => '']));
    }
}
