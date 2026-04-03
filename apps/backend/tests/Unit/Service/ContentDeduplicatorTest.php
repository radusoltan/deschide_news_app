<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\DuplicateCheckResult;
use App\Entity\Article;
use App\Entity\PressRelease;
use App\Enum\SourceType;
use App\Repository\PressReleaseRepository;
use App\Service\ContentDeduplicator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContentDeduplicatorTest extends TestCase
{
    private PressReleaseRepository $prRepo;
    private EntityManagerInterface $em;
    private ContentDeduplicator $deduplicator;

    protected function setUp(): void
    {
        $this->prRepo = $this->createMock(PressReleaseRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->deduplicator = new ContentDeduplicator($this->prRepo, $this->em);
    }

    #[Test]
    public function notDuplicateWhenNothingFound(): void
    {
        $this->prRepo->method('findByContentHash')->willReturn(null);

        $articleRepo = $this->createMock(EntityRepository::class);
        $articleRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(Article::class)->willReturn($articleRepo);

        $result = $this->deduplicator->isDuplicate('abc123');

        $this->assertFalse($result->isDuplicate);
        $this->assertNull($result->existingEntityType);
    }

    #[Test]
    public function duplicateWhenPressReleaseMatches(): void
    {
        $pr = $this->createMock(PressRelease::class);
        $pr->method('getId')->willReturn(42);
        $pr->method('getSourceType')->willReturn(SourceType::EMAIL);

        $this->prRepo->method('findByContentHash')->willReturn($pr);

        $result = $this->deduplicator->isDuplicate('abc123');

        $this->assertTrue($result->isDuplicate);
        $this->assertSame('PressRelease', $result->existingEntityType);
        $this->assertSame(42, $result->existingEntityId);
        $this->assertSame(SourceType::EMAIL, $result->existingSourceType);
    }

    #[Test]
    public function duplicateWhenArticleMatches(): void
    {
        $this->prRepo->method('findByContentHash')->willReturn(null);

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(99);

        $articleRepo = $this->createMock(EntityRepository::class);
        $articleRepo->method('findOneBy')->with(['contentHash' => 'hash456'])->willReturn($article);
        $this->em->method('getRepository')->with(Article::class)->willReturn($articleRepo);

        $result = $this->deduplicator->isDuplicate('hash456');

        $this->assertTrue($result->isDuplicate);
        $this->assertSame('Article', $result->existingEntityType);
        $this->assertSame(99, $result->existingEntityId);
    }

    #[Test]
    public function pressReleaseCheckHasPriority(): void
    {
        // If both PressRelease and Article match, PressRelease wins (checked first)
        $pr = $this->createMock(PressRelease::class);
        $pr->method('getId')->willReturn(10);
        $pr->method('getSourceType')->willReturn(SourceType::SCRAPE);

        $this->prRepo->method('findByContentHash')->willReturn($pr);
        // Article repo should NOT be called
        $this->em->expects($this->never())->method('getRepository');

        $result = $this->deduplicator->isDuplicate('hash789');

        $this->assertTrue($result->isDuplicate);
        $this->assertSame('PressRelease', $result->existingEntityType);
    }
}
