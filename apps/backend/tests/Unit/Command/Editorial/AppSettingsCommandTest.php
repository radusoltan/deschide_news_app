<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Editorial;

use App\Command\Editorial\AppSettingsGetCommand;
use App\Command\Editorial\AppSettingsListCommand;
use App\Command\Editorial\AppSettingsSetCommand;
use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the Sprint 56 T56.02.1 AppSettings CLI commands.
 *
 * Strategy: mock {@see AppSettingRepository} and drive each command through
 * {@see CommandTester}. Covers the happy path of each command plus the
 * operator-facing error paths (unknown key on get, invalid type coercion on
 * set) because that is where 3 AM runbook usage actually slips.
 */
class AppSettingsCommandTest extends TestCase
{
    private AppSettingRepository&MockObject $repo;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(AppSettingRepository::class);
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

        $tester = new CommandTester(new AppSettingsSetCommand($this->repo));
        $exit = $tester->execute([
            'key' => 'editorial.emergency_halt',
            'value' => 'yes', // filter_var → true → 'true' canonical string
            '--type' => 'bool',
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

        $tester = new CommandTester(new AppSettingsSetCommand($this->repo));
        $exit = $tester->execute([
            'key' => 'editorial.emergency_halt',
            'value' => 'maybe',
            '--type' => 'bool',
        ]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString("Cannot coerce 'maybe' to bool", $tester->getDisplay());
    }

    public function testSetCommandFailsOnInvalidJson(): void
    {
        $this->repo->expects($this->never())->method('set');

        $tester = new CommandTester(new AppSettingsSetCommand($this->repo));
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

        $tester = new CommandTester(new AppSettingsSetCommand($this->repo));
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

        $tester = new CommandTester(new AppSettingsSetCommand($this->repo));
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
}
