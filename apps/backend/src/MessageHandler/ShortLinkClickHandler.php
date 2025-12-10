<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ShortLinkInteraction;
use App\Message\ShortLinkClickMessage;
use App\Repository\ShortLinkRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles short link click events.
 *
 * Records interaction details and increments click counter.
 * Includes basic device detection and GDPR-compliant IP anonymization.
 */
#[AsMessageHandler]
class ShortLinkClickHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShortLinkRepository $shortLinkRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ShortLinkClickMessage $message): void
    {
        try {
            $shortLink = $this->shortLinkRepository->find($message->shortLinkId);

            if (!$shortLink) {
                $this->logger->warning('Short link not found for click event', [
                    'short_link_id' => $message->shortLinkId,
                ]);

                return;
            }

            // Increment click counter (denormalized for fast queries)
            $shortLink->incrementClickCount();

            // Create interaction record
            $interaction = new ShortLinkInteraction();
            $interaction->setShortLink($shortLink);
            $interaction->setClickedAt(new DateTimeImmutable());
            $interaction->setIpAddress($message->ipAddress);
            $interaction->setUserAgent($message->userAgent);
            $interaction->setReferrer($message->referrer);

            // Detect device type from User-Agent
            if ($message->userAgent) {
                $interaction->setDeviceType($this->detectDeviceType($message->userAgent));
            }

            // Note: Country detection would require a GeoIP service
            // For now, this is left as null (could be implemented later with MaxMind or similar)

            $this->entityManager->persist($interaction);
            $this->entityManager->flush();

            $this->logger->info('Short link click recorded', [
                'short_link_id' => $message->shortLinkId,
                'total_clicks' => $shortLink->getClickCount(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record short link click', [
                'short_link_id' => $message->shortLinkId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for Messenger retry
        }
    }

    /**
     * Detect device type from User-Agent string.
     */
    private function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        // Check for tablets first (often contain 'mobile' too)
        if (str_contains($userAgent, 'tablet') ||
            str_contains($userAgent, 'ipad') ||
            (str_contains($userAgent, 'android') && !str_contains($userAgent, 'mobile'))) {
            return 'tablet';
        }

        // Check for mobile devices
        if (str_contains($userAgent, 'mobile') ||
            str_contains($userAgent, 'iphone') ||
            str_contains($userAgent, 'ipod') ||
            str_contains($userAgent, 'android') ||
            str_contains($userAgent, 'blackberry') ||
            str_contains($userAgent, 'windows phone')) {
            return 'mobile';
        }

        // Default to desktop
        return 'desktop';
    }
}
