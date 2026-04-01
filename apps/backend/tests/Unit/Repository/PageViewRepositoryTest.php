<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\PageView;
use App\Repository\PageViewRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PageViewRepository.
 *
 * Tests pure-logic methods that do not require database access.
 * Query-based methods are covered in integration tests.
 */
class PageViewRepositoryTest extends TestCase
{
    private PageViewRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(PageView::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new PageViewRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PageViewRepository::class, $this->repository);
    }

    #[Test]
    public function getCompletionRateByArticleAndDateAlwaysReturnsNull(): void
    {
        $date = new DateTime('2026-01-15');
        $result = $this->repository->getCompletionRateByArticleAndDate(1, $date);

        $this->assertNull($result);
    }

    #[Test]
    public function getCompletionRateByArticleAndDateReturnsNullForAnyInput(): void
    {
        $result1 = $this->repository->getCompletionRateByArticleAndDate(0, new DateTime());
        $result2 = $this->repository->getCompletionRateByArticleAndDate(999999, new DateTime('2020-01-01'));

        $this->assertNull($result1);
        $this->assertNull($result2);
    }
}
