<?php

declare(strict_types=1);

namespace App\Service;

use PhpOffice\PhpWord\IOFactory;

/**
 * Extracts text content from .docx files, preserving paragraph structure.
 */
class DocxExtractor
{
    /**
     * Extract text from a .docx file path, returning HTML paragraphs.
     */
    public function extractHtml(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            $phpWord = IOFactory::load($filePath, 'Word2007');
        } catch (\Throwable) {
            return null;
        }

        $html = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text = $this->extractElementText($element);
                if ($text !== '' && mb_strlen($text) > 5) {
                    $html .= '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
                }
            }
        }

        return $html !== '' ? $html : null;
    }

    /**
     * Extract plain text from a .docx file.
     */
    public function extractText(string $filePath): ?string
    {
        $html = $this->extractHtml($filePath);
        if ($html === null) {
            return null;
        }

        return trim(strip_tags($html));
    }

    private function extractElementText(mixed $element): string
    {
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text)) {
                return trim($text);
            }
        }

        if (method_exists($element, 'getElements')) {
            $parts = [];
            foreach ($element->getElements() as $child) {
                $childText = $this->extractElementText($child);
                if ($childText !== '') {
                    $parts[] = $childText;
                }
            }
            return implode(' ', $parts);
        }

        return '';
    }
}
