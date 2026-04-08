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
