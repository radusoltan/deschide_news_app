<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for LiveTextPost permissions.
 */
class LiveTextPostVoter extends Voter
{
    public const CREATE = 'LIVE_TEXT_POST_CREATE';

    public const EDIT = 'LIVE_TEXT_POST_EDIT';

    public const DELETE = 'LIVE_TEXT_POST_DELETE';

    public const VIEW = 'LIVE_TEXT_POST_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::CREATE, self::EDIT, self::DELETE, self::VIEW], true)) {
            return false;
        }

        // For CREATE, subject can be a LiveText
        if ($attribute === self::CREATE && $subject instanceof LiveText) {
            return true;
        }

        // For other actions, subject must be LiveTextPost
        if (!$subject instanceof LiveTextPost) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Anonymous users can view posts
        if ($attribute === self::VIEW) {
            return true;
        }

        // Must be authenticated for other actions
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->canCreate($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            default => false,
        };
    }

    private function canCreate(mixed $subject, User $user): bool
    {
        // Admin can create posts in any LiveText
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Subject can be LiveText (when checking permission to post) or LiveTextPost (with liveText relation)
        if ($subject instanceof LiveText) {
            $liveText = $subject;
        } elseif ($subject instanceof LiveTextPost) {
            $liveText = $subject->getLiveText();
        } else {
            return false;
        }

        if (!$liveText) {
            return false;
        }

        // Author of the LiveText can create posts
        if ($liveText->getAuthor() === $user) {
            return true;
        }

        // Collaborators (both editor and contributor) can create posts
        foreach ($liveText->getCollaborators() as $collaborator) {
            if ($collaborator->getUser() === $user) {
                return true;
            }
        }

        return false;
    }

    private function canEdit(LiveTextPost $post, User $user): bool
    {
        // Admin can edit any post
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Post author can edit their own post
        if ($post->getAuthor() === $user) {
            return true;
        }

        // LiveText author can edit any post in their LiveText
        if ($post->getLiveText() && $post->getLiveText()->getAuthor() === $user) {
            return true;
        }

        // Collaborators with 'editor' role can edit any post
        if ($post->getLiveText()) {
            foreach ($post->getLiveText()->getCollaborators() as $collaborator) {
                if ($collaborator->getUser() === $user && $collaborator->getRole() === 'editor') {
                    return true;
                }
            }
        }

        return false;
    }

    private function canDelete(LiveTextPost $post, User $user): bool
    {
        // Admin can delete any post
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Post author can delete their own post
        if ($post->getAuthor() === $user) {
            return true;
        }

        // LiveText author can delete any post in their LiveText
        if ($post->getLiveText() && $post->getLiveText()->getAuthor() === $user) {
            return true;
        }

        return false;
    }
}
