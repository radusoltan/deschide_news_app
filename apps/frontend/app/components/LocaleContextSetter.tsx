'use client';

import { useEffect } from 'react';
import { usePublishedLocales } from '@/lib/contexts/PublishedLocalesContext';

interface Props {
  publishedLocales?: string[];
}

/**
 * Client island that pushes per-page locale availability metadata into
 * `PublishedLocalesContext`, so the shared Header's `LanguageSwitcher`
 * can render correct disabled states for unavailable locales.
 *
 * Renders nothing. Resets to undefined on unmount.
 */
export default function LocaleContextSetter({ publishedLocales }: Props) {
  const { setPublishedLocales, resetPublishedLocales } = usePublishedLocales();
  const key = publishedLocales ? publishedLocales.slice().sort().join(',') : '';

  useEffect(() => {
    setPublishedLocales(publishedLocales);
    return () => resetPublishedLocales();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, setPublishedLocales, resetPublishedLocales]);

  return null;
}
