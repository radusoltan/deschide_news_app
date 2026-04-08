<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Service\Scraping\RssFeedParser;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RssFeedParserTest extends TestCase
{
    public function testParsesValidRssFeed(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Moldpres</title>
    <item>
      <title>Guvernul aprobă reformă</title>
      <link>https://moldpres.md/news/123</link>
      <pubDate>Wed, 02 Apr 2026 10:00:00 +0000</pubDate>
      <description>Descriere scurtă</description>
    </item>
    <item>
      <title>Al doilea articol</title>
      <link>https://moldpres.md/news/124</link>
      <pubDate>Wed, 02 Apr 2026 11:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $mockClient = new MockHttpClient([new MockResponse($rssXml)]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $items = $parser->parse('https://moldpres.md/ro/rss', 'Moldpres', 'ro');

        $this->assertCount(2, $items);
        $this->assertSame('Guvernul aprobă reformă', $items[0]->title);
        $this->assertSame('https://moldpres.md/news/123', $items[0]->url);
        $this->assertSame('Moldpres', $items[0]->sourceName);
        $this->assertSame('ro', $items[0]->language);
        $this->assertSame('Descriere scurtă', $items[0]->description);
        $this->assertInstanceOf(\DateTimeImmutable::class, $items[0]->publishedAt);
    }

    public function testParsesAtomFeed(): void
    {
        $atomXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>IPN Press</title>
  <entry>
    <title>Știre din Atom</title>
    <link rel="alternate" href="https://ipn.md/ro/articol/1"/>
    <updated>2026-04-02T10:00:00Z</updated>
    <summary>Rezumat articol</summary>
  </entry>
</feed>
XML;

        $mockClient = new MockHttpClient([new MockResponse($atomXml)]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $items = $parser->parse('https://ipn.md/ro/rss', 'IPN', 'ro');

        $this->assertCount(1, $items);
        $this->assertSame('Știre din Atom', $items[0]->title);
        $this->assertSame('https://ipn.md/ro/articol/1', $items[0]->url);
    }

    public function testReturnsEmptyOnInvalidXml(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('not xml at all')]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $items = $parser->parse('https://test.md/rss', 'Test', 'ro');

        $this->assertSame([], $items);
    }

    public function testRespectsLimit(): void
    {
        $items = '';
        for ($i = 1; $i <= 10; $i++) {
            $items .= "<item><title>Article {$i}</title><link>https://test.md/{$i}</link></item>";
        }
        $rssXml = "<?xml version=\"1.0\"?><rss version=\"2.0\"><channel>{$items}</channel></rss>";

        $mockClient = new MockHttpClient([new MockResponse($rssXml)]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $result = $parser->parse('https://test.md/rss', 'Test', 'ro', 3);

        $this->assertCount(3, $result);
    }

    public function testSkipsItemsWithoutTitleOrLink(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
  <channel>
    <item><title>Valid</title><link>https://test.md/1</link></item>
    <item><title></title><link>https://test.md/2</link></item>
    <item><title>No Link</title></item>
  </channel>
</rss>
XML;

        $mockClient = new MockHttpClient([new MockResponse($rssXml)]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $items = $parser->parse('https://test.md/rss', 'Test', 'ro');

        $this->assertCount(1, $items);
        $this->assertSame('Valid', $items[0]->title);
    }

    public function testParsesSourceTag(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Google News</title>
    <item>
      <title>Breaking news - Moldova 1</title>
      <link>https://news.google.com/rss/articles/CBMi123</link>
      <description>Short description</description>
      <pubDate>Mon, 07 Apr 2026 10:00:00 GMT</pubDate>
      <source url="https://moldova1.md">Moldova 1</source>
    </item>
    <item>
      <title>Economy update - G4Media</title>
      <link>https://news.google.com/rss/articles/CBMi456</link>
      <description>Economy news</description>
      <source url="https://www.g4media.ro">G4Media</source>
    </item>
  </channel>
</rss>
XML;

        $mockClient = new MockHttpClient([new MockResponse($rssXml)]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $items = $parser->parse('https://news.google.com/rss/search?q=test', 'Google News', 'ro');

        $this->assertCount(2, $items);

        // First item: source url without www
        $this->assertSame('https://moldova1.md', $items[0]->sourcePublisherUrl);
        $this->assertSame('Moldova 1', $items[0]->sourcePublisherName);

        // Second item: source url with www
        $this->assertSame('https://www.g4media.ro', $items[1]->sourcePublisherUrl);
        $this->assertSame('G4Media', $items[1]->sourcePublisherName);
    }

    public function testSourceTagNullWhenMissing(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>No source tag</title>
      <link>https://test.md/article</link>
    </item>
  </channel>
</rss>
XML;

        $mockClient = new MockHttpClient([new MockResponse($rssXml)]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $items = $parser->parse('https://test.md/rss', 'Test', 'ro');

        $this->assertCount(1, $items);
        $this->assertNull($items[0]->sourcePublisherUrl);
        $this->assertNull($items[0]->sourcePublisherName);
    }

    public function testReturnsEmptyOnHttpError(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('', ['http_code' => 500])]);
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestBot/1.0', 30);

        $items = $parser->parse('https://test.md/rss', 'Test', 'ro');

        $this->assertSame([], $items);
    }
}
