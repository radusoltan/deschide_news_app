'use client';

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import type { Locale } from '@/lib/types';

type LocaleAlternates = Partial<Record<Locale, string>>;

interface PublishedLocalesContextValue {
  publishedLocales: string[] | undefined;
  localeAlternates: LocaleAlternates | undefined;
  setPublishedLocales: (locales: string[] | undefined) => void;
  setLocaleAlternates: (alternates: LocaleAlternates | undefined) => void;
  resetPublishedLocales: () => void;
}

const PublishedLocalesContext = createContext<PublishedLocalesContextValue>({
  publishedLocales: undefined,
  localeAlternates: undefined,
  setPublishedLocales: () => {},
  setLocaleAlternates: () => {},
  resetPublishedLocales: () => {},
});

export function PublishedLocalesProvider({ children }: { children: ReactNode }) {
  const [publishedLocales, setPublishedLocalesState] = useState<string[] | undefined>(undefined);
  const [localeAlternates, setLocaleAlternatesState] = useState<LocaleAlternates | undefined>(
    undefined,
  );

  const setPublishedLocales = useCallback((locales: string[] | undefined) => {
    setPublishedLocalesState(locales);
  }, []);

  const setLocaleAlternates = useCallback((alternates: LocaleAlternates | undefined) => {
    setLocaleAlternatesState(alternates);
  }, []);

  const resetPublishedLocales = useCallback(() => {
    setPublishedLocalesState(undefined);
    setLocaleAlternatesState(undefined);
  }, []);

  const value = useMemo(
    () => ({
      publishedLocales,
      localeAlternates,
      setPublishedLocales,
      setLocaleAlternates,
      resetPublishedLocales,
    }),
    [
      publishedLocales,
      localeAlternates,
      setPublishedLocales,
      setLocaleAlternates,
      resetPublishedLocales,
    ],
  );

  return (
    <PublishedLocalesContext.Provider value={value}>
      {children}
    </PublishedLocalesContext.Provider>
  );
}

export function usePublishedLocales(): PublishedLocalesContextValue {
  return useContext(PublishedLocalesContext);
}
