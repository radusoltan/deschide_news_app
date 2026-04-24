<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
#[ORM\Table(name: 'app_settings')]
class AppSetting
{
    /**
     * Keys whose updates require an operator-supplied `--reason` and are broadcast
     * on Mercure with `isCritical: true` in the payload (T57.P3, ADR-024 D5).
     *
     * Supports glob wildcards via {@see fnmatch()}. `editorial.tier_overrides.*`
     * matches any per-agent tier override key (e.g. `editorial.tier_overrides.flash_writer`).
     * Exact string entries match verbatim.
     *
     * Extending this list requires no listener change — the listener resolves
     * criticality dynamically at write time via {@see self::isCriticalKey()}.
     *
     * @var list<string>
     */
    public const CRITICAL_KEYS = [
        'editorial.emergency_halt',
        'editorial.pipeline.enabled',
        'editorial.tier_overrides.*',
        // T57.P4+P5 — legacy Gemini rollback flags for the briefing writer.
        // Flipping any of these routes production briefings through the
        // pre-migration Gemini draft + Claude polish path; operational
        // override with production blast radius, semantically parallel to
        // `editorial.tier_overrides.*`.
        'briefing.llm.use_legacy_gemini_*',
    ];

    /**
     * Returns true when `$key` matches one of {@see self::CRITICAL_KEYS}, with
     * glob-matching semantics (fnmatch): `*` matches any run of characters
     * including dots. Bare-prefix matches (e.g. `editorial.tier_overrides` with
     * no trailing segment) do NOT match `editorial.tier_overrides.*` — the
     * wildcard requires at least one character in the segment position.
     */
    public static function isCriticalKey(string $key): bool
    {
        foreach (self::CRITICAL_KEYS as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    #[ORM\Id]
    #[ORM\Column(length: 100)]
    private string $key;

    #[ORM\Column(type: Types::TEXT)]
    private string $value;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $key, string $value)
    {
        $this->key = $key;
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getKey(): string { return $this->key; }

    public function getValue(): string { return $this->value; }
    public function setValue(string $value): static
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
