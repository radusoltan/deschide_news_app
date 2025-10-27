import { createIntl, createIntlCache, IntlShape } from 'react-intl';

// Load messages for a specific locale
async function loadMessages(locale: string) {
  const messages = await import(`@/messages/${locale}.json`);
  return messages.default;
}

// Cache for intl instances
const cache = createIntlCache();

/**
 * Helper function for Server Components
 * Usage: const intl = await getIntl(locale);
 */
export async function getIntl(locale: string): Promise<IntlShape> {
  const messages = await loadMessages(locale);

  return createIntl(
    {
      locale,
      messages,
    },
    cache
  );
}
