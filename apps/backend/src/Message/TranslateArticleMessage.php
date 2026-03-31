<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\TranslationPriority;

final readonly class TranslateArticleMessage
{
    /**
     * @param string[] $locales
     */
    public function __construct(
        public int $articleId,
        public array $locales = ['ru', 'en'],
        public bool $forceRetranslate = false,
        public TranslationPriority $priority = TranslationPriority::NORMAL,
    ) {
    }
}
