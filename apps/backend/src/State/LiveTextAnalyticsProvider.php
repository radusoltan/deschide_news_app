<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\LiveText\LiveTextAnalyticsDto;
use App\Entity\LiveText;
use App\Service\LiveTextAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * State Provider for LiveText analytics endpoint.
 */
class LiveTextAnalyticsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LiveTextAnalyticsService $analyticsService
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?LiveTextAnalyticsDto
    {
        $liveTextId = $uriVariables['id'] ?? null;

        if ($liveTextId === null) {
            return null;
        }

        $liveText = $this->entityManager->getRepository(LiveText::class)->find($liveTextId);

        if ($liveText === null) {
            return null;
        }

        // Check if detailed analytics are requested
        $detailed = isset($context['request']) && $context['request']->query->get('detailed') === 'true';

        $analytics = $this->analyticsService->getAnalytics($liveText, $detailed);
        $analytics['liveTextId'] = $liveText->getId();

        return new LiveTextAnalyticsDto($analytics);
    }
}
