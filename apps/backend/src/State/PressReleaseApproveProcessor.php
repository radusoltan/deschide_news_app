<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use App\Enum\ArticleStatus;
use App\Service\Editorial\ArticleFactoryService;
use App\Service\Editorial\AutoPublishGateService;
use App\Service\Editorial\PostApprovalDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PressReleaseApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ArticleFactoryService $articleFactory,
        private readonly AutoPublishGateService $autoPublishGate,
        private readonly PostApprovalDispatcher $postApprovalDispatcher,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PressRelease
    {
        if (!$data instanceof PressRelease) {
            throw new BadRequestHttpException('Invalid data');
        }

        if ($data->getStatus() !== PressReleaseStatus::PENDING) {
            throw new BadRequestHttpException('Only pending press releases can be approved');
        }

        // Create article from press release
        $article = $this->articleFactory->createFromPressRelease($data);

        // Evaluate auto-publish gate
        $decision = $this->autoPublishGate->evaluate($article, $data);
        if ($decision->canPublish) {
            $article->setStatus(ArticleStatus::PUBLISHED);
        } else {
            $article->setStatus(ArticleStatus::SUBMITTED);
        }

        // Update press release status
        $data->setStatus(PressReleaseStatus::APPROVED);
        $data->setProcessedAt(new \DateTimeImmutable());
        $data->setArticle($article);

        $user = $this->security->getUser();
        if ($user !== null) {
            $data->setProcessedBy($user);
        }

        $this->em->flush();

        $this->logger->info('Article created from PressRelease', [
            'articleId' => $article->getId(),
            'pressReleaseId' => $data->getId(),
            'sourceType' => $data->getSourceType()->value,
            'autoPublishGate' => $decision->gate,
            'gateReasons' => $decision->reasons,
        ]);

        // Dispatch post-approval messages
        $this->postApprovalDispatcher->dispatch($article, $data);

        return $data;
    }
}
