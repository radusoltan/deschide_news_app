<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AppSettingAuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only audit row for every UPDATE on {@see AppSetting} (Sprint 57 T57.P3, ADR-024 D5).
 *
 * Scope discipline:
 *   - postUpdate only — creation of a key via {@see AppSettingRepository::set()} on a
 *     fresh entity or fixture seeding is NOT audited (ADR-024 D5 refinement: critical
 *     keys are seeded once with known-safe defaults; auditing creation adds zero
 *     operational signal).
 *   - All updates are recorded. `is_critical` lives in the `context` JSONB for
 *     filter-at-consumer rather than drop-at-producer — this keeps the audit trail
 *     complete when `CRITICAL_KEYS` evolves.
 *
 * Near-immutable: every column is write-once via the constructor. No setters.
 *
 * Storage shape mirrors `AppSetting.value` (TEXT) — values span bool/int/float/JSON
 * as encoded strings; upstream casts at read time. Storing as TEXT avoids conditional
 * JSON-validity requirements on `old_value`/`new_value` which would reject the
 * existing canonical `'true'`/`'false'`/`'42'` string forms.
 */
#[ORM\Entity(repositoryClass: AppSettingAuditLogRepository::class)]
#[ORM\Table(name: 'app_settings_audit_log')]
#[ORM\Index(name: 'idx_app_settings_audit_key_changed_at', columns: ['setting_key', 'changed_at'])]
#[ORM\Index(name: 'idx_app_settings_audit_changed_at', columns: ['changed_at'])]
class AppSettingAuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'setting_key', length: 100)]
    private string $settingKey;

    #[ORM\Column(name: 'old_value', type: Types::TEXT, nullable: true)]
    private ?string $oldValue;

    #[ORM\Column(name: 'new_value', type: Types::TEXT)]
    private string $newValue;

    #[ORM\Column(name: 'changed_by', length: 100)]
    private string $changedBy;

    #[ORM\Column(name: 'changed_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    #[ORM\Column(name: 'reason', type: Types::TEXT, nullable: true)]
    private ?string $reason;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'context', type: Types::JSON, options: ['jsonb' => true])]
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $settingKey,
        ?string $oldValue,
        string $newValue,
        string $changedBy,
        array $context,
        ?string $reason = null,
        ?\DateTimeImmutable $changedAt = null,
    ) {
        $this->settingKey = $settingKey;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->changedBy = $changedBy;
        $this->context = $context;
        $this->reason = $reason;
        $this->changedAt = $changedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function getNewValue(): string
    {
        return $this->newValue;
    }

    public function getChangedBy(): string
    {
        return $this->changedBy;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function isCritical(): bool
    {
        return (bool) ($this->context['isCritical'] ?? false);
    }
}
