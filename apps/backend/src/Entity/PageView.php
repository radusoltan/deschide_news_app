<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageViewRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageViewRepository::class)]
#[ORM\Table(name: 'page_views')]
#[ORM\Index(name: 'idx_article_views', columns: ['article_id', 'viewed_at'])]
#[ORM\Index(name: 'idx_visitor', columns: ['visitor_id'])]
#[ORM\Index(name: 'idx_viewed_at', columns: ['viewed_at'])]
#[ORM\Index(name: 'idx_page_views_category', columns: ['category_id'])]
class PageView
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Article $article = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $visitorId;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $referrer = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $viewedAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sessionDuration = null;

    public function __construct()
    {
        $this->viewedAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    public function getVisitorId(): string
    {
        return $this->visitorId;
    }

    public function setVisitorId(string $visitorId): self
    {
        $this->visitorId = $visitorId;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): self
    {
        $this->referrer = $referrer;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getViewedAt(): DateTimeInterface
    {
        return $this->viewedAt;
    }

    public function setViewedAt(DateTimeInterface $viewedAt): self
    {
        $this->viewedAt = $viewedAt;

        return $this;
    }

    public function getSessionDuration(): ?int
    {
        return $this->sessionDuration;
    }

    public function setSessionDuration(?int $sessionDuration): self
    {
        $this->sessionDuration = $sessionDuration;

        return $this;
    }
}
