<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * Result of AI entity extraction from article content.
 */
final readonly class EntityExtractionResult
{
    /**
     * @param list<array{name: string, role?: string, institution?: string}> $persons
     * @param list<array{name: string, abbreviation?: string, type?: string}> $institutions
     * @param list<array{name: string, date?: string, location?: string}> $events
     * @param list<array{name: string, type?: string}> $locations
     * @param list<string> $topics
     * @param list<string> $categoriesSuggested
     * @param float $confidence
     */
    public function __construct(
        public array $persons = [],
        public array $institutions = [],
        public array $events = [],
        public array $locations = [],
        public array $topics = [],
        public array $categoriesSuggested = [],
        public float $confidence = 0.0,
    ) {}

    /**
     * Check if any entities were extracted.
     */
    public function hasEntities(): bool
    {
        return $this->persons !== []
            || $this->institutions !== []
            || $this->events !== []
            || $this->locations !== [];
    }

    /**
     * Get total entity count.
     */
    public function totalCount(): int
    {
        return \count($this->persons)
            + \count($this->institutions)
            + \count($this->events)
            + \count($this->locations);
    }

    /**
     * Serialize to array for storage in article metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'persons' => $this->persons,
            'institutions' => $this->institutions,
            'events' => $this->events,
            'locations' => $this->locations,
            'topics' => $this->topics,
            'categories_suggested' => $this->categoriesSuggested,
            'confidence' => $this->confidence,
        ];
    }

    /**
     * Create from raw array (e.g., parsed from Gemini JSON output).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            persons: self::normalizeEntityList($data['persons'] ?? []),
            institutions: self::normalizeEntityList($data['institutions'] ?? []),
            events: self::normalizeEntityList($data['events'] ?? []),
            locations: self::normalizeEntityList($data['locations'] ?? []),
            topics: array_values(array_filter(
                is_array($data['topics'] ?? null) ? $data['topics'] : [],
                'is_string',
            )),
            categoriesSuggested: array_values(array_filter(
                is_array($data['categories_suggested'] ?? null) ? $data['categories_suggested'] : [],
                'is_string',
            )),
            confidence: (float) ($data['confidence'] ?? 0.8),
        );
    }

    /**
     * @param mixed $list
     * @return list<array<string, string>>
     */
    private static function normalizeEntityList(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }

        $result = [];
        foreach ($list as $item) {
            if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                $normalized = ['name' => $item['name']];
                foreach ($item as $k => $v) {
                    if ($k !== 'name' && is_string($v) && $v !== '') {
                        $normalized[$k] = $v;
                    }
                }
                $result[] = $normalized;
            }
        }

        return $result;
    }
}
