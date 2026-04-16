<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\Import\LegacyCategoryMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LegacyCategoryMapperTest extends TestCase
{
    private CategoryRepository&MockObject $categoryRepo;
    private LegacyCategoryMapper $mapper;

    protected function setUp(): void
    {
        $this->categoryRepo = $this->createMock(CategoryRepository::class);

        $this->mapper = new LegacyCategoryMapper(
            $this->categoryRepo,
            new NullLogger(),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function directMappingProvider(): iterable
    {
        yield 'politic → politica' => ['politic', 'politica'];
        yield 'social → societate' => ['social', 'societate'];
        yield 'economic → economie' => ['economic', 'economie'];
        yield 'editorial → editoriale' => ['editorial', 'editoriale'];
        yield 'externe → externe' => ['externe', 'externe'];
        yield 'romania → romania' => ['romania', 'romania'];
        yield 'cultura → cultura' => ['cultura', 'cultura'];
        yield 'opinii → opinii' => ['opinii', 'opinii'];
        yield 'sport → sport' => ['sport', 'sport'];
        yield 'advertorial → advertorial' => ['advertorial', 'advertorial'];
        yield 'anti-fake → anti-fake' => ['anti-fake', 'anti-fake'];
    }

    #[DataProvider('directMappingProvider')]
    public function testDirectMapping(string $csvSlug, string $expectedDbSlug): void
    {
        $category = $this->createStub(Category::class);
        $this->categoryRepo->method('findOneBy')
            ->with(['slug' => $expectedDbSlug])
            ->willReturn($category);

        $result = $this->mapper->map($csvSlug);

        $this->assertSame($category, $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function absorbedMappingProvider(): iterable
    {
        yield 'alegeri absorbed into politica' => ['alegeri', 'politica'];
        yield 'transnistria absorbed into politica' => ['transnistria', 'politica'];
        yield 'dialog-deschis absorbed into opinii' => ['dialog-deschis', 'opinii'];
    }

    #[DataProvider('absorbedMappingProvider')]
    public function testAbsorbedMapping(string $csvSlug, string $expectedDbSlug): void
    {
        $category = $this->createStub(Category::class);
        $this->categoryRepo->method('findOneBy')
            ->with(['slug' => $expectedDbSlug])
            ->willReturn($category);

        $result = $this->mapper->map($csvSlug);

        $this->assertSame($category, $result);
    }

    public function testEmptySlugReturnsNull(): void
    {
        $this->assertNull($this->mapper->map(''));
        $this->assertNull($this->mapper->map('   '));
    }

    public function testUnknownSlugReturnsNull(): void
    {
        $this->assertNull($this->mapper->map('non-existent-slug'));
    }

    public function testCaseInsensitiveMapping(): void
    {
        $category = $this->createStub(Category::class);
        $this->categoryRepo->method('findOneBy')
            ->with(['slug' => 'politica'])
            ->willReturn($category);

        $this->assertSame($category, $this->mapper->map('POLITIC'));
        $this->assertSame($category, $this->mapper->map('Politic'));
    }

    public function testCachingPreventsRepeatedDbQueries(): void
    {
        $category = $this->createStub(Category::class);
        $this->categoryRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'politica'])
            ->willReturn($category);

        $this->mapper->map('politic');
        $this->mapper->map('politic');
    }

    public function testClearCacheResetsInternalState(): void
    {
        $category = $this->createStub(Category::class);
        $this->categoryRepo->expects($this->exactly(2))
            ->method('findOneBy')
            ->with(['slug' => 'politica'])
            ->willReturn($category);

        $this->mapper->map('politic');
        $this->mapper->clearCache();
        $this->mapper->map('politic');
    }
}
