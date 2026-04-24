<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Monitor;

use App\Service\Editorial\Monitor\UrlNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * T53.5 — UrlNormalizer unit tests.
 */
class UrlNormalizerTest extends TestCase
{
    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UrlNormalizer();
    }

    public function testStripsUtmParametersAndSortsRemainingQuery(): void
    {
        $input = 'https://example.com/article?utm_source=facebook&id=42&utm_campaign=q4&ref=twitter&category=news';
        $output = $this->normalizer->normalize($input);

        self::assertSame('https://example.com/article?category=news&id=42', $output);
    }

    public function testStripsCommonTrackingPrefixes(): void
    {
        $cases = [
            'fbclid'   => 'https://example.com/p?fbclid=abc123',
            'gclid'    => 'https://example.com/p?gclid=xyz',
            'dclid'    => 'https://example.com/p?dclid=foo',
            'msclkid'  => 'https://example.com/p?msclkid=bar',
            'yclid'    => 'https://example.com/p?yclid=baz',
            'ref_src'  => 'https://example.com/p?ref_src=twsrc',
            '_ga'      => 'https://example.com/p?_ga=GA1.2.3',
            '_gl'      => 'https://example.com/p?_gl=1*abc',
            'mc_cid'   => 'https://example.com/p?mc_cid=123',
            'mc_eid'   => 'https://example.com/p?mc_eid=456',
        ];

        foreach ($cases as $label => $url) {
            self::assertSame(
                'https://example.com/p',
                $this->normalizer->normalize($url),
                sprintf('%s tracking param was not stripped', $label),
            );
        }
    }

    public function testLowercasesHost(): void
    {
        self::assertSame(
            'https://zdg.md/articol',
            $this->normalizer->normalize('https://ZDG.md/articol'),
        );
    }

    public function testPreservesUppercaseInPathAndQueryValues(): void
    {
        self::assertSame(
            'https://example.com/Path/To/Resource?q=HelloWorld',
            $this->normalizer->normalize('https://example.com/Path/To/Resource?q=HelloWorld'),
        );
    }

    public function testRemovesDefaultPorts(): void
    {
        self::assertSame('https://example.com/x', $this->normalizer->normalize('https://example.com:443/x'));
        self::assertSame('http://example.com/x', $this->normalizer->normalize('http://example.com:80/x'));
    }

    public function testPreservesNonDefaultPorts(): void
    {
        self::assertSame(
            'https://example.com:8443/x',
            $this->normalizer->normalize('https://example.com:8443/x'),
        );
    }

    public function testRemovesTrailingSlashOnlyForEmptyPath(): void
    {
        self::assertSame(
            'https://example.com',
            $this->normalizer->normalize('https://example.com/'),
        );

        // A non-empty path keeps its trailing slash (that's semantic).
        self::assertSame(
            'https://example.com/section/',
            $this->normalizer->normalize('https://example.com/section/'),
        );
    }

    public function testPreservesFragmentAndQueryWhenNoTracking(): void
    {
        self::assertSame(
            'https://example.com/article?id=1&sort=asc#top',
            $this->normalizer->normalize('https://example.com/article?sort=asc&id=1#top'),
        );
    }

    public function testDropsEmptyQueryStringAfterStripping(): void
    {
        self::assertSame(
            'https://example.com/article',
            $this->normalizer->normalize('https://example.com/article?utm_source=fb&utm_campaign=q4'),
        );
    }

    public function testPreservesUserInfo(): void
    {
        // Unusual but valid — ensure credentials in URL survive normalization.
        self::assertSame(
            'https://user:pass@example.com/x',
            $this->normalizer->normalize('https://user:pass@example.com/x'),
        );
    }

    public function testMalformedUrlReturnedVerbatim(): void
    {
        // parse_url on a fragment-only string returns something without host —
        // normaliser refuses to transform and returns input unchanged.
        $malformed = 'not a url';
        self::assertSame($malformed, $this->normalizer->normalize($malformed));
    }

    public function testCaseInsensitiveTrackingParamMatching(): void
    {
        // Feed publishers capitalise params inconsistently; match case-insensitively.
        self::assertSame(
            'https://example.com/p',
            $this->normalizer->normalize('https://example.com/p?UTM_SOURCE=fb&FBCLID=1'),
        );
    }
}
