<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Writer;

use App\Entity\Category;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\EditorialAlignment;
use App\Repository\CategoryRepository;
use App\Service\Editorial\Writer\SignalCategoryResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for {@see SignalCategoryResolver} (Sprint 55 T55.3).
 */
class SignalCategoryResolverTest extends TestCase
{
    /** @var CategoryRepository&MockObject */
    private CategoryRepository $categoryRepository;
    private SignalCategoryResolver $resolver;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->resolver = new SignalCategoryResolver($this->categoryRepository);
    }

    public function testMdGovernmentAlignmentMapsToPolitica(): void
    {
        $category = $this->mockCategory('politica');
        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'politica'])
            ->willReturn($category);

        $signal = $this->mockSignal(EditorialAlignment::MD_GOVERNMENT, 'Guvernul RM adoptă programul de reforme');

        $this->assertSame($category, $this->resolver->resolve($signal));
    }

    public function testMdIndependentProEuAlignmentMapsToPolitica(): void
    {
        $category = $this->mockCategory('politica');
        $this->categoryRepository->method('findOneBy')->willReturn($category);

        $signal = $this->mockSignal(EditorialAlignment::MD_INDEPENDENT_PRO_EU, 'Analiza săptămânii la Chișinău');

        $this->assertSame($category, $this->resolver->resolve($signal));
    }

    public function testMdInvestigativeAlignmentMapsToPolitica(): void
    {
        $category = $this->mockCategory('politica');
        $this->categoryRepository->method('findOneBy')->willReturn($category);

        $signal = $this->mockSignal(EditorialAlignment::MD_INVESTIGATIVE, 'Dezvăluire despre un fost demnitar');

        $this->assertSame($category, $this->resolver->resolve($signal));
    }

    public function testWireNeutralAlignmentMapsToExterne(): void
    {
        $category = $this->mockCategory('externe');
        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'externe'])
            ->willReturn($category);

        $signal = $this->mockSignal(EditorialAlignment::WIRE_NEUTRAL, 'Reuters: UE lansează pachet de asistență');

        $this->assertSame($category, $this->resolver->resolve($signal));
    }

    public function testKremlinAlignedMapsToExterne(): void
    {
        $category = $this->mockCategory('externe');
        $this->categoryRepository->method('findOneBy')->willReturn($category);

        $signal = $this->mockSignal(EditorialAlignment::KREMLIN_ALIGNED, 'TASS: ministerul apărării anunță...');

        $this->assertSame($category, $this->resolver->resolve($signal));
    }

    public function testUkrainianStateMapsToExterne(): void
    {
        $category = $this->mockCategory('externe');
        $this->categoryRepository->method('findOneBy')->willReturn($category);

        $signal = $this->mockSignal(EditorialAlignment::UKRAINIAN_STATE, 'Ukrinform: bilanț oficial');

        $this->assertSame($category, $this->resolver->resolve($signal));
    }

    public function testRoMainstreamMapsToRomania(): void
    {
        $category = $this->mockCategory('romania');
        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'romania'])
            ->willReturn($category);

        $signal = $this->mockSignal(EditorialAlignment::RO_MAINSTREAM, 'Digi24: premierul semnează acordul');

        $this->assertSame($category, $this->resolver->resolve($signal));
    }

    public function testTransnistriaKeywordOverridesWireAlignment(): void
    {
        // Wire signal about Transnistria must land in politica, not externe.
        $politica = $this->mockCategory('politica');
        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'politica'])
            ->willReturn($politica);

        $signal = $this->mockSignal(
            EditorialAlignment::WIRE_NEUTRAL,
            'Reuters: tensiuni noi în Transnistria după declarațiile Tiraspolului',
        );

        $this->assertSame($politica, $this->resolver->resolve($signal));
    }

    public function testGagauziaKeywordWithDiacriticsOverridesWireAlignment(): void
    {
        $politica = $this->mockCategory('politica');
        $this->categoryRepository->method('findOneBy')->willReturn($politica);

        $signal = $this->mockSignal(
            EditorialAlignment::WESTERN_MAINSTREAM,
            'Adunarea Populară a Găgăuziei adoptă o rezoluție',
        );

        $this->assertSame($politica, $this->resolver->resolve($signal));
    }

    public function testCecKeywordOverridesAlignment(): void
    {
        $politica = $this->mockCategory('politica');
        $this->categoryRepository->method('findOneBy')->willReturn($politica);

        $signal = $this->mockSignal(
            EditorialAlignment::WIRE_NEUTRAL,
            'CEC publică rezultatele intermediare',
        );

        $this->assertSame($politica, $this->resolver->resolve($signal));
    }

    public function testFallbackToPoliticaWhenPrimarySlugMissing(): void
    {
        // First lookup (romania) returns null, fallback to politica succeeds.
        $politica = $this->mockCategory('politica');
        $this->categoryRepository
            ->method('findOneBy')
            ->willReturnMap([
                [['slug' => 'romania'], null, null],
                [['slug' => 'politica'], null, $politica],
            ]);

        $signal = $this->mockSignal(EditorialAlignment::RO_MAINSTREAM, 'Bucharest update');

        $this->assertSame($politica, $this->resolver->resolve($signal));
    }

    public function testReturnsNullWhenEvenPoliticaFallbackMissing(): void
    {
        // Ultra-degraded DB where even politica is missing — caller must handle null.
        $this->categoryRepository->method('findOneBy')->willReturn(null);

        $signal = $this->mockSignal(EditorialAlignment::MD_GOVERNMENT, 'Comunicat guvernamental');

        $this->assertNull($this->resolver->resolve($signal));
    }

    private function mockCategory(string $slug): Category
    {
        $category = $this->createMock(Category::class);
        $category->method('getSlug')->willReturn($slug);

        return $category;
    }

    private function mockSignal(EditorialAlignment $alignment, string $title, ?string $summary = null): SourceSignal
    {
        $verifiedSource = $this->createMock(VerifiedSource::class);
        $verifiedSource->method('getEditorialAlignment')->willReturn($alignment);

        $signal = $this->createMock(SourceSignal::class);
        $signal->method('getVerifiedSource')->willReturn($verifiedSource);
        $signal->method('getTitle')->willReturn($title);
        $signal->method('getRawSummary')->willReturn($summary);

        return $signal;
    }
}
