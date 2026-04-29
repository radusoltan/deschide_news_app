'use client';

import { useEffect } from 'react';
import {
  useLocaleContext,
  type LocaleSwitcherContext,
} from '@/lib/contexts/LocaleContext';
import type { Locale } from '@/lib/types';

interface Props {
  publishedLocales?: string[];
  translatedSlugs?: Partial<Record<Locale, string>>;
  categoryTranslatedSlugs?: Partial<Record<Locale, string>>;
  context: LocaleSwitcherContext;
}

/**
 * Client helper that writes per-page locale metadata into `LocaleContext`
 * so the shared Header's `LanguageSwitcher` can render correct cross-locale
 * URLs and disabled states. Renders nothing.
 *
 * Cleanup on unmount restores the default (generic) context so the switcher
 * falls back to naive prefix-swap for pages that do not set their own.
 */
export default function LocaleContextSetter({
  publishedLocales,
  translatedSlugs,
  categoryTranslatedSlugs,
  context,
}: Props) {
  const { setLocaleContext, resetLocaleContext } = useLocaleContext();

  const key = JSON.stringify({
    publishedLocales,
    translatedSlugs,
    categoryTranslatedSlugs,
    context,
  });

  useEffect(() => {
    setLocaleContext({
      publishedLocales,
      translatedSlugs,
      categoryTranslatedSlugs,
      context,
    });
    return () => resetLocaleContext();
    // `key` is the stable JSON hash of all payload fields; safe to skip deep deps.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, setLocaleContext, resetLocaleContext]);

  return null;
}
