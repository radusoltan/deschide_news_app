<?php

declare(strict_types=1);

namespace App\Dto\Article;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating/updating articles
 * Maps to Article entity
 */
#[Map(target: \App\Entity\Article::class)]
final class ArticleInput
{
    #[Map(target: 'title')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Map(target: 'lead')]
    #[Assert\Length(max: 500)]
    public ?string $lead = null;

    #[Map(target: 'content')]
    #[Assert\NotBlank]
    public ?string $content = null;

    #[Map(target: 'status')]
    public string $status = 'new';

    #[Map(target: 'badge')]
    public ?string $badge = null;

    #[Map(target: 'isFeatured')]
    public bool $featured = false;
}
