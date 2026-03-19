<?php

declare(strict_types=1);

namespace App\Service\Archive;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Service pentru procesarea conținutului din bazele de date legacy (Newscoop/Beta).
 *
 * Transformă shortcodes proprietare în HTML modern:
 * - <!** Image X> → <figure><img></figure>
 * - <!** Link Internal ...> → <a href="...">
 * - <!** Title> → <h3>
 */
class ContentProcessor
{
    public const SOURCE_NEWSCOOP = 'newscoop';
    public const SOURCE_BETA = 'beta';

    // Regex patterns pentru shortcodes
    private const IMAGE_PATTERN = '/<!\\*\\*\\s*Image\\s+(\\d+)(?:\\s+align="([^"]*)")?(?:\\s+alt="([^"]*)")?(?:\\s+sub="([^"]*)")?(?:\\s+width="([^"]*)")?(?:\\s+height="([^"]*)")?\\s*>/i';
    private const LINK_INTERNAL_PATTERN = '/<!\\*\\*\\s*Link\\s+Internal\\s+([^>]+?)(?:\\s+TARGET\\s+([^>]+))?\\s*>(.*?)<!\\*\\*\\s*EndLink\\s*>/is';
    private const TITLE_PATTERN = '/<!\\*\\*\\s*Title\\s*>(.*?)<!\\*\\*\\s*EndTitle\\s*>/is';
    private const SNIPPET_PATTERN = '/<!--\\s*Snippet\\s+\\d+\\s*-->/i';

    // Cache pentru image lookups
    private array $imageCache = [];

    // Statistici procesare
    private array $stats = [
        'images_processed' => 0,
        'images_not_found' => 0,
        'links_processed' => 0,
        'links_broken' => 0,
        'titles_converted' => 0,
        'snippets_removed' => 0,
    ];

    public function __construct(
        private readonly Connection $newscoopConnection,
        private readonly Connection $betaDeschideConnection,
        private readonly LoggerInterface $logger,
        private readonly string $archiveImageBaseUrl = '/images/alpha',
        private readonly string $betaImageBaseUrl = '/images/beta',
    ) {
    }

    /**
     * Procesează conținutul unui articol și transformă shortcodes în HTML.
     *
     * @param string $content Conținutul brut din baza de date
     * @param int $articleNumber Numărul articolului (pentru lookup imagini)
     * @param string $source Sursa: 'newscoop' sau 'beta'
     * @param int $languageId ID-ul limbii (pentru lookup)
     *
     * @return string Conținutul procesat cu HTML modern
     */
    public function processContent(
        string $content,
        int $articleNumber,
        string $source = self::SOURCE_NEWSCOOP,
        int $languageId = 2
    ): string {
        // Reset stats pentru acest articol
        $this->resetStats();

        // 1. Procesează image shortcodes
        $content = $this->processImageShortcodes($content, $articleNumber, $source, $languageId);

        // 2. Procesează internal links
        $content = $this->processInternalLinks($content, $source);

        // 3. Procesează title/subheading shortcodes
        $content = $this->processTitleShortcodes($content);

        // 4. Elimină snippet shortcodes
        $content = $this->removeSnippets($content);

        // 5. Sanitizează și curăță HTML-ul
        $content = $this->sanitizeHtml($content);

        return $content;
    }

    /**
     * Transformă shortcodes <!** Image X> în HTML <figure><img>.
     */
    public function processImageShortcodes(
        string $content,
        int $articleNumber,
        string $source,
        int $languageId
    ): string {
        return preg_replace_callback(
            self::IMAGE_PATTERN,
            function (array $matches) use ($articleNumber, $source, $languageId) {
                return $this->transformImageShortcode($matches, $articleNumber, $source, $languageId);
            },
            $content
        ) ?? $content;
    }

