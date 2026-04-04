<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\EntityExtractionResult;
use App\Entity\Article;
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

    public function testServiceCanBeInstantiated(): void
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

        self::assertInstanceOf(ArticleIngestionService::class, $service);
    }
}
