<?php

declare(strict_types=1);

namespace App\Service\Translation;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final readonly class GeminiStructuredTranslator
{
    private const TIMEOUT = 120;
    private const MAX_RETRIES = 1;

    public function __construct(
        private LoggerInterface $logger,
        private string $geminiCliPath,
    ) {}

    /**
     * Translate article content using Gemini CLI with structured JSON output.
     *
     * @return array{
     *     translated_headline: string,
     *     translated_body: string,
     *     translated_description: string,
     *     suggested_slug: string,
     *     seo_description: string,
     *     entities: list<string>,
     *     topics: list<string>,
     *     locations: list<string>,
     *     editorial_risk_flags: list<string>,
     * }|null
     */
    public function translate(
        string $headline,
        string $body,
        string $sourceLang,
        string $targetLang,
    ): ?array {
        $prompt = $this->buildPrompt($headline, $body, $sourceLang, $targetLang);

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            $result = $this->callGemini($prompt);

            if ($result !== null) {
                $validated = $this->validateResponse($result);
                if ($validated !== null) {
                    return $validated;
                }

                $this->logger->warning('GeminiStructuredTranslator: invalid JSON response, retrying', [
                    'attempt' => $attempt + 1,
                    'raw' => mb_substr($result, 0, 200),
                ]);
            }
        }

        $this->logger->error('GeminiStructuredTranslator: all retries exhausted', [
            'sourceLang' => $sourceLang,
            'targetLang' => $targetLang,
            'headline' => mb_substr($headline, 0, 80),
        ]);

        return null;
    }

    private function buildPrompt(string $headline, string $body, string $sourceLang, string $targetLang): string
    {
        $langNames = ['ro' => 'Romanian', 'en' => 'English', 'ru' => 'Russian'];
        $sourceLabel = $langNames[$sourceLang] ?? $sourceLang;
        $targetLabel = $langNames[$targetLang] ?? $targetLang;

        // Truncate body to avoid exceeding process limits
        $truncatedBody = mb_substr($body, 0, 30000);

        return <<<PROMPT
Translate the following news article from {$sourceLabel} to {$targetLabel}.
Return ONLY a valid JSON object with this exact structure:
{
  "translated_headline": "...",
  "translated_body": "...",
  "translated_description": "...",
  "suggested_slug": "...",
  "seo_description": "...",
  "entities": ["entity1", "entity2"],
  "topics": ["topic1", "topic2"],
  "locations": ["location1"],
  "editorial_risk_flags": []
}

Rules:
- Maintain journalistic tone and accuracy
- Use comma-below diacritics for Romanian (ș, ț, not ş, ţ)
- For Russian slugs, transliterate to Latin characters
- seo_description max 160 characters
- suggested_slug: lowercase, hyphens, no special chars, max 80 chars
- entities: persons, institutions, organizations mentioned
- topics: 2-5 relevant topic categories
- editorial_risk_flags: empty unless content contains unverified claims, potential defamation, or sensitive political content
- translated_description: first 300 characters summary

Source text:
Headline: {$headline}
Body:
{$truncatedBody}
PROMPT;
    }

    private function callGemini(string $prompt): ?string
    {
        $process = new Process([$this->geminiCliPath, '-p', $prompt]);
        $process->setTimeout(self::TIMEOUT);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning('GeminiStructuredTranslator: process failed', [
                    'exitCode' => $process->getExitCode(),
                    'error' => mb_substr($process->getErrorOutput(), 0, 200),
                ]);

                return null;
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            $this->logger->error('GeminiStructuredTranslator: exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate and extract JSON from Gemini output.
     * Handles cases where Gemini wraps JSON in markdown code blocks.
     *
     * @return array<string, mixed>|null
     */
    private function validateResponse(string $raw): ?array
    {
        // Strip markdown code block wrappers if present
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $data = json_decode($cleaned, true);

        if (!\is_array($data)) {
            return null;
        }

        // Check required fields
        $required = ['translated_headline', 'translated_body', 'translated_description', 'suggested_slug'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || !\is_string($data[$field]) || $data[$field] === '') {
                return null;
            }
        }

        // Normalize optional array fields
        $arrayFields = ['entities', 'topics', 'locations', 'editorial_risk_flags'];
        foreach ($arrayFields as $field) {
            if (!isset($data[$field]) || !\is_array($data[$field])) {
                $data[$field] = [];
            }
        }

        // Ensure seo_description exists and respects length limit
        if (!isset($data['seo_description']) || !\is_string($data['seo_description'])) {
            $data['seo_description'] = mb_substr($data['translated_description'], 0, 160);
        } else {
            $data['seo_description'] = mb_substr($data['seo_description'], 0, 160);
        }

        return $data;
    }
}
