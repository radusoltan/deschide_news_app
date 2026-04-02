import Link from 'next/link';

const translations = {
  ro: {
    text: 'Articolele publicate pe',
    siteName: 'deschide.md',
    rights: 'sunt protejate de Legea drepturilor de autor. Preluarea integrala sau partiala a continutului este permisa doar cu mentionarea sursei si a unui link activ catre articolul original.',
  },
  en: {
    text: 'Articles published on',
    siteName: 'deschide.md',
    rights: 'are protected by copyright law. Full or partial reproduction is permitted only with source attribution and an active link to the original article.',
  },
  ru: {
    text: 'Статьи, опубликованные на',
    siteName: 'deschide.md',
    rights: 'защищены законом об авторском праве. Полное или частичное воспроизведение допускается только с указанием источника и активной ссылки на оригинальную статью.',
  },
} as const;

interface ArticleDisclaimerProps {
  locale: string;
}

export default function ArticleDisclaimer({ locale }: ArticleDisclaimerProps) {
  const t = translations[locale as keyof typeof translations] || translations.ro;

  return (
    <div className="mt-8 py-4 px-5 border-t border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-elevated-dark)]">
      <p className="text-sm leading-relaxed text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] font-sans">
        {t.text}{' '}
        <Link
          href="https://deschide.md"
          target="_blank"
          rel="noopener noreferrer"
          className="font-semibold text-[var(--color-accent)] hover:underline"
        >
          {t.siteName}
        </Link>{' '}
        {t.rights}
      </p>
    </div>
  );
}
