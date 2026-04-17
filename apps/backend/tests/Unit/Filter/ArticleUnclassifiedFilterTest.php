<?php

declare(strict_types=1);

namespace App\Tests\Unit\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Filter\ArticleUnclassifiedFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ArticleUnclassifiedFilterTest extends TestCase
{
    public function testExposesUnclassifiedParameterInOpenApiDescription(): void
    {
        $filter = new ArticleUnclassifiedFilter();
        $description = $filter->getDescription(\App\Entity\Article::class);

        self::assertArrayHasKey('unclassified', $description);
        self::assertSame('bool', $description['unclassified']['type']);
        self::assertFalse($description['unclassified']['required']);
    }

    public function testIgnoresEmptyOrFalsyValues(): void
    {
        $filter = new ArticleUnclassifiedFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        $gen = $this->createMock(QueryNameGeneratorInterface::class);

        $ref = new \ReflectionMethod(ArticleUnclassifiedFilter::class, 'filterProperty');
        foreach (['0', 'false', '', 'no', null] as $falsy) {
            $ref->invoke($filter, 'unclassified', $falsy, $qb, $gen, \App\Entity\Article::class);
        }
    }

    public function testIgnoresNonUnclassifiedProperty(): void
    {
        $filter = new ArticleUnclassifiedFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        $gen = $this->createMock(QueryNameGeneratorInterface::class);

        $ref = new \ReflectionMethod(ArticleUnclassifiedFilter::class, 'filterProperty');
        $ref->invoke($filter, 'someOtherProperty', '1', $qb, $gen, \App\Entity\Article::class);
    }

    public function testAppliesNotExistsOnTruthyValue(): void
    {
        $filter = new ArticleUnclassifiedFilter();

        $em = $this->createMock(EntityManagerInterface::class);
        $subBuilder = $this->createMock(QueryBuilder::class);
        $subBuilder->method('select')->willReturnSelf();
        $subBuilder->method('from')->willReturnSelf();
        $subBuilder->method('innerJoin')->willReturnSelf();
        $subBuilder->method('where')->willReturnSelf();
        $subBuilder->method('getDQL')->willReturn('SELECT 1 FROM stub');
        $em->method('createQueryBuilder')->willReturn($subBuilder);

        $captured = null;
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootAliases')->willReturn(['a']);
        $qb->method('getEntityManager')->willReturn($em);
        $qb->expects(self::once())
            ->method('andWhere')
            ->willReturnCallback(function (string $expr) use ($qb, &$captured): QueryBuilder {
                $captured = $expr;

                return $qb;
            });

        $gen = $this->createMock(QueryNameGeneratorInterface::class);
        $gen->method('generateJoinAlias')->willReturnOnConsecutiveCalls('sub_a', 'sub_t');

        $ref = new \ReflectionMethod(ArticleUnclassifiedFilter::class, 'filterProperty');
        $ref->invoke($filter, 'unclassified', '1', $qb, $gen, \App\Entity\Article::class);

        self::assertNotNull($captured);
        self::assertStringContainsString('NOT EXISTS', $captured);
    }
}
