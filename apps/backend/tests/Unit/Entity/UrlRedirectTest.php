<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\UrlRedirect;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UrlRedirect entity.
 *
 * Tests URL redirect functionality for preserving SEO value
 * when article categories or slugs change.
 */
class UrlRedirectTest extends TestCase
{
    public function testUrlRedirectCreation(): void
    {
        $redirect = new UrlRedirect();

        $this->assertNull($redirect->getId());
        $this->assertInstanceOf(UrlRedirect::class, $redirect);
    }

    public function testSetAndGetOldUrl(): void
    {
        $redirect = new UrlRedirect();
        $oldUrl = '/politica/vechiul-articol';

        $result = $redirect->setOldUrl($oldUrl);

        $this->assertSame($redirect, $result); // Test fluent interface
        $this->assertEquals($oldUrl, $redirect->getOldUrl());
    }

    public function testSetAndGetNewUrl(): void
    {
        $redirect = new UrlRedirect();
        $newUrl = '/economie/vechiul-articol';

        $result = $redirect->setNewUrl($newUrl);

        $this->assertSame($redirect, $result);
        $this->assertEquals($newUrl, $redirect->getNewUrl());
    }

    public function testSetAndGetLocale(): void
    {
        $redirect = new UrlRedirect();

        $redirect->setLocale('ro');
        $this->assertEquals('ro', $redirect->getLocale());

        $redirect->setLocale('en');
        $this->assertEquals('en', $redirect->getLocale());

        $redirect->setLocale('ru');
        $this->assertEquals('ru', $redirect->getLocale());
    }

    public function testSetAndGetHttpStatusCode(): void
    {
        $redirect = new UrlRedirect();

        $result = $redirect->setHttpStatusCode(302);

        $this->assertSame($redirect, $result);
        $this->assertEquals(302, $redirect->getHttpStatusCode());
    }

    public function testDefaultHttpStatusCode(): void
    {
        $redirect = new UrlRedirect();

        // Default should be 301 (permanent redirect)
        $this->assertEquals(301, $redirect->getHttpStatusCode());
    }

    public function testValidHttpStatusCodes(): void
    {
        $redirect = new UrlRedirect();

        // Test all valid status codes
        $validCodes = [301, 302, 307, 308];

        foreach ($validCodes as $code) {
            $redirect->setHttpStatusCode($code);
            $this->assertEquals($code, $redirect->getHttpStatusCode());
        }
    }

    public function testSetAndGetType(): void
    {
        $redirect = new UrlRedirect();

        $redirect->setType('article');
        $this->assertEquals('article', $redirect->getType());

        $redirect->setType('category');
        $this->assertEquals('category', $redirect->getType());

        $redirect->setType('author');
        $this->assertEquals('author', $redirect->getType());

        $redirect->setType('manual');
        $this->assertEquals('manual', $redirect->getType());
    }

    public function testSetAndGetEntityId(): void
    {
        $redirect = new UrlRedirect();

        $result = $redirect->setEntityId(123);

        $this->assertSame($redirect, $result);
        $this->assertEquals(123, $redirect->getEntityId());
    }

    public function testEntityIdCanBeNull(): void
    {
        $redirect = new UrlRedirect();

        $redirect->setEntityId(null);

        $this->assertNull($redirect->getEntityId());
    }

    public function testSetAndGetHitCount(): void
    {
        $redirect = new UrlRedirect();

        $result = $redirect->setHitCount(50);

        $this->assertSame($redirect, $result);
        $this->assertEquals(50, $redirect->getHitCount());
    }

    public function testDefaultHitCount(): void
    {
        $redirect = new UrlRedirect();

        // Default should be 0
        $this->assertEquals(0, $redirect->getHitCount());
    }

    public function testIncrementHitCount(): void
    {
        $redirect = new UrlRedirect();

        $this->assertEquals(0, $redirect->getHitCount());

        $result = $redirect->incrementHitCount();

        $this->assertSame($redirect, $result);
        $this->assertEquals(1, $redirect->getHitCount());

        $redirect->incrementHitCount();
        $this->assertEquals(2, $redirect->getHitCount());
    }

    public function testCreatedAtTimestamp(): void
    {
        $redirect = new UrlRedirect();

        // Initially null (will be set on persist)
        $this->assertNull($redirect->getCreatedAt());

        $now = new DateTimeImmutable();
        $redirect->setCreatedAt($now);

        $this->assertEquals($now, $redirect->getCreatedAt());
    }

    public function testLastAccessedAtTimestamp(): void
    {
        $redirect = new UrlRedirect();

        // Initially null
        $this->assertNull($redirect->getLastAccessedAt());

        $now = new DateTimeImmutable();
        $redirect->setLastAccessedAt($now);

        $this->assertEquals($now, $redirect->getLastAccessedAt());
    }

    public function testFluentInterface(): void
    {
        $redirect = new UrlRedirect();
        $now = new DateTimeImmutable();

        $result = $redirect
            ->setOldUrl('/old/path')
            ->setNewUrl('/new/path')
            ->setLocale('ro')
            ->setHttpStatusCode(301)
            ->setType('article')
            ->setEntityId(42)
            ->setHitCount(10)
            ->setCreatedAt($now)
            ->setLastAccessedAt($now);

        $this->assertSame($redirect, $result);
        $this->assertEquals('/old/path', $redirect->getOldUrl());
        $this->assertEquals('/new/path', $redirect->getNewUrl());
        $this->assertEquals('ro', $redirect->getLocale());
        $this->assertEquals(301, $redirect->getHttpStatusCode());
        $this->assertEquals('article', $redirect->getType());
        $this->assertEquals(42, $redirect->getEntityId());
        $this->assertEquals(10, $redirect->getHitCount());
        $this->assertEquals($now, $redirect->getCreatedAt());
        $this->assertEquals($now, $redirect->getLastAccessedAt());
    }

    public function testMaxLengthForUrls(): void
    {
        $redirect = new UrlRedirect();
        $longUrl = str_repeat('a', 500);

        $redirect->setOldUrl($longUrl);
        $this->assertEquals(500, \strlen($redirect->getOldUrl()));

        $redirect->setNewUrl($longUrl);
        $this->assertEquals(500, \strlen($redirect->getNewUrl()));
    }

    public function testSeoPreservationScenario(): void
    {
        // Scenario: Article moved from "politica" to "economie" category
        $redirect = new UrlRedirect();

        $redirect
            ->setOldUrl('/politica/reforma-economica')
            ->setNewUrl('/economie/reforma-economica')
            ->setLocale('ro')
            ->setHttpStatusCode(301) // Permanent redirect for SEO
            ->setType('article')
            ->setEntityId(123);

        $this->assertEquals('/politica/reforma-economica', $redirect->getOldUrl());
        $this->assertEquals('/economie/reforma-economica', $redirect->getNewUrl());
        $this->assertEquals(301, $redirect->getHttpStatusCode());
        $this->assertEquals('article', $redirect->getType());
        $this->assertEquals(123, $redirect->getEntityId());
    }
}
