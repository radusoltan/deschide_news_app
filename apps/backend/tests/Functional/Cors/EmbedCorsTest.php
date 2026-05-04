<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cors;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Regression guards for ADR-033 — CORS wildcard intentional on /api/embed.
 *
 * The wildcard `Access-Control-Allow-Origin: *` on /api/embed/* is a
 * deliberate product-design choice (third-party publishers consume it
 * via WordPress shortcode, iframe embed, JavaScript SDK — see
 * docs/features/EMBED_CAPABILITY_GUIDE.md).
 *
 * The decision is bounded by three safety invariants. This test enforces
 * them so future contributors cannot silently drift the design:
 *
 *   1. CORS preflight on /api/embed/* returns ACAO: * (the wildcard works).
 *   2. CORS responses never set Access-Control-Allow-Credentials: true
 *      (CORS spec forbids the combination ACAO:* + ACAC:true; browsers
 *      reject it; this test enforces it explicitly).
 *   3. POST/PUT/PATCH/DELETE on /api/embed/* are rejected at the routing
 *      layer (405 Method Not Allowed) — write operations cannot be
 *      smuggled in under the wildcard CORS umbrella.
 *   4. Cross-origin GET (with Origin header) returns ACAO: * — the
 *      end-to-end "fetch from any origin" use case works.
 *   5. Regression guard: every route registered under /api/embed/*
 *      uses ONLY {GET, HEAD, OPTIONS}. Adding a write verb requires
 *      relocating the route off the wildcard CORS path (see ADR-033
 *      reverse-decision triggers).
 *
 * If you are adding a write endpoint that needs to live under embed,
 * STOP and read ADR-033. The right move is to put it under a different
 * path with the project's standard %env(CORS_ALLOW_ORIGIN)% allowlist.
 */
final class EmbedCorsTest extends WebTestCase
{
    /**
     * Invariant #1 — CORS preflight returns wildcard ACAO.
     */
    public function testPreflightReturnsWildcardAcao(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/embed/list', [], [], [
            'HTTP_ORIGIN' => 'https://random-publisher.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $response = $client->getResponse();

        self::assertContains(
            $response->getStatusCode(),
            [200, 204],
            'CORS preflight must succeed with 200 or 204',
        );
        self::assertSame(
            '*',
            $response->headers->get('access-control-allow-origin'),
            'ADR-033 invariant: /api/embed/* must keep ACAO: * for third-party publishers',
        );
    }

    /**
     * Invariant #2 — Access-Control-Allow-Credentials is never set on /api/embed/*.
     *
     * The CORS specification (https://fetch.spec.whatwg.org/#http-access-control-allow-credentials)
     * forbids the combination `ACAO: *` + `ACAC: true`. Browsers reject such
     * responses. ADR-033 makes this explicit: if you ever need credentials,
     * leave the wildcard CORS path entirely and use an allowlist instead.
     */
    public function testCredentialsHeaderIsNeverSet(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/embed/list', [], [], [
            'HTTP_ORIGIN' => 'https://random-publisher.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response = $client->getResponse();

        self::assertNotSame(
            'true',
            $response->headers->get('access-control-allow-credentials'),
            'ADR-033 invariant: ACAC must never be true on /api/embed/* (forbidden combination with ACAO: *)',
        );
        self::assertNull(
            $response->headers->get('access-control-allow-credentials'),
            'ADR-033 invariant: ACAC header should be absent (NelmioCorsBundle default)',
        );
    }

    /**
     * Invariant #3 — Write verbs on /api/embed/* return 405.
     *
     * If routing ever changes such that POST/PUT/PATCH/DELETE start matching
     * a route under /api/embed/*, ADR-033 has been violated. The route MUST
     * move to an authenticated path with the standard CORS allowlist.
     */
    #[DataProvider('provideWriteVerbs')]
    public function testWriteVerbsAreRejected(string $method): void
    {
        $client = static::createClient();
        $client->request($method, '/api/embed/list', [], [], [
            'HTTP_ORIGIN' => 'https://random-publisher.example',
        ]);

        $status = $client->getResponse()->getStatusCode();

        self::assertSame(
            405,
            $status,
            \sprintf(
                'ADR-033 violation: %s on /api/embed/list returned %d. /api/embed/* is GET-only by design.',
                $method,
                $status,
            ),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWriteVerbs(): iterable
    {
        yield 'POST'   => ['POST'];
        yield 'PUT'    => ['PUT'];
        yield 'PATCH'  => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    /**
     * Invariant #4 — Cross-origin GET returns the wildcard ACAO.
     *
     * Smoke test for the actual use case: a third-party publisher fetches
     * /api/embed/list from its own origin and reads the JSON. /list is
     * always 200 (returns an empty array if no live texts exist), so this
     * test does not depend on fixture state.
     */
    public function testCrossOriginGetReturnsWildcardAcao(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/embed/list', [], [], [
            'HTTP_ORIGIN' => 'https://random-publisher.example',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $client->getResponse();

        self::assertSame(
            200,
            $response->getStatusCode(),
            '/api/embed/list must return 200 even with no live texts (empty array)',
        );
        self::assertSame(
            '*',
            $response->headers->get('access-control-allow-origin'),
            'ADR-033 invariant: cross-origin GET on /api/embed/* must surface ACAO: *',
        );
        self::assertNull(
            $response->headers->get('access-control-allow-credentials'),
            'ADR-033 invariant: ACAC must remain absent on actual GET responses',
        );
    }

    /**
     * Invariant #5 — Regression guard on registered routes.
     *
     * Iterate the full route collection. Every route whose path starts with
     * /api/embed must declare ONLY safe HTTP methods (GET, HEAD, OPTIONS).
     * If a contributor adds a POST route under /api/embed (intentionally or
     * accidentally), this assertion fails CI and forces them to read ADR-033.
     */
    public function testNoWriteRoutesUnderEmbedPrefix(): void
    {
        static::createClient();

        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');
        $routes = $router->getRouteCollection();

        $allowedMethods = ['GET', 'HEAD', 'OPTIONS'];
        $offenders      = [];

        foreach ($routes as $name => $route) {
            $path = $route->getPath();
            if (!str_starts_with($path, '/api/embed')) {
                continue;
            }

            $methods = $route->getMethods();
            if ([] === $methods) {
                // Empty methods array = route accepts ALL verbs. That's an
                // automatic ADR-033 violation under /api/embed.
                $offenders[] = \sprintf('%s [path=%s methods=ANY]', $name, $path);
                continue;
            }

            $disallowed = array_diff($methods, $allowedMethods);
            if ([] !== $disallowed) {
                $offenders[] = \sprintf(
                    '%s [path=%s methods=%s disallowed=%s]',
                    $name,
                    $path,
                    implode(',', $methods),
                    implode(',', $disallowed),
                );
            }
        }

        self::assertSame(
            [],
            $offenders,
            "ADR-033 violation — write methods registered under /api/embed/*. "
            . "Read docs/adr/ADR-033-cors-wildcard-intentional-on-api-embed.md before adding a route under this prefix. "
            . "Offenders: \n  - " . implode("\n  - ", $offenders),
        );
    }
}
