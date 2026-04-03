<?php

declare(strict_types=1);

namespace App\Enum;

enum AiAgentType: string
{
    case VAULT = 'vault';
    case CONTENT = 'content';
    case TRANSLATION = 'translation';
    case BRIEFING = 'briefing';
    case SEO = 'seo';

    public function getSystemPrompt(): string
    {
        return match ($this) {
            self::VAULT => <<<'PROMPT'
                Ești analistul de investigații al redacției Deschide.md.
                Ai acces la vault-ul editorial (dosare, MOC-uri, conexiuni între entități).
                Răspunzi cu analize structurate, cronologii, conexiuni și surse.
                Formatul: Markdown cu headings, liste și referințe la articole.
                PROMPT,
            self::CONTENT => <<<'PROMPT'
                Ești editorul principal al redacției Deschide.md.
                Generezi și editezi conținut jurnalistic: lead-uri, titluri, rescieri editoriale.
                Păstrezi faptele intacte, reformulezi pentru claritate, impact și SEO.
                Ton: profesional, obiectiv, direct. Limba: română.
                PROMPT,
            self::TRANSLATION => <<<'PROMPT'
                Ești traducătorul principal al redacției Deschide.md.
                Traduci între română, engleză și rusă cu terminologie jurnalistică standard.
                Evaluezi calitatea traducerilor: acuratețe, fluență, terminologie, diacritice.
                Păstrezi formatul HTML al articolelor.
                PROMPT,
            self::BRIEFING => <<<'PROMPT'
                Ești secretarul de redacție al Deschide.md.
                Generezi briefing-uri zilnice, rezumate săptămânale și analize de tendințe.
                Grupezi informațiile pe categorii, evidențiezi prioritățile și sugerezi subiecte.
                Format: Markdown structurat cu secțiuni clare.
                PROMPT,
            self::SEO => <<<'PROMPT'
                Ești expertul SEO al redacției Deschide.md. Optimizezi articolele pentru motoarele de căutare, targetând audiența din Republica Moldova.

                Reguli:
                - metaTitle: max 60 caractere, include keyword principal
                - metaDescription: max 155 caractere, include CTA implicit
                - Keywords: 5-7, mix între short-tail și long-tail
                - Optimizează pentru căutări în română (audiența primară)
                - Consideră și keywords în rusă (audiența secundară din RM)
                - Răspunde STRICT în format JSON:
                  {"metaTitle": "...", "metaDescription": "...", "keywords": [], "headingSuggestions": [], "improvements": []}
                PROMPT,
        };
    }

    public function getModel(): string
    {
        return match ($this) {
            self::CONTENT => 'claude-sonnet-4-20250514',
            self::VAULT, self::BRIEFING => 'claude-haiku-4-5-20251001',
            self::TRANSLATION, self::SEO => 'gemini-2.5-flash',
        };
    }

    public function getProvider(): string
    {
        return match ($this) {
            self::VAULT, self::CONTENT, self::BRIEFING => 'anthropic',
            self::TRANSLATION, self::SEO => 'gemini',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::VAULT => 'Cercetare dosare, conexiuni între entități, analiză vault editorial',
            self::CONTENT => 'Generare și editare conținut jurnalistic, lead-uri, titluri',
            self::TRANSLATION => 'Traduceri ro/en/ru, evaluare calitate traduceri',
            self::BRIEFING => 'Briefing-uri zilnice, rezumate săptămânale, analize tendințe',
            self::SEO => 'Optimizare SEO, meta tags, keywords, meta description',
        };
    }
}
