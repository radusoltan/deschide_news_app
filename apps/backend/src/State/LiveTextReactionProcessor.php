<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete as DeleteOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post as PostOperation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\LiveTextReaction;
use App\Repository\LiveTextReactionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * @implements ProcessorInterface<LiveTextReaction>
 */
final class LiveTextReactionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly LiveTextReactionRepository $reactionRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LiveTextReaction
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($operation instanceof PostOperation) {
            return $this->handleCreate($data, $request);
        }

        if ($operation instanceof DeleteOperation) {
            return $this->handleDelete($data);
        }

        return $data;
    }

    private function handleCreate(LiveTextReaction $reaction, $request): LiveTextReaction
    {
        $user = $this->security->getUser();
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');

        // Set user if authenticated
        if ($user) {
            $reaction->setUser($user);
        } else {
            // Anonymous user - track by IP
            if (!$ipAddress) {
                throw new BadRequestHttpException('Unable to determine client IP address');
            }
            $reaction->setIpAddress($ipAddress);
        }

        // Set user agent
        if ($userAgent) {
            $reaction->setUserAgent(substr($userAgent, 0, 255));
        }

        // Check if user/IP already reacted to this post
        $existingReaction = $this->reactionRepository->getUserReaction(
            $reaction->getLiveTextPost(),
            $user?->getId(),
            $ipAddress
        );

        if ($existingReaction) {
            // If same reaction type, do nothing (idempotent)
            if ($existingReaction->getReactionType() === $reaction->getReactionType()) {
                return $existingReaction;
            }

            // Different reaction type - update existing
            $existingReaction->setReactionType($reaction->getReactionType());
            $this->entityManager->flush();

            return $existingReaction;
        }

        // Rate limiting for anonymous users (max 10 reactions per hour)
        if (!$user && $ipAddress) {
            $recentReactionsCount = $this->countRecentReactions($ipAddress, 3600); // 1 hour
            if ($recentReactionsCount >= 10) {
                throw new TooManyRequestsHttpException(3600, 'Too many reactions. Please try again later.');
            }
        }

        // Persist new reaction
        $this->entityManager->persist($reaction);
        $this->entityManager->flush();

        return $reaction;
    }

    private function handleDelete(LiveTextReaction $reaction): null
    {
        $user = $this->security->getUser();

        // Only allow deletion of own reactions
        if ($reaction->getUser() && (!$user || $reaction->getUser()->getId() !== $user->getId())) {
            throw new BadRequestHttpException('You can only delete your own reactions');
        }

        $this->entityManager->remove($reaction);
        $this->entityManager->flush();

        return null;
    }

    private function countRecentReactions(string $ipAddress, int $seconds): int
    {
        $since = new DateTime(\sprintf('-%d seconds', $seconds));

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(r.id)')
            ->from(LiveTextReaction::class, 'r')
            ->where('r.ipAddress = :ipAddress')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('ipAddress', $ipAddress)
            ->setParameter('since', $since);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
