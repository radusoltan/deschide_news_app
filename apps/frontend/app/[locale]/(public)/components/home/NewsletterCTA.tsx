/**
 * Newsletter CTA Section - Slot 9
 * Full-width newsletter subscription CTA
 * Email input + button (visual only for now, no backend)
 */

'use client';

import { useState } from 'react';

interface NewsletterCTAProps {
  locale: string;
}

// Localized labels
const labels = {
  ro: {
    newsletter: 'Abonează-te la newsletter',
    newsletterDesc: 'Primește cele mai importante știri direct în inbox',
    emailPlaceholder: 'Adresa ta de email',
    subscribe: 'Abonează-te',
    subscribeSuccess: 'Mulțumim! Te-ai abonat cu succes.',
  },
  en: {
    newsletter: 'Subscribe to newsletter',
    newsletterDesc: 'Get the most important news straight to your inbox',
    emailPlaceholder: 'Your email address',
    subscribe: 'Subscribe',
    subscribeSuccess: 'Thank you! You have successfully subscribed.',
  },
  ru: {
    newsletter: 'Подпишитесь на рассылку',
    newsletterDesc: 'Получайте самые важные новости прямо в почту',
    emailPlaceholder: 'Ваш email',
    subscribe: 'Подписаться',
    subscribeSuccess: 'Спасибо! Вы успешно подписались.',
  },
} as const;

export default function NewsletterCTA({ locale }: NewsletterCTAProps) {
  const [email, setEmail] = useState('');
  const [isSubscribed, setIsSubscribed] = useState(false);

  const localeLabels = labels[locale as keyof typeof labels] || labels.ro;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // TODO: Implement actual newsletter subscription API call
    // For now, just show success state
    if (email.trim() && email.includes('@')) {
      setIsSubscribed(true);
      setEmail('');

      // Reset after 3 seconds
      setTimeout(() => {
        setIsSubscribed(false);
      }, 3000);
    }
  };

  return (
    <div className="bg-gradient-to-r from-[var(--color-accent)] to-[var(--color-accent-hover)] dark:from-[var(--color-accent)] dark:to-[var(--color-accent-hover)] rounded-[var(--radius-card-lg)] p-8 lg:p-12 text-center">

      {/* Newsletter icon */}
      <div className="flex justify-center mb-6">
        <div className="w-16 h-16 bg-surface/20 rounded-full flex items-center justify-center">
          <svg
            className="w-8 h-8 text-white"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
        </div>
      </div>

      {/* Heading */}
      <h2
        className="text-white font-sans mb-4"
        style={{ fontSize: 'var(--font-size-3xl)' }}
      >
        {localeLabels.newsletter}
      </h2>

      {/* Description */}
      <p
        className="text-white/90 font-serif mb-8 max-w-2xl mx-auto"
        style={{ fontSize: 'var(--font-size-lg)' }}
      >
        {localeLabels.newsletterDesc}
      </p>

      {/* Subscription Form */}
      {!isSubscribed ? (
        <form onSubmit={handleSubmit} className="max-w-md mx-auto">
          <div className="flex flex-col sm:flex-row gap-4">
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder={localeLabels.emailPlaceholder}
              className="flex-1 px-4 py-3 rounded-[var(--radius-md)] border-0 text-[var(--color-text-primary)] placeholder:text-[var(--color-text-secondary)] focus:ring-2 focus:ring-white focus:outline-none font-sans"
              style={{ fontSize: 'var(--font-size-base)' }}
              required
            />
            <button
              type="submit"
              className="px-6 py-3 bg-surface text-[var(--color-accent)] font-semibold rounded-[var(--radius-md)] hover:bg-surface/95 transition-all duration-200 hover-lift-sm font-sans"
              style={{ fontSize: 'var(--font-size-base)' }}
            >
              {localeLabels.subscribe}
            </button>
          </div>
        </form>
      ) : (
        /* Success State */
        <div className="flex items-center justify-center gap-3 max-w-md mx-auto">
          <div className="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
            <svg
              className="w-4 h-4 text-white"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
          </div>
          <p
            className="text-white font-serif animate-fade-in"
            style={{ fontSize: 'var(--font-size-base)' }}
          >
            {localeLabels.subscribeSuccess}
          </p>
        </div>
      )}

      {/* Privacy note */}
      <p
        className="text-white/70 mt-6 font-sans"
        style={{ fontSize: 'var(--font-size-xs)' }}
      >
        {locale === 'ru'
          ? 'Мы уважаем вашу конфиденциальность. Никакого спама.'
          : locale === 'en'
          ? 'We respect your privacy. No spam.'
          : 'Respectăm confidențialitatea ta. Fără spam.'
        }
      </p>
    </div>
  );
}