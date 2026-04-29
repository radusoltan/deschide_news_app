'use client';

import { useEffect } from 'react';
import { usePublishedLocales } from '@/lib/contexts/PublishedLocalesContext';
import type { Locale } from '@/lib/types';

interface Props {
  publishedLocales?: string[];
  localeAlternates?: Partial<Record<Locale, string>>;
}

/**
 * Client island that pushes per-page locale availability metadata into
 * `PublishedLocalesContext`, so the shared Header's `LanguageSwitcher`
 * can render correct disabled states for unavailable locales and emit
 * locale-correct hrefs that consume `translatedSlugs` (T60.6 Cluster B).
 *
 * Renders nothing. Resets to undefined on unmount.
 */
export default function LocaleContextSetter({ publishedLocales, localeAlternates }: Props) {
  const { setPublishedLocales, setLocaleAlternates, resetPublishedLocales } = usePublishedLocales();
  const publishedKey = publishedLocales ? publishedLocales.slice().sort().join(',') : '';
  const alternatesKey = localeAlternates
    ? Object.entries(localeAlternates)
        .filter(([, v]) => typeof v === 'string')
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([k, v]) => `${k}=${v}`)
        .join('|')
    : '';

  useEffect(() => {
    setPublishedLocales(publishedLocales);
    setLocaleAlternates(localeAlternates);
    return () => resetPublishedLocales();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [publishedKey, alternatesKey, setPublishedLocales, setLocaleAlternates, resetPublishedLocales]);

  return null;
}
