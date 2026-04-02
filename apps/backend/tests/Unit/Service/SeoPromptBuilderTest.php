<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\SeoPromptBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SeoPromptBuilderTest extends TestCase
{
    #[Test]
    public function itBuildsPromptWithAllFields(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getTitle')->willReturn('Politica');

        $tag1 = new Tag();
        $tag1->setName('UE');
        $tag2 = new Tag();
        $tag2->setName('Moldova');

        $tagRepo = $this->createMock(TagRepository::class);
        $tagRepo->method('findPopularTags')->willReturn([$tag1, $tag2]);

        $article = $this->createMock(Article::class);
        $article->method('getTitle')->willReturn('Titlu test articol');
        $article->method('getLead')->willReturn('Lead text test');
        $article->method('getContent')->willReturn('<p>Continut articol de test cu mai mult text.</p>');
        $article->method('getCategory')->willReturn($category);
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);

        $builder = new SeoPromptBuilder($tagRepo);
        $prompt = $builder->build($article, [
            'generateMeta' => true,
            'suggestTags' => true,
            'force' => false,
        ]);

        $this->assertStringContainsString('Titlu test articol', $prompt);
        $this->assertStringContainsString('Lead text test', $prompt);
        $this->assertStringContainsString('Politica', $prompt);
        $this->assertStringContainsString('UE, Moldova', $prompt);
        $this->assertStringContainsString('metaTitle', $prompt);
        $this->assertStringContainsString('metaDescription', $prompt);
        $this->assertStringContainsString('suggestedTags', $prompt);
    }

    #[Test]
    public function itSkipsMetaWhenAlreadySetAndNotForced(): void
    {
        $tagRepo = $this->createMock(TagRepository::class);
        $tagRepo->method('findPopularTags')->willReturn([]);

        $article = $this->createMock(Article::class);
        $article->method('getTitle')->willReturn('Titlu');
        $article->method('getLead')->willReturn(null);
        $article->method('getContent')->willReturn('<p>Continut</p>');
        $article->method('getCategory')->willReturn(null);
        $article->method('getMetaTitle')->willReturn('Existing meta title');
        $article->method('getMetaDescription')->willReturn('Existing meta description');

        $builder = new SeoPromptBuilder($tagRepo);
        $prompt = $builder->build($article, [
            'generateMeta' => true,
            'suggestTags' => true,
            'force' => false,
        ]);

        // Should NOT contain metaTitle/metaDescription instructions (skip meta generation)
        $this->assertStringNotContainsString('MAXIM 60 caractere', $prompt);
        $this->assertStringNotContainsString('MAXIM 160 caractere', $prompt);
        // Should still contain tag suggestions
        $this->assertStringContainsString('suggestedTags', $prompt);
    }

    #[Test]
    public function itSkipsTagsWhenDisabled(): void
    {
        $tagRepo = $this->createMock(TagRepository::class);
        // Should NOT call findPopularTags when suggestTags=false
        $tagRepo->expects($this->never())->method('findPopularTags');

        $article = $this->createMock(Article::class);
        $article->method('getTitle')->willReturn('Titlu');
        $article->method('getLead')->willReturn(null);
        $article->method('getContent')->willReturn('<p>Continut</p>');
        $article->method('getCategory')->willReturn(null);
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);

        $builder = new SeoPromptBuilder($tagRepo);
        $prompt = $builder->build($article, [
            'generateMeta' => true,
            'suggestTags' => false,
            'force' => false,
        ]);

        $this->assertStringNotContainsString('suggestedTags', $prompt);
        $this->assertStringContainsString('metaTitle', $prompt);
    }

    #[Test]
    public function itTruncatesLongContent(): void
    {
        $tagRepo = $this->createMock(TagRepository::class);
        $tagRepo->method('findPopularTags')->willReturn([]);

        $longContent = str_repeat('Cuvant ', 500); // ~3500 chars

        $article = $this->createMock(Article::class);
        $article->method('getTitle')->willReturn('Titlu');
        $article->method('getLead')->willReturn(null);
        $article->method('getContent')->willReturn($longContent);
        $article->method('getCategory')->willReturn(null);
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);

        $builder = new SeoPromptBuilder($tagRepo);
        $prompt = $builder->build($article, [
            'generateMeta' => true,
            'suggestTags' => true,
            'force' => false,
        ]);

        // The content in the prompt should not exceed ~2000 chars for the article content
        // The full prompt will be larger, but the content portion should be truncated
        $this->assertLessThan(4000, mb_strlen($prompt));
    }
}
