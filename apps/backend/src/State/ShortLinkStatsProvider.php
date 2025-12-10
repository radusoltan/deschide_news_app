<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ShortLink;
use App\Repository\ShortLinkInteractionRepository;
use App\Repository\ShortLinkRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides aggregated statistics for a short link.
 *
 * @implements ProviderInterface<object>
 */
final class ShortLinkStatsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ShortLinkRepository $shortLinkRepository,
        private readonly ShortLinkInteractionRepository $interactionRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $id = $uriVariables['id'] ?? null;

        if (!$id) {
            throw new NotFoundHttpException('Short link ID required');
        }

        $shortLink = $this->shortLinkRepository->find($id);

        if (!$shortLink) {
            throw new NotFoundHttpException('Short link not found');
        }

        // Get statistics
        $clicksPerDay = $this->interactionRepository->getClicksPerDay($shortLink, 30);
        $topReferrers = $this->interactionRepository->getTopReferrers($shortLink, 10);
        $deviceTypes = $this->interactionRepository->getDeviceTypeDistribution($shortLink);
        $countries = $this->interactionRepository->getCountryDistribution($shortLink, 10);

        return new ShortLinkStats(
            shortLink: $shortLink,
            clicksPerDay: $clicksPerDay,
            topReferrers: $topReferrers,
            deviceTypes: $deviceTypes,
            countries: $countries
        );
    }
}

/**
 * DTO for short link statistics.
 */
final class ShortLinkStats
{
    public function __construct(
        public readonly ShortLink $shortLink,
        public readonly array $clicksPerDay,
        public readonly array $topReferrers,
        public readonly array $deviceTypes,
        public readonly array $countries
    ) {
    }
}
