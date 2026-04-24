import type { Locale } from '@/lib/types';

const DEFAULT_LOCALE: Locale = 'ro';
const SUPPORTED_LOCALES = ['ro', 'en', 'ru'] as const;

const localeLabels: Record<Locale, string> = {
  ro: 'română',
  en: 'engleză',
  ru: 'rusă',
};

export interface LocaleFallbackResult<T> {
  content: T | null;
  requestedLocale: Locale;
  effectiveLocale: Locale;
  isFallback: boolean;
  translationPending: boolean;
}

interface FallbackOptions<T> {
  isMissing?: (content: T) => boolean;
  isTranslationPending?: (content: T | null) => boolean;
}

export function isSupportedLocale(locale: string): locale is Locale {
  return SUPPORTED_LOCALES.includes(locale as Locale);
}

export function hasPendingTranslation(content: unknown): boolean {
  if (!content || typeof content !== 'object' || !('translationStatus' in content)) {
    return false;
  }

  const status = String((content as { translationStatus?: unknown }).translationStatus ?? '').toLowerCase();
  return status === 'pending' || status === 'processing' || status === 'in_progress';
}

async function safelyLoadContent<T>(
  locale: Locale,
  loadContent: (locale: Locale) => Promise<T | null>
): Promise<T | null> {
  try {
    return await loadContent(locale);
  } catch {
    return null;
  }
}

export async function getFallbackContent<T>(
  requestedLocale: string,
  loadContent: (locale: Locale) => Promise<T | null>,
  options: FallbackOptions<T> = {}
): Promise<LocaleFallbackResult<T>> {
  const normalizedLocale = isSupportedLocale(requestedLocale)
    ? requestedLocale
    : DEFAULT_LOCALE;
  const isMissing = options.isMissing ?? (() => false);

  const requestedContent = await safelyLoadContent(normalizedLocale, loadContent);
  const requestedMissing = requestedContent === null || isMissing(requestedContent);
  const translationPending = options.isTranslationPending?.(requestedContent) ?? false;

  if (!requestedMissing) {
    return {
      content: requestedContent,
      requestedLocale: normalizedLocale,
      effectiveLocale: normalizedLocale,
      isFallback: false,
      translationPending,
    };
  }

  if (normalizedLocale !== DEFAULT_LOCALE) {
    const defaultContent = await safelyLoadContent(DEFAULT_LOCALE, loadContent);
    const defaultMissing = defaultContent === null || isMissing(defaultContent);

    if (!defaultMissing) {
      return {
        content: defaultContent,
        requestedLocale: normalizedLocale,
        effectiveLocale: DEFAULT_LOCALE,
        isFallback: true,
        translationPending,
      };
    }
  }

  return {
    content: requestedContent,
    requestedLocale: normalizedLocale,
    effectiveLocale: normalizedLocale,
    isFallback: false,
    translationPending,
  };
}

export function LocaleFallbackNotice({
  requestedLocale,
  effectiveLocale = DEFAULT_LOCALE,
  translationPending = false,
  className = '',
}: {
  requestedLocale?: string;
  effectiveLocale?: Locale;
  translationPending?: boolean;
  className?: string;
}) {
  const requestedLabel = requestedLocale && isSupportedLocale(requestedLocale)
    ? localeLabels[requestedLocale]
    : 'limba selectată';
  const fallbackLabel = localeLabels[effectiveLocale];
  const message = translationPending
    ? 'Traducerea se pregătește, revino în câteva minute. Până atunci afișăm versiunea în română.'
    : `Conținutul nu este disponibil în ${requestedLabel}. Afișăm versiunea în ${fallbackLabel}.`;

  return (
    <div
      role="status"
      className={`mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200 ${className}`}
    >
      {message}
    </div>
  );
}
