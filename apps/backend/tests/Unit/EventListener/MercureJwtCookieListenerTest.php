<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\MercureJwtCookieListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class MercureJwtCookieListenerTest extends TestCase
{
    private MercureJwtCookieListener $listener;

    protected function setUp(): void
    {
        // The secret must be at least 256 bits (32 bytes) for HMAC-SHA256
        $this->listener = new MercureJwtCookieListener(
            str_repeat('s', 64)
        );
    }

    public function testInvokeSetsMercureCookie(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('admin');

        $response = new Response();
        $event = new AuthenticationSuccessEvent([], $user, $response);

        ($this->listener)($event);

        $cookies = $response->headers->getCookies();
        $mercureCookies = array_filter($cookies, fn ($c) => $c->getName() === 'mercureAuthorization');

        $this->assertNotEmpty($mercureCookies, 'Mercure JWT cookie should be set');

        $cookie = reset($mercureCookies);
        $this->assertSame('/.well-known/mercure', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function testCookieValueIsValidJwt(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('editor');

        $response = new Response();
        $event = new AuthenticationSuccessEvent([], $user, $response);

        ($this->listener)($event);

        $cookies = $response->headers->getCookies();
        $mercureCookies = array_filter($cookies, fn ($c) => $c->getName() === 'mercureAuthorization');
        $cookie = reset($mercureCookies);

        // JWT format: header.payload.signature
        $jwtValue = $cookie->getValue();
        $this->assertNotEmpty($jwtValue);
        $parts = explode('.', $jwtValue);
        $this->assertCount(3, $parts, 'JWT should have 3 parts');
    }

    public function testCookieContainsMercureSubscribeClaim(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('testuser');

        $response = new Response();
        $event = new AuthenticationSuccessEvent([], $user, $response);

        ($this->listener)($event);

        $cookies = $response->headers->getCookies();
        $mercureCookies = array_filter($cookies, fn ($c) => $c->getName() === 'mercureAuthorization');
        $cookie = reset($mercureCookies);

        $jwtValue = $cookie->getValue();
        $parts = explode('.', $jwtValue);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertArrayHasKey('mercure', $payload);
        $this->assertArrayHasKey('subscribe', $payload['mercure']);
        $this->assertContains(
            'deschide_news/admin/notifications/testuser',
            $payload['mercure']['subscribe']
        );
    }

    public function testCookieHasExpiry(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('admin');

        $response = new Response();
        $event = new AuthenticationSuccessEvent([], $user, $response);

        ($this->listener)($event);

        $cookies = $response->headers->getCookies();
        $mercureCookies = array_filter($cookies, fn ($c) => $c->getName() === 'mercureAuthorization');
        $cookie = reset($mercureCookies);

        // Cookie should have an expiry set
        $this->assertGreaterThan(0, $cookie->getExpiresTime());
    }
}
