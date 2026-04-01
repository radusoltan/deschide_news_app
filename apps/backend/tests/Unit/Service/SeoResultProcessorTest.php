<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Service\SeoResultProcessor;
use App\Service\TagService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SeoResultProcessorTest extends TestCase
{
    private TagService $tagService;
    private EntityManagerInterface $em;
    private SeoResultProcessor $processor;

    protected function setUp(): void
    {
        $this->tagService = $this->createMock(TagService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('flush');

        $this->processor = new SeoResultProcessor(
            $this->tagService,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function itSetsMetaTitleAndDescription(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);
        $article->expects($this->once())->method('setMetaTitle')->with('SEO Title Test');
        $article->expects($this->once())->method('setMetaDescription')->with('SEO Description Test');

        $data = [
            'metaTitle' => 'SEO Title Test',
            'metaDescription' => 'SEO Description Test',
        ];

        $result = $this->processor->process($article, $data, [
            'generateMeta' => true,
            'suggestTags' => false,
            'force' => false,
        ]);

        $this->assertSame('SEO Title Test', $result['metaTitle']);
        $this->assertSame('SEO Description Test', $result['metaDescription']);
    }

    #[Test]
    public function itSkipsMetaWhenAlreadySetAndNotForced(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getMetaTitle')->willReturn('Existing title');
        $article->method('getMetaDescription')->willReturn('Existing desc');
        $article->expects($this->never())->method('setMetaTitle');
        $article->expects($this->never())->method('setMetaDescription');

        $data = [
            'metaTitle' => 'New Title',
            'metaDescription' => 'New Desc',
        ];

        $result = $this->processor->process($article, $data, [
            'generateMeta' => true,
            'suggestTags' => false,
            'force' => false,
        ]);

        $this->assertNull($result['metaTitle']);
        $this->assertNull($result['metaDescription']);
    }

    #[Test]
    public function itOverwritesMetaWithForce(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getMetaTitle')->willReturn('Existing title');
        $article->method('getMetaDescription')->willReturn('Existing desc');
        $article->expects($this->once())->method('setMetaTitle')->with('New Title');
        $article->expects($this->once())->method('setMetaDescription')->with('New Desc');

        $data = [
            'metaTitle' => 'New Title',
            'metaDescription' => 'New Desc',
        ];

        $result = $this->processor->process($article, $data, [
            'generateMeta' => true,
            'suggestTags' => false,
            'force' => true,
        ]);

        $this->assertSame('New Title', $result['metaTitle']);
        $this->assertSame('New Desc', $result['metaDescription']);
    }

    #[Test]
    public function itTruncatesLongMetaTitle(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);

        $longTitle = str_repeat('Abcde ', 15); // 90 chars
        $article->expects($this->once())
            ->method('setMetaTitle')
            ->with($this->callback(function (string $value): bool {
                return mb_strlen($value) <= 60;
            }));

        $data = [
            'metaTitle' => $longTitle,
        ];

        $result = $this->processor->process($article, $data, [
            'generateMeta' => true,
            'suggestTags' => false,
            'force' => false,
        ]);

        $this->assertNotNull($result['metaTitle']);
        $this->assertLessThanOrEqual(60, mb_strlen($result['metaTitle']));
    }

    #[Test]
    public function itProcessesSuggestedTags(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);

        $this->tagService->expects($this->once())
            ->method('addTagsToArticle')
            ->with(
                $article,
                ['UE', 'Moldova', 'Alegeri'],
                'ro'
            );

        $data = [
            'suggestedTags' => [
                ['name' => 'UE', 'isNew' => false],
                ['name' => 'Moldova', 'isNew' => false],
                ['name' => 'Alegeri', 'isNew' => true],
            ],
        ];

        $result = $this->processor->process($article, $data, [
            'generateMeta' => false,
            'suggestTags' => true,
            'force' => false,
        ]);

        $this->assertSame(['Alegeri'], $result['tagsAdded']);
        $this->assertSame(['UE', 'Moldova'], $result['tagsExisting']);
    }

    #[Test]
    public function itSkipsInvalidTagEntries(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);

        $this->tagService->expects($this->once())
            ->method('addTagsToArticle')
            ->with(
                $article,
                ['Valid Tag'],
                'ro'
            );

        $data = [
            'suggestedTags' => [
                ['name' => 'Valid Tag', 'isNew' => false],
                ['name' => '', 'isNew' => false],         // empty name
                ['noname' => true],                         // missing name
                'not-an-array',                             // invalid entry
            ],
        ];

        $result = $this->processor->process($article, $data, [
            'generateMeta' => false,
            'suggestTags' => true,
            'force' => false,
        ]);

        $this->assertSame(['Valid Tag'], $result['tagsExisting']);
    }

    #[Test]
    public function itHandlesEmptyResponse(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->expects($this->never())->method('setMetaTitle');
        $article->expects($this->never())->method('setMetaDescription');

        $result = $this->processor->process($article, [], [
            'generateMeta' => true,
            'suggestTags' => true,
            'force' => false,
        ]);

        $this->assertNull($result['metaTitle']);
        $this->assertNull($result['metaDescription']);
        $this->assertSame([], $result['tagsAdded']);
        $this->assertSame([], $result['tagsExisting']);
    }
}
