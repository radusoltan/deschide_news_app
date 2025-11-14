<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\LiveTextPostRepository;
use App\Repository\LiveTextReactionRepository;
use stdClass;

/**
 * Provider for fetching reaction statistics for a LiveTextPost.
 *
 * @implements ProviderInterface<array>
 */
final class LiveTextPostReactionsProvider implements ProviderInterface
{
    public function __construct(
        private readonly LiveTextPostRepository $postRepository,
        private readonly LiveTextReactionRepository $reactionRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $postId = $uriVariables['id'] ?? null;

        if (!$postId) {
            return [];
        }

        $post = $this->postRepository->find($postId);

        if (!$post) {
            return null;
        }

        // Get reaction counts grouped by type
        $counts = $this->reactionRepository->getReactionCountsByPost($post);

        // Calculate total
        $total = array_sum($counts);

        // Return as stdClass to avoid API Platform wrapping
        $result = new stdClass();
        $result->postId = $postId;
        $result->total = $total;
        $result->counts = $counts;

        return $result;
    }
}
