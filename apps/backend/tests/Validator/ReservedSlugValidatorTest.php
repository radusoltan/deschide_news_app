<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\ReservedSlug;
use App\Validator\ReservedSlugValidator;
use Generator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Test case for ReservedSlugValidator.
 *
 * Tests that the validator correctly identifies and rejects reserved slugs
 * as defined in url-structure-APPROVED.md
 */
class ReservedSlugValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * Test that all 19 reserved slugs are correctly identified as invalid.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('reservedSlugsProvider')]
    public function testReservedSlugIsInvalid(string $reservedSlug): void
    {
        $constraint = new ReservedSlug();

        $this->validator->validate($reservedSlug, $constraint);

        $this->buildViolation('The slug "{{ slug }}" is reserved for system pages and cannot be used.')
            ->setParameter('{{ slug }}', $reservedSlug)
            ->assertRaised();
    }

    /**
     * Test that reserved slugs are case-insensitive.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('caseInsensitiveSlugsProvider')]
    public function testReservedSlugIsCaseInsensitive(string $slug): void
    {
        $constraint = new ReservedSlug();

        $this->validator->validate($slug, $constraint);

        $this->buildViolation('The slug "{{ slug }}" is reserved for system pages and cannot be used.')
            ->setParameter('{{ slug }}', $slug)
            ->assertRaised();
    }

    /**
     * Test that non-reserved slugs pass validation.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validSlugsProvider')]
    public function testNonReservedSlugIsValid(string $slug): void
    {
        $constraint = new ReservedSlug();

        $this->validator->validate($slug, $constraint);

        $this->assertNoViolation();
    }

    /**
     * Test that null values are allowed (handled by NotBlank if needed).
     */
    public function testNullValueIsValid(): void
    {
        $constraint = new ReservedSlug();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    /**
     * Test that empty string is allowed (handled by NotBlank if needed).
     */
    public function testEmptyStringIsValid(): void
    {
        $constraint = new ReservedSlug();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    /**
     * Test that whitespace around slug is trimmed before validation.
     */
    public function testSlugWithWhitespaceIsTrimmed(): void
    {
        $constraint = new ReservedSlug();

        // "  all  " should be recognized as reserved after trimming
        $this->validator->validate('  all  ', $constraint);

        $this->buildViolation('The slug "{{ slug }}" is reserved for system pages and cannot be used.')
            ->setParameter('{{ slug }}', '  all  ')
            ->assertRaised();
    }

    /**
     * Provider for all 19 reserved slugs.
     */
    public static function reservedSlugsProvider(): Generator
    {
        yield 'all' => ['all'];
        yield 'search' => ['search'];
        yield 'trending' => ['trending'];
        yield 'archive' => ['archive'];
        yield 'about' => ['about'];
        yield 'contact' => ['contact'];
        yield 'author' => ['author'];
        yield 'authors' => ['authors'];
        yield 'admin' => ['admin'];
        yield 'login' => ['login'];
        yield 'api' => ['api'];
        yield 'sitemap' => ['sitemap'];
        yield 'robots' => ['robots'];
        yield 'feed' => ['feed'];
        yield 'rss' => ['rss'];
        yield 'privacy' => ['privacy'];
        yield 'terms' => ['terms'];
    }

    /**
     * Provider for case-insensitive slug testing.
     */
    public static function caseInsensitiveSlugsProvider(): Generator
    {
        yield 'SEARCH uppercase' => ['SEARCH'];
        yield 'Search capitalized' => ['Search'];
        yield 'SeArCh mixed case' => ['SeArCh'];
        yield 'ADMIN uppercase' => ['ADMIN'];
        yield 'Admin capitalized' => ['Admin'];
        yield 'aDmIn mixed case' => ['aDmIn'];
        yield 'ALL uppercase' => ['ALL'];
        yield 'All capitalized' => ['All'];
    }

    /**
     * Provider for valid (non-reserved) slugs.
     */
    public static function validSlugsProvider(): Generator
    {
        yield 'politica' => ['politica'];
        yield 'economie' => ['economie'];
        yield 'sport' => ['sport'];
        yield 'cultura' => ['cultura'];
        yield 'tehnologie' => ['tehnologie'];
        yield 'news' => ['news'];
        yield 'politics' => ['politics'];
        yield 'economy' => ['economy'];
        yield 'новости' => ['новости'];
        yield 'политика' => ['политика'];
        yield 'my-category' => ['my-category'];
        yield 'test-slug-123' => ['test-slug-123'];
    }

    /**
     * Test that the reserved slugs list contains exactly 18 items.
     */
    public function testReservedSlugsListHas18Items(): void
    {
        $this->assertCount(18, ReservedSlug::RESERVED_SLUGS, 'Reserved slugs list should contain exactly 18 items (including s for short links)');
    }

    /**
     * Test that all reserved slugs are lowercase.
     */
    public function testAllReservedSlugsAreLowercase(): void
    {
        foreach (ReservedSlug::RESERVED_SLUGS as $slug) {
            $this->assertEquals(
                strtolower($slug),
                $slug,
                "Reserved slug '{$slug}' should be lowercase"
            );
        }
    }

    /**
     * Test that there are no duplicate slugs in the reserved list.
     */
    public function testNoDuplicateReservedSlugs(): void
    {
        $uniqueSlugs = array_unique(ReservedSlug::RESERVED_SLUGS);
        $this->assertCount(
            \count(ReservedSlug::RESERVED_SLUGS),
            $uniqueSlugs,
            'Reserved slugs list should not contain duplicates'
        );
    }

    protected function createValidator(): ReservedSlugValidator
    {
        return new ReservedSlugValidator();
    }
}
