<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Dto\Scraping\RelevanceResult;
use App\Repository\RelevanceKeywordRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class RelevanceFilterService
{
    private const CACHE_KEY = 'relevance_keywords';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * @param list<string> $tier1Keywords YAML fallback
     * @param list<string> $tier2Keywords YAML fallback
     * @param list<string> $tier3Keywords YAML fallback
     */
    public function __construct(
        #[Autowire(param: 'relevance.tier1_direct')]
        private array $tier1Keywords,
        #[Autowire(param: 'relevance.tier2_regional')]
        private array $tier2Keywords,
        #[Autowire(param: 'relevance.tier3_entities')]
        private array $tier3Keywords,
        #[Autowire(param: 'relevance.min_score')]
        private int $minScore,
        private LoggerInterface $logger,
        private ?RelevanceKeywordRepository $keywordRepository = null,
        private ?CacheInterface $cache = null,
    ) {}

    /**
     * Evaluează relevanța unui articol pentru audiența Moldova.
     * Returnează scor (0 = irelevant, 1+ = relevant) și motivul.
     *
     * @param string[] $dynamicKeywords Extra keywords from TrendQueryGenerator
     */
    public function evaluate(string $title, string $bodyText, string $sourceName = '', array $dynamicKeywords = []): RelevanceResult
    {
        $tiers = $this->loadKeywordsByTier();
        $score = 0;
        $matches = [];
        $textToSearch = mb_strtolower($title . ' ' . $bodyText);

        // Tier 1: mențiune directă Moldova — scor 3 per match
        $tier1Count = 0;
        foreach ($tiers[1] as $keyword) {
            if (str_contains($textToSearch, mb_strtolower($keyword))) {
                $score += 3;
                $tier1Count++;
                $matches[] = ['tier' => 1, 'keyword' => $keyword];
            }
        }

        // Tier 2: context regional — scor 1 per match, dar doar dacă cel puțin 2 match-uri
        $tier2Matches = 0;
        foreach ($tiers[2] as $keyword) {
            if (str_contains($textToSearch, mb_strtolower($keyword))) {
                $tier2Matches++;
                $matches[] = ['tier' => 2, 'keyword' => $keyword];
            }
        }
        if ($tier2Matches >= 2) {
            $score += $tier2Matches;
        }

        // Tier 3: persoane/instituții cheie — scor 2 per match
        $tier3Count = 0;
        foreach ($tiers[3] as $keyword) {
            if (str_contains($textToSearch, mb_strtolower($keyword))) {
                $score += 2;
                $tier3Count++;
                $matches[] = ['tier' => 3, 'keyword' => $keyword];
            }
        }

        // Tier 4: declarații/statements — scor 1 per match, ONLY if Tier 1 or Tier 3 also matched
        if (($tier1Count > 0 || $tier3Count > 0) && !empty($tiers[4])) {
            foreach ($tiers[4] as $keyword) {
                if (str_contains($textToSearch, mb_strtolower($keyword))) {
                    $score += 1;
                    $matches[] = ['tier' => 4, 'keyword' => $keyword];
                }
            }
        }

        // Dynamic keywords from trending topics — scor 1 per match
        foreach ($dynamicKeywords as $keyword) {
            if ($keyword !== '' && str_contains($textToSearch, mb_strtolower($keyword))) {
                $score += 1;
                $matches[] = ['tier' => 0, 'keyword' => $keyword];
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
            tier1Count: $tier1Count,
            tier2Count: $tier2Matches,
            tier3Count: $tier3Count,
        );
    }

    /**
     * Load keywords grouped by tier, preferring DB + cache, falling back to YAML.
     *
     * @return array<int, list<string>>
     */
    private function loadKeywordsByTier(): array
    {
        // Try loading from DB via cache
        if ($this->keywordRepository !== null && $this->cache !== null) {
            try {
                return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                    $item->expiresAfter(self::CACHE_TTL);

                    $all = $this->keywordRepository->findAllActive();

                    if (\count($all) === 0) {
                        // DB empty — fall back to YAML
                        return $this->getYamlFallback();
                    }

                    $tiers = [1 => [], 2 => [], 3 => [], 4 => []];
                    foreach ($all as $kw) {
                        $tiers[$kw->getTier()][] = $kw->getKeyword();
                    }

                    return $tiers;
                });
            } catch (\Throwable $e) {
                $this->logger->warning('RelevanceFilter: failed to load from DB/cache, using YAML fallback', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->getYamlFallback();
    }

    /**
     * @return array<int, list<string>>
     */
    private function getYamlFallback(): array
    {
        return [
            1 => $this->tier1Keywords,
            2 => $this->tier2Keywords,
            3 => $this->tier3Keywords,
            4 => [],
        ];
    }
}
