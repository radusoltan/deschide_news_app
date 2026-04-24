<?php

declare(strict_types=1);

namespace App\Service\Translation;

use App\Entity\PressRelease;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

class AggregatorTranslationService
{
    private const GEMINI_TIMEOUT = 60;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Translate a non-Romanian PressRelease content to Romanian.
     * Only call this when originalLanguage is not 'ro'.
     */
    public function translateToRomanian(PressRelease $pressRelease): void
    {
        $originalLanguage = $pressRelease->getOriginalLanguage();

        if ($originalLanguage === null || $originalLanguage === 'ro') {
            return;
        }

        $originalContent = $pressRelease->getContent();
        $originalTitle = $pressRelease->getTitle();

        $this->logger->info('AggregatorTranslationService: translating from {lang} to ro', [
            'lang' => $originalLanguage,
            'title' => mb_substr($originalTitle, 0, 80),
        ]);

        // Translate title
        $translatedTitle = $this->callGemini(
            "Translate the following news headline to Romanian. Return ONLY the translated text, nothing else.\n\n" . $originalTitle
        );

        if ($translatedTitle !== null) {
            $pressRelease->setTitle(mb_substr(trim($translatedTitle), 0, 255));
        }

        // Translate content
        $translatedContent = $this->callGemini(
            "Translate the following news article to Romanian. Return ONLY the translated text, no explanations.\n\n" . $originalContent
        );

        if ($translatedContent !== null) {
            $pressRelease->setContent(trim($translatedContent));
        }

        // Translate lead if present
        $originalLead = $pressRelease->getLead();
        if ($originalLead !== null && $originalLead !== '') {
            $translatedLead = $this->callGemini(
                "Translate the following news summary to Romanian. Return ONLY the translated text.\n\n" . $originalLead
            );

            if ($translatedLead !== null) {
                $pressRelease->setLead(mb_substr(trim($translatedLead), 0, 500));
            }
        }

        $this->logger->info('AggregatorTranslationService: translation completed', [
            'originalLang' => $originalLanguage,
            'translatedTitle' => mb_substr($pressRelease->getTitle(), 0, 80),
        ]);
    }

    private function callGemini(string $prompt): ?string
    {
        try {
            $output = $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);

            return $output !== '' ? $output : null;
        } catch (GeminiCliException $e) {
            $this->logger->error('AggregatorTranslationService: exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
