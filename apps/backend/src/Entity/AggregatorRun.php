<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\AggregatorRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AggregatorRunRepository::class)]
#[ORM\Table(name: 'aggregator_runs')]
#[ORM\Index(name: 'idx_agg_run_started', columns: ['started_at'])]
#[ORM\Index(name: 'idx_agg_run_source', columns: ['source'])]
#[ORM\Index(name: 'idx_agg_run_status', columns: ['status'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/aggregator-runs',
            normalizationContext: ['groups' => ['agg_run:read']],
            security: "is_granted('ROLE_EDITOR')",
            paginationItemsPerPage: 20,
        ),
        new Get(
            uriTemplate: '/aggregator-runs/{id}',
            normalizationContext: ['groups' => ['agg_run:read']],
            security: "is_granted('ROLE_EDITOR')",
        ),
    ],
    order: ['startedAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['source' => 'exact', 'status' => 'exact', 'triggeredBy' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['startedAt'])]
class AggregatorRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['agg_run:read'])]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    #[Groups(['agg_run:read'])]
    private string $source;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['agg_run:read'])]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['agg_run:read'])]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(length: 20)]
    #[Groups(['agg_run:read'])]
    private string $status = 'running';

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['agg_run:read'])]
    private int $articlesFound = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['agg_run:read'])]
    private int $duplicatesSkipped = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['agg_run:read'])]
    private int $errorsCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['agg_run:read'])]
    private ?array $errorDetails = null;

    #[ORM\Column(length: 100)]
    #[Groups(['agg_run:read'])]
    private string $triggeredBy = 'scheduler';

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getArticlesFound(): int
    {
        return $this->articlesFound;
    }

    public function setArticlesFound(int $articlesFound): self
    {
        $this->articlesFound = $articlesFound;

        return $this;
    }

    public function incrementArticlesFound(int $count = 1): self
    {
        $this->articlesFound += $count;

        return $this;
    }

    public function getDuplicatesSkipped(): int
    {
        return $this->duplicatesSkipped;
    }

    public function setDuplicatesSkipped(int $duplicatesSkipped): self
    {
        $this->duplicatesSkipped = $duplicatesSkipped;

        return $this;
    }

    public function incrementDuplicatesSkipped(int $count = 1): self
    {
        $this->duplicatesSkipped += $count;

        return $this;
    }

    public function getErrorsCount(): int
    {
        return $this->errorsCount;
    }

    public function setErrorsCount(int $errorsCount): self
    {
        $this->errorsCount = $errorsCount;

        return $this;
    }

    public function incrementErrorsCount(int $count = 1): self
    {
        $this->errorsCount += $count;

        return $this;
    }

    public function getErrorDetails(): ?array
    {
        return $this->errorDetails;
    }

    public function setErrorDetails(?array $errorDetails): self
    {
        $this->errorDetails = $errorDetails;

        return $this;
    }

    public function addErrorDetail(string $error): self
    {
        $details = $this->errorDetails ?? [];
        $details[] = $error;
        $this->errorDetails = $details;

        return $this;
    }

    public function getTriggeredBy(): string
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(string $triggeredBy): self
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }

    public function markCompleted(): self
    {
        $this->status = 'completed';
        $this->finishedAt = new \DateTimeImmutable();

        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->status = 'failed';
        $this->finishedAt = new \DateTimeImmutable();
        $this->addErrorDetail($error);
        $this->incrementErrorsCount();

        return $this;
    }
}
