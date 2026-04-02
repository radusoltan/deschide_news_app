<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\MarkdownConverter;

class MarkdownParser
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new FrontMatterExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function parse(string $markdownContent): MarkdownParseResult
    {
        if (trim($markdownContent) === '') {
            return new MarkdownParseResult([], '', '');
        }

        $rendered = $this->converter->convert($markdownContent);

        $frontmatter = [];
        if ($rendered instanceof RenderedContentWithFrontMatter) {
            $fm = $rendered->getFrontMatter();
            $frontmatter = is_array($fm) ? $fm : [];
        }

        $bodyHtml = $rendered->getContent();
        $bodyMarkdown = $this->extractBodyMarkdown($markdownContent);

        return new MarkdownParseResult($frontmatter, $bodyHtml, $bodyMarkdown);
    }

    private function extractBodyMarkdown(string $content): string
    {
        // Remove YAML frontmatter block (--- ... ---)
        if (str_starts_with(trim($content), '---')) {
            $parts = preg_split('/^---\s*$/m', $content, 3);
            if ($parts !== false && count($parts) >= 3) {
                return trim($parts[2]);
            }
        }

        return trim($content);
    }
}
