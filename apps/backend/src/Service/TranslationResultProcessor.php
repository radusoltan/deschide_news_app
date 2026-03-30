<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\Log\LoggerInterface;

final class TranslationResultProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Result returned by process() so the caller can decide final status.
     *
     * @param string[] $savedLocales  Locales that were successfully persisted
     * @param bool     $needsReview   True if any quality gate flagged the translation
     */
    public function process(Article $article, string $rawOutput, array $locales = ['ru', 'en']): ProcessResult
    {
        $data = $this->parseJson($rawOutput);

        if (!isset($data['translations']) || !\is_array($data['translations'])) {
            throw new \RuntimeException('Invalid translation response: missing "translations" key');
        }

        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

        // Source content for quality gates (Romanian = default locale)
        $roContent = $article->getContent() ?? '';

        $translatedLocales = [];
        $needsReview = false;

        foreach ($locales as $locale) {
            if (!isset($data['translations'][$locale])) {
                $this->logger->warning('TranslationResultProcessor: locale missing from response', [
                    'articleId' => $article->getId(),
                    'locale' => $locale,
                ]);

                continue;
            }

            $tData = $data['translations'][$locale];

            if (!\is_array($tData)) {
                continue;
            }

            $content = $tData['content'] ?? '';

            // Quality gate 1: HTML paragraph count audit
            $pRO = substr_count($roContent, '<p>');
            $pTR = substr_count($content, '<p>');
            if ($pRO > 0 && $pTR > 0 && abs($pRO - $pTR) / $pRO > 0.30) {
                $this->logger->warning('TranslationResultProcessor: paragraph count mismatch', [
                    'articleId' => $article->getId(),
                    'locale' => $locale,
                    'ro_count' => $pRO,
                    'tr_count' => $pTR,
                ]);
                $needsReview = true;
            }

            // Quality gate 2: Length sanity check (±35%)
            $lenRO = mb_strlen(strip_tags($roContent));
            $lenTR = mb_strlen(strip_tags($content));
            if ($lenRO > 0 && abs($lenRO - $lenTR) / $lenRO > 0.35) {
                $this->logger->warning('TranslationResultProcessor: length sanity failed', [
                    'articleId' => $article->getId(),
                    'locale' => $locale,
                    'ro_len' => $lenRO,
                    'tr_len' => $lenTR,
                    'ratio' => round(abs($lenRO - $lenTR) / $lenRO, 2),
                ]);
                $needsReview = true;
            }

            // Save translatable fields via Gedmo
            if (!empty($tData['title'])) {
                $translationRepo->translate($article, 'title', $locale, $tData['title']);
            }

            if (!empty($tData['lead'])) {
                $translationRepo->translate($article, 'lead', $locale, $tData['lead']);
            }

            if (!empty($tData['content'])) {
                $translationRepo->translate($article, 'content', $locale, $tData['content']);
            }

            if (!empty($tData['slug'])) {
                $translationRepo->translate($article, 'slug', $locale, $tData['slug']);
            }

            $translatedLocales[] = $locale;

            $this->logger->info('Translation saved', [
                'articleId' => $article->getId(),
                'locale' => $locale,
            ]);
        }

        // Quality gate 3: qualityNotes flag
        if (isset($data['qualityNotes']) && \is_array($data['qualityNotes'])) {
            foreach ($data['qualityNotes'] as $locale => $note) {
                if (\is_string($note) && !empty(trim($note))) {
                    $this->logger->info('TranslationResultProcessor: quality note from Gemini', [
                        'articleId' => $article->getId(),
                        'locale' => $locale,
                        'note' => $note,
                    ]);
                    $needsReview = true;
                }
            }
        }

        $this->entityManager->flush();

        return new ProcessResult($translatedLocales, $needsReview);
    }

    /**
     * Set final translation status on article and send editor notification.
     * Called by the handler after all per-language iterations are done.
     *
     * @param string[] $allSavedLocales  All locales saved across iterations
     */
    public function finalize(Article $article, string $status, array $allSavedLocales, bool $needsReview): void
    {
        $article->setTranslationStatus($status);
        $article->setTranslatedAt(new \DateTimeImmutable());
        $article->setTranslatedBy('gemini-agent');
        $this->entityManager->flush();

        if ($allSavedLocales === []) {
            return;
        }

        $roTitle = $article->getTitle() ?? 'Articol';
        $localeList = implode(', ', array_map('strtoupper', $allSavedLocales));

        $importance = $needsReview
            ? NotificationImportance::MEDIUM
            : NotificationImportance::LOW;
        $title = $needsReview
            ? 'Traduceri finalizate — revizie necesară'
            : 'Traduceri finalizate';
        $message = $needsReview
            ? \sprintf('„%s" — traducerile %s necesită revizie editorială.', $roTitle, $localeList)
            : \sprintf('„%s" — traducerile %s sunt gata.', $roTitle, $localeList);

        $this->notificationService->notify(
            type: NotificationType::ARTICLE_TRANSLATED,
            title: $title,
            message: $message,
            importance: $importance,
            relatedEntityType: 'article',
            relatedEntityId: $article->getId(),
            actionUrl: \sprintf('/admin/articles/%s/edit', $article->getId()),
        );
    }

    /**
     * Replace ASCII control characters (0x00-0x1f, 0x7f) with spaces.
     * Uses byte-level replacement to avoid PCRE issues with large UTF-8 strings.
     */
    private function stripControlChars(string $input): string
    {
        // Build translation table: every control char → space
        $map = [];
        for ($i = 0; $i <= 0x1F; ++$i) {
            $map[\chr($i)] = ' ';
        }
        $map[\chr(0x7F)] = ' ';

        return strtr($input, $map);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(string $rawOutput): array
    {
        $cleaned = trim($rawOutput);

        // Gemini CLI wraps translation in a JSON envelope where the "response"
        // field contains literal newlines/tabs inside its string value.
        // Strip ALL ASCII control characters (0x00-0x1f, 0x7f) including \n, \r, \t.
        $cleaned = $this->stripControlChars($cleaned);

        try {
            $decoded = json_decode($cleaned, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Log cleaned version for debugging
            $this->logger->error('TranslationResultProcessor: json_decode failed on cleaned input', [
                'cleaned_first_300' => mb_substr($cleaned, 0, 300),
                'json_error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'TranslationResultProcessor: invalid JSON: ' . $e->getMessage()
                . "\nRaw (first 500): " . mb_substr($rawOutput, 0, 500)
            );
        }

        if (!\is_array($decoded)) {
            throw new \RuntimeException('Translation JSON did not decode to an array');
        }

        // Gemini CLI wraps output in { session_id, response, stats } envelope
        // The actual translation JSON is inside the "response" field as a string
        if (isset($decoded['response']) && \is_string($decoded['response'])) {
            $responseStr = $decoded['response'];

            // Strip markdown JSON wrappers if Gemini added them inside the response
            $responseStr = preg_replace('/^```(?:json)?\s*/m', '', $responseStr);
            $responseStr = preg_replace('/\s*```\s*$/m', '', $responseStr ?? $decoded['response']);
            $responseStr = trim($responseStr ?? $decoded['response']);

            // Gemini sometimes prepends preamble text ("I will translate...")
            // before the JSON object. Extract from first '{' to last '}'.
            $jsonStart = strpos($responseStr, '{');
            $jsonEnd = strrpos($responseStr, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                if ($jsonStart > 0) {
                    $this->logger->debug('TranslationResultProcessor: stripped preamble', [
                        'preamble' => mb_substr($responseStr, 0, min($jsonStart, 120)),
                    ]);
                }
                $responseStr = substr($responseStr, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            // Strip control characters that break JSON parsing
            $responseStr = $this->stripControlChars($responseStr);

            try {
                $inner = json_decode($responseStr, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \RuntimeException(
                    'TranslationResultProcessor: invalid inner JSON from Gemini response: ' . $e->getMessage()
                    . "\nResponse (first 500): " . mb_substr($responseStr, 0, 500)
                );
            }

            if (\is_array($inner)) {
                return $inner;
            }
        }

        // If already has "translations" key, return as-is (direct JSON output)
        if (isset($decoded['translations'])) {
            return $decoded;
        }

        // Strip markdown JSON wrappers as fallback
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $rawOutput);
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned ?? $rawOutput);
        $cleaned = trim($cleaned ?? $rawOutput);

        $decoded = json_decode($cleaned, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('Translation JSON did not decode to an array');
        }

        return $decoded;
    }
}
