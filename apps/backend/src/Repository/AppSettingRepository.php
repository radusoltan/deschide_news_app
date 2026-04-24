<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
class AppSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->find($key);

        return $setting?->getValue() ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value !== null ? filter_var($value, \FILTER_VALIDATE_BOOLEAN) : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return $value !== null ? (float) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value !== null ? (int) $value : $default;
    }

    /**
     * @return array<mixed>
     */
    public function getJson(string $key, array $default = []): array
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return \is_array($decoded) ? $decoded : $default;
    }

    public function set(string $key, string $value): void
    {
        $em = $this->getEntityManager();
        $setting = $this->find($key);

        if ($setting !== null) {
            $setting->setValue($value);
        } else {
            $setting = new AppSetting($key, $value);
            $em->persist($setting);
        }

        $em->flush();
    }
}
