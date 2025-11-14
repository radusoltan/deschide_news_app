<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Session;
use App\Message\SessionEndEvent;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SessionEndHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(SessionEndEvent $message): void
    {
        try {
            $session = new Session();
            $session->setId($message->sessionId);
            $session->setVisitorId($message->visitorId);
            $session->setStartedAt(DateTime::createFromImmutable($message->startedAt));
            $session->setEndedAt(DateTime::createFromImmutable($message->endedAt));
            $session->setPageCount($message->pageCount);
            $session->setDuration($message->duration);

            if ($message->ipAddress) {
                $session->setIpAddress($message->ipAddress);
            }
            if ($message->userAgent) {
                $session->setUserAgent($message->userAgent);
            }
            if ($message->referrer) {
                $session->setReferrer($message->referrer);
            }

            $this->em->persist($session);
            $this->em->flush();

            $this->logger->info('Session persisted', [
                'session_id' => $message->sessionId,
                'visitor_id' => $message->visitorId,
                'duration' => $message->duration,
                'page_count' => $message->pageCount,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to persist session', [
                'session_id' => $message->sessionId,
                'visitor_id' => $message->visitorId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for Messenger retry
        }
    }
}
