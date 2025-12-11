<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShortLinkInteractionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ShortLinkInteractionRepository::class)]
#[ORM\Table(name: 'short_link_interactions')]
#[ORM\Index(name: 'idx_sli_short_link', columns: ['short_link_id'])]
#[ORM\Index(name: 'idx_sli_clicked_at', columns: ['clicked_at'])]
#[ORM\Index(name: 'idx_sli_country_code', columns: ['country_code'])]
#[ORM\Index(name: 'idx_sli_device_type', columns: ['device_type'])]
#[ORM\Index(name: 'idx_sli_short_link_clicked', columns: ['short_link_id', 'clicked_at'])]
class ShortLinkInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['short_link:stats'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShortLink::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(name: 'short_link_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ShortLink $shortLink = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['short_link:stats'])]
    private ?DateTimeImmutable $clickedAt = null;

    /**
     * Anonymized IP address (GDPR compliant - last octet zeroed).
     */
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['short_link:stats'])]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Groups(['short_link:stats'])]
    private ?string $referrer = null;

    /**
     * ISO 3166-1 alpha-2 country code.
     */
    #[ORM\Column(type: Types::STRING, length: 2, nullable: true)]
    #[Groups(['short_link:stats'])]
    private ?string $countryCode = null;

    /**
     * Device type: mobile, desktop, tablet.
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['short_link:stats'])]
    private ?string $deviceType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShortLink(): ?ShortLink
    {
        return $this->shortLink;
    }

    public function setShortLink(?ShortLink $shortLink): self
    {
        $this->shortLink = $shortLink;

        return $this;
    }

    public function getClickedAt(): ?DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function setClickedAt(DateTimeImmutable $clickedAt): self
    {
        $this->clickedAt = $clickedAt;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        // Anonymize IP for GDPR compliance (zero out last octet)
        if ($ipAddress !== null) {
            if (str_contains($ipAddress, ':')) {
                // IPv6: Remove last segment
                $parts = explode(':', $ipAddress);
                if (\count($parts) > 1) {
                    $parts[\count($parts) - 1] = '0';
                    $ipAddress = implode(':', $parts);
                }
            } else {
                // IPv4: Zero out last octet
                $parts = explode('.', $ipAddress);
                if (\count($parts) === 4) {
                    $parts[3] = '0';
                    $ipAddress = implode('.', $parts);
                }
            }
        }
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        // Truncate if too long
        if ($userAgent !== null && \strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): self
    {
        // Truncate if too long
        if ($referrer !== null && \strlen($referrer) > 500) {
            $referrer = substr($referrer, 0, 500);
        }
        $this->referrer = $referrer;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): self
    {
        // Normalize to uppercase
        if ($countryCode !== null) {
            $countryCode = strtoupper(substr($countryCode, 0, 2));
        }
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): self
    {
        $this->deviceType = $deviceType;

        return $this;
    }
}
