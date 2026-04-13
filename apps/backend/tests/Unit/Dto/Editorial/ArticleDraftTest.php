<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Editorial;

use App\Dto\Editorial\ArticleDraft;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArticleDraftTest extends TestCase
{
    #[Test]
    public function constructionAndReadonlyProperties(): void
    {
        $draft = new ArticleDraft(
            titleRo: 'Titlul articolului',
            leadRo: 'Lead-ul articolului',
            contentRo: 'Conținutul articolului complet.',
            metaDescription: 'Meta description SEO',
            suggestedTags: ['tag1', 'tag2'],
            clusterId: 42,
            confidenceScore: 0.85,
            sourcePressReleaseIds: [1, 2, 3],
            rawPrompt: 'The prompt sent to Gemini',
            rawResponse: '{"title": "..."}',
        );

        $this->assertSame('Titlul articolului', $draft->titleRo);
        $this->assertSame('Lead-ul articolului', $draft->leadRo);
        $this->assertSame('Conținutul articolului complet.', $draft->contentRo);
        $this->assertSame('Meta description SEO', $draft->metaDescription);
        $this->assertSame(['tag1', 'tag2'], $draft->suggestedTags);
        $this->assertSame(42, $draft->clusterId);
        $this->assertSame(0.85, $draft->confidenceScore);
        $this->assertSame([1, 2, 3], $draft->sourcePressReleaseIds);
        $this->assertSame('The prompt sent to Gemini', $draft->rawPrompt);
        $this->assertSame('{"title": "..."}', $draft->rawResponse);
    }

    #[Test]
    public function emptyTagsAndSources(): void
    {
        $draft = new ArticleDraft(
            titleRo: 'Title',
            leadRo: 'Lead',
            contentRo: 'Content',
            metaDescription: 'Meta',
            suggestedTags: [],
            clusterId: 1,
            confidenceScore: 0.5,
            sourcePressReleaseIds: [],
            rawPrompt: '',
            rawResponse: '',
        );

        $this->assertSame([], $draft->suggestedTags);
        $this->assertSame([], $draft->sourcePressReleaseIds);
    }
}
