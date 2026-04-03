<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AiConversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, AiConversation>
 */
final class AiConversationVoter extends Voter
{
    public const VIEW = 'AI_CONVERSATION_VIEW';
    public const EDIT = 'AI_CONVERSATION_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof AiConversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $subject->getUser()->getId() === $user->getId();
    }
}
