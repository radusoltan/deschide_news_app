<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Service\Editorial\MarkdownParser;
use App\Service\Editorial\MarkdownParseResult;
use PHPUnit\Framework\TestCase;

class MarkdownParserTest extends TestCase
{
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MarkdownParser();
    }

    public function testParseWithValidTrilingualFrontmatter(): void
    {
        $content = <<<'MD'
---
id: "art-2026-04-02-reforma-energetica"
type: "press-release"
language: "ro"
title:
  ro: "Guvernul aprobă noul plan de reformă energetică"
  en: "Government Approves Energy Reform Plan"
  ru: "Правительство утвердило план"
status: "draft"
date_created: "2026-04-02T06:30:00Z"
---

# Titlu articol

Corpul articolului în **Markdown**.
MD;

        $result = $this->parser->parse($content);

        $this->assertInstanceOf(MarkdownParseResult::class, $result);
        $this->assertSame('art-2026-04-02-reforma-energetica', $result->frontmatter['id']);
        $this->assertSame('press-release', $result->frontmatter['type']);
        $this->assertSame('ro', $result->frontmatter['language']);
        $this->assertSame('Guvernul aprobă noul plan de reformă energetică', $result->frontmatter['title']['ro']);
        $this->assertSame('Government Approves Energy Reform Plan', $result->frontmatter['title']['en']);
        $this->assertSame('draft', $result->frontmatter['status']);

        $this->assertStringContainsString('<h1>Titlu articol</h1>', $result->bodyHtml);
        $this->assertStringContainsString('<strong>Markdown</strong>', $result->bodyHtml);
        $this->assertStringContainsString('# Titlu articol', $result->bodyMarkdown);
        $this->assertStringNotContainsString('---', $result->bodyMarkdown);
    }

    public function testParseWithoutFrontmatter(): void
    {
        $content = "# Just a heading\n\nSome **bold** text.";

        $result = $this->parser->parse($content);

        $this->assertSame([], $result->frontmatter);
        $this->assertStringContainsString('<h1>Just a heading</h1>', $result->bodyHtml);
        $this->assertStringContainsString('# Just a heading', $result->bodyMarkdown);
    }

    public function testParseEmptyContent(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame([], $result->frontmatter);
        $this->assertSame('', $result->bodyHtml);
        $this->assertSame('', $result->bodyMarkdown);
    }

    public function testParseWhitespaceOnlyContent(): void
    {
        $result = $this->parser->parse("   \n\n  ");

        $this->assertSame([], $result->frontmatter);
        $this->assertSame('', $result->bodyHtml);
        $this->assertSame('', $result->bodyMarkdown);
    }

    public function testParseWithFrontmatterOnly(): void
    {
        $content = "---\nid: \"art-test\"\ntype: \"news\"\n---\n";

        $result = $this->parser->parse($content);

        $this->assertSame('art-test', $result->frontmatter['id']);
        $this->assertSame('news', $result->frontmatter['type']);
    }

    public function testBodyMarkdownDoesNotContainFrontmatter(): void
    {
        $content = <<<'MD'
---
id: "art-test"
---

Body text here.
MD;

        $result = $this->parser->parse($content);

        $this->assertStringNotContainsString('id:', $result->bodyMarkdown);
        $this->assertSame('Body text here.', $result->bodyMarkdown);
    }
}
