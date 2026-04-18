<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Editorial/political alignment taxonomy for editorial-pipeline signal sources.
 *
 * Defined by ADR-020 D4 (Editorial AI Multi-Agent Pipeline).
 *
 * This enum is orthogonal to {@see SourceCategory} — `SourceCategory` classifies
 * what *kind* of source it is (agency, regional, institutional, ...), while
 * `EditorialAlignment` classifies the source's editorial stance/alignment for
 * the verification layer (who backs the source, with what bias).
 */
enum EditorialAlignment: string
{
    case WIRE_NEUTRAL          = 'wire_neutral';          // Reuters, AP, AFP
    case WESTERN_MAINSTREAM    = 'western_mainstream';    // BBC, FT, NYT, Bloomberg, Politico EU
    case EU_OFFICIAL           = 'eu_official';           // ec.europa.eu, consilium.europa.eu, europarl.europa.eu
    case KREMLIN_ALIGNED       = 'kremlin_aligned';       // TASS, RIA, RT, Kommersant, kremlin.ru
    case INDEPENDENT_RU        = 'independent_ru';        // Meduza, Novaya Gazeta Europe
    case UKRAINIAN_STATE       = 'ukrainian_state';       // Ukrinform, Suspilne
    case UKRAINIAN_INDEPENDENT = 'ukrainian_independent'; // Kyiv Independent, Ukrainska Pravda
    case MD_GOVERNMENT         = 'md_government';         // moldpres.md, gov.md, CEC
    case MD_INDEPENDENT_PRO_EU = 'md_independent_pro_eu'; // Agora, NewsMaker, RFE/RL Moldova
    case MD_INDEPENDENT_PRO_RU = 'md_independent_pro_ru'; // curated individually
    case MD_INVESTIGATIVE      = 'md_investigative';      // Ziarul de Gardă, RISE Moldova
    case RO_MAINSTREAM         = 'ro_mainstream';         // Digi24, HotNews, G4Media
    case OSINT_CURATED         = 'osint_curated';         // Bellingcat, ISW
}
