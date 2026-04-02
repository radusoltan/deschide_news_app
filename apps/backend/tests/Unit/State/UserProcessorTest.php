<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\User;
use App\State\UserProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class UserProcessorTest extends TestCase
{
    private UserProcessor $processor;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private Security $security;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->security = $this->createStub(Security::class);

        $this->processor = new UserProcessor(
            $this->entityManager,
            $this->passwordHasher,
            $this->security,
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesUser(): void
    {
        $currentUser = $this->createStub(User::class);
        $currentUser->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($currentUser);

        $managedUser = $this->createStub(User::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(2)->willReturn($managedUser);

        $this->entityManager->method('getRepository')
            ->with(User::class)
            ->willReturn($repo);

        $this->entityManager->expects($this->once())->method('remove')->with($managedUser);
        $this->entityManager->expects($this->once())->method('flush');

        $data = $this->createStub(User::class);

        $operation = new Delete();
        $result = $this->processor->process($data, $operation, ['id' => 2]);

        $this->assertNull($result);
    }

    #[Test]
    public function itPreventsSelfDeletion(): void
    {
        $currentUser = $this->createStub(User::class);
        $currentUser->method('getId')->willReturn(1);
        $this->security->method('getUser')->willReturn($currentUser);

        $data = $this->createStub(User::class);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Cannot delete your own account');

        $operation = new Delete();
        $this->processor->process($data, $operation, ['id' => 1]);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewUserWithHashedPassword(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getPlainPassword')->willReturn('secret123');
        $user->method('getId')->willReturn(null);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'secret123')
            ->willReturn('$2y$hashed');

        $user->expects($this->once())->method('setPassword')->with('$2y$hashed');
        $user->expects($this->once())->method('eraseCredentials');

        $this->entityManager->expects($this->once())->method('persist')->with($user);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($user, $operation);

        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function itCreatesNewUserWithoutPlainPassword(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getPlainPassword')->willReturn(null);
        $user->method('getId')->willReturn(null);

        $this->passwordHasher->expects($this->never())->method('hashPassword');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($user, $operation);

        $this->assertInstanceOf(User::class, $result);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingUser(): void
    {
        $existingUser = $this->createMock(User::class);
        $existingUser->method('getId')->willReturn(10);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingUser);

        $this->entityManager->method('getRepository')
            ->with(User::class)
            ->willReturn($repo);

        $data = $this->createStub(User::class);
        $data->method('getUsername')->willReturn('newname');
        $data->method('getEmail')->willReturn('new@test.com');
        $data->method('getFirstName')->willReturn('New');
        $data->method('getLastName')->willReturn('Name');
        $data->method('getRoles')->willReturn(['ROLE_ADMIN']);
        $data->method('isActive')->willReturn(true);
        $data->method('getPlainPassword')->willReturn(null);

        $existingUser->expects($this->once())->method('setUsername')->with('newname');
        $existingUser->expects($this->once())->method('setEmail')->with('new@test.com');

        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function itHashesPasswordOnUpdate(): void
    {
        $existingUser = $this->createMock(User::class);
        $existingUser->method('getId')->willReturn(10);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingUser);

        $this->entityManager->method('getRepository')
            ->with(User::class)
            ->willReturn($repo);

        $data = $this->createStub(User::class);
        $data->method('getUsername')->willReturn('user');
        $data->method('getEmail')->willReturn('user@test.com');
        $data->method('getFirstName')->willReturn('First');
        $data->method('getLastName')->willReturn('Last');
        $data->method('getRoles')->willReturn(['ROLE_USER']);
        $data->method('isActive')->willReturn(true);
        $data->method('getPlainPassword')->willReturn('newpassword');

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($existingUser, 'newpassword')
            ->willReturn('$2y$newhash');

        $existingUser->expects($this->once())->method('setPassword')->with('$2y$newhash');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentUser(): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($repo);

        $data = $this->createStub(User::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    // ========================
    // Non-User Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonUserData(): void
    {
        $operation = new Post();
        $result = $this->processor->process('not-a-user', $operation);

        $this->assertNull($result);
    }
}
