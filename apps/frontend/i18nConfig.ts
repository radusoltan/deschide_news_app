export const locales = ['ro', 'en', 'ru'] as const;
export const defaultLocale = 'ro' as const;

export type Locale = (typeof locales)[number];

const i18nConfig = {
  locales,
  defaultLocale,
  prefixDefault: false, // Don't add /ro prefix for default locale
};

export default i18nConfig;
