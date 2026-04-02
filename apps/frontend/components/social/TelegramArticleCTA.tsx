/**
 * Telegram Article CTA Component
 * Displayed after article body to encourage Telegram channel subscription
 * Placed outside <article> for Telegram IV compatibility
 */

'use client';

import { Send } from 'lucide-react';
import type { Locale } from '@/lib/types';

interface TelegramArticleCTAProps {
  locale: Locale;
  className?: string;
}

/**
 * Get localized text for the Telegram CTA
 */
function getLocalizedText(locale: Locale) {
  const texts = {
    ro: {
      title: 'Urmărește-ne pe Telegram',
      description: 'Primește cele mai importante știri direct în telefon',
      button: 'Abonează-te'
    },
    en: {
      title: 'Follow us on Telegram',
      description: 'Get the most important news directly on your phone',
      button: 'Subscribe'
    },
    ru: {
      title: 'Следите за нами в Telegram',
      description: 'Получайте самые важные новости прямо на телефон',
      button: 'Подписаться'
    }
  };

  return texts[locale] || texts.ro;
}

export default function TelegramArticleCTA({
  locale,
  className = ''
}: TelegramArticleCTAProps) {
  const text = getLocalizedText(locale);
  const telegramChannelUrl = 'https://t.me/deschidenews';

  const handleClick = () => {
    window.open(telegramChannelUrl, '_blank', 'noopener,noreferrer');
  };

  return (
    <aside
      className={`telegram-cta bg-gradient-to-r from-[#0088cc] to-[#0088cc]/90 rounded-lg p-6 mb-8 ${className}`}
      aria-label="Telegram channel"
    >
      <div className="flex items-center justify-between">
        {/* Left side - Icon + Text */}
        <div className="flex items-center gap-4">
          <div className="flex-shrink-0 w-12 h-12 bg-surface/20 rounded-full flex items-center justify-center">
            <Send className="w-6 h-6 text-white" />
          </div>
          <div>
            <h3 className="text-white font-bold text-lg mb-1">
              {text.title}
            </h3>
            <p className="text-white/90 text-sm">
              {text.description}
            </p>
          </div>
        </div>

        {/* Right side - Button */}
        <button
          onClick={handleClick}
          className="flex-shrink-0 bg-surface text-[#0088cc] px-6 py-3 rounded-full font-semibold hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
          aria-label={`${text.button} - ${text.title}`}
        >
          {text.button}
        </button>
      </div>
    </aside>
  );
}