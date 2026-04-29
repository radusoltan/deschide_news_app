'use client'

import Link from 'next/link';
import { useIntl } from 'react-intl';
import { Logo } from '@/components/brand/Logo';
import { getMenuItemHref } from '@/lib/api/public-menu';
import type { MenuItem } from '@/lib/types/menu';

interface FooterProps {
  locale: string;
  menuItems?: MenuItem[];
}

// Static locale-aware slug map for the menuItems-empty fallback so the footer
// never emits a RO slug under /en or /ru (T60.6 Cluster B). Mirrors the slug
// translations seeded by CategoryFixtures.
const FALLBACK_CATEGORY_SLUGS: Record<'politics' | 'economy' | 'society', { ro: string; en: string; ru: string }> = {
  politics: { ro: 'politica', en: 'politics', ru: 'politika' },
  economy: { ro: 'economie', en: 'economy', ru: 'ekonomika' },
  society: { ro: 'societate', en: 'society', ru: 'obshchestvo' },
};

function buildFallbackCategoryHref(
  category: 'politics' | 'economy' | 'society',
  locale: string,
): string {
  const slugs = FALLBACK_CATEGORY_SLUGS[category];
  const targetLocale: 'ro' | 'en' | 'ru' = locale === 'en' || locale === 'ru' ? locale : 'ro';
  const slug = slugs[targetLocale];
  return targetLocale === 'ro' ? `/${slug}` : `/${targetLocale}/${slug}`;
}

