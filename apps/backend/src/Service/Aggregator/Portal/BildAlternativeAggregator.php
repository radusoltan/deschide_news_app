<?php

declare(strict_types=1);

namespace App\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\AggregatorInterface;
use App\Service\Aggregator\BingNewsAggregator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Aggregates German news about Moldova by filtering BingNewsAggregator results
 * to only German-language domains.
 *
 * Bild.de uses DataDome/Cloudflare protection that is nearly impossible to bypass
 * with direct scraping. This aggregator wraps BingNewsAggregator with mkt=de-DE
 * and filters results to major German news domains.
 */
#[AutoconfigureTag('app.aggregator')]
final readonly class BildAlternativeAggregator implements AggregatorInterface
{
    /**
     * @param list<string> $germanDomains
     */
    public function __construct(
        private BingNewsAggregator $bingNewsAggregator,
        private LoggerInterface $logger,
        #[Autowire('%portal.bild_alternative.enabled%')]
        private bool $enabled = true,
        #[Autowire('%portal.bild_alternative.german_domains%')]
        private array $germanDomains = [],
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('BildAlternativeAggregator: disabled, skipping.');

            return [];
        }

        $allResults = $this->bingNewsAggregator->fetch();

        $filtered = array_filter(
            $allResults,
            fn (AggregatorResult $r) => $this->isFromGermanDomain($r->sourceUrl),
        );

        $results = array_values($filtered);

        $this->logger->info('BildAlternativeAggregator: filtered German domain results', [
            'total_bing' => \count($allResults),
            'german_filtered' => \count($results),
        ]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::DIRECT_PORTAL;
    }

    public function getName(): string
    {
        return 'German News (via Bing)';
    }

    private function isFromGermanDomain(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }

        $host = strtolower($host);

        foreach ($this->germanDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }
}
