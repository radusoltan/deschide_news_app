<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Trigger on-demand press email fetching from Zoho Mail.
 *
 * Runs the app:fetch-press-emails command programmatically and returns
 * JSON with the results (queued / skipped / errors).
 */
#[Route('/api/press-emails')]
#[IsGranted('ROLE_EDITOR')]
final class PressEmailFetchController extends AbstractController
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/fetch', name: 'api_press_emails_fetch', methods: ['POST'])]
    public function fetch(): JsonResponse
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:fetch-press-emails',
            '--limit' => '20',
        ]);

        $output = new BufferedOutput();

        try {
            $exitCode = $application->run($input, $output);
        } catch (\Throwable $e) {
            $this->logger->error('Press email fetch failed', ['error' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => 'Eroare la preluarea emailurilor: ' . $e->getMessage(),
                'queued' => 0,
                'skipped' => 0,
                'errors' => 1,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $text = $output->fetch();

        // Parse the summary from command output
        $queued = $this->extractNumber($text, 'Queued for review');
        $skipped = $this->extractNumber($text, 'Skipped');
        $errors = $this->extractNumber($text, 'Errors');

        $this->logger->info('Press email fetch triggered via API', [
            'exitCode' => $exitCode,
            'queued' => $queued,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return $this->json([
            'success' => $exitCode === 0,
            'queued' => $queued,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * Extract a number from the command's definition-list output.
     *
     * The command outputs lines like:
     *   Queued for review   5
     *   Skipped             3
     *   Errors              0
     */
    private function extractNumber(string $text, string $label): int
    {
        if (preg_match('/' . preg_quote($label, '/') . '\s+(\d+)/i', $text, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
