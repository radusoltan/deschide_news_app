<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Service\Scraping\RssFeedParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RssFeedParserImageTest extends TestCase
{
    private function createParser(string $xml): RssFeedParser
    {
        $httpClient = new MockHttpClient(new MockResponse($xml, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/rss+xml'],
        ]));

        return new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 10);
    }

    #[Test]
    public function extractsImageFromEnclosure(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Article with enclosure</title>
      <link>https://example.com/article/1</link>
      <description>Some description</description>
      <enclosure url="https://example.com/images/photo.jpg" type="image/jpeg" length="12345"/>
    </item>
  </channel>
</rss>
XML;

        $items = $this->createParser($xml)->parse('https://example.com/feed', 'Test', 'en', 10);

        $this->assertCount(1, $items);
        $this->assertSame('https://example.com/images/photo.jpg', $items[0]->imageUrl);
    }

    #[Test]
    public function extractsImageFromMediaContent(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <item>
      <title>Article with media:content</title>
      <link>https://example.com/article/2</link>
      <media:content url="https://cdn.example.com/img.jpg" medium="image"/>
    </item>
  </channel>
</rss>
XML;

        $items = $this->createParser($xml)->parse('https://example.com/feed', 'Test', 'en', 10);

        $this->assertCount(1, $items);
        $this->assertSame('https://cdn.example.com/img.jpg', $items[0]->imageUrl);
    }

    #[Test]
    public function extractsImageFromDescriptionHtml(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Gov.md article</title>
      <link>https://gov.md/ro/article/1</link>
      <description>&lt;div&gt;&lt;img src="https://gov.md/sites/default/files/photo.jpg" alt="Photo"&gt;&lt;p&gt;Content&lt;/p&gt;&lt;/div&gt;</description>
    </item>
  </channel>
</rss>
XML;

        $items = $this->createParser($xml)->parse('https://gov.md/feed', 'Gov.md', 'ro', 10);

        $this->assertCount(1, $items);
        $this->assertSame('https://gov.md/sites/default/files/photo.jpg', $items[0]->imageUrl);
    }

    #[Test]
    public function returnsNullWhenNoImageInFeed(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Article without image</title>
      <link>https://example.com/article/3</link>
      <description>Just text, no images</description>
    </item>
  </channel>
</rss>
XML;

        $items = $this->createParser($xml)->parse('https://example.com/feed', 'Test', 'en', 10);

        $this->assertCount(1, $items);
        $this->assertNull($items[0]->imageUrl);
    }

    #[Test]
    public function enclosureTakesPriorityOverDescriptionImg(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Article with both</title>
      <link>https://example.com/article/4</link>
      <description>&lt;img src="https://example.com/inline.jpg"&gt;Text</description>
      <enclosure url="https://example.com/enclosure.jpg" type="image/jpeg" length="5000"/>
    </item>
  </channel>
</rss>
XML;

        $items = $this->createParser($xml)->parse('https://example.com/feed', 'Test', 'en', 10);

        $this->assertCount(1, $items);
        $this->assertSame('https://example.com/enclosure.jpg', $items[0]->imageUrl);
    }

    #[Test]
    public function ignoresNonImageEnclosure(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Article with audio enclosure</title>
      <link>https://example.com/article/5</link>
      <description>Podcast episode</description>
      <enclosure url="https://example.com/audio.mp3" type="audio/mpeg" length="50000"/>
    </item>
  </channel>
</rss>
XML;

        $items = $this->createParser($xml)->parse('https://example.com/feed', 'Test', 'en', 10);

        $this->assertCount(1, $items);
        $this->assertNull($items[0]->imageUrl);
    }
}
