<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Writer;

use App\Entity\Author;
use App\Enum\AuthorStatus;
use App\Enum\AuthorType;
use App\Repository\AuthorRepository;
use App\Service\Editorial\Writer\AiAuthorProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for {@see AiAuthorProvider} (Sprint 55 T55.3).
 */
class AiAuthorProviderTest extends TestCase
{
    private AuthorRepository&MockObject $authorRepository;
    private EntityManagerInterface&MockObject $em;
    private AiAuthorProvider $provider;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->provider = new AiAuthorProvider($this->authorRepository, $this->em);
    }

    public function testReturnsExistingAuthorWhenPresent(): void
    {
        $existing = $this->createMock(Author::class);
        $existing->method('getId')->willReturn(42);

        $this->authorRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'deschide-ai'])
            ->willReturn($existing);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->assertSame($existing, $this->provider->getOrCreate());
    }

    public function testCreatesAuthorWhenMissing(): void
    {
        $this->authorRepository->method('findOneBy')->willReturn(null);

        $persistedAuthor = null;
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedAuthor): void {
                $this->assertInstanceOf(Author::class, $entity);
                $persistedAuthor = $entity;
            });
        $this->em->expects($this->once())->method('flush');

        $result = $this->provider->getOrCreate();

        $this->assertInstanceOf(Author::class, $result);
        $this->assertSame($persistedAuthor, $result);
        $this->assertSame('Deschide', $result->getFirstName());
        $this->assertSame('AI', $result->getLastName());
        $this->assertSame('deschide-ai', $result->getSlug());
        $this->assertSame('ai@deschide.md', $result->getEmail());
        $this->assertSame(AuthorType::AGENCY, $result->getType());
        $this->assertSame(AuthorStatus::ACTIVE, $result->getStatus());
    }

    public function testCachesResultWithinSameInstance(): void
    {
        $existing = $this->createMock(Author::class);
        $existing->method('getId')->willReturn(10);

        // First call hits the repository, second is served from cache.
        $this->authorRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existing);

        $first = $this->provider->getOrCreate();
        $second = $this->provider->getOrCreate();

        $this->assertSame($first, $second);
    }
}
