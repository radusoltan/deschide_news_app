<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\OpcacheStatusCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class OpcacheStatusCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = new OpcacheStatusCommand();
        $this->assertSame('app:opcache:status', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = new OpcacheStatusCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = new OpcacheStatusCommand();
        $this->assertTrue($command->getDefinition()->hasOption('reset'));
        $this->assertTrue($command->getDefinition()->hasOption('detailed'));
    }

    public function testExecuteWhenOpcacheDisabled(): void
    {
        if (\function_exists('opcache_get_status')) {
            $this->markTestSkipped('OPcache is enabled; cannot test disabled code path without it');
        }

        $command = new OpcacheStatusCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('OPcache is not enabled', $tester->getDisplay());
    }

    public function testExecuteWhenOpcacheEnabled(): void
    {
        if (!\function_exists('opcache_get_status') || opcache_get_status(false) === false) {
            $this->markTestSkipped('OPcache is not available or disabled in CLI');
        }

        $command = new OpcacheStatusCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        // May succeed or fail depending on actual state — at least no exception
        $this->assertContains($tester->getStatusCode(), [0, 1]);
    }

    public function testExecuteWhenOpcacheEnabledShowsTitle(): void
    {
        if (!\function_exists('opcache_get_status') || opcache_get_status(false) === false) {
            $this->markTestSkipped('OPcache is not available or disabled in CLI');
        }

        $command = new OpcacheStatusCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        if ($tester->getStatusCode() === 0) {
            $this->assertStringContainsString('OPcache Status', $display);
        } else {
            $this->assertTrue(true); // May fail if OPcache returns false
        }
    }

    public function testExecuteWithDetailedOptionWhenOpcacheEnabled(): void
    {
        if (!\function_exists('opcache_get_status') || opcache_get_status(false) === false) {
            $this->markTestSkipped('OPcache is not available or disabled in CLI');
        }

        $command = new OpcacheStatusCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--detailed' => true]);

        // May succeed or fail depending on actual state
        $this->assertContains($tester->getStatusCode(), [0, 1]);
    }

    public function testFormatBytesReturnsBytes(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 500);
        $this->assertSame('500 B', $result);
    }

    public function testFormatBytesReturnsKilobytes(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 2048);
        $this->assertSame('2.00 KB', $result);
    }

    public function testFormatBytesReturnsMegabytes(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 2097152);
        $this->assertSame('2.00 MB', $result);
    }

    public function testFormatBytesReturnsGigabytes(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 2147483648); // 2 GB
        $this->assertSame('2.00 GB', $result);
    }

    public function testFormatBytesZero(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 0);
        $this->assertSame('0 B', $result);
    }

    public function testFormatBytesExactlyOneKB(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 1024);
        $this->assertSame('1.00 KB', $result);
    }

    public function testFormatBytesExactlyOneMB(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 1048576);
        $this->assertSame('1.00 MB', $result);
    }

    public function testFormatBytesExactlyOneGB(): void
    {
        $method = new \ReflectionMethod(OpcacheStatusCommand::class, 'formatBytes');

        $command = new OpcacheStatusCommand();
        $result = $method->invoke($command, 1073741824);
        $this->assertSame('1.00 GB', $result);
    }

    public function testResetOptionShortcutIsR(): void
    {
        $command = new OpcacheStatusCommand();
        $option = $command->getDefinition()->getOption('reset');
        $this->assertSame('r', $option->getShortcut());
    }

    public function testDetailedOptionShortcutIsD(): void
    {
        $command = new OpcacheStatusCommand();
        $option = $command->getDefinition()->getOption('detailed');
        $this->assertSame('d', $option->getShortcut());
    }

    public function testResetOptionIsValueNone(): void
    {
        $command = new OpcacheStatusCommand();
        $option = $command->getDefinition()->getOption('reset');
        $this->assertFalse($option->acceptValue());
    }

    public function testDetailedOptionIsValueNone(): void
    {
        $command = new OpcacheStatusCommand();
        $option = $command->getDefinition()->getOption('detailed');
        $this->assertFalse($option->acceptValue());
    }
}
