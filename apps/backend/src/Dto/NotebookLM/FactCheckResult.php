<?php

declare(strict_types=1);

namespace App\Dto\NotebookLM;

final readonly class FactCheckResult
{
    public function __construct(
        public string $answer,
        public string $question,
        public int $topicId,
        public ?string $notebookId,
        public bool $cached,
        public \DateTimeImmutable $checkedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'answer' => $this->answer,
            'question' => $this->question,
            'topicId' => $this->topicId,
            'notebookId' => $this->notebookId,
            'cached' => $this->cached,
            'checkedAt' => $this->checkedAt->format('c'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromCacheArray(array $data): self
    {
        return new self(
            answer: (string) ($data['answer'] ?? ''),
            question: (string) ($data['question'] ?? ''),
            topicId: (int) ($data['topicId'] ?? 0),
            notebookId: $data['notebookId'] ?? null,
            cached: true,
            checkedAt: new \DateTimeImmutable($data['checkedAt'] ?? 'now'),
        );
    }
}
