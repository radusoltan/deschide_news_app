<?php

declare(strict_types=1);

namespace App\Service\Import;

use League\Csv\Reader;

/**
 * Parses the legacy deschide.md CSV export (semicolon-delimited, UTF-8).
 * Yields associative arrays for each row with normalized field names.
 */
class CsvLegacyParser
{
    /** CSV column name → internal key mapping */
    private const COLUMN_MAP = [
        'id' => 'uuid',
        'title' => 'title',
        'slug' => 'slug',
        'main_image' => 'mainImage',
        'short_description' => 'shortDescription',
        'lead_text' => 'lead',
        'video_link' => 'videoLink',
        'content_text' => 'content',
        'category' => 'category',
        'keywords' => 'keywords',
        'author' => 'author',
        'is_featured' => 'isFeatured',
        'is_breaking_news' => 'isBreakingNews',
        'is_news_alert' => 'isNewsAlert',
        'is_flash_news' => 'isFlashNews',
        'archived' => 'archived',
        'draft' => 'draft',
        'created_at' => 'createdAt',
        'updated_at' => 'updatedAt',
        'published_at' => 'publishedAt',
        'created_by' => 'createdBy',
        'title_ru' => 'titleRu',
        'slug_ru' => 'slugRu',
        'lead_text_ru' => 'leadRu',
        'content_text_ru' => 'contentRu',
        'short_description_ru' => 'shortDescriptionRu',
        'keywords_ru' => 'keywordsRu',
        'scheduled_publish_at' => 'scheduledPublishAt',
        'main_image_ru' => 'mainImageRu',
    ];

    public function __construct(
        private readonly LegacyHtmlSanitizer $sanitizer,
    ) {
    }

    /**
     * Parse the CSV file and yield normalized row arrays.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function parse(string $csvPath, int $offset = 0, int $limit = 0): \Generator
    {
        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $rowIndex = 0;
        $yielded = 0;

        foreach ($csv->getRecords() as $record) {
            if ($rowIndex < $offset) {
                ++$rowIndex;
                continue;
            }

            if ($limit > 0 && $yielded >= $limit) {
                return;
            }

            $parsed = $this->normalizeRecord($record);
            if ($parsed !== null) {
                yield $rowIndex => $parsed;
                ++$yielded;
            }

            ++$rowIndex;
        }
    }

    /**
     * Count total records in the CSV file.
     */
    public function countRecords(string $csvPath): int
    {
        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        return iterator_count($csv->getRecords());
    }

    /**
     * Normalize a single CSV record into an internal format.
     */
    private function normalizeRecord(array $record): ?array
    {
        $data = [];

        foreach (self::COLUMN_MAP as $csvCol => $internalKey) {
            $data[$internalKey] = $record[$csvCol] ?? '';
        }

        // Validate required fields
        if (empty(trim($data['uuid'])) || empty(trim($data['title'])) || empty(trim($data['slug']))) {
            return null;
        }

        // Normalize diacritics on all text fields
        $data['title'] = $this->sanitizer->normalizeDiacritics(trim($data['title']));
        $data['lead'] = $this->sanitizer->sanitizePlainText($data['lead']);
        $data['content'] = $this->sanitizer->sanitize($data['content']);
        $data['slug'] = trim($data['slug']);

        // Append video embed if present
        if (!empty(trim($data['videoLink']))) {
            $data['content'] = $this->sanitizer->appendVideoEmbed($data['content'], $data['videoLink']);
        }

        // Parse boolean fields
        $data['isFeatured'] = $this->parseBool($data['isFeatured']);
        $data['isBreakingNews'] = $this->parseBool($data['isBreakingNews']);
        $data['isNewsAlert'] = $this->parseBool($data['isNewsAlert']);
        $data['isFlashNews'] = $this->parseBool($data['isFlashNews']);
        $data['archived'] = $this->parseBool($data['archived']);
        $data['draft'] = $this->parseBool($data['draft']);

        // Parse dates
        $data['createdAt'] = $this->parseDate($data['createdAt']);
        $data['updatedAt'] = $this->parseDate($data['updatedAt']);
        $data['publishedAt'] = $this->parseDate($data['publishedAt']);
        $data['scheduledPublishAt'] = $this->parseDate($data['scheduledPublishAt']);

        // RU translation fields — sanitize but only if non-empty
        if (!empty(trim($data['titleRu']))) {
            $data['titleRu'] = $this->sanitizer->normalizeDiacritics(trim($data['titleRu']));
            $data['slugRu'] = trim($data['slugRu']);
            $data['leadRu'] = $this->sanitizer->sanitizePlainText($data['leadRu']);
            $data['contentRu'] = $this->sanitizer->sanitize($data['contentRu']);
            $data['hasRuTranslation'] = true;
        } else {
            $data['hasRuTranslation'] = false;
        }

        // Truncate title if exceeds 255 chars
        if (mb_strlen($data['title']) > 255) {
            $data['title'] = mb_substr($data['title'], 0, 252) . '...';
        }

        return $data;
    }

    private function parseBool(string $value): bool
    {
        return mb_strtolower(trim($value)) === 'true' || $value === '1';
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if (empty($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
