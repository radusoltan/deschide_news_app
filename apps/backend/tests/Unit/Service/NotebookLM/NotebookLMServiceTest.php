<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\NotebookLM;

use App\Entity\Topic;
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

    public function testResolveNotebookIdReturnsIdFromTopic(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Politica');
        $topic->setNotebookLmId('nb-001');

        self::assertSame('nb-001', $service->resolveNotebookId($topic));
    }

    public function testResolveNotebookIdReturnsNullWhenTopicHasNoNotebook(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Economie');

        self::assertNull($service->resolveNotebookId($topic));
    }

    public function testEnsureNotebookForTopicReturnsExistingId(): void
    {
        $service = new NotebookLMService(
            enabled: true,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Politica');
        $topic->setNotebookLmId('existing-notebook-id');

        self::assertSame('existing-notebook-id', $service->ensureNotebookForTopic($topic));
    }

    public function testEnsureNotebookForTopicReturnsNullWhenDisabled(): void
    {
        $service = new NotebookLMService(
            enabled: false,
            cliPath: '/usr/bin/notebooklm',
            logger: new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Politica');

        self::assertNull($service->ensureNotebookForTopic($topic));
    }
}
