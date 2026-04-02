<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\EntityExtractionResult;
use App\Entity\Article;
use App\Entity\Category;
use App\Service\Editorial\ArticleIngestionService;
use App\Service\NotebookLM\NotebookLMService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ArticleIngestionServiceTest extends TestCase
{
    public function testEntityExtractionResultFromArrayWithValidData(): void
    {
        $data = [
            'persons' => [
                ['name' => 'Ion Popescu', 'role' => 'Ministru', 'institution' => 'Ministerul Economiei'],
            ],
            'institutions' => [
                ['name' => 'Ministerul Economiei', 'abbreviation' => 'ME', 'type' => 'gov'],
            ],
            'events' => [
                ['name' => 'Ședința Guvernului', 'date' => '2026-04-01', 'location' => 'Chișinău'],
            ],
            'locations' => [
                ['name' => 'Chișinău', 'type' => 'city'],
            ],
            'topics' => ['economie', 'guvern'],
            'categories_suggested' => ['economie', 'politică'],
            'confidence' => 0.85,
        ];

        $result = EntityExtractionResult::fromArray($data);

        self::assertCount(1, $result->persons);
        self::assertCount(1, $result->institutions);
        self::assertCount(1, $result->events);
        self::assertCount(1, $result->locations);
        self::assertCount(2, $result->topics);
        self::assertCount(2, $result->categoriesSuggested);
        self::assertSame(0.85, $result->confidence);
        self::assertTrue($result->hasEntities());
        self::assertSame(4, $result->totalCount());
    }

    public function testEntityExtractionResultFromArrayWithInvalidData(): void
    {
        $result = EntityExtractionResult::fromArray([]);

        self::assertFalse($result->hasEntities());
        self::assertSame(0, $result->totalCount());
        self::assertSame([], $result->persons);
        self::assertSame([], $result->topics);
    }

    public function testEntityExtractionResultFromArrayNormalizesEntities(): void
    {
        $data = [
            'persons' => [
                ['name' => 'Ion Popescu', 'role' => 'Ministru'],
                'invalid string entry',
                ['no_name' => 'missing name field'],
            ],
        ];

        $result = EntityExtractionResult::fromArray($data);

        // Only the first entry (with 'name' key) should be kept
        self::assertCount(1, $result->persons);
        self::assertSame('Ion Popescu', $result->persons[0]['name']);
    }

    public function testEntityExtractionResultToArray(): void
    {
        $result = new EntityExtractionResult(
            persons: [['name' => 'Ion Popescu']],
            topics: ['politică'],
            confidence: 0.9,
        );

        $array = $result->toArray();

        self::assertSame([['name' => 'Ion Popescu']], $array['persons']);
        self::assertSame(['politică'], $array['topics']);
        self::assertSame(0.9, $array['confidence']);
    }

    public function testCreateAtomicNotesCreatesPersonNote(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-test-' . uniqid();
        mkdir($tmpDir . '/knowledge/persons', 0o755, true);

        $notebookLM = new NotebookLMService(
            enabled: false,
            cliPath: '/nonexistent',
            logger: new NullLogger(),
        );

        $service = new ArticleIngestionService(
            geminiCliPath: '/usr/bin/gemini',
            notebookLMService: $notebookLM,
            logger: new NullLogger(),
        );

        $article = new Article();
        $article->setTitle('Test article');

        $result = new EntityExtractionResult(
            persons: [['name' => 'Ion Popescu', 'role' => 'Ministru', 'institution' => 'Guvern']],
        );

        $created = $service->createAtomicNotes($result, $article, $tmpDir);

        self::assertSame(1, $created);
        self::assertFileExists($tmpDir . '/knowledge/persons/PER-ion-popescu.md');

        $content = file_get_contents($tmpDir . '/knowledge/persons/PER-ion-popescu.md');
        self::assertStringContainsString('type: person', $content);
        self::assertStringContainsString('name: Ion Popescu', $content);
        self::assertStringContainsString('auto_generated: true', $content);
        self::assertStringContainsString('# Ion Popescu', $content);

        // Cleanup
        $this->removeDir($tmpDir);
    }

    public function testCreateAtomicNotesSkipsExistingNotes(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-test-' . uniqid();
        mkdir($tmpDir . '/knowledge/persons', 0o755, true);

        // Pre-create the note
        file_put_contents(
            $tmpDir . '/knowledge/persons/PER-ion-popescu.md',
            "---\ntype: person\nname: Ion Popescu\n---\n\n# Ion Popescu\n\n## Menționări\n\n",
        );

        $notebookLM = new NotebookLMService(
            enabled: false,
            cliPath: '/nonexistent',
            logger: new NullLogger(),
        );

        $service = new ArticleIngestionService(
            geminiCliPath: '/usr/bin/gemini',
            notebookLMService: $notebookLM,
            logger: new NullLogger(),
        );

        $article = new Article();
        $article->setTitle('New article');

        $result = new EntityExtractionResult(
            persons: [['name' => 'Ion Popescu']],
        );

        $created = $service->createAtomicNotes($result, $article, $tmpDir);

        // Should skip creation (note exists) and only append backlink
        self::assertSame(0, $created);

        $this->removeDir($tmpDir);
    }

    public function testFeedNotebookLMReturnsFalseWhenUnavailable(): void
    {
        $notebookLM = new NotebookLMService(
            enabled: false,
            cliPath: '/nonexistent',
            logger: new NullLogger(),
        );

        $service = new ArticleIngestionService(
            geminiCliPath: '/usr/bin/gemini',
            notebookLMService: $notebookLM,
            logger: new NullLogger(),
        );

        $article = new Article();
        $article->setTitle('Test');

        self::assertFalse($service->feedNotebookLM($article));
    }

    public function testUpdateMOCsAddsChronologyEntry(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-test-' . uniqid();
        mkdir($tmpDir . '/mocs', 0o755, true);

        // Create a MOC file
        file_put_contents(
            $tmpDir . '/mocs/MOC-Economie.md',
            "---\ntype: moc\n---\n\n# MOC Economie\n\n## Cronologie\n\n",
        );

        $notebookLM = new NotebookLMService(
            enabled: false,
            cliPath: '/nonexistent',
            logger: new NullLogger(),
        );

        $service = new ArticleIngestionService(
            geminiCliPath: '/usr/bin/gemini',
            notebookLMService: $notebookLM,
            logger: new NullLogger(),
        );

        $category = new Category();
        $category->setTitle('Economie');

        $article = new Article();
        $article->setTitle('Test economic article');
        $ref = new \ReflectionProperty(Article::class, 'category');
        $ref->setValue($article, $category);

        $result = new EntityExtractionResult(
            categoriesSuggested: ['economie'],
        );

        $updated = $service->updateMOCs($article, $result, $tmpDir);

        self::assertSame(1, $updated);

        $content = file_get_contents($tmpDir . '/mocs/MOC-Economie.md');
        self::assertStringContainsString('[[art-', $content);

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
