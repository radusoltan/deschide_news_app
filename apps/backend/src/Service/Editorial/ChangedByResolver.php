<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves the actor identifier persisted into `app_settings_audit_log.changed_by`
 * (T57.P3, ADR-024 D5).
 *
 * Resolution order:
 *   1. HTTP/authenticated context — Symfony Security token present → `user:<identifier>`.
 *   2. CLI context (`php_sapi_name() === 'cli'`) — use `$_SERVER['USER']` → `cli:<name>`.
 *   3. Fallback — no token, no shell user → literal string `system`.
 *
 * The prefix makes downstream audit queries readable without joining — a
 * dashboard filter on "only CLI flips" becomes `WHERE changed_by LIKE 'cli:%'`.
 * The `system` sentinel distinguishes listener-initiated writes (none exist
 * today; kept as an explicit catch-all rather than letting a null slip through
 * the NOT NULL column).
 */
class ChangedByResolver
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public function resolve(): string
    {
        $user = $this->security->getUser();
        if ($user !== null) {
            return 'user:' . $user->getUserIdentifier();
        }

        if (\PHP_SAPI === 'cli') {
            $shellUser = $_SERVER['USER'] ?? $_SERVER['LOGNAME'] ?? null;
            if (\is_string($shellUser) && $shellUser !== '') {
                return 'cli:' . $shellUser;
            }

            return 'cli:unknown';
        }

        return 'system';
    }
}
