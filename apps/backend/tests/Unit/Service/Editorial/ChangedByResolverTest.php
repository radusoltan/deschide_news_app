<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Service\Editorial\ChangedByResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Unit tests for the T57.P3 actor-identifier resolver (ADR-024 D5).
 *
 * The `changed_by` column is NOT NULL — a silent fallback to empty string
 * would leak into the audit trail. These tests pin the three resolution
 * branches (auth user, CLI, system fallback).
 */
class ChangedByResolverTest extends TestCase
{
    public function testResolveReturnsUserIdentifierWhenAuthenticated(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('editor@deschide.md');
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $resolver = new ChangedByResolver($security);

        $this->assertSame('user:editor@deschide.md', $resolver->resolve());
    }

    public function testResolveReturnsCliPrefixedShellUserInCliContext(): void
    {
        // Test runs under PHP CLI SAPI, so the CLI branch is live without a test double.
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $originalUser = $_SERVER['USER'] ?? null;
        $_SERVER['USER'] = 'radu';
        try {
            $resolver = new ChangedByResolver($security);
            $this->assertSame('cli:radu', $resolver->resolve());
        } finally {
            if ($originalUser === null) {
                unset($_SERVER['USER']);
            } else {
                $_SERVER['USER'] = $originalUser;
            }
        }
    }

    public function testResolveReturnsCliUnknownWhenShellUserMissing(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $originalUser = $_SERVER['USER'] ?? null;
        $originalLogname = $_SERVER['LOGNAME'] ?? null;
        unset($_SERVER['USER'], $_SERVER['LOGNAME']);
        try {
            $resolver = new ChangedByResolver($security);
            $this->assertSame('cli:unknown', $resolver->resolve());
        } finally {
            if ($originalUser !== null) {
                $_SERVER['USER'] = $originalUser;
            }
            if ($originalLogname !== null) {
                $_SERVER['LOGNAME'] = $originalLogname;
            }
        }
    }
}
