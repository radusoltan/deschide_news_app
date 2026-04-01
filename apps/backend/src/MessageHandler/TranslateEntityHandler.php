<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Author;
use App\Entity\Category;
use App\Enum\TranslatableEntityType;
use App\Message\TranslateEntityMessage;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class TranslateEntityHandler
{
    private const TIMEOUT = 60;
    private const AGENT_FILE = '.gemini/agents/entity-translator.md';

    public function __construct(
        private CategoryRepository $categoryRepository,
        private AuthorRepository $authorRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $geminiCliPath,
        private string $projectDir,
    ) {
    }

    public function __invoke(TranslateEntityMessage $message): void
    {
        match ($message->entityType) {
            TranslatableEntityType::CATEGORY => $this->translateCategory($message),
            TranslatableEntityType::AUTHOR => $this->translateAuthor($message),
        };
    }

    private function translateCategory(TranslateEntityMessage $message): void
    {
        $category = $this->categoryRepository->find($message->entityId);

        if ($category === null) {
            $this->logger->warning('TranslateEntityHandler: category not found', [
                'entityId' => $message->entityId,
            ]);

            return;
        }

        $roTitle = $category->getTitle();

        if (empty($roTitle)) {
            $this->logger->warning('TranslateEntityHandler: category has no title', [
                'entityId' => $message->entityId,
            ]);

            return;
        }

        if (!$message->force && $category->getTranslationStatus() === 'completed') {
            $this->logger->info('TranslateEntityHandler: category already translated, skipping', [
                'entityId' => $message->entityId,
            ]);

            return;
        }

        $category->setTranslationStatus('in_progress');
        $this->em->flush();

        $this->logger->info('TranslateEntityHandler: starting category translation', [
            'entityId' => $message->entityId,
            'locales' => $message->locales,
        ]);

        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->em->getRepository('Gedmo\Translatable\Entity\Translation');

        $successes = [];
        $failures = [];

        foreach ($message->locales as $locale) {
            $start = microtime(true);

            try {
                $promptJson = $this->buildCategoryPrompt($category, $locale);
                $output = $this->runGemini($promptJson, $locale);
                $parsed = $this->parseGeminiOutput($output);

                $translatedTitle = $parsed['translations'][$locale]['title'] ?? null;

                if (empty($translatedTitle)) {
                    throw new \RuntimeException('Missing translated title for locale ' . $locale);
                }

                // Quality gate: length check ±50%
                $lenRO = mb_strlen($roTitle);
                $lenTR = mb_strlen($translatedTitle);
                if ($lenRO > 0 && abs($lenRO - $lenTR) / $lenRO > 0.50) {
                    $this->logger->warning('TranslateEntityHandler: category title length mismatch', [
                        'entityId' => $message->entityId,
                        'locale' => $locale,
                        'ro_len' => $lenRO,
                        'tr_len' => $lenTR,
                    ]);
                }

                $translationRepo->translate($category, 'title', $locale, $translatedTitle);
                $this->em->flush();

                $successes[] = $locale;

                $this->logger->info('TranslateEntityHandler: category locale completed', [
                    'entityId' => $message->entityId,
                    'locale' => $locale,
                    'duration' => round(microtime(true) - $start, 2) . 's',
                ]);
            } catch (\Throwable $e) {
                $failures[] = $locale;
                $this->logger->error('TranslateEntityHandler: category locale failed', [
                    'entityId' => $message->entityId,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->finalizeEntity($category, $successes, $failures, $message);
    }

    private function translateAuthor(TranslateEntityMessage $message): void
    {
        $author = $this->authorRepository->find($message->entityId);

        if ($author === null) {
            $this->logger->warning('TranslateEntityHandler: author not found', [
                'entityId' => $message->entityId,
            ]);

            return;
        }

        $roBio = $author->getBio();

        if (empty($roBio)) {
            $this->logger->warning('TranslateEntityHandler: author has no bio, skipping', [
                'entityId' => $message->entityId,
            ]);

            return;
        }

        if (!$message->force && $author->getTranslationStatus() === 'completed') {
            $this->logger->info('TranslateEntityHandler: author already translated, skipping', [
                'entityId' => $message->entityId,
            ]);

            return;
        }

        $author->setTranslationStatus('in_progress');
        $this->em->flush();

        $this->logger->info('TranslateEntityHandler: starting author translation', [
            'entityId' => $message->entityId,
            'locales' => $message->locales,
        ]);

        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->em->getRepository('Gedmo\Translatable\Entity\Translation');

        $successes = [];
        $failures = [];

        foreach ($message->locales as $locale) {
            $start = microtime(true);

            try {
                $promptJson = $this->buildAuthorPrompt($author, $locale);
                $output = $this->runGemini($promptJson, $locale);
                $parsed = $this->parseGeminiOutput($output);

                $translatedBio = $parsed['translations'][$locale]['bio'] ?? null;

                if (empty($translatedBio)) {
                    throw new \RuntimeException('Missing translated bio for locale ' . $locale);
                }

                // Quality gate: length check ±50%
                $lenRO = mb_strlen($roBio);
                $lenTR = mb_strlen($translatedBio);
                if ($lenRO > 0 && abs($lenRO - $lenTR) / $lenRO > 0.50) {
                    $this->logger->warning('TranslateEntityHandler: author bio length mismatch', [
                        'entityId' => $message->entityId,
                        'locale' => $locale,
                        'ro_len' => $lenRO,
                        'tr_len' => $lenTR,
                    ]);
                }

                $translationRepo->translate($author, 'bio', $locale, $translatedBio);
                $this->em->flush();

                $successes[] = $locale;

                $this->logger->info('TranslateEntityHandler: author locale completed', [
                    'entityId' => $message->entityId,
                    'locale' => $locale,
                    'duration' => round(microtime(true) - $start, 2) . 's',
                ]);
            } catch (\Throwable $e) {
                $failures[] = $locale;
                $this->logger->error('TranslateEntityHandler: author locale failed', [
                    'entityId' => $message->entityId,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->finalizeEntity($author, $successes, $failures, $message);
    }

    private function finalizeEntity(
        Category|Author $entity,
        array $successes,
        array $failures,
        TranslateEntityMessage $message,
    ): void {
        if ($successes === []) {
            $entity->setTranslationStatus('failed');
            $this->em->flush();

            throw new \RuntimeException(\sprintf(
                'All translations failed for %s %d: %s',
                $message->entityType->value,
                $message->entityId,
                implode(', ', $failures),
            ));
        }

        $finalStatus = $failures !== [] ? 'needs_review' : 'completed';
        $entity->setTranslationStatus($finalStatus);
        $entity->setTranslatedAt(new \DateTimeImmutable());
        $entity->setTranslatedBy('gemini-entity-translator');
        $this->em->flush();

        $this->logger->info('TranslateEntityHandler: completed', [
            'entityType' => $message->entityType->value,
            'entityId' => $message->entityId,
            'status' => $finalStatus,
            'successes' => $successes,
            'failures' => $failures,
        ]);
    }

    private function buildCategoryPrompt(Category $category, string $locale): string
    {
        $data = [
            'entityType' => 'category',
            'sourceLocale' => 'ro',
            'targetLocale' => $locale,
            'fields' => [
                'title' => $category->getTitle(),
            ],
        ];

        return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    private function buildAuthorPrompt(Author $author, string $locale): string
    {
        $data = [
            'entityType' => 'author',
            'sourceLocale' => 'ro',
            'targetLocale' => $locale,
            'fields' => [
                'bio' => $author->getBio(),
            ],
        ];

        return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    private function runGemini(string $promptJson, string $locale): string
    {
        // Read agent system prompt
        $agentFile = $this->projectDir . '/' . self::AGENT_FILE;
        $systemPrompt = '';
        if (is_file($agentFile)) {
            $systemPrompt = file_get_contents($agentFile);
        }

        // Combine system prompt + entity JSON via stdin
        $stdinContent = $systemPrompt . "\n\n---\n\nEntity JSON to translate:\n" . $promptJson;

        // Gemini CLI: use -p with short instruction, full content via stdin
        $process = new Process(
            command: [
                $this->geminiCliPath,
                '-p', 'Translate the entity metadata from the input below. Return JSON with translations key containing ' . $locale . '.',
                '-o', 'json',
            ],
            cwd: $this->projectDir,
            env: ['HOME' => '/home/radu', 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'],
            timeout: self::TIMEOUT,
        );

        $process->setInput($stdinContent);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Gemini CLI failed (exit ' . $process->getExitCode() . '): '
                . $process->getErrorOutput()
            );
        }

        $output = trim($process->getOutput());

        if (empty($output)) {
            throw new \RuntimeException('Gemini CLI returned empty output');
        }

        return $output;
    }

    /**
     * Parse Gemini CLI output, handling both direct JSON and envelope format.
     *
     * @return array<string, mixed>
     */
    private function parseGeminiOutput(string $rawOutput): array
    {
        $cleaned = trim($rawOutput);

        // Strip control characters
        $map = [];
        for ($i = 0; $i <= 0x1F; ++$i) {
            $map[\chr($i)] = ' ';
        }
        $map[\chr(0x7F)] = ' ';
        $cleaned = strtr($cleaned, $map);

        $decoded = json_decode($cleaned, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('Entity translation JSON did not decode to an array');
        }

        // Handle Gemini envelope { session_id, response, stats }
        if (isset($decoded['response']) && \is_string($decoded['response'])) {
            $responseStr = $decoded['response'];

            // Strip markdown JSON wrappers
            $responseStr = preg_replace('/^```(?:json)?\s*/m', '', $responseStr);
            $responseStr = preg_replace('/\s*```\s*$/m', '', $responseStr ?? $decoded['response']);
            $responseStr = trim($responseStr ?? $decoded['response']);

            // Extract JSON from possible preamble
            $jsonStart = strpos($responseStr, '{');
            $jsonEnd = strrpos($responseStr, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $responseStr = substr($responseStr, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            // Strip control characters
            $responseStr = strtr($responseStr, $map);

            $inner = json_decode($responseStr, true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($inner)) {
                return $inner;
            }
        }

        // Direct JSON with translations key
        if (isset($decoded['translations'])) {
            return $decoded;
        }

        throw new \RuntimeException('Invalid entity translation response: missing "translations" key');
    }
}
