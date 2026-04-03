<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Dto\Scraping\RelevanceResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RelevanceFilterService
{
    /**
     * @param list<string> $tier1Keywords
     * @param list<string> $tier2Keywords
     * @param list<string> $tier3Keywords
     */
    public function __construct(
        #[Autowire(param: 'relevance.keywords.tier1_direct')]
        private array $tier1Keywords,
        #[Autowire(param: 'relevance.keywords.tier2_regional')]
        private array $tier2Keywords,
        #[Autowire(param: 'relevance.keywords.tier3_entities')]
        private array $tier3Keywords,
        #[Autowire(param: 'relevance.min_score')]
        private int $minScore,
        private LoggerInterface $logger,
    ) {}

    /**
     * Evaluează relevanța unui articol pentru audiența Moldova.
     * Returnează scor (0 = irelevant, 1+ = relevant) și motivul.
     */
    public function evaluate(string $title, string $bodyText, string $sourceName = ''): RelevanceResult
    {
        $score = 0;
        $matches = [];
        $textToSearch = mb_strtolower($title . ' ' . $bodyText);

        // Tier 1: mențiune directă Moldova — scor 3 per match
        foreach ($this->tier1Keywords as $keyword) {
            if (str_contains($textToSearch, mb_strtolower($keyword))) {
                $score += 3;
                $matches[] = ['tier' => 1, 'keyword' => $keyword];
            }
        }

        // Tier 2: context regional — scor 1 per match, dar doar dacă cel puțin 2 match-uri
        $tier2Matches = 0;
        foreach ($this->tier2Keywords as $keyword) {
            if (str_contains($textToSearch, mb_strtolower($keyword))) {
                $tier2Matches++;
                $matches[] = ['tier' => 2, 'keyword' => $keyword];
            }
        }
        if ($tier2Matches >= 2) {
            $score += $tier2Matches;
        }

        // Tier 3: persoane/instituții cheie — scor 2 per match
        foreach ($this->tier3Keywords as $keyword) {
            if (str_contains($textToSearch, mb_strtolower($keyword))) {
                $score += 2;
                $matches[] = ['tier' => 3, 'keyword' => $keyword];
            }
        }

        $isRelevant = $score >= $this->minScore;

        if (!$isRelevant) {
            $this->logger->debug('RelevanceFilter: REJECTED', [
                'title' => mb_substr($title, 0, 100),
                'source' => $sourceName,
                'score' => $score,
            ]);
        }

        return new RelevanceResult(
            isRelevant: $isRelevant,
            score: $score,
            matches: $matches,
            tier1Count: \count(array_filter($matches, fn ($m) => $m['tier'] === 1)),
            tier2Count: $tier2Matches,
            tier3Count: \count(array_filter($matches, fn ($m) => $m['tier'] === 3)),
        );
    }
}
