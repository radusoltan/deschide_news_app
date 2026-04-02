<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Import\ImportTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportTokenServiceTest extends TestCase
{
    private JWTTokenManagerInterface $jwtManager;
    private UserRepository $userRepository;
    private ImportTokenService $service;

    protected function setUp(): void
    {
        $this->jwtManager = $this->createStub(JWTTokenManagerInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->service = new ImportTokenService($this->jwtManager, $this->userRepository);
    }

    // --- getImportToken ---

    public function testGetImportTokenReturnsJwtString(): void
    {
        $user = $this->createStub(User::class);
        $this->userRepository->method('findOneBy')
            ->with(['username' => 'admin'])
            ->willReturn($user);
        $this->jwtManager->method('create')->willReturn('jwt-token-123');

        $result = $this->service->getImportToken();

        $this->assertSame('jwt-token-123', $result);
    }

    public function testGetImportTokenThrowsWhenAdminNotFound(): void
    {
        $this->userRepository->method('findOneBy')
            ->with(['username' => 'admin'])
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Admin user not found');

        $this->service->getImportToken();
    }

    public function testGetImportTokenUsesCorrectAdminUsername(): void
    {
        $user = $this->createStub(User::class);

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'admin'])
            ->willReturn($user);

        $this->jwtManager->method('create')->willReturn('token');

        $service = new ImportTokenService($this->jwtManager, $this->userRepository);
        $service->getImportToken();
    }

    // --- validateToken ---

    public function testValidateTokenReturnsTrueForValidToken(): void
    {
        $this->jwtManager->method('parse')->willReturn(['sub' => 'admin']);

        $result = $this->service->validateToken('valid-jwt-token');

        $this->assertTrue($result);
    }

    public function testValidateTokenReturnsFalseForInvalidToken(): void
    {
        $this->jwtManager->method('parse')->willThrowException(new \Exception('Invalid token'));

        $result = $this->service->validateToken('invalid-token');

        $this->assertFalse($result);
    }

    public function testValidateTokenReturnsFalseForEmptyToken(): void
    {
        $this->jwtManager->method('parse')->willThrowException(new \Exception('Empty'));

        $result = $this->service->validateToken('');

        $this->assertFalse($result);
    }
}
