<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\GeneratedContentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: GeneratedContentRepository::class)]
#[ORM\Table(name: 'generated_content')]
#[ORM\Index(columns: ['type', 'generated_at'], name: 'idx_gc_type_date')]
#[ORM\Index(columns: ['locale'], name: 'idx_gc_locale')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/generated_contents',
            normalizationContext: ['groups' => ['generated_content:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Get(
            uriTemplate: '/generated_contents/{id}',
            normalizationContext: ['groups' => ['generated_content:read', 'generated_content:detail']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            uriTemplate: '/generated_contents/{id}',
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    order: ['generatedAt' => 'DESC'],
    paginationItemsPerPage: 20,
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'locale' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['generatedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['generatedAt', 'type'])]
class GeneratedContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['generated_content:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Groups(['generated_content:read'])]
    private string $type;

    #[ORM\Column(length: 255)]
    #[Groups(['generated_content:read'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['generated_content:detail'])]
    private string $content;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['generated_content:detail'])]
    private ?array $metadata = null;

    #[ORM\Column]
    #[Groups(['generated_content:read'])]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['generated_content:read'])]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(length: 5, options: ['default' => 'ro'])]
    #[Groups(['generated_content:read'])]
    private string $locale = 'ro';

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }
}
