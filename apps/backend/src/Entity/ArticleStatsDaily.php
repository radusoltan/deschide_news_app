<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ArticleStatsDailyRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleStatsDailyRepository::class)]
#[ORM\Table(name: 'article_stats_daily')]
#[ORM\Index(name: 'idx_article_date', columns: ['article_id', 'date'])]
#[ORM\Index(name: 'idx_date', columns: ['date'])]
#[ORM\UniqueConstraint(name: 'uniq_article_date', columns: ['article_id', 'date'])]
class ArticleStatsDaily
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(type: 'date')]
    private DateTimeInterface $date;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $views = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $uniqueVisitors = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $avgReadingTime = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $completionRate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function setArticle(Article $article): self
    {
        $this->article = $article;

        return $this;
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

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;

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

    public function getAvgReadingTime(): ?int
    {
        return $this->avgReadingTime;
    }

    public function setAvgReadingTime(?int $avgReadingTime): self
    {
        $this->avgReadingTime = $avgReadingTime;

        return $this;
    }

    public function getCompletionRate(): ?float
    {
        return $this->completionRate !== null ? (float) $this->completionRate : null;
    }

    public function setCompletionRate(?float $completionRate): self
    {
        $this->completionRate = $completionRate !== null ? (string) $completionRate : null;

        return $this;
    }
}
