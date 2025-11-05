<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteStatsDailyRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteStatsDailyRepository::class)]
#[ORM\Table(name: 'site_stats_daily')]
#[ORM\Index(name: 'idx_site_stats_date', columns: ['date'])]
#[ORM\UniqueConstraint(name: 'uniq_site_date', columns: ['date'])]
class SiteStatsDaily
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'date', unique: true)]
    private DateTimeInterface $date;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalVisits = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $uniqueVisitors = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $newVisitors = 0;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $bounceRate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $avgSessionDuration = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getTotalVisits(): int
    {
        return $this->totalVisits;
    }

    public function setTotalVisits(int $totalVisits): self
    {
        $this->totalVisits = $totalVisits;

        return $this;
    }

    public function getUniqueVisitors(): int
    {
        return $this->uniqueVisitors;
    }

    public function setUniqueVisitors(int $uniqueVisitors): self
    {
        $this->uniqueVisitors = $uniqueVisitors;

        return $this;
    }

    public function getNewVisitors(): int
    {
        return $this->newVisitors;
    }

    public function setNewVisitors(int $newVisitors): self
    {
        $this->newVisitors = $newVisitors;

        return $this;
    }

    public function getBounceRate(): ?float
    {
        return $this->bounceRate !== null ? (float) $this->bounceRate : null;
    }

    public function setBounceRate(?float $bounceRate): self
    {
        $this->bounceRate = $bounceRate !== null ? (string) $bounceRate : null;

        return $this;
    }

    public function getAvgSessionDuration(): ?int
    {
        return $this->avgSessionDuration;
    }

    public function setAvgSessionDuration(?int $avgSessionDuration): self
    {
        $this->avgSessionDuration = $avgSessionDuration;

        return $this;
    }
}
