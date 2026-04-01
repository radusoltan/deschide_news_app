<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\User;
use DateTime;
use PHPUnit\Framework\TestCase;

class LiveTextCollaboratorTest extends TestCase
{
    private LiveTextCollaborator $collaborator;

    protected function setUp(): void
    {
        $this->collaborator = new LiveTextCollaborator();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->collaborator->getId());
        $this->assertSame('contributor', $this->collaborator->getRole());
        $this->assertNull($this->collaborator->getLiveText());
        $this->assertNull($this->collaborator->getUser());
        $this->assertNull($this->collaborator->getCreatedAt());
    }

    public function testSetGetRole(): void
    {
        $result = $this->collaborator->setRole('editor');
        $this->assertSame('editor', $this->collaborator->getRole());
        $this->assertSame($this->collaborator, $result);
    }

    public function testSetGetRoleContributor(): void
    {
        $this->collaborator->setRole('contributor');
        $this->assertSame('contributor', $this->collaborator->getRole());
    }

    public function testSetGetLiveText(): void
    {
        $liveText = new LiveText();
        $result = $this->collaborator->setLiveText($liveText);
        $this->assertSame($liveText, $this->collaborator->getLiveText());
        $this->assertSame($this->collaborator, $result);
    }

    public function testSetGetLiveTextNull(): void
    {
        $liveText = new LiveText();
        $this->collaborator->setLiveText($liveText);
        $this->collaborator->setLiveText(null);
        $this->assertNull($this->collaborator->getLiveText());
    }

    public function testSetGetUser(): void
    {
        $user = new User();
        $result = $this->collaborator->setUser($user);
        $this->assertSame($user, $this->collaborator->getUser());
        $this->assertSame($this->collaborator, $result);
    }

    public function testSetGetUserNull(): void
    {
        $user = new User();
        $this->collaborator->setUser($user);
        $this->collaborator->setUser(null);
        $this->assertNull($this->collaborator->getUser());
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-06-15');
        $result = $this->collaborator->setCreatedAt($date);
        $this->assertSame($date, $this->collaborator->getCreatedAt());
        $this->assertSame($this->collaborator, $result);
    }
}
