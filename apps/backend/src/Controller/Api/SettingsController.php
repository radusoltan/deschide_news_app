<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Clustering\AutoPromoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings')]
class SettingsController extends AbstractController
{
    #[Route('/auto-promote-threshold', name: 'api_settings_auto_promote_threshold_get', methods: ['GET'])]
    public function getThreshold(AutoPromoteService $autoPromoteService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->json([
            'threshold' => $autoPromoteService->getThreshold(),
        ]);
    }

    #[Route('/auto-promote-threshold', name: 'api_settings_auto_promote_threshold_set', methods: ['PUT'])]
    public function setThreshold(Request $request, AutoPromoteService $autoPromoteService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $threshold = $data['threshold'] ?? null;

        if ($threshold === null || !is_numeric($threshold)) {
            return $this->json(['error' => 'threshold must be a number between 0.0 and 1.0'], 400);
        }

        $value = (float) $threshold;
        if ($value < 0.0 || $value > 1.0) {
            return $this->json(['error' => 'threshold must be between 0.0 and 1.0'], 400);
        }

        $autoPromoteService->setThreshold($value);

        return $this->json([
            'threshold' => $autoPromoteService->getThreshold(),
        ]);
    }
}
