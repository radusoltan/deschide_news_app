<?php

declare(strict_types=1);

namespace App\Dto\NotebookLM;

final readonly class FactCheckResult
{
    /**
     * Romanian-language contradiction markers used by {@see self::isContradictory}.
     * The list is intentionally conservative — the verdict override this drives
     * (downgrade to ESCALATE_HUMAN in VerificationGate T55.10) must not fire on
     * mild qualifiers; we only match phrasing that explicitly names the claim
     * as incorrect / unsupported.
     *
     * @var list<string>
     */
    private const CONTRADICTION_MARKERS = [
        'nu este adevărat',
        'nu corespunde adevărului',
        'nu corespunde realității',
        'este fals',
        'este incorect',
        'contrazic', // catches "contrazic", "contrazice", "contrazicere"
        'contrazis',
        'afirmație incorectă',
        'informație falsă',
        'dezmințit',
        'infirmat',
        'nu este susținut',
        'nu este susținută',
        'nu este confirmat',
        'nu este confirmată',
        'nu confirmă',
    ];

    public function __construct(
        public string $answer,
        public string $question,
        public int $topicId,
        public ?string $notebookId,
        public bool $cached,
        public \DateTimeImmutable $checkedAt,
    ) {}

    /**
     * True when the NotebookLM answer explicitly contradicts the claim
     * (Sprint 55 T55.10). Keyword-heuristic — intentionally strict to avoid
     * false positives that would trigger an unjustified verdict downgrade.
     *
     * Matching is case-insensitive against the full answer text. A single
     * marker is sufficient — the answer is usually a paragraph and any
     * contradictory phrase inside it warrants editor review.
     *
     * Future iterations may swap this for an LLM classifier; the heuristic
     * is good enough for S55 scope and has zero latency cost.
     */
    public function isContradictory(): bool
    {
        $lower = mb_strtolower($this->answer);
        foreach (self::CONTRADICTION_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

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
            'contradictory' => $this->isContradictory(),
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
