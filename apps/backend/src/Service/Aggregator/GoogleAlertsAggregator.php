<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\ZohoMailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.aggregator')]
class GoogleAlertsAggregator implements AggregatorInterface
{
    private const GOOGLE_ALERTS_SENDER = 'googlealerts-noreply@google.com';

    public function __construct(
        private readonly ZohoMailService $zohoMailService,
        private readonly GoogleAlertEmailParser $emailParser,
        private readonly LoggerInterface $logger,
    ) {}

    public function fetch(): array
    {
        $this->logger->info('GoogleAlertsAggregator: fetching emails from Zoho Mail');

        try {
            $emails = $this->zohoMailService->listEmails(limit: 50);
        } catch (\Throwable $e) {
            $this->logger->error('GoogleAlertsAggregator: failed to list emails', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $alertEmails = array_filter(
            $emails,
            fn(array $email): bool => str_contains($email['fromAddress'], self::GOOGLE_ALERTS_SENDER)
        );

        $this->logger->info('GoogleAlertsAggregator: found {count} Google Alert emails', [
            'count' => \count($alertEmails),
        ]);

        $results = [];

        foreach ($alertEmails as $email) {
            try {
                $html = $this->zohoMailService->getEmailContent(
                    $email['messageId'],
                    $email['folderId'],
                );

                $parsed = $this->emailParser->parse($html);

                foreach ($parsed as $item) {
                    $results[] = $this->mapToResult($item, $email['subject'] ?? '');
                }

                $this->logger->info('GoogleAlertsAggregator: parsed {count} items from alert "{subject}"', [
                    'count' => \count($parsed),
                    'subject' => mb_substr($email['subject'], 0, 80),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('GoogleAlertsAggregator: failed to parse email', [
                    'messageId' => $email['messageId'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('GoogleAlertsAggregator: total {count} results', [
            'count' => \count($results),
        ]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::GOOGLE_ALERTS;
    }

    public function getName(): string
    {
        return 'Google Alerts (Zoho Mail)';
    }

    /**
     * Also accepts pre-parsed email HTML for testing.
     *
     * @param list<string> $htmlContents
     *
     * @return AggregatorResult[]
     */
    public function fetchFromHtml(array $htmlContents): array
    {
        $results = [];

        foreach ($htmlContents as $html) {
            $parsed = $this->emailParser->parse($html);
            foreach ($parsed as $item) {
                $results[] = $this->mapToResult($item, '');
            }
        }

        return $results;
    }

    /**
     * @param array{title: string, url: string, snippet: string} $item
     */
    private function mapToResult(array $item, string $alertSubject): AggregatorResult
    {
        // Try to detect language from URL domain or default to 'en'
        $language = $this->detectLanguage($item['url']);

        return new AggregatorResult(
            title: $item['title'],
            summary: $item['snippet'],
            sourceUrl: $item['url'],
            sourceLanguage: $language,
            sourceName: 'Google Alerts',
            publishedAt: new \DateTimeImmutable(),
            rawContent: $item['snippet'],
            keywords: $alertSubject !== '' ? [$alertSubject] : [],
            aggregatorSourceType: AggregatorSourceType::GOOGLE_ALERTS,
        );
    }

    private function detectLanguage(string $url): string
    {
        $host = parse_url($url, \PHP_URL_HOST) ?? '';

        return match (true) {
            str_ends_with($host, '.md') => 'ro',
            str_ends_with($host, '.ro') => 'ro',
            str_ends_with($host, '.ru') => 'ru',
            str_ends_with($host, '.it') => 'it',
            str_ends_with($host, '.de') => 'de',
            str_ends_with($host, '.fr') => 'fr',
            default => 'en',
        };
    }
}
