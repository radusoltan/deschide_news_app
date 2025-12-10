<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\ShortLinkClickMessage;
use App\Repository\ShortLinkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles short link redirections.
 *
 * This controller is optimized for speed:
 * - Analytics are dispatched asynchronously via Messenger
 * - No caching to ensure accurate stats
 */
class ShortLinkRedirectController extends AbstractController
{
    public function __construct(
        private readonly ShortLinkRepository $shortLinkRepository,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    #[Route('/s/{code}', name: 'short_link_redirect', methods: ['GET'])]
    public function handleRedirect(string $code, Request $request): Response
    {
        $shortLink = $this->shortLinkRepository->findByCode($code);

        if (!$shortLink) {
            throw new NotFoundHttpException('Short link not found');
        }

        // Dispatch analytics message asynchronously
        // This ensures the redirect is fast (< 50ms target)
        $this->messageBus->dispatch(new ShortLinkClickMessage(
            shortLinkId: $shortLink->getId(),
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
            referrer: $request->headers->get('Referer')
        ));

        // Create redirect response with no caching
        $response = new RedirectResponse(
            $shortLink->getOriginalUrl(),
            Response::HTTP_MOVED_PERMANENTLY
        );

        // Ensure no caching for accurate stats
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Preview endpoint - shows link info without redirecting.
     * Useful for security/preview before clicking unknown links.
     */
    #[Route('/s/{code}/preview', name: 'short_link_preview', methods: ['GET'])]
    public function preview(string $code): Response
    {
        $shortLink = $this->shortLinkRepository->findByCode($code);

        if (!$shortLink) {
            throw new NotFoundHttpException('Short link not found');
        }

        return $this->json([
            'code' => $shortLink->getCode(),
            'originalUrl' => $shortLink->getOriginalUrl(),
            'title' => $shortLink->getTitle(),
            'clickCount' => $shortLink->getClickCount(),
            'createdAt' => $shortLink->getCreatedAt()?->format('c'),
            'article' => $shortLink->getArticle() ? [
                'id' => $shortLink->getArticle()->getId(),
                'title' => $shortLink->getArticle()->getTitle(),
            ] : null,
        ]);
    }
}
