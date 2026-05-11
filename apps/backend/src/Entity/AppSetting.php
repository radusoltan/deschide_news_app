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
     * Keys whose updates require an operator-supplied `--reason`.
     *
     * Supports glob wildcards via {@see fnmatch()}. Exact string entries match verbatim.
     *
     * @var list<string>
     */
    public const CRITICAL_KEYS = [
        'agent.emergency_halt',
    ];

    /**
     * Returns true when `$key` matches one of {@see self::CRITICAL_KEYS}, with
     * glob-matching semantics (fnmatch): `*` matches any run of characters
     * including dots.
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
