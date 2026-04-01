<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\LiveTextPost;
use App\Entity\User;
use App\Security\Voter\LiveTextPostVoter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class LiveTextPostVoterTest extends TestCase
{
    private LiveTextPostVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new LiveTextPostVoter();
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

    private function createPost(?User $author = null, ?LiveText $liveText = null): LiveTextPost
    {
        $post = $this->createStub(LiveTextPost::class);
        $post->method('getAuthor')->willReturn($author);
        $post->method('getLiveText')->willReturn($liveText);

        return $post;
    }

    // --- VIEW tests ---

    public function testViewIsAlwaysGranted(): void
    {
        $post = $this->createPost();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // --- CREATE tests ---

    public function testCreateGrantedForAdmin(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $liveText = $this->createLiveText();
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $liveText, [LiveTextPostVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateGrantedForLiveTextAuthor(): void
    {
        $author = $this->createUser();
        $liveText = $this->createLiveText($author);
        $token = $this->createToken($author);

        $result = $this->voter->vote($token, $liveText, [LiveTextPostVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateGrantedForCollaborator(): void
    {
        $user = $this->createUser();
        $collaborator = $this->createStub(LiveTextCollaborator::class);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('contributor');

        $liveText = $this->createLiveText(null, [$collaborator]);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $liveText, [LiveTextPostVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateDeniedForAnonymous(): void
    {
        $liveText = $this->createLiveText();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $liveText, [LiveTextPostVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testCreateDeniedForNonRelatedUser(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $liveText = $this->createLiveText($otherUser);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $liveText, [LiveTextPostVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // --- EDIT tests ---

    public function testEditGrantedForAdmin(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $post = $this->createPost();
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditGrantedForPostAuthor(): void
    {
        $author = $this->createUser();
        $post = $this->createPost($author);
        $token = $this->createToken($author);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditGrantedForLiveTextAuthor(): void
    {
        $liveTextAuthor = $this->createUser();
        $liveText = $this->createLiveText($liveTextAuthor);
        $postAuthor = $this->createUser();
        $post = $this->createPost($postAuthor, $liveText);
        $token = $this->createToken($liveTextAuthor);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditGrantedForEditorCollaborator(): void
    {
        $user = $this->createUser();
        $collaborator = $this->createStub(LiveTextCollaborator::class);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('editor');

        $liveText = $this->createLiveText(null, [$collaborator]);
        $post = $this->createPost(null, $liveText);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditDeniedForContributorCollaborator(): void
    {
        $user = $this->createUser();
        $collaborator = $this->createStub(LiveTextCollaborator::class);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('contributor');

        $liveText = $this->createLiveText(null, [$collaborator]);
        $post = $this->createPost(null, $liveText);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForAnonymous(): void
    {
        $post = $this->createPost();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // --- DELETE tests ---

    public function testDeleteGrantedForAdmin(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $post = $this->createPost();
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteGrantedForPostAuthor(): void
    {
        $author = $this->createUser();
        $post = $this->createPost($author);
        $token = $this->createToken($author);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteGrantedForLiveTextAuthor(): void
    {
        $liveTextAuthor = $this->createUser();
        $liveText = $this->createLiveText($liveTextAuthor);
        $post = $this->createPost(null, $liveText);
        $token = $this->createToken($liveTextAuthor);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteDeniedForCollaborator(): void
    {
        $user = $this->createUser();
        $collaborator = $this->createStub(LiveTextCollaborator::class);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('editor');

        $liveText = $this->createLiveText(null, [$collaborator]);
        $post = $this->createPost(null, $liveText);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteDeniedForAnonymous(): void
    {
        $post = $this->createPost();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $post, [LiveTextPostVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // --- supports tests ---

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $post = $this->createPost();
        $token = $this->createToken();

        $result = $this->voter->vote($token, $post, ['UNSUPPORTED']);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnNonLiveTextPostSubject(): void
    {
        $token = $this->createToken();

        $result = $this->voter->vote($token, new \stdClass(), [LiveTextPostVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testCreateSupportsLiveTextSubject(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $liveText = $this->createLiveText();
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $liveText, [LiveTextPostVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }
}
