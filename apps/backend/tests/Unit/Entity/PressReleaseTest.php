<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PressRelease;
use App\Entity\Source;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PressReleaseTest extends TestCase
{
    #[Test]
    public function originalTitleDefaultsToNull(): void
    {
        $pr = new PressRelease();
        $this->assertNull($pr->getOriginalTitle());
    }

    #[Test]
    public function originalContentDefaultsToNull(): void
    {
        $pr = new PressRelease();
        $this->assertNull($pr->getOriginalContent());
    }

    #[Test]
    public function setAndGetOriginalTitle(): void
    {
        $pr = new PressRelease();
        $result = $pr->setOriginalTitle('EU announces new trade deal');

        $this->assertSame('EU announces new trade deal', $pr->getOriginalTitle());
        $this->assertSame($pr, $result, 'Setter should return $this for fluent API');
    }

    #[Test]
    public function setAndGetOriginalContent(): void
    {
        $pr = new PressRelease();
        $result = $pr->setOriginalContent('Full original article content in English.');

        $this->assertSame('Full original article content in English.', $pr->getOriginalContent());
        $this->assertSame($pr, $result, 'Setter should return $this for fluent API');
    }

    #[Test]
    public function originalTitleCanBeSetToNull(): void
    {
        $pr = new PressRelease();
        $pr->setOriginalTitle('Something');
        $pr->setOriginalTitle(null);

        $this->assertNull($pr->getOriginalTitle());
    }

    #[Test]
    public function sourceHostnameExtractsFromUrl(): void
    {
        $pr = new PressRelease();
        $pr->setSourceUrl('https://www.leparisien.fr/article/some-news');

        $this->assertSame('leparisien.fr', $pr->getSourceHostname());
    }

    #[Test]
    public function sourceHostnameWithoutWww(): void
    {
        $pr = new PressRelease();
        $pr->setSourceUrl('https://reuters.com/world/article');

        $this->assertSame('reuters.com', $pr->getSourceHostname());
    }

    #[Test]
    public function sourceHostnameReturnsNullWhenNoUrl(): void
    {
        $pr = new PressRelease();

        $this->assertNull($pr->getSourceHostname());
    }

    #[Test]
    public function sourceHostnameHandlesGoogleNewsUrl(): void
    {
        $pr = new PressRelease();
        $pr->setSourceUrl('https://news.google.com/rss/articles/CBMi123');

        // Without sourcePublisherDomain, falls back to parsed URL hostname
        $this->assertSame('news.google.com', $pr->getSourceHostname());
    }

    #[Test]
    public function sourceHostnamePrefersPublisherDomain(): void
    {
        $pr = new PressRelease();
        $pr->setSourceUrl('https://news.google.com/rss/articles/CBMi123');
        $pr->setSourcePublisherDomain('moldova1.md');

        // sourcePublisherDomain takes priority over parsed sourceUrl
        $this->assertSame('moldova1.md', $pr->getSourceHostname());
    }

    #[Test]
    public function sourceHostnameFallsBackToUrlWhenNoPublisherDomain(): void
    {
        $pr = new PressRelease();
        $pr->setSourceUrl('https://www.reuters.com/article/test');
        // No sourcePublisherDomain set

        $this->assertSame('reuters.com', $pr->getSourceHostname());
    }

    #[Test]
    public function sourcePublisherDomainGetterSetter(): void
    {
        $pr = new PressRelease();
        $this->assertNull($pr->getSourcePublisherDomain());

        $result = $pr->setSourcePublisherDomain('adevarul.ro');
        $this->assertSame('adevarul.ro', $pr->getSourcePublisherDomain());
        $this->assertSame($pr, $result);
    }

    #[Test]
    public function sourceRelationshipDefaultsToNull(): void
    {
        $pr = new PressRelease();
        $this->assertNull($pr->getSource());
    }

    #[Test]
    public function sourceRelationshipSetAndGet(): void
    {
        $pr = new PressRelease();
        $source = new Source();
        $source->setName('Test Source');

        $result = $pr->setSource($source);
        $this->assertSame($source, $pr->getSource());
        $this->assertSame($pr, $result);
    }

    #[Test]
    public function detectedLanguageGetterSetter(): void
    {
        $pr = new PressRelease();
        $this->assertNull($pr->getDetectedLanguage());

        $pr->setDetectedLanguage('fr');
        $this->assertSame('fr', $pr->getDetectedLanguage());
    }
}
