<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\ImportantArticlesListRepository;
use App\State\ImportantArticlesListProvider;
use App\Validator\ImportantArticlesCount;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ImportantArticlesListRepository::class)]
#[ORM\Table(name: 'important_articles_list')]
#[ORM\Index(name: 'idx_important_articles_position', columns: ['position'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/important_articles/{id}',
            normalizationContext: ['groups' => ['important_articles:read', 'article:read', 'article:detail'], 'enable_max_depth' => true],
            provider: ImportantArticlesListProvider::class
        ),
        new GetCollection(
            uriTemplate: '/important_articles',
            normalizationContext: ['groups' => ['important_articles:read', 'article:read', 'article:detail'], 'enable_max_depth' => true],
            provider: ImportantArticlesListProvider::class
        ),
        new Post(
            uriTemplate: '/important_articles',
            denormalizationContext: ['groups' => ['important_articles:write']]
        ),
        new Delete(
            uriTemplate: '/important_articles/{id}'
        ),
    ]
)]
#[ImportantArticlesCount]
#[UniqueEntity(fields: ['article'], message: 'This article is already in the important articles list.')]
class ImportantArticlesList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['important_articles:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['important_articles:read', 'important_articles:write'])]
    private ?Article $article = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(min: 1, max: 25)]
    #[Groups(['important_articles:read', 'important_articles:write'])]
    private ?int $position = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['important_articles:read'])]
    private ?DateTimeImmutable $createdAt = null;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
