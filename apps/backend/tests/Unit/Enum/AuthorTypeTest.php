<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\AuthorType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuthorType enum.
 *
 * Tests all enum values, label() method, and ::from() / ::tryFrom() behavior.
 */
class AuthorTypeTest extends TestCase
{
    #[Test]
    public function itHasExactlyFourCases(): void
    {
        $cases = AuthorType::cases();

        $this->assertCount(4, $cases);
    }

    #[Test]
    public function itHasJournalistCase(): void
    {
        $this->assertSame('journalist', AuthorType::JOURNALIST->value);
    }

    #[Test]
    public function itHasAgencyCase(): void
    {
        $this->assertSame('agency', AuthorType::AGENCY->value);
    }

    #[Test]
    public function itHasEditorialistCase(): void
    {
        $this->assertSame('editorialist', AuthorType::EDITORIALIST->value);
    }

    #[Test]
    public function itHasPressOfficeCase(): void
    {
        $this->assertSame('press_office', AuthorType::PRESS_OFFICE->value);
    }

    #[Test]
    public function labelReturnsCorrectRomanianForJournalist(): void
    {
        $this->assertSame('Jurnalist', AuthorType::JOURNALIST->label());
    }

    #[Test]
    public function labelReturnsCorrectRomanianForEditorialist(): void
    {
        $this->assertSame('Editorialist', AuthorType::EDITORIALIST->label());
    }

    #[Test]
    public function labelReturnsCorrectRomanianForAgency(): void
    {
        $this->assertSame('Agenție de știri', AuthorType::AGENCY->label());
    }

    #[Test]
    public function labelReturnsCorrectRomanianForPressOffice(): void
    {
        $this->assertSame('Oficiu de presă', AuthorType::PRESS_OFFICE->label());
    }

    #[Test]
    #[DataProvider('validStringProvider')]
    public function fromReturnsCorrectCaseForValidStrings(string $value, AuthorType $expected): void
    {
        $this->assertSame($expected, AuthorType::from($value));
    }

    public static function validStringProvider(): array
    {
        return [
            'journalist' => ['journalist', AuthorType::JOURNALIST],
            'editorialist' => ['editorialist', AuthorType::EDITORIALIST],
            'agency' => ['agency', AuthorType::AGENCY],
            'press_office' => ['press_office', AuthorType::PRESS_OFFICE],
        ];
    }

    #[Test]
    public function fromThrowsValueErrorForInvalidString(): void
    {
        $this->expectException(\ValueError::class);

        AuthorType::from('invalid');
    }

    #[Test]
    public function fromThrowsValueErrorForEmptyString(): void
    {
        $this->expectException(\ValueError::class);

        AuthorType::from('');
    }

    #[Test]
    #[DataProvider('invalidStringProvider')]
    public function tryFromReturnsNullForInvalidStrings(string $value): void
    {
        $this->assertNull(AuthorType::tryFrom($value));
    }

    public static function invalidStringProvider(): array
    {
        return [
            'empty string' => [''],
            'invalid value' => ['invalid'],
            'uppercase JOURNALIST' => ['JOURNALIST'],
            'camelCase pressOffice' => ['pressOffice'],
            'with spaces' => ['press office'],
        ];
    }

    #[Test]
    #[DataProvider('validStringProvider')]
    public function tryFromReturnsCorrectCaseForValidStrings(string $value, AuthorType $expected): void
    {
        $this->assertSame($expected, AuthorType::tryFrom($value));
    }

    #[Test]
    public function allCasesHaveNonEmptyLabels(): void
    {
        foreach (AuthorType::cases() as $case) {
            $label = $case->label();
            $this->assertNotEmpty($label, "Label for {$case->value} should not be empty");
            $this->assertIsString($label);
        }
    }
}
