<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Search;

use App\Service\Search\ElasticsearchIndexManager;
use App\Service\Search\SearchService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SearchServiceTest extends TestCase
{
    public function testSearchReturnsEmptyWhenDisabled(): void
    {
        $indexManager = $this->createMock(ElasticsearchIndexManager::class);
        $indexManager->method('isEnabled')->willReturn(false);

        $service = new SearchService($indexManager, new NullLogger());

        $result = $service->search('moldova', 'ro');

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['hits']);
    }

    public function testGetFieldsForLocaleUsesCorrectBoosts(): void
    {
        $service = new SearchService(
            $this->createMock(ElasticsearchIndexManager::class),
            new NullLogger(),
        );

        $reflection = new \ReflectionMethod($service, 'getFieldsForLocale');

        $fields = $reflection->invoke($service, 'ro');
        $this->assertContains('title_ro^3', $fields);
        $this->assertContains('description_ro^2', $fields);
        $this->assertContains('body_ro', $fields);

        $fieldsEn = $reflection->invoke($service, 'en');
        $this->assertContains('title_en^3', $fieldsEn);

        $fieldsRu = $reflection->invoke($service, 'ru');
        $this->assertContains('title_ru^3', $fieldsRu);
    }
}
