'use client';

import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import type { Locale } from '@/lib/types';

export type LocaleSwitcherContext = 'article' | 'category' | 'topic' | 'tag' | 'generic';

export interface LocaleContextData {
  publishedLocales?: string[];
  translatedSlugs?: Partial<Record<Locale, string>>;
  categoryTranslatedSlugs?: Partial<Record<Locale, string>>;
  context: LocaleSwitcherContext;
}

export interface LocaleContextValue extends LocaleContextData {
  setLocaleContext: (value: Partial<LocaleContextData>) => void;
  resetLocaleContext: () => void;
}

const INITIAL_DATA: LocaleContextData = {
  publishedLocales: undefined,
  translatedSlugs: undefined,
  categoryTranslatedSlugs: undefined,
  context: 'generic',
};

const LocaleContext = createContext<LocaleContextValue>({
  ...INITIAL_DATA,
  setLocaleContext: () => {},
  resetLocaleContext: () => {},
});

function shallowEqualSlugs(
  a: Partial<Record<Locale, string>> | undefined,
  b: Partial<Record<Locale, string>> | undefined,
): boolean {
  if (a === b) return true;
  if (!a || !b) return false;
  const keys = ['ro', 'en', 'ru'] as const;
  for (const key of keys) {
    if (a[key] !== b[key]) return false;
  }
  return true;
}

function shallowEqualPublishedLocales(
  a: string[] | undefined,
  b: string[] | undefined,
): boolean {
  if (a === b) return true;
  if (!a || !b) return false;
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i++) {
    if (a[i] !== b[i]) return false;
  }
  return true;
}

function localeContextDataEqual(a: LocaleContextData, b: LocaleContextData): boolean {
  return (
    a.context === b.context &&
    shallowEqualPublishedLocales(a.publishedLocales, b.publishedLocales) &&
    shallowEqualSlugs(a.translatedSlugs, b.translatedSlugs) &&
    shallowEqualSlugs(a.categoryTranslatedSlugs, b.categoryTranslatedSlugs)
  );
}

export function LocaleContextProvider({
  children,
  initialData,
}: {
  children: ReactNode;
  initialData?: LocaleContextData;
}) {
  const [data, setData] = useState<LocaleContextData>(initialData ?? INITIAL_DATA);

  const setLocaleContext = useCallback((value: Partial<LocaleContextData>) => {
    setData((prev) => {
      const next: LocaleContextData = { ...prev, ...value };
      // Skip state update when payload is identical — prevents the
      // useEffect-driven setter from re-rendering when SSR initialData
      // already matches the page-level setter call.
      if (localeContextDataEqual(prev, next)) {
        return prev;
      }
      return next;
    });
  }, []);

  const resetLocaleContext = useCallback(() => {
    setData((prev) => (localeContextDataEqual(prev, INITIAL_DATA) ? prev : INITIAL_DATA));
  }, []);

  const value = useMemo<LocaleContextValue>(
    () => ({ ...data, setLocaleContext, resetLocaleContext }),
    [data, setLocaleContext, resetLocaleContext]
  );

  return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>;
}

export function useLocaleContext(): LocaleContextValue {
  return useContext(LocaleContext);
}
