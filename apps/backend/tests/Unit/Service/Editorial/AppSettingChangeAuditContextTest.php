<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Service\Editorial\AppSettingChangeAuditContext;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the T57.P3 transient audit-context carrier (ADR-024 D5).
 *
 * Contract pinned here:
 *   - Freshly constructed service returns null reason.
 *   - clear() returns it to the null state after a set().
 *   - set(null) is valid (CLI may legitimately propagate an absent reason for
 *     a non-critical flip) and reads back as null.
 */
class AppSettingChangeAuditContextTest extends TestCase
{
    public function testFreshInstanceHasNoReason(): void
    {
        $context = new AppSettingChangeAuditContext();

        $this->assertNull($context->getReason());
    }

    public function testSetAndGetReasonRoundTrip(): void
    {
        $context = new AppSettingChangeAuditContext();
        $context->setReason('Emergency halt during incident INC-42');

        $this->assertSame('Emergency halt during incident INC-42', $context->getReason());
    }

    public function testClearResetsReasonToNull(): void
    {
        $context = new AppSettingChangeAuditContext();
        $context->setReason('some reason');

        $context->clear();

        $this->assertNull($context->getReason());
    }

    public function testSetNullReasonIsIdempotent(): void
    {
        $context = new AppSettingChangeAuditContext();
        $context->setReason(null);

        $this->assertNull($context->getReason());
    }
}
