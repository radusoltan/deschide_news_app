<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LiveText;
use App\Repository\LiveTextPostRepository;
use App\Repository\LiveTextRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route('/api/embed', name: 'api_embed_')]
class EmbedController extends AbstractController
{
    public function __construct(
        private readonly LiveTextRepository $liveTextRepository,
        private readonly LiveTextPostRepository $liveTextPostRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $frontendUrl
    ) {
    }

    /**
     * Get LiveText data for embedding.
     */
    #[Route('/live-text/{id}', name: 'live_text', methods: ['GET'])]
    public function getLiveText(int $id, Request $request): JsonResponse
    {
        $liveText = $this->liveTextRepository->find($id);

        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Get locale from query or accept-language header
        $locale = $request->query->get('locale', $request->getPreferredLanguage(['ro', 'en', 'ru']));
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        // Set locale for translatable fields
        $liveText->setTranslatableLocale($locale);
        $this->entityManager->refresh($liveText);

        // Get posts with limit
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        $posts = $this->liveTextPostRepository->createQueryBuilder('p')
            ->where('p.liveText = :liveText')
            ->setParameter('liveText', $liveText)
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        // Build simplified response for embedding
        $data = [
            'id' => $liveText->getId(),
            'title' => $liveText->getTitle(),
            'slug' => $liveText->getSlug(),
            'description' => $liveText->getDescription(),
            'status' => $liveText->getStatus()->value,
            'startTime' => $liveText->getStartTime()?->format('c'),
            'endTime' => $liveText->getEndTime()?->format('c'),
            'locale' => $locale,
            'category' => $liveText->getCategory() ? [
                'id' => $liveText->getCategory()->getId(),
                'name' => $liveText->getCategory()->getName(),
            ] : null,
            'author' => [
                'id' => $liveText->getAuthor()->getId(),
                'name' => $liveText->getAuthor()->getName(),
            ],
            'posts' => array_map(function ($post) {
                return [
                    'id' => $post->getId(),
                    'content' => $post->getContent(),
                    'contentHtml' => $post->getContentHtml(),
                    'isKeyPoint' => $post->isKeyPoint(),
                    'publishedAt' => $post->getPublishedAt()?->format('c'),
                    'author' => [
                        'id' => $post->getAuthor()->getId(),
                        'name' => $post->getAuthor()->getName(),
                    ],
                ];
            }, $posts),
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => \count($posts) === $limit,
            ],
            'embedInfo' => [
                'version' => '1.0',
                'sourceUrl' => \sprintf('%s/%s/live/%s', $this->frontendUrl, $locale, $liveText->getSlug()),
                'mercureTopic' => \sprintf('deschide_news/live_text/%d', $liveText->getId()),
            ],
        ];

        // Add sport match if exists
        if ($liveText->getSportMatch()) {
            $sportMatch = $liveText->getSportMatch();
            $data['sportMatch'] = [
                'id' => $sportMatch->getId(),
                'sportType' => $sportMatch->getSportType(),
                'homeTeam' => $sportMatch->getHomeTeam(),
                'awayTeam' => $sportMatch->getAwayTeam(),
                'homeScore' => $sportMatch->getHomeScore(),
                'awayScore' => $sportMatch->getAwayScore(),
                'status' => $sportMatch->getStatus(),
                'currentMinute' => $sportMatch->getCurrentMinute(),
            ];
        }

        return $this->json($data);
    }

    /**
     * Get LiveText by slug for embedding.
     */
    #[Route('/live-text/slug/{slug}', name: 'live_text_by_slug', methods: ['GET'])]
    public function getLiveTextBySlug(string $slug, Request $request): JsonResponse
    {
        $liveText = $this->liveTextRepository->findOneBy(['slug' => $slug]);

        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->forward(self::class . '::getLiveText', [
            'id' => $liveText->getId(),
            'request' => $request,
        ]);
    }

    /**
     * Get embed code (HTML snippet).
     */
    #[Route('/code/{id}', name: 'get_code', methods: ['GET'])]
    public function getEmbedCode(int $id, Request $request): JsonResponse
    {
        $liveText = $this->liveTextRepository->find($id);

        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $locale = $request->query->get('locale', 'ro');
        $width = $request->query->get('width', '100%');
        $height = $request->query->get('height', '600px');
        $theme = $request->query->get('theme', 'light'); // light or dark

        $embedUrl = \sprintf(
            '%s/%s/embed/live/%s?theme=%s',
            $this->frontendUrl,
            $locale,
            $liveText->getSlug(),
            $theme
        );

        // Generate iframe code
        $iframeCode = \sprintf(
            '<iframe src="%s" width="%s" height="%s" frameborder="0" allowfullscreen></iframe>',
            $embedUrl,
            $width,
            $height
        );

        // Generate JavaScript SDK code
        $jsCode = \sprintf(
            '<div id="deschide-livetext-%d"></div>
<script src="%s/embed.js"></script>
<script>
  DeschideLiveText.embed({
    containerId: "deschide-livetext-%d",
    liveTextId: %d,
    locale: "%s",
    theme: "%s",
    width: "%s",
    height: "%s"
  });
</script>',
            $id,
            $this->frontendUrl,
            $id,
            $id,
            $locale,
            $theme,
            $width,
            $height
        );

        return $this->json([
            'liveTextId' => $id,
            'slug' => $liveText->getSlug(),
            'embedUrl' => $embedUrl,
            'iframeCode' => $iframeCode,
            'javascriptCode' => $jsCode,
            'options' => [
                'locale' => $locale,
                'width' => $width,
                'height' => $height,
                'theme' => $theme,
            ],
        ]);
    }

    /**
     * Get list of embeddable live texts.
     */
    #[Route('/list', name: 'list', methods: ['GET'])]
    public function listEmbeddableLiveTexts(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $limit = (int) $request->query->get('limit', 20);

        $qb = $this->liveTextRepository->createQueryBuilder('lt')
            ->orderBy('lt.startTime', 'DESC')
            ->setMaxResults($limit);

        if ($status) {
            $qb->where('lt.status = :status')
                ->setParameter('status', $status);
        }

        $liveTexts = $qb->getQuery()->getResult();

        $data = array_map(function ($liveText) {
            return [
                'id' => $liveText->getId(),
                'title' => $liveText->getTitle(),
                'slug' => $liveText->getSlug(),
                'status' => $liveText->getStatus()->value,
                'startTime' => $liveText->getStartTime()?->format('c'),
                'embedUrl' => \sprintf(
                    '%s/ro/embed/live/%s',
                    $this->frontendUrl,
                    $liveText->getSlug()
                ),
            ];
        }, $liveTexts);

        return $this->json([
            'liveTexts' => $data,
            'count' => \count($data),
        ]);
    }
}
