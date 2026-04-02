<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ArchiveReason;
use PHPUnit\Framework\TestCase;

class ArchiveReasonTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ArchiveReason::cases();
        $this->assertCount(7, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('old_content', ArchiveReason::OLD_CONTENT->value);
        $this->assertSame('outdated_info', ArchiveReason::OUTDATED_INFO->value);
        $this->assertSame('legal_request', ArchiveReason::LEGAL_REQUEST->value);
        $this->assertSame('duplicate', ArchiveReason::DUPLICATE->value);
        $this->assertSame('low_quality', ArchiveReason::LOW_QUALITY->value);
        $this->assertSame('policy_violation', ArchiveReason::POLICY_VIOLATION->value);
        $this->assertSame('manual', ArchiveReason::MANUAL->value);
    }

    public function testLabels(): void
    {
        $this->assertSame('Conținut vechi (4+ ani)', ArchiveReason::OLD_CONTENT->label());
        $this->assertSame('Informații depășite', ArchiveReason::OUTDATED_INFO->label());
        $this->assertSame('Cerere legală/GDPR', ArchiveReason::LEGAL_REQUEST->label());
        $this->assertSame('Conținut duplicat', ArchiveReason::DUPLICATE->label());
        $this->assertSame('Calitate scăzută', ArchiveReason::LOW_QUALITY->label());
        $this->assertSame('Încălcare politică editorială', ArchiveReason::POLICY_VIOLATION->label());
        $this->assertSame('Decizie manuală editor', ArchiveReason::MANUAL->label());
    }

    public function testDescriptions(): void
    {
        $this->assertStringContainsString('4 ani', ArchiveReason::OLD_CONTENT->description());
        $this->assertStringContainsString('actuale', ArchiveReason::OUTDATED_INFO->description());
        $this->assertStringContainsString('GDPR', ArchiveReason::LEGAL_REQUEST->description());
        $this->assertStringContainsString('duplicat', ArchiveReason::DUPLICATE->description());
        $this->assertStringContainsString('standardelor', ArchiveReason::LOW_QUALITY->description());
        $this->assertStringContainsString('politicile', ArchiveReason::POLICY_VIOLATION->description());
        $this->assertStringContainsString('manual', ArchiveReason::MANUAL->description());
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(ArchiveReason::MANUAL, ArchiveReason::from('manual'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ArchiveReason::from('invalid');
    }
}
