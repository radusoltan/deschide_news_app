<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ShortLink;
use App\Entity\ShortLinkInteraction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ShortLinkInteractionTest extends TestCase
{
    private ShortLinkInteraction $interaction;

    protected function setUp(): void
    {
        $this->interaction = new ShortLinkInteraction();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->interaction->getId());
        $this->assertNull($this->interaction->getShortLink());
        $this->assertNull($this->interaction->getClickedAt());
        $this->assertNull($this->interaction->getIpAddress());
        $this->assertNull($this->interaction->getUserAgent());
        $this->assertNull($this->interaction->getReferrer());
        $this->assertNull($this->interaction->getCountryCode());
        $this->assertNull($this->interaction->getDeviceType());
    }

    public function testSetGetShortLink(): void
    {
        $shortLink = new ShortLink();
        $result = $this->interaction->setShortLink($shortLink);
        $this->assertSame($shortLink, $this->interaction->getShortLink());
        $this->assertSame($this->interaction, $result);
    }

    public function testSetGetShortLinkNull(): void
    {
        $shortLink = new ShortLink();
        $this->interaction->setShortLink($shortLink);
        $this->interaction->setShortLink(null);
        $this->assertNull($this->interaction->getShortLink());
    }

    public function testSetGetClickedAt(): void
    {
        $date = new DateTimeImmutable('2024-06-15 10:00:00');
        $result = $this->interaction->setClickedAt($date);
        $this->assertSame($date, $this->interaction->getClickedAt());
        $this->assertSame($this->interaction, $result);
    }

    public function testSetIpAddressAnonymizesIPv4(): void
    {
        $this->interaction->setIpAddress('192.168.1.100');
        $this->assertSame('192.168.1.0', $this->interaction->getIpAddress());
    }

    public function testSetIpAddressAnonymizesIPv6(): void
    {
        $this->interaction->setIpAddress('2001:db8:85a3:0:0:8a2e:370:7334');
        $this->assertSame('2001:db8:85a3:0:0:8a2e:370:0', $this->interaction->getIpAddress());
    }

    public function testSetIpAddressNull(): void
    {
        $this->interaction->setIpAddress(null);
        $this->assertNull($this->interaction->getIpAddress());
    }

    public function testSetUserAgentNormal(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $result = $this->interaction->setUserAgent($ua);
        $this->assertSame($ua, $this->interaction->getUserAgent());
        $this->assertSame($this->interaction, $result);
    }

    public function testSetUserAgentTruncatesLongValue(): void
    {
        $longUa = str_repeat('x', 600);
        $this->interaction->setUserAgent($longUa);
        $this->assertSame(500, strlen($this->interaction->getUserAgent()));
    }

    public function testSetUserAgentNull(): void
    {
        $this->interaction->setUserAgent(null);
        $this->assertNull($this->interaction->getUserAgent());
    }

    public function testSetReferrerNormal(): void
    {
        $result = $this->interaction->setReferrer('https://google.com');
        $this->assertSame('https://google.com', $this->interaction->getReferrer());
        $this->assertSame($this->interaction, $result);
    }

    public function testSetReferrerTruncatesLongValue(): void
    {
        $longRef = str_repeat('y', 600);
        $this->interaction->setReferrer($longRef);
        $this->assertSame(500, strlen($this->interaction->getReferrer()));
    }

    public function testSetReferrerNull(): void
    {
        $this->interaction->setReferrer(null);
        $this->assertNull($this->interaction->getReferrer());
    }

    public function testSetCountryCodeNormalizes(): void
    {
        $result = $this->interaction->setCountryCode('md');
        $this->assertSame('MD', $this->interaction->getCountryCode());
        $this->assertSame($this->interaction, $result);
    }

    public function testSetCountryCodeTruncates(): void
    {
        $this->interaction->setCountryCode('mda');
        $this->assertSame('MD', $this->interaction->getCountryCode());
    }

    public function testSetCountryCodeNull(): void
    {
        $this->interaction->setCountryCode(null);
        $this->assertNull($this->interaction->getCountryCode());
    }

    public function testSetGetDeviceType(): void
    {
        $result = $this->interaction->setDeviceType('mobile');
        $this->assertSame('mobile', $this->interaction->getDeviceType());
        $this->assertSame($this->interaction, $result);
    }

    public function testSetGetDeviceTypeNull(): void
    {
        $this->interaction->setDeviceType('desktop');
        $this->interaction->setDeviceType(null);
        $this->assertNull($this->interaction->getDeviceType());
    }
}
