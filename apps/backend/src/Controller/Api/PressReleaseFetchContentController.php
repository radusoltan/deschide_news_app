<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PressReleaseRepository;
use App\Service\Aggregator\RemoteContentFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * On-demand content fetching for aggregated PressReleases.
 *
 * Downloads the article page from sourceUrl, extracts clean content
 * using Readability, and updates the PressRelease.
 */
#[Route('/api/press-releases')]
#[IsGranted('ROLE_EDITOR')]
final class PressReleaseFetchContentController extends AbstractController
{
    public function __construct(
        private readonly PressReleaseRepository $repository,
        private readonly RemoteContentFetcher $contentFetcher,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/{id}/fetch-content', name: 'api_press_release_fetch_content', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $pressRelease = $this->repository->find($id);
        if ($pressRelease === null) {
            return $this->json(['error' => 'PressRelease not found'], Response::HTTP_NOT_FOUND);
        }

        $sourceUrl = $pressRelease->getSourceUrl();
        if ($sourceUrl === null || $sourceUrl === '') {
            return $this->json(['error' => 'PressRelease has no sourceUrl'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Skip if already has substantial content (unless force=true)
        $force = $request->query->getBoolean('force', false);
        if (!$force && $pressRelease->getContentLength() > 500) {
            return $this->json([
                'error' => 'PressRelease already has content (' . $pressRelease->getContentLength() . ' chars). Use ?force=true to override.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->contentFetcher->fetchAndExtract($sourceUrl);
        if ($result === null) {
            $this->logger->warning('PressRelease content fetch failed', [
                'id' => $id,
                'sourceUrl' => mb_substr($sourceUrl, 0, 100),
            ]);

            return $this->json([
                'error' => 'Nu s-a putut extrage conținutul de la sursa originală',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update PressRelease with fetched content
        $pressRelease->setContent($result->htmlContent);

        if ($result->excerpt !== null && $result->excerpt !== '') {
            $pressRelease->setLead(mb_substr($result->excerpt, 0, 500));
        }

        // Update source image if we found one and don't already have one
        if ($result->imageUrl !== null && $pressRelease->getSourceImageUrl() === null) {
            $pressRelease->setSourceImageUrl($result->imageUrl);
        }

        $this->em->flush();

        $this->logger->info('PressRelease content fetched', [
            'id' => $id,
            'wordCount' => $result->wordCount,
            'siteName' => $result->siteName,
        ]);

        return $this->json([
            'success' => true,
            'id' => $pressRelease->getId(),
            'contentLength' => $pressRelease->getContentLength(),
            'wordCount' => $result->wordCount,
            'siteName' => $result->siteName,
            'title' => $pressRelease->getTitle(),
            'lead' => $pressRelease->getLead(),
            'sourceHostname' => $pressRelease->getSourceHostname(),
        ]);
    }
}
