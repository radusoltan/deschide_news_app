<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\LiveText;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for LiveText permissions.
 */
class LiveTextVoter extends Voter
{
    public const EDIT = 'LIVE_TEXT_EDIT';

    public const DELETE = 'LIVE_TEXT_DELETE';

    public const VIEW = 'LIVE_TEXT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::EDIT, self::DELETE, self::VIEW], true)) {
            return false;
        }

        if (!$subject instanceof LiveText) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Anonymous users can view LiveTexts
        if ($attribute === self::VIEW) {
            return true;
        }

        // Must be authenticated for other actions
        if (!$user instanceof User) {
            return false;
        }

        /** @var LiveText $liveText */
        $liveText = $subject;

        return match ($attribute) {
            self::EDIT => $this->canEdit($liveText, $user),
            self::DELETE => $this->canDelete($liveText, $user),
            default => false,
        };
    }

    private function canEdit(LiveText $liveText, User $user): bool
    {
        // Admin can edit any LiveText
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Author can edit their own LiveText
        if ($liveText->getAuthor() === $user) {
            return true;
        }

        // Collaborators with 'editor' role can edit
        foreach ($liveText->getCollaborators() as $collaborator) {
            if ($collaborator->getUser() === $user && $collaborator->getRole() === 'editor') {
                return true;
            }
        }

        return false;
    }

    private function canDelete(LiveText $liveText, User $user): bool
    {
        // Only admin and author can delete
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $liveText->getAuthor() === $user;
    }
}
