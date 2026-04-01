<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\SeoBatchPromptBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SeoBatchPromptBuilderTest extends TestCase
{
    #[Test]
    public function itBuildsBatchPromptWithMultipleArticles(): void
    {
        $tag1 = new Tag();
        $tag1->setName('UE');

        $tagRepo = $this->createMock(TagRepository::class);
        $tagRepo->method('findPopularTags')->willReturn([$tag1]);

        $category = $this->createMock(Category::class);
        $category->method('getTitle')->willReturn('Politica');

        $articles = [];
        for ($i = 1; $i <= 3; ++$i) {
            $article = $this->createMock(Article::class);
            $article->method('getId')->willReturn($i);
            $article->method('getTitle')->willReturn("Titlu articol {$i}");
            $article->method('getLead')->willReturn("Lead {$i}");
            $article->method('getContent')->willReturn("<p>Continut articol {$i}</p>");
            $article->method('getCategory')->willReturn($category);
            $articles[] = $article;
        }

        $builder = new SeoBatchPromptBuilder($tagRepo);
        $prompt = $builder->build($articles, [
            'generateMeta' => true,
            'suggestTags' => true,
            'force' => false,
        ]);

        // Must contain all article titles
        $this->assertStringContainsString('Titlu articol 1', $prompt);
        $this->assertStringContainsString('Titlu articol 2', $prompt);
        $this->assertStringContainsString('Titlu articol 3', $prompt);

        // Must contain articleId references
        $this->assertStringContainsString('articleId: 1', $prompt);
        $this->assertStringContainsString('articleId: 2', $prompt);
        $this->assertStringContainsString('articleId: 3', $prompt);

        // Must contain article count
        $this->assertStringContainsString('3 articole', $prompt);

        // Must contain existing tags
        $this->assertStringContainsString('UE', $prompt);

        // Must specify JSON array format
        $this->assertStringContainsString('JSON array', $prompt);
    }

    #[Test]
    public function itTruncatesContentPerArticle(): void
    {
        $tagRepo = $this->createMock(TagRepository::class);
        $tagRepo->method('findPopularTags')->willReturn([]);

        $longContent = str_repeat('Cuvant ', 200); // ~1400 chars

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Titlu');
        $article->method('getLead')->willReturn(null);
        $article->method('getContent')->willReturn($longContent);
        $article->method('getCategory')->willReturn(null);

        $builder = new SeoBatchPromptBuilder($tagRepo);
        $prompt = $builder->build([$article], [
            'generateMeta' => true,
            'suggestTags' => true,
            'force' => false,
        ]);

        // Full content is ~1400 chars, should be truncated to ~500
        // The prompt should be significantly shorter than with full content
        $this->assertLessThan(2500, mb_strlen($prompt));
    }

    #[Test]
    public function itSkipsTagsWhenDisabled(): void
    {
        $tagRepo = $this->createMock(TagRepository::class);
        $tagRepo->expects($this->never())->method('findPopularTags');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Titlu');
        $article->method('getLead')->willReturn('Lead');
        $article->method('getContent')->willReturn('<p>Content</p>');
        $article->method('getCategory')->willReturn(null);

        $builder = new SeoBatchPromptBuilder($tagRepo);
        $prompt = $builder->build([$article], [
            'generateMeta' => true,
            'suggestTags' => false,
            'force' => false,
        ]);

        $this->assertStringNotContainsString('suggestedTags', $prompt);
        $this->assertStringContainsString('metaTitle', $prompt);
    }
}
