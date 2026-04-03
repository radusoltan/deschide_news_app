<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class InternalSummaryService
{
    private const GEMINI_TIMEOUT = 60;
    private const MIN_BODY_LENGTH = 100;
    private const MAX_BODY_LENGTH = 3000;
    private const MIN_SUMMARY_LINES = 2;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generează un rezumat intern de 3-5 puncte principale din textul articolului.
     * Returnat ca text formatat (bullet points), nu HTML.
     */
    public function generateSummary(string $title, string $bodyText, string $sourceName = ''): ?string
    {
        if (mb_strlen(trim($bodyText)) < self::MIN_BODY_LENGTH) {
            $this->logger->debug('InternalSummaryService: body too short, skipping', [
                'title' => mb_substr($title, 0, 100),
                'bodyLength' => mb_strlen($bodyText),
            ]);

            return null;
        }

        $prompt = $this->buildPrompt($title, $bodyText, $sourceName);
        $output = $this->callGemini($prompt);

        if ($output === null) {
            return null;
        }

        $summary = $this->parseSummary($output);

        // Validare: trebuie să conțină cel puțin 2 linii non-goale
        $lines = array_filter(explode("\n", trim($summary)), fn (string $l): bool => trim($l) !== '');
        if (\count($lines) < self::MIN_SUMMARY_LINES) {
            $this->logger->warning('InternalSummaryService: summary prea scurt', [
                'title' => mb_substr($title, 0, 100),
                'lines' => \count($lines),
            ]);

            return null;
        }

        return trim($summary);
    }

    private function buildPrompt(string $title, string $bodyText, string $sourceName): string
    {
        $truncatedBody = mb_substr($bodyText, 0, self::MAX_BODY_LENGTH);
        $sourceInfo = $sourceName !== '' ? "Sursă: {$sourceName}\n" : '';

        return <<<PROMPT
Ești un editor senior la o agenție de presă din Republica Moldova.
Generează un rezumat intern (TL;DR) al următorului comunicat/articol.

Reguli:
- Exact 3-5 puncte principale, fiecare pe o linie nouă precedată de "•"
- Fiecare punct: maxim 2 propoziții, concis și factual
- Limba: română cu diacritice comma-below (ș, ț, nu ş, ţ)
- Ton: neutru-jurnalistic, fără opinii
- Menționează cifrele exacte și numele proprii dacă apar
- NU adăuga informații care nu sunt în text
- NU include salutări, concluzii sau metadate

{$sourceInfo}Titlu: {$title}

Text:
{$truncatedBody}

Rezumat:
PROMPT;
    }

    private function callGemini(string $prompt): ?string
    {
        $process = new Process([$this->geminiCliPath, '-p', $prompt]);
        $process->setTimeout(self::GEMINI_TIMEOUT);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning('InternalSummaryService: Gemini failed', [
                    'exitCode' => $process->getExitCode(),
                    'error' => mb_substr($process->getErrorOutput(), 0, 200),
                ]);

                return null;
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            $this->logger->error('InternalSummaryService: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse Gemini CLI output — strip markdown code blocks if present.
     */
    private function parseSummary(string $raw): string
    {
        // Strip markdown code block wrappers
        $cleaned = preg_replace('/^```(?:\w+)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);

        return trim($cleaned);
    }
}
