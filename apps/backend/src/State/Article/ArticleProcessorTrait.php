<?php

declare(strict_types=1);

namespace App\State\Article;

use App\Entity\Article;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

trait ArticleProcessorTrait
{
    private function resolveLocale(RequestStack $requestStack): string
    {
        $request = $requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        return $locale;
    }

    private function publishArticleUpdateEvent(
        Article $article,
        string $action,
        string $mercureUrl,
        string $mercureJwtSecret,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
    ): void {
        if ($mercureUrl === '' || $mercureJwtSecret === '') {
            return;
        }

        try {
            $data = json_encode([
                'type' => 'article.' . $action,
                'articleId' => $article->getId(),
                'badge' => $article->getBadge()?->value,
                'isFeatured' => $article->isFeatured(),
                'status' => $article->getStatus()->value,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);

            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($mercureJwtSecret),
            );

            $jwt = $config->builder()
                ->withClaim('mercure', ['publish' => ['*']])
                ->getToken($config->signer(), $config->signingKey())
                ->toString();

            $httpClient->request('POST', $mercureUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'topic' => 'deschide_news/articles',
                    'data' => $data,
                ],
            ]);
        } catch (\Throwable $e) {
            $logger->warning('Failed to publish article Mercure event', [
                'articleId' => $article->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
