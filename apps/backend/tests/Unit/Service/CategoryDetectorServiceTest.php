<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CategoryDetectorService;
use App\Repository\CategoryRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CategoryDetectorServiceTest extends TestCase
{
    private CategoryDetectorService $detector;

    protected function setUp(): void
    {
        $repo = $this->createMock(CategoryRepository::class);
        $this->detector = new CategoryDetectorService($repo);
    }

    #[Test]
    #[DataProvider('categoryProvider')]
    public function detectSlugReturnsCorrectCategory(string $text, string $expectedSlug): void
    {
        $this->assertSame($expectedSlug, $this->detector->detectSlug($text));
    }

    public static function categoryProvider(): array
    {
        return [
            'economic keyword' => ['Situația economică a țării', 'economie'],
            'budget keyword' => ['Bugetul de stat pentru 2026', 'economie'],
            'political keyword' => ['Alegeri parlamentare anticipate', 'politica'],
            'government keyword' => ['Guvernul a adoptat noi măsuri', 'politica'],
            'justice keyword' => ['Procuratura Generală a deschis dosar penal', 'justitie'],
            'health keyword' => ['Noi spitale în capitală', 'sanatate'],
            'education keyword' => ['Universitatea Tehnică anunță', 'educatie'],
            'sport keyword' => ['Campionatul național de fotbal', 'sport'],
            'culture keyword' => ['Festival internațional de film', 'cultura'],
            'environment keyword' => ['Poluarea aerului crește', 'mediu'],
            'external keyword' => ['Ambasadorul NATO a vizitat', 'externe'],
            'fallback to societate' => ['O zi obișnuită în oraș', 'societate'],
            'empty string' => ['', 'societate'],
        ];
    }

    #[Test]
    public function firstMatchWins(): void
    {
        // "economie" keywords appear before "politica" so it should match first
        $slug = $this->detector->detectSlug('investiții economice și politice');
        $this->assertSame('economie', $slug);
    }
}
