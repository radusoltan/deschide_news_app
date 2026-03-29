<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Parses press release emails from IPN, Gov, Moldpres etc.
 * Extracts clean text, source URL, title, and metadata.
 */
class PressEmailParser
{
    /**
     * Category slug mapping: keyword patterns → category slug.
     * Order matters — first match wins.
     *
     * @var array<string, list<string>>
     */
    /**
     * Category keywords — ORDER MATTERS (first match wins).
     * Most specific categories first, generic ones last.
     * All keywords are lowercase, diacritics removed.
     */
    private const CATEGORY_KEYWORDS = [
        'economie' => ['economi', 'finant', 'buget', 'energi', 'infrastructur', 'transport', 'feroviar', 'electric', 'investi', ' pib ', ' bnm ', 'banca', 'estacad'],
        'politica' => ['politic', 'partid', 'alegeri', 'parlament', 'guvern', 'reform', 'deputat', 'vot', 'electoral', 'coalit', 'primar', 'lege'],
        'mediu' => ['ecologi', 'poluare', 'climat', 'pamant', 'salubriz', 'reciclare', 'deseu', 'plantar', 'arbor'],
        'justitie' => ['procuratur', 'judecat', 'penal', ' csm ', 'instan', 'corupti'],
        'externe' => [' nato ', ' ue ', 'diplomat', 'ambasad', 'ucraina', 'bruxelles', 'bucuresti'],
        'sanatate' => ['sanata', 'medical', 'spital', 'pacient', 'boal'],
        'educatie' => ['educat', 'universit', 'scoal', 'elev', 'student', 'liceu'],
        'cultura' => ['cultur', 'teatru', 'muzeu', 'patrimoniu', 'festival', 'film', 'artist'],
        'sport' => ['sport', 'fotbal', 'campionat', 'olimpi'],
        'societate' => [], // fallback — always last
    ];

    /**
     * Parse email HTML and extract structured article data.
     *
     * @return array{title: string, lead: string, content: string, sourceUrl: string|null, sourceName: string, categorySlug: string, textLength: int}|null
     */
    public function parse(string $html, string $subject, string $fromAddress): ?array
    {
        $text = $this->stripHtml($html);

        // Skip "Flux de știri" / "Agenda" digest emails
        if (preg_match('/^(IPN:\s*)?Flux de știri/ui', $subject) || preg_match('/^Agenda:/ui', $subject)) {
            return null;
        }

        // Editorialists/columnists may send shorter emails with links to full articles
        $minLength = $this->isEditorialist($fromAddress) ? 100 : 300;
        if (mb_strlen($text) < $minLength) {
            return null;
        }

        $sourceUrl = $this->extractSourceUrl($html);
        $sourceName = $this->resolveSourceName($fromAddress);
        $paragraphs = $this->extractParagraphs($html);
        $title = $this->extractTitle($html, $subject);
        $lead = $this->buildLead($paragraphs);
        $content = $this->buildContent($paragraphs, $sourceName, $sourceUrl);
        // Prioritize title + lead for category detection (body text has too much noise)
        $categorySlug = $this->detectCategory($title . ' ' . $lead);

        $contentText = strip_tags($content);
        if (mb_strlen($contentText) < 800) {
            // If structured extraction is too short, use all text
            $content = $this->buildContentFromFullText($text, $sourceName, $sourceUrl);
            $contentText = strip_tags($content);
        }

        return [
            'title' => mb_substr($title, 0, 100),
            'lead' => mb_substr($lead, 0, 300),
            'content' => $content,
            'sourceUrl' => $sourceUrl,
            'sourceName' => $sourceName,
            'categorySlug' => $categorySlug,
            'textLength' => mb_strlen($contentText),
        ];
    }

