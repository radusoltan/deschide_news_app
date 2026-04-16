<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TopicDetectionMethod;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Pivot entity linking PressRelease to Topic with detection metadata.
 *
 * Each row records that a specific topic was detected in a press release,
 * along with the confidence score, detection method, and timestamp.
 */
#[ORM\Entity]
#[ORM\Table(name: 'press_release_topics')]
#[ORM\Index(name: 'idx_prt_topic_created', columns: ['topic_id', 'detected_at'])]
#[ORM\Index(name: 'idx_prt_press_release', columns: ['press_release_id'])]
#[ORM\UniqueConstraint(name: 'uniq_prt_pr_topic', columns: ['press_release_id', 'topic_id'])]
class PressReleaseTopic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['press:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PressRelease::class, inversedBy: 'pressReleaseTopics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PressRelease $pressRelease;

    #[ORM\ManyToOne(targetEntity: Topic::class, inversedBy: 'pressReleaseTopics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['press:read'])]
    private Topic $topic;

    /** Detection confidence score (0.0–1.0) */
    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(['press:read'])]
    private float $confidence;

    /** How the topic was detected */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: TopicDetectionMethod::class)]
    #[Groups(['press:read'])]
    private TopicDetectionMethod $detectedBy;

    /** When the detection occurred */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['press:read'])]
    private \DateTimeImmutable $detectedAt;

    public function __construct(
        PressRelease $pressRelease,
        Topic $topic,
        float $confidence,
        TopicDetectionMethod $detectedBy,
    ) {
        $this->pressRelease = $pressRelease;
        $this->topic = $topic;
        $this->confidence = $confidence;
        $this->detectedBy = $detectedBy;
        $this->detectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPressRelease(): PressRelease
    {
        return $this->pressRelease;
    }

    public function getTopic(): Topic
    {
        return $this->topic;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getDetectedBy(): TopicDetectionMethod
    {
        return $this->detectedBy;
    }

    public function getDetectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }
}
