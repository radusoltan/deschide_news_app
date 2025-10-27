'use client';

import { ReactNode } from 'react';
import { IntlProvider } from 'react-intl';

type ServerIntlProviderProps = {
  messages: Record<string, string>;
  locale: string;
  children: ReactNode;
};

/**
 * Client-side IntlProvider wrapper for react-intl
 * Used in root layout to provide translations to all Client Components
 */
export default function ServerIntlProvider({
  messages,
  locale,
  children,
}: ServerIntlProviderProps) {
  return (
    <IntlProvider messages={messages} locale={locale}>
      {children}
    </IntlProvider>
  );
}
