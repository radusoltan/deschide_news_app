<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\User;
use App\Security\Voter\LiveTextVoter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class LiveTextVoterTest extends TestCase
{
    private LiveTextVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new LiveTextVoter();
    }

    private function createToken(?User $user = null): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createUser(array $roles = ['ROLE_USER']): User
    {
        $user = $this->createStub(User::class);
        $user->method('getRoles')->willReturn($roles);

        return $user;
    }

    private function createLiveText(?User $author = null, array $collaborators = []): LiveText
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getCollaborators')->willReturn(new ArrayCollection($collaborators));

        return $liveText;
    }

    public function testViewIsAlwaysGranted(): void
    {
        $liveText = $this->createLiveText();
        $token = $this->createToken(); // anonymous

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditDeniedForAnonymous(): void
    {
        $liveText = $this->createLiveText();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditGrantedForAdmin(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $liveText = $this->createLiveText();
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditGrantedForAuthor(): void
    {
        $author = $this->createUser();
        $liveText = $this->createLiveText($author);
        $token = $this->createToken($author);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditGrantedForEditorCollaborator(): void
    {
        $user = $this->createUser();

        $collaborator = $this->createStub(LiveTextCollaborator::class);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('editor');

        $liveText = $this->createLiveText(null, [$collaborator]);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditDeniedForContributorCollaborator(): void
    {
        $user = $this->createUser();

        $collaborator = $this->createStub(LiveTextCollaborator::class);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('contributor');

        $liveText = $this->createLiveText(null, [$collaborator]);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForNonRelatedUser(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $liveText = $this->createLiveText($otherUser);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteGrantedForAdmin(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $liveText = $this->createLiveText();
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteGrantedForAuthor(): void
    {
        $author = $this->createUser();
        $liveText = $this->createLiveText($author);
        $token = $this->createToken($author);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteDeniedForNonAuthor(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $liveText = $this->createLiveText($otherUser);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteDeniedForAnonymous(): void
    {
        $liveText = $this->createLiveText();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $liveText, [LiveTextVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $liveText = $this->createLiveText();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $liveText, ['UNSUPPORTED']);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnNonLiveTextSubject(): void
    {
        $token = $this->createToken();

        $result = $this->voter->vote($token, new \stdClass(), [LiveTextVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}
