<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\Entity\ShortLink;
use App\Entity\User;
use App\Service\ShortCodeGenerator;
use App\State\ShortLinkProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ShortLinkProcessorTest extends TestCase
{
    private ShortLinkProcessor $processor;
    private EntityManagerInterface $entityManager;
    private ShortCodeGenerator $shortCodeGenerator;
    private Security $security;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->shortCodeGenerator = $this->createStub(ShortCodeGenerator::class);
        $this->security = $this->createStub(Security::class);

        $this->processor = new ShortLinkProcessor(
            $this->entityManager,
            $this->shortCodeGenerator,
            $this->security
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesShortLink(): void
    {
        $shortLink = $this->createStub(ShortLink::class);

        $this->entityManager->expects($this->once())->method('remove')->with($shortLink);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($shortLink, $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesShortLinkWithGeneratedCode(): void
    {
        $shortLink = new ShortLink();
        $shortLink->setOriginalUrl('https://example.com/article/test');

        $this->shortCodeGenerator->method('generate')->willReturn('abc123');

        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())->method('persist')->with($shortLink);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($shortLink, $operation);

        $this->assertInstanceOf(ShortLink::class, $result);
        $this->assertEquals('abc123', $result->getCode());
    }

    #[Test]
    public function itCreatesShortLinkWithCustomCode(): void
    {
        $shortLink = new ShortLink();
        $shortLink->setOriginalUrl('https://example.com/article/test');
        $shortLink->setCode('my-code');

        $this->shortCodeGenerator->method('isValidCode')
            ->with('my-code')
            ->willReturn(true);

        $this->shortCodeGenerator->method('isCodeAvailable')
            ->with('my-code')
            ->willReturn(true);

        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($shortLink, $operation);

        $this->assertInstanceOf(ShortLink::class, $result);
        $this->assertEquals('my-code', $result->getCode());
    }

    #[Test]
    public function itThrowsExceptionForInvalidCustomCode(): void
    {
        $shortLink = new ShortLink();
        $shortLink->setOriginalUrl('https://example.com');
        $shortLink->setCode('invalid code!');

        $this->shortCodeGenerator->method('isValidCode')
            ->with('invalid code!')
            ->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid short code format');

        $operation = new Post();
        $this->processor->process($shortLink, $operation);
    }

    #[Test]
    public function itThrowsConflictForDuplicateCode(): void
    {
        $shortLink = new ShortLink();
        $shortLink->setOriginalUrl('https://example.com');
        $shortLink->setCode('existing-code');

        $this->shortCodeGenerator->method('isValidCode')
            ->with('existing-code')
            ->willReturn(true);

        $this->shortCodeGenerator->method('isCodeAvailable')
            ->with('existing-code')
            ->willReturn(false);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('This short code is already in use');

        $operation = new Post();
        $this->processor->process($shortLink, $operation);
    }

    #[Test]
    public function itSetsCreatedByUserWhenAuthenticated(): void
    {
        $shortLink = new ShortLink();
        $shortLink->setOriginalUrl('https://example.com');

        $this->shortCodeGenerator->method('generate')->willReturn('gen123');

        $user = $this->createStub(User::class);
        $this->security->method('getUser')->willReturn($user);

        $operation = new Post();
        $result = $this->processor->process($shortLink, $operation);

        $this->assertSame($user, $result->getCreatedBy());
    }

    // ========================
    // Non-ShortLink Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonShortLinkData(): void
    {
        $operation = new Post();
        $result = $this->processor->process('not-a-shortlink', $operation);

        $this->assertNull($result);
    }
}
