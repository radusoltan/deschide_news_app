'use client';

interface LanguageBadgeProps {
  language: string;
}

const LANG_CONFIG: Record<string, { flag: string; label: string }> = {
  en: { flag: '🇬🇧', label: 'EN' },
  ru: { flag: '🇷🇺', label: 'RU' },
  it: { flag: '🇮🇹', label: 'IT' },
  de: { flag: '🇩🇪', label: 'DE' },
  fr: { flag: '🇫🇷', label: 'FR' },
  es: { flag: '🇪🇸', label: 'ES' },
  pt: { flag: '🇵🇹', label: 'PT' },
};

export default function LanguageBadge({ language }: LanguageBadgeProps) {
  if (language === 'ro' || !language) return null;
  const config = LANG_CONFIG[language] ?? { flag: '🌐', label: language.toUpperCase() };
  return (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
      {config.flag} {config.label}
    </span>
  );
}
