<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;

class CategoryDetectorService
{
    /**
     * Category keywords — ORDER MATTERS (first match wins).
     * Most specific categories first, generic ones last.
     * All keywords are lowercase, diacritics removed.
     *
     * @var array<string, list<string>>
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

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * Detect category from text content.
     * Returns Category entity if found, null if only fallback matches.
     */
    public function detect(string $title, string $body = ''): ?Category
    {
        $slug = $this->detectSlug($title . ' ' . $body);

        return $this->categoryRepository->findOneBy(['slug' => $slug]);
    }

    public function detectSlug(string $text): string
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

    private function removeDiacritics(string $text): string
    {
        $map = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T',
            'ş' => 's', 'ţ' => 't', 'Ş' => 'S', 'Ţ' => 'T',
        ];

        return strtr($text, $map);
    }
}
