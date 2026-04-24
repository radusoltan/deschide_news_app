<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Topic;

use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Topic\BatchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchResult::class)]
class BatchTopicClassifierTest extends TestCase
{
    public function testBatchResultDefaultValues(): void
    {
        $result = new BatchResult();

        self::assertSame(0, $result->classified);
        self::assertSame(0, $result->skipped);
        self::assertSame(0, $result->totalAssignments);
        self::assertSame(0, $result->failedChunks);
        self::assertSame(0, $result->failedArticles);
    }

    public function testBatchResultAccumulation(): void
    {
        $result = new BatchResult();
        $result->classified = 15;
        $result->skipped = 3;
        $result->totalAssignments = 30;
        $result->failedChunks = 1;
        $result->failedArticles = 20;

        self::assertSame(15, $result->classified);
        self::assertSame(3, $result->skipped);
        self::assertSame(30, $result->totalAssignments);
        self::assertSame(1, $result->failedChunks);
        self::assertSame(20, $result->failedArticles);
    }

    /**
     * Test the JSON parsing logic via reflection.
     */
    public function testParseResponseValidJson(): void
    {
        $classifier = $this->createClassifierWithReflection();
        $method = new \ReflectionMethod($classifier, 'parseResponse');

        $json = '[{"articleId": 100, "topicIds": [5, 12]}, {"articleId": 200, "topicIds": [3]}]';
        $result = $method->invoke($classifier, $json);

        self::assertCount(2, $result);
        self::assertSame(100, $result[0]['articleId']);
        self::assertSame([5, 12], $result[0]['topicIds']);
        self::assertSame(200, $result[1]['articleId']);
        self::assertSame([3], $result[1]['topicIds']);
    }

    public function testParseResponseWithMarkdownWrapping(): void
    {
        $classifier = $this->createClassifierWithReflection();
        $method = new \ReflectionMethod($classifier, 'parseResponse');

        $json = "```json\n[{\"articleId\": 42, \"topicIds\": [1]}]\n```";
        $result = $method->invoke($classifier, $json);

        self::assertCount(1, $result);
        self::assertSame(42, $result[0]['articleId']);
    }

    public function testParseResponseInvalidJson(): void
    {
        $classifier = $this->createClassifierWithReflection();
        $method = new \ReflectionMethod($classifier, 'parseResponse');

        $this->expectException(\RuntimeException::class);
        $method->invoke($classifier, 'not json at all');
    }

    public function testParseResponseEmptyTopicIds(): void
    {
        $classifier = $this->createClassifierWithReflection();
        $method = new \ReflectionMethod($classifier, 'parseResponse');

        $json = '[{"articleId": 100, "topicIds": []}]';
        $result = $method->invoke($classifier, $json);

        self::assertCount(1, $result);
        self::assertSame([], $result[0]['topicIds']);
    }

    public function testParseResponseSkipsMissingArticleId(): void
    {
        $classifier = $this->createClassifierWithReflection();
        $method = new \ReflectionMethod($classifier, 'parseResponse');

        $json = '[{"topicIds": [5]}, {"articleId": 200, "topicIds": [3]}]';
        $result = $method->invoke($classifier, $json);

        self::assertCount(1, $result);
        self::assertSame(200, $result[0]['articleId']);
    }

    private function createClassifierWithReflection(): \App\Service\Topic\BatchTopicClassifier
    {
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $topicRepo = $this->createMock(\App\Repository\TopicRepository::class);
        $logger = new \Psr\Log\NullLogger();

        return new \App\Service\Topic\BatchTopicClassifier(
            $em,
            $topicRepo,
            new GeminiCliService('/usr/bin/false', '/tmp', $logger),
            $logger,
            '/tmp',
        );
    }
}
