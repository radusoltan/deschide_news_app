<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\GoogleAlertEmailParser;
use App\Service\Aggregator\GoogleAlertsAggregator;
use App\Service\ZohoMailService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(GoogleAlertsAggregator::class)]
#[CoversClass(GoogleAlertEmailParser::class)]
class GoogleAlertsAggregatorTest extends TestCase
{
    private string $fixtureHtml;

    protected function setUp(): void
    {
        $this->fixtureHtml = file_get_contents(__DIR__ . '/../../../Fixtures/google-alert-email-sample.html');
    }

    public function testParserExtractsArticleLinks(): void
    {
        $parser = new GoogleAlertEmailParser();
        $items = $parser->parse($this->fixtureHtml);

        self::assertCount(3, $items);
        self::assertSame('Moldovan citizen wins international award in Rome', $items[0]['title']);
        self::assertSame('https://www.example.com/news/moldovan-citizen-wins-award', $items[0]['url']);
    }

    public function testParserSkipsUnsubscribeAndHelpLinks(): void
    {
        $parser = new GoogleAlertEmailParser();
        $items = $parser->parse($this->fixtureHtml);

        $urls = array_column($items, 'url');
        foreach ($urls as $url) {
            self::assertStringNotContainsString('google.com/alerts', $url);
            self::assertStringNotContainsString('support.google.com', $url);
        }
    }

    public function testParserExtractsRealUrlFromGoogleRedirect(): void
    {
        $parser = new GoogleAlertEmailParser();
        $items = $parser->parse($this->fixtureHtml);

        // The fixture uses google.com/url?...&url=https://www.reuters.com/...
        $urls = array_column($items, 'url');
        self::assertContains('https://www.reuters.com/world/europe/moldova-eu-talks', $urls);
    }

    public function testFetchFromHtmlReturnsAggregatorResults(): void
    {
        $zoho = $this->createMock(ZohoMailService::class);
        $parser = new GoogleAlertEmailParser();

        $aggregator = new GoogleAlertsAggregator($zoho, $parser, new NullLogger());
        $results = $aggregator->fetchFromHtml([$this->fixtureHtml]);

        self::assertCount(3, $results);
        self::assertContainsOnlyInstancesOf(AggregatorResult::class, $results);
        self::assertSame(AggregatorSourceType::GOOGLE_ALERTS, $results[0]->aggregatorSourceType);
    }

    public function testLanguageDetectionFromUrl(): void
    {
        $zoho = $this->createMock(ZohoMailService::class);
        $parser = new GoogleAlertEmailParser();

        $aggregator = new GoogleAlertsAggregator($zoho, $parser, new NullLogger());
        $results = $aggregator->fetchFromHtml([$this->fixtureHtml]);

        // deschide.md → ro, reuters.com → en, example.com → en
        $languages = array_map(fn(AggregatorResult $r) => $r->sourceLanguage, $results);
        self::assertContains('en', $languages);
        self::assertContains('ro', $languages);
    }

    public function testGetSourceType(): void
    {
        $zoho = $this->createMock(ZohoMailService::class);
        $aggregator = new GoogleAlertsAggregator($zoho, new GoogleAlertEmailParser(), new NullLogger());

        self::assertSame(AggregatorSourceType::GOOGLE_ALERTS, $aggregator->getSourceType());
    }

    public function testGetName(): void
    {
        $zoho = $this->createMock(ZohoMailService::class);
        $aggregator = new GoogleAlertsAggregator($zoho, new GoogleAlertEmailParser(), new NullLogger());

        self::assertSame('Google Alerts (Zoho Mail)', $aggregator->getName());
    }

    public function testFetchFiltersGoogleAlertsSender(): void
    {
        $zoho = $this->createMock(ZohoMailService::class);
        $zoho->method('listEmails')->willReturn([
            [
                'messageId' => '1',
                'folderId' => 'inbox',
                'subject' => 'Google Alert - moldovean',
                'fromAddress' => 'googlealerts-noreply@google.com',
                'receivedTime' => '1712404800000',
                'summary' => '',
                'hasAttachment' => false,
                'hasInline' => false,
            ],
            [
                'messageId' => '2',
                'folderId' => 'inbox',
                'subject' => 'Newsletter',
                'fromAddress' => 'newsletter@example.com',
                'receivedTime' => '1712404800000',
                'summary' => '',
                'hasAttachment' => false,
                'hasInline' => false,
            ],
        ]);
        // Only the Google Alert email should be fetched
        $zoho->expects(self::once())
            ->method('getEmailContent')
            ->with('1', 'inbox')
            ->willReturn($this->fixtureHtml);

        $aggregator = new GoogleAlertsAggregator($zoho, new GoogleAlertEmailParser(), new NullLogger());
        $results = $aggregator->fetch();

        self::assertCount(3, $results);
    }
}
