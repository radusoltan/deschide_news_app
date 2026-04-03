<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AiConversation;
use App\Entity\User;
use App\Security\Voter\AiConversationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AiConversationVoterTest extends TestCase
{
    private AiConversationVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new AiConversationVoter();
    }

    public function testOwnerCanView(): void
    {
        $user = $this->createUser(1);
        $conversation = $this->createConversation($user);
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        $result = $this->voter->vote($token, $conversation, [AiConversationVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testOwnerCanEdit(): void
    {
        $user = $this->createUser(1);
        $conversation = $this->createConversation($user);
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        $result = $this->voter->vote($token, $conversation, [AiConversationVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testNonOwnerCannotView(): void
    {
        $owner = $this->createUser(1);
        $other = $this->createUser(2);
        $conversation = $this->createConversation($owner);
        $token = new UsernamePasswordToken($other, 'main', ['ROLE_USER']);

        $result = $this->voter->vote($token, $conversation, [AiConversationVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $user = $this->createUser(1);
        $conversation = $this->createConversation($user);
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        $result = $this->voter->vote($token, $conversation, ['SOME_OTHER_ATTRIBUTE']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnNonConversationSubject(): void
    {
        $user = $this->createUser(1);
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        $result = $this->voter->vote($token, new \stdClass(), [AiConversationVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    private function createUser(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    private function createConversation(User $owner): AiConversation
    {
        $conv = new AiConversation();
        $conv->setUser($owner);

        return $conv;
    }
}