    /**
     * Transformă un singur image shortcode în HTML.
     */
    private function transformImageShortcode(
        array $matches,
        int $articleNumber,
        string $source,
        int $languageId
    ): string {
        $attachmentNumber = (int) $matches[1];
        $align = $matches[2] ?? null;
        $alt = $matches[3] ?? '';
        $caption = $matches[4] ?? null;
        $width = $matches[5] ?? null;
        $height = $matches[6] ?? null;

        // Lookup imagine în baza de date
        $imageData = $this->lookupImage($articleNumber, $attachmentNumber, $source, $languageId);

        if (!$imageData) {
            $this->stats['images_not_found']++;
            $this->logger->warning('Image not found for shortcode', [
                'article_number' => $articleNumber,
                'attachment_number' => $attachmentNumber,
                'source' => $source,
            ]);

            // Returnează comentariu HTML pentru debugging
            return sprintf('<!-- Image %d not found -->', $attachmentNumber);
        }

        $this->stats['images_processed']++;

        // Construiește URL-ul imaginii
        $baseUrl = $source === self::SOURCE_BETA ? $this->betaImageBaseUrl : $this->archiveImageBaseUrl;
        $imageUrl = $baseUrl . '/' . $imageData['filename'];

        // Generează HTML modern
        return $this->generateFigureHtml(
            $imageUrl,
            $alt ?: ($imageData['description'] ?? ''),
            $caption,
            $align,
            $width ? (int) $width : ($imageData['width'] ?? null),
            $height ? (int) $height : ($imageData['height'] ?? null)
        );
    }

