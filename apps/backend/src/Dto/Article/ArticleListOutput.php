<?php

declare(strict_types=1);

namespace App\Dto\Article;

use Symfony\Component\ObjectMapper\Attribute\Map;

/**
 * DTO for reading article in lists (lightweight version)
 * Maps from Article entity
 */
#[Map(source: \App\Entity\Article::class)]
final class ArticleListOutput
{
    #[Map(source: 'id')]
    public ?int $id = null;

    #[Map(source: 'title')]
    public ?string $title = null;

    #[Map(source: 'slug')]
    public ?string $slug = null;

    #[Map(source: 'lead')]
    public ?string $lead = null;

    // Exclude full content in lists for performance

    #[Map(source: 'status')]
    public ?string $status = null;

    #[Map(source: 'badge')]
    public ?string $badge = null;

    #[Map(source: 'featured')]
    public bool $featured = false;

    #[Map(source: 'viewCount')]
    public int $viewCount = 0;

    #[Map(source: 'publishedAt')]
    public ?\DateTimeImmutable $publishedAt = null;

    #[Map(source: 'locale')]
    public ?string $locale = null;

    /**
     * Computed property - reading time in minutes
     */
    #[Map(transform: \App\Transformer\Article\ReadingTimeTransformer::class)]
    public ?int $readingTime = null;
}
