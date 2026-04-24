<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Message\Aggregator\TriggerAggregatorRunMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/aggregator')]
#[IsGranted('ROLE_ADMIN')]
class AggregatorTriggerController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Route('/run', name: 'api_aggregator_run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $source = $data['source'] ?? null;
        $user = $this->getUser();

        $this->messageBus->dispatch(new TriggerAggregatorRunMessage(
            source: $source,
            triggeredBy: $user?->getUserIdentifier() ?? 'api',
        ));

        return $this->json([
            'status' => 'queued',
            'message' => 'Aggregator run queued',
            'source' => $source ?? 'all',
        ], Response::HTTP_ACCEPTED);
    }
}
