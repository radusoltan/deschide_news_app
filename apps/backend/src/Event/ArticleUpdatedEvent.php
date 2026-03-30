<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Article;

final readonly class ArticleUpdatedEvent
{
    public function __construct(
        public Article $article,
    ) {
    }
}
