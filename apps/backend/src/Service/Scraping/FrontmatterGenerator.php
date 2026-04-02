<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Dto\Scraping\ScrapedContent;
use App\Service\RomanianSlugger;

final readonly class FrontmatterGenerator
{
    public function __construct(
        private RomanianSlugger $slugger,
    ) {}

    /**
     * Generate a complete Markdown document with YAML frontmatter from scraped content.
     */
    public function generate(ScrapedContent $content, string $bodyMarkdown): string
    {
        $frontmatter = $this->buildFrontmatter($content);
        $yaml = $this->toYaml($frontmatter);

        return "---\n{$yaml}---\n\n{$bodyMarkdown}\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFrontmatter(ScrapedContent $content): array
    {
        $date = $content->publishedAt ?? new \DateTimeImmutable();
        $slug = $this->slugger->slugify($content->title);
        $datePrefix = $date->format('Y-m-d');
        $id = "art-{$datePrefix}-{$slug}";

        // Truncate ID to 128 chars max
        if (mb_strlen($id) > 128) {
            $id = mb_substr($id, 0, 128);
        }

        $description = mb_substr(strip_tags($content->bodyText), 0, 300);

        $lang = $content->language;

        return [
            'id' => $id,
            'type' => 'press-release',
            'language' => $lang,
            'title' => [$lang => $content->title],
            'description' => [$lang => $description],
            'slug' => [$lang => $slug],
            'status' => 'draft',
            'date_created' => $date->format('c'),
            'date_published' => $content->publishedAt?->format('c'),
            'source' => [
                'name' => $content->sourceName,
                'url' => $content->url,
                'original_language' => $lang,
            ],
            'ai' => [
                'translation_status' => [$lang => 'complete'],
                'auto_generated' => true,
                'reviewed' => false,
            ],
            'categories' => [],
            'tags' => [],
        ];
    }

    /**
     * Simple YAML serialization for frontmatter.
     */
    private function toYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (\is_array($value)) {
                if ($value === []) {
                    $yaml .= "{$prefix}{$key}: []\n";
                } elseif (array_is_list($value)) {
                    $yaml .= "{$prefix}{$key}:\n";
                    foreach ($value as $item) {
                        if (\is_array($item)) {
                            $yaml .= "{$prefix}  -\n" . $this->toYaml($item, $indent + 2);
                        } else {
                            $yaml .= "{$prefix}  - " . $this->escapeYamlValue($item) . "\n";
                        }
                    }
                } else {
                    $yaml .= "{$prefix}{$key}:\n" . $this->toYaml($value, $indent + 1);
                }
            } elseif (\is_bool($value)) {
                $yaml .= "{$prefix}{$key}: " . ($value ? 'true' : 'false') . "\n";
            } else {
                $yaml .= "{$prefix}{$key}: " . $this->escapeYamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    private function escapeYamlValue(mixed $value): string
    {
        $str = (string) $value;

        // Quote strings that contain special YAML characters
        if (preg_match('/[:#\[\]{}|>!&*?,]/', $str) || str_starts_with($str, "'") || str_starts_with($str, '"')) {
            return '"' . addcslashes($str, '"\\') . '"';
        }

        return $str;
    }
}