export default function Footer({ locale, menuItems = [] }: FooterProps) {
  const intl = useIntl();

  return (
    <footer className="bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)]">
      {/* Footer content */}
      <div id="footer-content" className="relative pt-8 xl:pt-16 pb-6 xl:pb-12">
        <div className="max-w-[1440px] mx-auto px-3 sm:px-4 xl:px-6 overflow-hidden">
          <div className="flex flex-wrap flex-row lg:justify-between -mx-3">
            {/* Left side - Brand and Social */}
            <div className="flex-shrink max-w-full w-full lg:w-2/5 px-3 lg:pr-16">
              <div className="flex items-center mb-4">
                <Logo variant="blue" size="lg" />
              </div>
              <p className="font-serif text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] text-[var(--font-size-base)] leading-relaxed mb-6">
                {intl.formatMessage({ id: 'footer.tagline' })}
              </p>

              {/* Social Links */}
              <div className="space-y-3 mb-6 lg:mb-0">
                <h3 className="font-sans text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] text-[var(--font-size-sm)] font-semibold uppercase tracking-wider">
                  {intl.formatMessage({ id: 'footer.followUs', defaultMessage: 'Urmărește-ne' })}
                </h3>
                <ul className="flex gap-4">
                  {/* Telegram - prominently featured */}
                  <li>
                    <a
                      target="_blank"
                      className="flex items-center gap-2 text-[var(--color-accent)] hover:text-[var(--color-accent-hover)] transition-colors group font-sans text-[var(--font-size-sm)] font-medium"
                      rel="noopener noreferrer"
                      href="https://t.me/deschide"
                      title="Telegram"
                    >
                      <svg className="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-.1.01-1.81 1.14-.81.56-1.46.4-2.16-.13-.24-.18-.43-.33-.78-.49-.42-.2-.9-.31-1.44-.31-.64 0-1.27.2-1.27.2z"/>
                      </svg>
                      Telegram
                    </a>
                  </li>

                  {/* Facebook */}
                  <li>
                    <a
                      target="_blank"
                      className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors group"
                      rel="noopener noreferrer"
                      href="https://facebook.com/deschide"
                      title="Facebook"
                    >
                      <svg className="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                      </svg>
                    </a>
                  </li>

                  {/* Twitter/X */}
                  <li>
                    <a
                      target="_blank"
                      className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors group"
                      rel="noopener noreferrer"
                      href="https://twitter.com/deschide"
                      title="Twitter/X"
                    >
                      <svg className="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/>
                      </svg>
                    </a>
                  </li>

                  {/* YouTube */}
                  <li>
                    <a
                      target="_blank"
                      className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors group"
                      rel="noopener noreferrer"
                      href="https://youtube.com/@deschide"
                      title="YouTube"
                    >
                      <svg className="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                      </svg>
                    </a>
                  </li>

                  {/* Instagram */}
                  <li>
                    <a
                      target="_blank"
                      className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors group"
                      rel="noopener noreferrer"
                      href="https://instagram.com/deschide"
                      title="Instagram"
                    >
                      <svg className="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                      </svg>
                    </a>
                  </li>
                </ul>
              </div>
            </div>

            {/* Right side - Footer columns */}
            <div className="flex-shrink max-w-full w-full lg:w-3/5 px-3">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                {/* Categories Column — dynamic from MenuItem API */}
                <div>
                  <h3 className="font-sans text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] text-[var(--font-size-sm)] font-semibold uppercase tracking-wider mb-3">
                    {intl.formatMessage({ id: 'footer.categories' })}
                  </h3>
                  <ul className="space-y-2 font-sans text-[var(--font-size-sm)]">
                    {menuItems.length > 0 ? menuItems.map((item) => (
                      <li key={item.id}>
                        <Link
                          href={getMenuItemHref(item, locale)}
                          className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                          {...(item.type === 'external_link' && item.openInNewTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                        >
                          {item.label}
                        </Link>
                      </li>
                    )) : (
                      <>
                        <li>
                          <Link
                            href={buildFallbackCategoryHref('politics', locale)}
                            className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                          >
                            {intl.formatMessage({ id: 'categories.politics' })}
                          </Link>
                        </li>
                        <li>
                          <Link
                            href={buildFallbackCategoryHref('economy', locale)}
                            className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                          >
                            {intl.formatMessage({ id: 'categories.economy', defaultMessage: 'Economie' })}
                          </Link>
                        </li>
                        <li>
                          <Link
                            href={buildFallbackCategoryHref('society', locale)}
                            className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                          >
                            {intl.formatMessage({ id: 'categories.society' })}
                          </Link>
                        </li>
                      </>
                    )}
                  </ul>
                </div>

                {/* Quick Links Column */}
                <div>
                  <h3 className="font-sans text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] text-[var(--font-size-sm)] font-semibold uppercase tracking-wider mb-3">
                    {intl.formatMessage({ id: 'footer.quickLinks' })}
                  </h3>
                  <ul className="space-y-2 font-sans text-[var(--font-size-sm)]">
                    <li>
                      <Link
                        href={locale === 'ro' ? '/' : `/${locale}`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'common.home' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/all' : `/${locale}/all`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.latestNews' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/trending' : `/${locale}/trending`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.trending' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/archive' : `/${locale}/archive`}
                        className="flex items-center gap-1.5 text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        {intl.formatMessage({ id: 'footer.archive' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/contact' : `/${locale}/contact`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'common.contact' })}
                      </Link>
                    </li>
                  </ul>
                </div>

                {/* About Us Column */}
                <div>
                  <h3 className="font-sans text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] text-[var(--font-size-sm)] font-semibold uppercase tracking-wider mb-3">
                    {intl.formatMessage({ id: 'footer.aboutUs' })}
                  </h3>
                  <ul className="space-y-2 font-sans text-[var(--font-size-sm)]">
                    <li>
                      <Link
                        href={locale === 'ro' ? '/about' : `/${locale}/about`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.ourStory' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/team' : `/${locale}/team`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.ourTeam' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/careers' : `/${locale}/careers`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.careers' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/advertise' : `/${locale}/advertise`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.advertise' })}
                      </Link>
                    </li>
                  </ul>
                </div>

                {/* Legal Column */}
                <div>
                  <h3 className="font-sans text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] text-[var(--font-size-sm)] font-semibold uppercase tracking-wider mb-3">
                    {intl.formatMessage({ id: 'footer.legal' })}
                  </h3>
                  <ul className="space-y-2 font-sans text-[var(--font-size-sm)]">
                    <li>
                      <Link
                        href={locale === 'ro' ? '/privacy' : `/${locale}/privacy`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.privacyPolicy' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/terms' : `/${locale}/terms`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.termsOfUse' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/license' : `/${locale}/license`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.license' })}
                      </Link>
                    </li>
                    <li>
                      <Link
                        href={locale === 'ro' ? '/gdpr' : `/${locale}/gdpr`}
                        className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors"
                      >
                        {intl.formatMessage({ id: 'footer.gdpr' })}
                      </Link>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Footer copyright */}
      <div className="border-t border-[var(--color-border)] dark:border-[var(--color-border-dark)]">
        <div className="max-w-[1440px] mx-auto px-3 sm:px-4 xl:px-6 py-4">
          <div className="text-center">
            <p className="font-sans text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-[var(--font-size-sm)]">
              © {new Date().getFullYear()} Deschide News | {intl.formatMessage({ id: 'footer.copyright' })}
            </p>
          </div>
        </div>
      </div>
    </footer>
  );
}
