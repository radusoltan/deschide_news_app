<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Editorial;

use App\Command\Editorial\AppSettingsGetCommand;
use App\Command\Editorial\AppSettingsListCommand;
use App\Command\Editorial\AppSettingsSetCommand;
use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\AppSettingChangeAuditContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the Sprint 56 T56.02.1 AppSettings CLI commands, extended
 * in T57.P3 (ADR-024 D5) with `--reason` enforcement and audit-context
 * propagation.
 *
 * Strategy: mock {@see AppSettingRepository} and drive each command through
 * {@see CommandTester}. Uses a real {@see AppSettingChangeAuditContext}
 * (thin data carrier, no behavior worth mocking) so assertions can pin both
 * the call to `Repository::set()` AND the reason that would reach the
 * audit listener.
 */
class AppSettingsCommandTest extends TestCase
{
    private AppSettingRepository&MockObject $repo;

    private AppSettingChangeAuditContext $auditContext;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(AppSettingRepository::class);
        $this->auditContext = new AppSettingChangeAuditContext();
    }

    private function makeSetCommand(): AppSettingsSetCommand
    {
        return new AppSettingsSetCommand($this->repo, $this->auditContext);
    }

    public function testGetCommandPrintsValueAndExitsSuccess(): void
    {
        $this->repo->method('get')
            ->with('editorial.emergency_halt')
            ->willReturn('false');

        $tester = new CommandTester(new AppSettingsGetCommand($this->repo));
        $exit = $tester->execute(['key' => 'editorial.emergency_halt']);

        $this->assertSame(Command::SUCCESS, $exit);
        // Pipe-friendly: one line, verbatim value.
        $this->assertSame("false\n", $tester->getDisplay());
    }

    public function testGetCommandFailsOnUnknownKey(): void
    {
        $this->repo->method('get')
            ->with('does.not.exist')
            ->willReturn(null);

        $tester = new CommandTester(new AppSettingsGetCommand($this->repo));
        $exit = $tester->execute(['key' => 'does.not.exist']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString("Setting 'does.not.exist' is not set.", $tester->getDisplay());
    }

    public function testSetCommandCoercesBoolAndPersists(): void
    {
        $this->repo->expects($this->once())
            ->method('set')
            ->with('editorial.emergency_halt', 'true');

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'editorial.emergency_halt',
            'value' => 'yes', // filter_var → true → 'true' canonical string
            '--type' => 'bool',
            '--reason' => 'Drill: validating emergency halt path',
        ]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString(
            "Set 'editorial.emergency_halt' = 'true' (type: bool)",
            $tester->getDisplay(),
        );
    }

    public function testSetCommandFailsOnInvalidBoolInput(): void
    {
        // Garbage input like `maybe` must not silently persist `'false'`.
        $this->repo->expects($this->never())->method('set');

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'editorial.emergency_halt',
            'value' => 'maybe',
            '--type' => 'bool',
            // Reason supplied so the critical-key gate passes — this test's
            // intent is the bool-coercion error path, not reason enforcement.
            '--reason' => 'test',
        ]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString("Cannot coerce 'maybe' to bool", $tester->getDisplay());
    }

    public function testSetCommandFailsOnInvalidJson(): void
    {
        $this->repo->expects($this->never())->method('set');

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'some.json.key',
            'value' => '{broken',
            '--type' => 'json',
        ]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('Invalid JSON:', $tester->getDisplay());
    }

    public function testSetCommandCoercesIntLiteral(): void
    {
        $this->repo->expects($this->once())
            ->method('set')
            ->with('editorial.throttle.articles_per_hour', '42');

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'editorial.throttle.articles_per_hour',
            'value' => '42',
            '--type' => 'int',
        ]);

        $this->assertSame(Command::SUCCESS, $exit);
    }

    public function testSetCommandFailsOnUnknownType(): void
    {
        $this->repo->expects($this->never())->method('set');

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'k',
            'value' => 'v',
            '--type' => 'xml',
        ]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString("Unsupported --type 'xml'", $tester->getDisplay());
    }

    public function testListCommandRendersSortedTableWithTotal(): void
    {
        $this->repo->method('findAll')->willReturn([
            new AppSetting('editorial.pipeline.enabled', 'false'),
            new AppSetting('agent.flash_writer.enabled', 'true'),
            new AppSetting('editorial.emergency_halt', 'false'),
        ]);

        $tester = new CommandTester(new AppSettingsListCommand($this->repo));
        $exit = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exit);
        // Header rendered.
        $this->assertStringContainsString('Key', $display);
        $this->assertStringContainsString('Value', $display);
        // Total line reflects all 3 settings.
        $this->assertStringContainsString('Total: 3 setting(s)', $display);
        // Sort order: agent.* before editorial.* (ASCII alphabetical).
        $agentPos = strpos($display, 'agent.flash_writer.enabled');
        $editorialPos = strpos($display, 'editorial.emergency_halt');
        $this->assertNotFalse($agentPos);
        $this->assertNotFalse($editorialPos);
        $this->assertLessThan($editorialPos, $agentPos);
    }

    public function testListCommandFiltersByPrefix(): void
    {
        $this->repo->method('findAll')->willReturn([
            new AppSetting('editorial.pipeline.enabled', 'false'),
            new AppSetting('agent.flash_writer.enabled', 'true'),
            new AppSetting('editorial.emergency_halt', 'false'),
        ]);

        $tester = new CommandTester(new AppSettingsListCommand($this->repo));
        $exit = $tester->execute(['--prefix' => 'editorial.']);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('editorial.emergency_halt', $display);
        $this->assertStringContainsString('editorial.pipeline.enabled', $display);
        $this->assertStringNotContainsString('agent.flash_writer.enabled', $display);
        $this->assertStringContainsString("Total: 2 setting(s) (prefix='editorial.')", $display);
    }

    public function testListCommandTruncatesLongValues(): void
    {
        $longValue = str_repeat('x', 200);
        $this->repo->method('findAll')->willReturn([
            new AppSetting('some.blob.key', $longValue),
        ]);

        $tester = new CommandTester(new AppSettingsListCommand($this->repo));
        $tester->execute([]);
        $display = $tester->getDisplay();

        // Output must NOT contain the full 200-char string.
        $this->assertStringNotContainsString($longValue, $display);
        // Ellipsis suffix present.
        $this->assertStringContainsString('...', $display);
    }

    // ---- T57.P3 --reason enforcement (ADR-024 D5) -------------------------

    public function testSetCommandFailsClosedOnCriticalFlipWithoutReason(): void
    {
        // Runbook contract: cannot flip a CRITICAL_KEYS entry without telling
        // us why. Repository::set() MUST NOT be called.
        $this->repo->expects($this->never())->method('set');

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'editorial.emergency_halt',
            'value' => 'true',
            '--type' => 'bool',
        ]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString(
            "Key 'editorial.emergency_halt' is in AppSetting::CRITICAL_KEYS",
            $tester->getDisplay(),
        );
        $this->assertStringContainsString('--reason is mandatory', $tester->getDisplay());
        // Context must NOT carry a stale reason after a failed attempt.
        $this->assertNull($this->auditContext->getReason());
    }

    public function testSetCommandFailsClosedOnCriticalFlipWithBlankReason(): void
    {
        // Whitespace-only reason is equivalent to "no reason" — otherwise
        // operators would game the gate with `--reason=" "`.
        $this->repo->expects($this->never())->method('set');

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'editorial.tier_overrides.flash_writer',
            'value' => '"pro"',
            '--type' => 'json',
            '--reason' => '   ',
        ]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('--reason is mandatory', $tester->getDisplay());
    }

    public function testSetCommandSucceedsOnCriticalFlipWithReasonAndPropagatesToContext(): void
    {
        // Capture the reason as it reaches the repository call, since the
        // `finally { clear() }` wipes it before the CommandTester returns.
        $seenReason = null;
        $this->repo->expects($this->once())
            ->method('set')
            ->with('editorial.pipeline.enabled', 'true')
            ->willReturnCallback(function () use (&$seenReason): void {
                $seenReason = $this->auditContext->getReason();
            });

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'editorial.pipeline.enabled',
            'value' => 'true',
            '--type' => 'bool',
            '--reason' => 'S57 pipeline enable after verification layer landed',
        ]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertSame(
            'S57 pipeline enable after verification layer landed',
            $seenReason,
            'Audit context must carry the reason during Repository::set() so '
                . 'the listener can read it in postFlush.',
        );
        // Context must be cleared after command finishes (finally block).
        $this->assertNull($this->auditContext->getReason());
    }

    public function testSetCommandReasonIsOptionalOnNonCriticalFlip(): void
    {
        // Non-critical key + no reason: succeeds, audit context stays null.
        $seenReason = 'SENTINEL_NOT_INVOKED';
        $this->repo->expects($this->once())
            ->method('set')
            ->with('article_generation.window_hours', '48')
            ->willReturnCallback(function () use (&$seenReason): void {
                $seenReason = $this->auditContext->getReason();
            });

        $tester = new CommandTester($this->makeSetCommand());
        $exit = $tester->execute([
            'key' => 'article_generation.window_hours',
            'value' => '48',
            '--type' => 'int',
        ]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertNull($seenReason);
    }

    public function testSetCommandClearsAuditContextEvenOnRepositoryFailure(): void
    {
        // finally-block contract: a downstream repository exception must not
        // leave a reason leaking into the next flush in the same process.
        $this->repo->expects($this->once())
            ->method('set')
            ->willThrowException(new \RuntimeException('db down'));

        $tester = new CommandTester($this->makeSetCommand());

        $this->expectException(\RuntimeException::class);
        try {
            $tester->execute([
                'key' => 'editorial.emergency_halt',
                'value' => 'true',
                '--type' => 'bool',
                '--reason' => 'incident response',
            ]);
        } finally {
            // The finally in the CLI must have cleared the context before
            // re-throwing.
            $this->assertNull($this->auditContext->getReason());
        }
    }
}