    /**
     * Lookup imagine în ArticleImages și Images tables.
     *
     * @return array|null ['filename' => ..., 'description' => ..., 'width' => ..., 'height' => ...]
     */
    private function lookupImage(
        int $articleNumber,
        int $attachmentNumber,
        string $source,
        int $languageId
    ): ?array {
        $cacheKey = sprintf('%s_%d_%d_%d', $source, $articleNumber, $attachmentNumber, $languageId);

        if (isset($this->imageCache[$cacheKey])) {
            return $this->imageCache[$cacheKey];
        }

        $connection = $source === self::SOURCE_BETA
            ? $this->betaDeschideConnection
            : $this->newscoopConnection;

        try {
            // Query pentru a obține Image ID din ArticleImages
            $sql = '
                SELECT i.Id, i.ImageFileName as filename, i.Description as description,
                       i.width, i.height, i.ContentType as content_type
                FROM ArticleImages ai
                JOIN Images i ON ai.IdImage = i.Id
                WHERE ai.NrArticle = :articleNumber
                  AND ai.Number = :attachmentNumber
                LIMIT 1
            ';

            $result = $connection->fetchAssociative($sql, [
                'articleNumber' => $articleNumber,
                'attachmentNumber' => $attachmentNumber,
            ]);

            $this->imageCache[$cacheKey] = $result ?: null;

            return $result ?: null;
        } catch (\Exception $e) {
            $this->logger->error('Error looking up image', [
                'article_number' => $articleNumber,
                'attachment_number' => $attachmentNumber,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generează HTML <figure> modern.
     */
    private function generateFigureHtml(
        string $imageUrl,
        string $alt,
        ?string $caption,
        ?string $align,
        ?int $width,
        ?int $height
    ): string {
        // Mapare align la clase CSS
        $alignClass = match ($align) {
            'left' => 'float-left',
            'right' => 'float-right',
            'middle', 'center' => 'mx-auto',
            default => '',
        };

        $figureClasses = array_filter(['figure', $alignClass]);

        $html = sprintf('<figure class="%s">', implode(' ', $figureClasses));

        // Tag img cu atribute
        $imgAttrs = [
            'src' => htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'),
            'alt' => htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'),
            'loading' => 'lazy',
        ];

        if ($width) {
            $imgAttrs['width'] = (string) $width;
        }
        if ($height) {
            $imgAttrs['height'] = (string) $height;
        }

        $imgHtml = '<img';
        foreach ($imgAttrs as $name => $value) {
            $imgHtml .= sprintf(' %s="%s"', $name, $value);
        }
        $imgHtml .= '>';

        $html .= $imgHtml;

        // Caption (figcaption)
        if ($caption) {
            $html .= sprintf('<figcaption>%s</figcaption>', htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'));
        }

        $html .= '</figure>';

        return $html;
    }

    /**
     * Transformă shortcodes <!** Link Internal ...> în <a href="...">.
     */
    public function processInternalLinks(string $content, string $source): string
    {
        return preg_replace_callback(
            self::LINK_INTERNAL_PATTERN,
            function (array $matches) use ($source) {
                return $this->transformInternalLink($matches, $source);
            },
            $content
        ) ?? $content;
    }

    /**
     * Transformă un singur internal link shortcode în HTML <a>.
     */
    private function transformInternalLink(array $matches, string $source): string
    {
        $queryString = trim($matches[1]);
        $target = isset($matches[2]) ? trim($matches[2]) : null;
        $linkText = $matches[3];

        // Parse query string: IdPublication=1&IdLanguage=2&NrArticle=123
        parse_str(str_replace('&amp;', '&', $queryString), $params);

        $articleNumber = isset($params['NrArticle']) ? (int) $params['NrArticle'] : null;

        if (!$articleNumber) {
            $this->stats['links_broken']++;
            $this->logger->warning('Invalid internal link shortcode', [
                'query_string' => $queryString,
                'source' => $source,
            ]);

            // Returnează doar textul link-ului
            return $linkText;
        }

        $this->stats['links_processed']++;

        // Generează URL pentru arhivă
        $url = sprintf('/arhiva/article/%d', $articleNumber);

        // Construiește tag <a>
        $targetAttr = $target ? sprintf(' target="%s"', htmlspecialchars($target, ENT_QUOTES, 'UTF-8')) : '';

        return sprintf(
            '<a href="%s"%s>%s</a>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            $targetAttr,
            $linkText
        );
    }

    /**
     * Transformă shortcodes <!** Title> în <h3>.
     */
    public function processTitleShortcodes(string $content): string
    {
        $result = preg_replace_callback(
            self::TITLE_PATTERN,
            function (array $matches) {
                $this->stats['titles_converted']++;

                return sprintf('<h3 class="article-subheading">%s</h3>', $matches[1]);
            },
            $content
        );

        return $result ?? $content;
    }

    /**
     * Elimină snippet shortcodes (<!-- Snippet X -->).
     */
    public function removeSnippets(string $content): string
    {
        $result = preg_replace_callback(
            self::SNIPPET_PATTERN,
            function () {
                $this->stats['snippets_removed']++;

                return '';
            },
            $content
        );

        return $result ?? $content;
    }

    /**
     * Sanitizează și curăță HTML-ul.
     */
    public function sanitizeHtml(string $content): string
    {
        // Elimină tag-uri goale
        $content = preg_replace('/<(\w+)>\s*<\/\1>/i', '', $content) ?? $content;

        // Elimină multiple spații/newlines consecutive
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        // Convertește newlines în <br> dacă nu sunt în blocuri
        // (opțional, depinde de nevoile specifice)

        // Elimină atribute style inline periculoase
        $content = preg_replace('/\s+style\s*=\s*"[^"]*"/i', '', $content) ?? $content;

        // Elimină atribute onclick și alte event handlers
        $content = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $content) ?? $content;

        return trim($content);
    }

    /**
     * Returnează statisticile procesării curente.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Resetează statisticile.
     */
    public function resetStats(): void
    {
        $this->stats = [
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ];
    }

    /**
     * Golește cache-ul de imagini.
     */
    public function clearImageCache(): void
    {
        $this->imageCache = [];
    }

    /**
     * Verifică dacă conținutul are shortcodes care necesită procesare.
     */
    public function hasShortcodes(string $content): bool
    {
        return (bool) preg_match(self::IMAGE_PATTERN, $content)
            || (bool) preg_match(self::LINK_INTERNAL_PATTERN, $content)
            || (bool) preg_match(self::TITLE_PATTERN, $content);
    }

    /**
     * Numără shortcodes într-un conținut.
     *
     * @return array<string, int>
     */
    public function countShortcodes(string $content): array
    {
        return [
            'images' => preg_match_all(self::IMAGE_PATTERN, $content),
            'internal_links' => preg_match_all(self::LINK_INTERNAL_PATTERN, $content),
            'titles' => preg_match_all(self::TITLE_PATTERN, $content),
            'snippets' => preg_match_all(self::SNIPPET_PATTERN, $content),
        ];
    }
}
