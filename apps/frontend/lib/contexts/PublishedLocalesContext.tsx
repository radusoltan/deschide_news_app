'use client';

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from 'react';

interface PublishedLocalesContextValue {
  publishedLocales: string[] | undefined;
  setPublishedLocales: (locales: string[] | undefined) => void;
  resetPublishedLocales: () => void;
}

const PublishedLocalesContext = createContext<PublishedLocalesContextValue>({
  publishedLocales: undefined,
  setPublishedLocales: () => {},
  resetPublishedLocales: () => {},
});

export function PublishedLocalesProvider({ children }: { children: ReactNode }) {
  const [publishedLocales, setPublishedLocalesState] = useState<string[] | undefined>(undefined);

  const setPublishedLocales = useCallback((locales: string[] | undefined) => {
    setPublishedLocalesState(locales);
  }, []);

  const resetPublishedLocales = useCallback(() => {
    setPublishedLocalesState(undefined);
  }, []);

  const value = useMemo(
    () => ({ publishedLocales, setPublishedLocales, resetPublishedLocales }),
    [publishedLocales, setPublishedLocales, resetPublishedLocales],
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
