<?php

declare(strict_types=1);

namespace App\EventListener;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Sets a Mercure JWT cookie on successful authentication.
 * This cookie allows the frontend EventSource to subscribe to private topics.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
final readonly class MercureJwtCookieListener
{
    public function __construct(
        private string $mercureJwtSecret,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $mercureJwt = $this->createMercureSubscriberJwt($user);

        $response = $event->getResponse();
        $response->headers->setCookie(
            Cookie::create('mercureAuthorization')
                ->withValue($mercureJwt)
                ->withPath('/.well-known/mercure')
                ->withHttpOnly(true)
                ->withSecure(false) // Set to true in production with HTTPS
                ->withSameSite('lax')
                ->withExpires(new \DateTimeImmutable('+1 hour'))
        );
    }

    private function createMercureSubscriberJwt(UserInterface $user): string
    {
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->mercureJwtSecret),
        );

        $username = $user->getUserIdentifier();

        $token = $config->builder()
            ->withClaim('mercure', [
                'subscribe' => [
                    \sprintf('deschide_news/admin/notifications/%s', $username),
                ],
            ])
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt(new \DateTimeImmutable('+1 hour'))
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }
}
