<?php

declare(strict_types=1);

namespace App\Service\Editorial;

final readonly class MarkdownParseResult
{
    /**
     * @param array<string, mixed> $frontmatter
     */
    public function __construct(
        public array $frontmatter,
        public string $bodyHtml,
        public string $bodyMarkdown,
    ) {}
}