    private function stripHtml(string $html): string
    {
        // Remove style blocks
        $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html) ?? $html;
        // Remove script blocks
        $text = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $text) ?? $text;
        // Convert block elements to newlines
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr|br\s*\/?)>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        // Strip remaining tags
        $text = strip_tags($text);
        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Extract meaningful paragraphs from email HTML.
     *
     * @return string[]
     */
    private function extractParagraphs(string $html): array
    {
        // Remove style/script blocks
        $clean = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html) ?? $html;
        $clean = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $clean) ?? $clean;

        // Extract text from <p> tags and <div> content
        $paragraphs = [];

        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/si', $clean, $matches)) {
            foreach ($matches[1] as $p) {
                $text = trim(strip_tags(html_entity_decode($p, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                // Filter out noise: dates, links only, footer text, very short fragments
                if (mb_strlen($text) > 50
                    && !preg_match('/^https?:\/\//', $text)
                    && !preg_match('/^©\s*\d{4}/i', $text)
                    && !preg_match('/Urmăriți-ne/i', $text)
                    && !preg_match('/All Rights Reserved/i', $text)
                    && !preg_match('/^\d{2}-\d{2}-\d{4}/', $text)
                ) {
                    $paragraphs[] = $text;
                }
            }
        }

        // If no <p> extraction, fallback to plain text split
        if (empty($paragraphs)) {
            $text = $this->stripHtml($html);
            $lines = preg_split('/\n{2,}/', $text) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (mb_strlen($line) > 50) {
                    $paragraphs[] = $line;
                }
            }
        }

        return $paragraphs;
    }

    private function extractTitle(string $html, string $subject): string
    {
        // Try <h3> from IPN emails
        if (preg_match('/<h3[^>]*>(.*?)<\/h3>/si', $html, $m)) {
            $title = trim(strip_tags(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            // Skip generic headers like "IPN: Flux de știri"
            if (mb_strlen($title) > 15 && !preg_match('/^IPN:/i', $title)) {
                return $title;
            }
        }

        // Clean up subject line (remove prefixes)
        $title = preg_replace('/^(Comunicat de presă\s*[-–—]\s*)/ui', '', $subject) ?? $subject;
        $title = preg_replace('/^(IPN:\s*)/ui', '', $title) ?? $title;

        return trim($title);
    }

    private function buildLead(array $paragraphs): string
    {
        if (empty($paragraphs)) {
            return '';
        }

        $lead = $paragraphs[0];

        // If first paragraph is too short, combine with second
        if (mb_strlen($lead) < 100 && isset($paragraphs[1])) {
            $lead .= ' ' . $paragraphs[1];
        }

        return mb_substr($lead, 0, 300);
    }

    private function buildContent(array $paragraphs, string $sourceName, ?string $sourceUrl): string
    {
        if (empty($paragraphs)) {
            return '';
        }

        $html = '';
        foreach ($paragraphs as $p) {
            $html .= '<p>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $html .= $this->buildSourceParagraph($sourceName, $sourceUrl);

        return $html;
    }

    private function buildContentFromFullText(string $text, string $sourceName, ?string $sourceUrl): string
    {
        // Remove header noise (site name, date lines)
        $text = preg_replace('/^.*?https:\/\/ipn\.md\s*/s', '', $text, 1) ?? $text;
        $text = preg_replace('/^\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2}\s*/m', '', $text) ?? $text;
        // Remove footer noise
        $text = preg_replace('/Urmăriți-ne.*$/si', '', $text) ?? $text;
        $text = preg_replace('/©\s*\d{4}.*$/si', '', $text) ?? $text;
        $text = preg_replace('/Mai multe detalii pe site-ul:.*$/si', '', $text) ?? $text;

        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (mb_strlen($p) > 30) {
                $html .= '<p>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }

        $html .= $this->buildSourceParagraph($sourceName, $sourceUrl);

        return $html;
    }

    private function buildSourceParagraph(string $sourceName, ?string $sourceUrl): string
    {
        if ($sourceUrl !== null) {
            return '<p>Sursă: <a href="' . htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }

        return '<p>Sursă: ' . htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    private function extractSourceUrl(string $html): ?string
    {
        // Match article URLs from known news agencies
        $patterns = [
            '/href="(https?:\/\/ipn\.md\/[^"]+)"/i',
            '/href="(https?:\/\/(?:www\.)?gov\.md\/[^"]+)"/i',
            '/href="(https?:\/\/(?:www\.)?moldpres\.md\/[^"]+)"/i',
            '/href="(https?:\/\/(?:www\.)?presidency\.md\/[^"]+)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function resolveSourceName(string $fromAddress): string
    {
        return match (true) {
            str_contains($fromAddress, 'ipn.md') => 'IPN (Info-Prim Neo)',
            str_contains($fromAddress, 'gov.md') => 'Guvernul Republicii Moldova',
            str_contains($fromAddress, 'moldpres.md') => 'Moldpres',
            str_contains($fromAddress, 'presidency.md') => 'Președinția Republicii Moldova',
            str_contains($fromAddress, 'parlament.md') => 'Parlamentul Republicii Moldova',
            str_contains($fromAddress, 'bnm.md') => 'Banca Națională a Moldovei',
            str_contains($fromAddress, 'pnru.md') => 'Partidul Nostru',
            str_contains($fromAddress, 'vvovc@yahoo.fr') => 'Vitalie Vovc',
            default => $fromAddress,
        };
    }

    private function detectCategory(string $text): string
    {
        $normalized = $this->removeDiacritics(mb_strtolower($text));

        foreach (self::CATEGORY_KEYWORDS as $slug => $keywords) {
            if ($slug === 'societate') {
                continue; // fallback
            }
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, mb_strtolower($keyword))) {
                    return $slug;
                }
            }
        }

        return 'societate';
    }

    private function isEditorialist(string $fromAddress): bool
    {
        $editorialists = ['vvovc@yahoo.fr'];

        foreach ($editorialists as $email) {
            if (str_contains(strtolower($fromAddress), $email)) {
                return true;
            }
        }

        return false;
    }

    private function removeDiacritics(string $text): string
    {
        $map = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T',
            'ş' => 's', 'ţ' => 't', 'Ş' => 'S', 'Ţ' => 'T', // cedilla variants
        ];

        return strtr($text, $map);
    }
}
