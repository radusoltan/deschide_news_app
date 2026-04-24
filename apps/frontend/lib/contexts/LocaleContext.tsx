'use client';

import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import type { Locale } from '@/lib/types';

export type LocaleSwitcherContext = 'article' | 'category' | 'generic';

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

export function LocaleContextProvider({ children }: { children: ReactNode }) {
  const [data, setData] = useState<LocaleContextData>(INITIAL_DATA);

  const setLocaleContext = useCallback((value: Partial<LocaleContextData>) => {
    setData((prev) => ({ ...prev, ...value }));
  }, []);

  const resetLocaleContext = useCallback(() => {
    setData(INITIAL_DATA);
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
