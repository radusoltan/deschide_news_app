<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class SeoResultProcessor
{
    public function __construct(
        private TagService $tagService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Process the parsed Gemini SEO response.
     *
     * @param array{generateMeta: bool, suggestTags: bool, force: bool} $options
     *
     * @return array{metaTitle: ?string, metaDescription: ?string, tagsAdded: string[], tagsExisting: string[]}
     */
    public function process(Article $article, array $data, array $options, string $locale = 'ro'): array
    {
        $result = [
            'metaTitle' => null,
            'metaDescription' => null,
            'tagsAdded' => [],
            'tagsExisting' => [],
        ];

        // Process metaTitle
        if ($options['generateMeta'] && isset($data['metaTitle']) && \is_string($data['metaTitle'])) {
            $metaTitle = trim($data['metaTitle']);

            if ($metaTitle !== '') {
                // Truncate at last space before 60 chars if needed
                if (mb_strlen($metaTitle) > 60) {
                    $metaTitle = $this->truncateAtWord($metaTitle, 60);
                }

                if ($article->getMetaTitle() === null || $options['force']) {
                    $article->setMetaTitle($metaTitle);
                    $result['metaTitle'] = $metaTitle;

                    $this->logger->info('SeoResultProcessor: metaTitle set', [
                        'articleId' => $article->getId(),
                        'length' => mb_strlen($metaTitle),
                    ]);
                }
            }
        }

        // Process metaDescription
        if ($options['generateMeta'] && isset($data['metaDescription']) && \is_string($data['metaDescription'])) {
            $metaDesc = trim($data['metaDescription']);

            if ($metaDesc !== '') {
                // Truncate at last space before 160 chars if needed
                if (mb_strlen($metaDesc) > 160) {
                    $metaDesc = $this->truncateAtWord($metaDesc, 160);
                }

                if ($article->getMetaDescription() === null || $options['force']) {
                    $article->setMetaDescription($metaDesc);
                    $result['metaDescription'] = $metaDesc;

                    $this->logger->info('SeoResultProcessor: metaDescription set', [
                        'articleId' => $article->getId(),
                        'length' => mb_strlen($metaDesc),
                    ]);
                }
            }
        }

        // Process suggested tags
        if ($options['suggestTags'] && isset($data['suggestedTags']) && \is_array($data['suggestedTags'])) {
            $tagNamesToAdd = [];

            foreach ($data['suggestedTags'] as $tagData) {
                if (!\is_array($tagData) || !isset($tagData['name']) || !\is_string($tagData['name'])) {
                    continue;
                }

                $tagName = trim($tagData['name']);
                if ($tagName === '') {
                    continue;
                }

                $isNew = $tagData['isNew'] ?? false;

                $tagNamesToAdd[] = $tagName;

                if ($isNew) {
                    $result['tagsAdded'][] = $tagName;
                } else {
                    $result['tagsExisting'][] = $tagName;
                }
            }

            if ($tagNamesToAdd !== []) {
                // addTagsToArticle handles find-or-create and deduplication
                $this->tagService->addTagsToArticle($article, $tagNamesToAdd, $locale);

                $this->logger->info('SeoResultProcessor: tags processed', [
                    'articleId' => $article->getId(),
                    'added' => $result['tagsAdded'],
                    'existing' => $result['tagsExisting'],
                ]);
            }
        }

        $this->em->flush();

        return $result;
    }

    /**
     * Truncate string at the last space before $maxLen.
     */
    private function truncateAtWord(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLen);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > $maxLen * 0.5) {
            return mb_substr($text, 0, $lastSpace);
        }

        return $truncated;
    }
}
