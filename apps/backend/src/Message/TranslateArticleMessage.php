<?php

declare(strict_types=1);

namespace App\Message;

final readonly class TranslateArticleMessage
{
    /**
     * @param string[] $locales
     */
    public function __construct(
        public int $articleId,
        public array $locales = ['ru', 'en'],
        public bool $forceRetranslate = false,
    ) {
    }
}
