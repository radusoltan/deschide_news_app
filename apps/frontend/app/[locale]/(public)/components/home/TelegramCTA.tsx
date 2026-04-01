/**
 * Telegram CTA Section - Slot 10
 * Full-width Telegram channel promotion
 * Link to Telegram channel (placeholder URL for now)
 */

interface TelegramCTAProps {
  locale: string;
}

// Localized labels
const labels = {
  ro: {
    telegram: 'Urmărește-ne pe Telegram',
    telegramDesc: 'Fii la curent cu ultimele știri din Moldova',
    followButton: 'Urmărește',
    subscriberCount: 'membri',
  },
  en: {
    telegram: 'Follow us on Telegram',
    telegramDesc: 'Stay up to date with the latest news from Moldova',
    followButton: 'Follow',
    subscriberCount: 'members',
  },
  ru: {
    telegram: 'Следите за нами в Telegram',
    telegramDesc: 'Будьте в курсе последних новостей Молдовы',
    followButton: 'Подписаться',
    subscriberCount: 'подписчиков',
  },
} as const;

export default function TelegramCTA({ locale }: TelegramCTAProps) {
  const localeLabels = labels[locale as keyof typeof labels] || labels.ro;

  // TODO: Replace with actual Telegram channel URL
  const telegramUrl = 'https://t.me/deschide_news';

  return (
    <div className="bg-gradient-to-br from-[#0088cc] via-[#0088cc] to-[#006bb3] rounded-[var(--radius-card-lg)] p-8 lg:p-12 text-center relative overflow-hidden">

      {/* Background pattern */}
      <div className="absolute inset-0 opacity-5">
        <div
          className="absolute inset-0"
          style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Ccircle cx='20' cy='20' r='2'/%3E%3Ccircle cx='40' cy='40' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
          }}
        />
      </div>

      <div className="relative z-10">
        {/* Telegram icon */}
        <div className="flex justify-center mb-6">
          <div className="w-20 h-20 bg-surface/20 rounded-full flex items-center justify-center">
            <svg
              className="w-10 h-10 text-white"
              viewBox="0 0 24 24"
              fill="currentColor"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
            </svg>
          </div>
        </div>

        {/* Heading */}
        <h2
          className="text-white font-sans mb-4"
          style={{ fontSize: 'var(--font-size-3xl)' }}
        >
          {localeLabels.telegram}
        </h2>

        {/* Description */}
        <p
          className="text-white/90 font-serif mb-6 max-w-2xl mx-auto"
          style={{ fontSize: 'var(--font-size-lg)' }}
        >
          {localeLabels.telegramDesc}
        </p>

        {/* Stats */}
        <div className="flex justify-center gap-8 mb-8 text-white/80">
          <div className="text-center">
            <div
              className="font-bold text-white font-sans"
              style={{ fontSize: 'var(--font-size-2xl)' }}
            >
              12.5K
            </div>
            <div
              className="font-sans"
              style={{ fontSize: 'var(--font-size-sm)' }}
            >
              {localeLabels.subscriberCount}
            </div>
          </div>
          <div className="text-center">
            <div
              className="font-bold text-white font-sans"
              style={{ fontSize: 'var(--font-size-2xl)' }}
            >
              24/7
            </div>
            <div
              className="font-sans"
              style={{ fontSize: 'var(--font-size-sm)' }}
            >
              {locale === 'ru' ? 'Новости' : locale === 'en' ? 'News' : 'Știri'}
            </div>
          </div>
        </div>

        {/* Call to Action Button */}
        <a
          href={telegramUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-3 px-8 py-4 bg-surface text-[#0088cc] font-bold rounded-full hover:bg-surface/95 transition-all duration-300 hover-lift font-sans shadow-lg"
          style={{ fontSize: 'var(--font-size-lg)' }}
        >
          <svg
            className="w-5 h-5"
            viewBox="0 0 24 24"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
          </svg>
          {localeLabels.followButton}
        </a>

        {/* Features */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 mt-12 text-white/80">
          <div className="text-center">
            <div className="w-12 h-12 bg-surface/20 rounded-full flex items-center justify-center mx-auto mb-3">
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div className="font-sans text-sm">
              {locale === 'ru' ? 'Мгновенные уведомления' : locale === 'en' ? 'Instant notifications' : 'Notificări instant'}
            </div>
          </div>

          <div className="text-center">
            <div className="w-12 h-12 bg-surface/20 rounded-full flex items-center justify-center mx-auto mb-3">
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <div className="font-sans text-sm">
              {locale === 'ru' ? 'Конфиденциально' : locale === 'en' ? 'Private & secure' : 'Privat și securizat'}
            </div>
          </div>

          <div className="text-center">
            <div className="w-12 h-12 bg-surface/20 rounded-full flex items-center justify-center mx-auto mb-3">
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 0h10l1 14H6l1-14z" />
              </svg>
            </div>
            <div className="font-sans text-sm">
              {locale === 'ru' ? 'Без рекламы' : locale === 'en' ? 'Ad-free' : 'Fără reclame'}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}