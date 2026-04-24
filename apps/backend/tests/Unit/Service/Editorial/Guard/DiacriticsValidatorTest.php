<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Service\Editorial\Guard\DiacriticsValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for {@see DiacriticsValidator} (Sprint 55 T55.6).
 */
class DiacriticsValidatorTest extends TestCase
{
    private DiacriticsValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DiacriticsValidator();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->assertSame([], $this->validator->validate(''));
        $this->assertTrue($this->validator->isValid(''));
    }

    public function testCorrectDiacriticsPass(): void
    {
        $text = 'Chișinău și Bălți au votat. Țara a ales președintele.';

        $this->assertSame([], $this->validator->validate($text));
        $this->assertTrue($this->validator->isValid($text));
    }

    public function testCedillaLowercaseSDetected(): void
    {
        $text = 'Text cu cedilă greşită.';

        $violations = $this->validator->validate($text);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString("Cedilla 'ş'", $violations[0]);
    }

    public function testCedillaUppercaseSDetected(): void
    {
        $text = 'Ştire de ultimă oră.';

        $violations = $this->validator->validate($text);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString("Cedilla 'Ş'", $violations[0]);
    }

    public function testCedillaLowercaseTDetected(): void
    {
        $text = 'Ţara aceasta.';

        $violations = $this->validator->validate($text);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString("Cedilla 'Ţ'", $violations[0]);
    }

    public function testCedillaUppercaseTDetected(): void
    {
        $text = 'Locuieşte în ţara asta.';

        $violations = $this->validator->validate($text);
        $this->assertCount(2, $violations);
    }

    public function testMultipleCedillasAllReported(): void
    {
        $text = 'Ştirea aceasta despre ţara ş-a făcut înconjurul.';

        $violations = $this->validator->validate($text);
        $this->assertGreaterThanOrEqual(3, \count($violations));
    }

    public function testViolationIncludesCharOffset(): void
    {
        $text = 'Un text scurt cu ş cedilă.';

        $violations = $this->validator->validate($text);
        $this->assertCount(1, $violations);
        // 'ş' is at character offset 17 ('Un text scurt cu ' = 17 chars).
        $this->assertStringContainsString('offset 17', $violations[0]);
    }

    public function testMixedCleanAndDirtyText(): void
    {
        // Expected cedilla matches: `Greşit` (ş), `ş`, `şi` (ş), `ţ` = 4.
        $text = 'Corect: ș și ț. Greşit: ş şi ţ.';

        $violations = $this->validator->validate($text);
        $this->assertCount(4, $violations);
    }

    public function testIsValidShortcut(): void
    {
        $this->assertTrue($this->validator->isValid('Chișinău'));
        $this->assertFalse($this->validator->isValid('Chişinău'));
    }
}
