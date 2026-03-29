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
     * @param string[] $locales
     */
    public function process(Article $article, string $rawOutput, array $locales = ['ru', 'en']): void
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

        // Set final status based on quality gates
        $finalStatus = $needsReview ? 'needs_review' : 'completed';
        $article->setTranslationStatus($finalStatus);
        $article->setTranslatedAt(new \DateTimeImmutable());
        $article->setTranslatedBy('gemini-agent');

        $this->entityManager->flush();

        // Notify editors
        $roTitle = $article->getTitle() ?? 'Articol';
        $localeList = implode(', ', array_map('strtoupper', $translatedLocales));

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
     * @return array<string, mixed>
     */
    private function parseJson(string $rawOutput): array
    {
        $cleaned = trim($rawOutput);

        // Gemini CLI wraps translation in a JSON envelope where the "response"
        // field is a string containing the actual JSON. That inner string often
        // has literal newlines/tabs which are invalid inside JSON string values.
        // We must sanitize control characters BEFORE the first json_decode.
        // We only strip \x00-\x08, \x0b, \x0c, \x0e-\x1f (preserve \n=0x0a, \r=0x0d, \t=0x09
        // which are legal JSON whitespace outside of strings but illegal inside).
        // The safest approach: replace control chars inside JSON string values only.
        // Simpler approach that works: replace all control chars with spaces,
        // then restore structural newlines.
        $cleaned = preg_replace('/[\x00-\x09\x0b\x0c\x0e-\x1f\x7f]/', ' ', $cleaned) ?? $cleaned;

        try {
            $decoded = json_decode($cleaned, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
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

            // Fix control characters that break JSON parsing (Gemini sometimes
            // embeds raw newlines/tabs inside JSON string values)
            $responseStr = preg_replace('/[\x00-\x1f\x7f]/', ' ', $responseStr) ?? $responseStr;

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
