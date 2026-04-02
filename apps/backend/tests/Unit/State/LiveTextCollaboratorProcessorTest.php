<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\User;
use App\State\LiveTextCollaboratorProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextCollaboratorProcessorTest extends TestCase
{
    private LiveTextCollaboratorProcessor $processor;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->processor = new LiveTextCollaboratorProcessor($this->entityManager);
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesCollaborator(): void
    {
        $collaborator = $this->createMock(LiveTextCollaborator::class);

        $this->entityManager->expects($this->once())->method('remove')->with($collaborator);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($collaborator, $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesCollaborator(): void
    {
        $user = $this->createStub(User::class);
        $author = $this->createStub(User::class);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getAuthor')->willReturn($author);

        $collaborator = $this->createMock(LiveTextCollaborator::class);
        $collaborator->method('getLiveText')->willReturn($liveText);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('editor');

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(LiveTextCollaborator::class)
            ->willReturn($repo);

        $this->entityManager->expects($this->once())->method('persist')->with($collaborator);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($collaborator, $operation);

        $this->assertInstanceOf(LiveTextCollaborator::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenLiveTextMissing(): void
    {
        $collaborator = $this->createMock(LiveTextCollaborator::class);
        $collaborator->method('getLiveText')->willReturn(null);
        $collaborator->method('getUser')->willReturn($this->createMock(User::class));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Collaborator must be associated with a LiveText');

        $operation = new Post();
        $this->processor->process($collaborator, $operation);
    }

    #[Test]
    public function itThrowsExceptionWhenUserMissing(): void
    {
        $collaborator = $this->createMock(LiveTextCollaborator::class);
        $collaborator->method('getLiveText')->willReturn($this->createMock(LiveText::class));
        $collaborator->method('getUser')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Collaborator must have a User');

        $operation = new Post();
        $this->processor->process($collaborator, $operation);
    }

    #[Test]
    public function itThrowsExceptionWhenAddingAuthorAsCollaborator(): void
    {
        $author = $this->createStub(User::class);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getAuthor')->willReturn($author);

        $collaborator = $this->createMock(LiveTextCollaborator::class);
        $collaborator->method('getLiveText')->willReturn($liveText);
        $collaborator->method('getUser')->willReturn($author); // Same user as author

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The LiveText author is already a collaborator by default');

        $operation = new Post();
        $this->processor->process($collaborator, $operation);
    }

    #[Test]
    public function itThrowsExceptionForDuplicateCollaborator(): void
    {
        $user = $this->createStub(User::class);
        $author = $this->createStub(User::class);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getAuthor')->willReturn($author);

        $collaborator = $this->createMock(LiveTextCollaborator::class);
        $collaborator->method('getLiveText')->willReturn($liveText);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('contributor');

        $existing = $this->createStub(LiveTextCollaborator::class);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $this->entityManager->method('getRepository')
            ->with(LiveTextCollaborator::class)
            ->willReturn($repo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('This user is already a collaborator');

        $operation = new Post();
        $this->processor->process($collaborator, $operation);
    }

    #[Test]
    public function itDefaultsInvalidRoleToContributor(): void
    {
        $user = $this->createStub(User::class);
        $author = $this->createStub(User::class);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getAuthor')->willReturn($author);

        $collaborator = $this->createMock(LiveTextCollaborator::class);
        $collaborator->method('getLiveText')->willReturn($liveText);
        $collaborator->method('getUser')->willReturn($user);
        $collaborator->method('getRole')->willReturn('invalid_role');
        $collaborator->expects($this->once())->method('setRole')->with('contributor');

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(LiveTextCollaborator::class)
            ->willReturn($repo);

        $operation = new Post();
        $this->processor->process($collaborator, $operation);
    }

    // ========================
    // Non-Collaborator Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonCollaboratorData(): void
    {
        $operation = new Post();
        $result = $this->processor->process('not-a-collaborator', $operation);

        $this->assertNull($result);
    }
}
