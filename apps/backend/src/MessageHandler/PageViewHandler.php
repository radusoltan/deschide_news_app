<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\PageView;
use App\Message\PageViewEvent;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PageViewHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(PageViewEvent $message): void
    {
        try {
            $pageView = new PageView();
            $pageView->setVisitorId($message->visitorId);
            $pageView->setIpAddress($message->ipAddress);
            $pageView->setUserAgent($message->userAgent);
            $pageView->setReferrer($message->referrer);
            $pageView->setViewedAt(DateTime::createFromImmutable($message->timestamp));

            // Set article relation
            if ($message->articleId) {
                $article = $this->articleRepository->find($message->articleId);
                $pageView->setArticle($article);

                // Auto-detect category if not provided
                if (!$message->categoryId && $article) {
                    $pageView->setCategory($article->getCategory());
                }
            }

            // Set category relation
            if ($message->categoryId) {
                $category = $this->categoryRepository->find($message->categoryId);
                $pageView->setCategory($category);
            }

            $this->em->persist($pageView);
            $this->em->flush();

            $this->logger->info('Page view persisted', [
                'article_id' => $message->articleId,
                'visitor_id' => $message->visitorId,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to persist page view', [
                'article_id' => $message->articleId,
                'visitor_id' => $message->visitorId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for Messenger retry
        }
    }
}
