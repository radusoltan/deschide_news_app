<?php

declare(strict_types=1);

namespace App\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\AggregatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('app.aggregator')]
final readonly class GuardianApiAggregator implements AggregatorInterface
{
    private const ENDPOINT = 'https://content.guardianapis.com/search';
    private const PAGE_SIZE = 20;

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'GUARDIAN_API_KEY')]
        private string $apiKey = '',
        #[Autowire('%portal.guardian.enabled%')]
        private bool $enabled = true,
        #[Autowire('%portal.guardian.keywords%')]
        private array $keywords = [],
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('GuardianApiAggregator: disabled, skipping.');

            return [];
        }

        if ($this->apiKey === '') {
            $this->logger->info('GuardianApiAggregator: no API key configured, skipping.');

            return [];
        }

        $query = implode(' OR ', $this->keywords);
        if ($query === '') {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => [
                    'q' => $query,
                    'api-key' => $this->apiKey,
                    'show-fields' => 'headline,trailText,bodyText',
                    'page-size' => self::PAGE_SIZE,
                    'order-by' => 'newest',
                ],
            ]);

            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->warning('GuardianApiAggregator: request failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $results = [];

        foreach ($data['response']['results'] ?? [] as $article) {
            $publishedAt = isset($article['webPublicationDate'])
                ? new \DateTimeImmutable($article['webPublicationDate'])
                : new \DateTimeImmutable();

            $trailText = $article['fields']['trailText'] ?? '';
            $headline = $article['fields']['headline'] ?? $article['webTitle'] ?? '';

            $results[] = new AggregatorResult(
                title: $headline,
                summary: strip_tags($trailText),
                sourceUrl: $article['webUrl'] ?? '',
                sourceLanguage: 'en',
                sourceName: 'The Guardian',
                publishedAt: $publishedAt,
                rawContent: strip_tags($trailText),
                keywords: $this->keywords,
                aggregatorSourceType: AggregatorSourceType::DIRECT_PORTAL,
            );
        }

        $this->logger->info('GuardianApiAggregator: fetched articles', [
            'query' => mb_substr($query, 0, 80),
            'count' => \count($results),
        ]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::DIRECT_PORTAL;
    }

    public function getName(): string
    {
        return 'The Guardian API';
    }
}
