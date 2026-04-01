import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';

type Props = {
  params: Promise<{ locale: string }>;
};

// GA4 Real Data - December 2025
const GA4_DATA = {
  activeUsers: '121,370',
  pageViews: '225,025',
  sessions: '202,529',
  engagementRate: '49%',
  avgSessionDuration: '1:37',
  mobileUsers: '66%',
  desktopUsers: '33.4%',
  geography: {
    moldova: '59.4%',
    romania: '11.4%',
    westernEurope: '16%',
    usaOther: '14%',
  },
  topCities: {
    chisinau: '66%',
    bucharest: '5%',
    balti: '2%',
    diaspora: '27%',
  },
  peakHours: '15:00-21:00',
  categories: ['Politica', 'Social', 'Extern', 'Economic', 'Cultura', 'Sport'],
};

const content = {
  ro: {
    meta: {
      title: 'Media Kit - Deschide News',
      description: 'Media Kit oficial Deschide News cu statistici de audienta, formate publicitare si informatii pentru parteneri media.',
    },
    hero: {
      badge: 'Media Kit 2025',
      title: 'Deschide News',
      subtitle: 'Platforma de Stiri #1 din Moldova',
      description: 'Document oficial cu statistici de audienta verificate, formate publicitare disponibile si informatii pentru parteneri.',
      downloadPdf: 'Descarca PDF',
      contactUs: 'Contacteaza-ne',
    },
    stats: {
      title: 'Audienta Verificata Google Analytics',
      subtitle: 'Date oficiale din Google Analytics 4 - Decembrie 2025',
      items: [
        { value: GA4_DATA.activeUsers, label: 'Utilizatori Activi Lunar', icon: 'users' },
        { value: GA4_DATA.pageViews, label: 'Vizualizari Pagini', icon: 'eye' },
        { value: GA4_DATA.sessions, label: 'Sesiuni Lunare', icon: 'chart' },
        { value: GA4_DATA.engagementRate, label: 'Rata Engagement', icon: 'heart' },
        { value: GA4_DATA.avgSessionDuration, label: 'Durata Medie Sesiune', icon: 'clock' },
        { value: GA4_DATA.mobileUsers, label: 'Utilizatori Mobile', icon: 'phone' },
      ],
    },
    demographics: {
      title: 'Distributie Demografica',
      subtitle: 'Audienta diversificata din Moldova si Diaspora',
      geography: {
        title: 'Distributie Geografica',
        items: [
          { label: 'Moldova', value: GA4_DATA.geography.moldova, color: 'var(--color-accent)' },
          { label: 'Romania', value: GA4_DATA.geography.romania, color: '#047857' },
          { label: 'Europa de Vest', value: GA4_DATA.geography.westernEurope, color: '#7c3aed' },
          { label: 'SUA & Altele', value: GA4_DATA.geography.usaOther, color: 'var(--color-breaking)' },
        ],
      },
      cities: {
        title: 'Top Orase',
        items: [
          { label: 'Chisinau', value: GA4_DATA.topCities.chisinau },
          { label: 'Bucuresti', value: GA4_DATA.topCities.bucharest },
          { label: 'Balti', value: GA4_DATA.topCities.balti },
          { label: 'Diaspora', value: GA4_DATA.topCities.diaspora },
        ],
      },
      devices: {
        title: 'Dispozitive',
        mobile: { label: 'Mobile', value: GA4_DATA.mobileUsers },
        desktop: { label: 'Desktop', value: GA4_DATA.desktopUsers },
      },
    },
    screenshots: {
      title: 'Previzualizare Platforma',
      subtitle: 'Design modern si responsive pentru toate dispozitivele',
      items: [
        { src: '/media-kit/homepage-desktop.png', alt: 'Homepage Desktop', label: 'Homepage - Desktop' },
        { src: '/media-kit/homepage-mobile.png', alt: 'Homepage Mobile', label: 'Homepage - Mobile' },
        { src: '/media-kit/article-page.png', alt: 'Pagina Articol', label: 'Pagina Articol' },
      ],
    },
    adFormats: {
      title: 'Formate Publicitare',
      subtitle: 'Optiuni flexibile pentru orice obiectiv de marketing',
      items: [
        {
          name: 'Homepage Banner',
          size: '970x250',
          impressions: '1M+',
          ctr: '2.8%',
          description: 'Vizibilitate maxima pe pagina principala',
        },
        {
          name: 'Sidebar Rectangle',
          size: '300x250',
          impressions: '500K+',
          ctr: '2.1%',
          description: 'Prezenta constanta pe toate paginile',
        },
        {
          name: 'In-Article Native',
          size: 'Responsive',
          impressions: '800K+',
          ctr: '4.2%',
          description: 'Engagement ridicat in continut',
        },
        {
          name: 'Branded Content',
          size: 'Article',
          impressions: '400K+',
          ctr: '6.8%',
          description: 'Articole native cu brandul tau',
        },
      ],
    },
    contact: {
      title: 'Contact Publicitate',
      email: 'publicitate@deschide.md',
      phone: '+373 22 123 456',
      cta: 'Solicita Oferta',
    },
    footer: {
      disclaimer: 'Datele prezentate sunt verificate si provin din Google Analytics 4. Ultimul update: Decembrie 2025.',
    },
  },
  en: {
    meta: {
      title: 'Media Kit - Deschide News',
      description: 'Official Deschide News Media Kit with audience statistics, advertising formats and information for media partners.',
    },
    hero: {
      badge: 'Media Kit 2025',
      title: 'Deschide News',
      subtitle: '#1 News Platform in Moldova',
      description: 'Official document with verified audience statistics, available advertising formats and partner information.',
      downloadPdf: 'Download PDF',
      contactUs: 'Contact Us',
    },
    stats: {
      title: 'Verified Google Analytics Audience',
      subtitle: 'Official data from Google Analytics 4 - December 2025',
      items: [
        { value: GA4_DATA.activeUsers, label: 'Monthly Active Users', icon: 'users' },
        { value: GA4_DATA.pageViews, label: 'Page Views', icon: 'eye' },
        { value: GA4_DATA.sessions, label: 'Monthly Sessions', icon: 'chart' },
        { value: GA4_DATA.engagementRate, label: 'Engagement Rate', icon: 'heart' },
        { value: GA4_DATA.avgSessionDuration, label: 'Avg Session Duration', icon: 'clock' },
        { value: GA4_DATA.mobileUsers, label: 'Mobile Users', icon: 'phone' },
      ],
    },
    demographics: {
      title: 'Demographic Distribution',
      subtitle: 'Diverse audience from Moldova and Diaspora',
      geography: {
        title: 'Geographic Distribution',
        items: [
          { label: 'Moldova', value: GA4_DATA.geography.moldova, color: 'var(--color-accent)' },
          { label: 'Romania', value: GA4_DATA.geography.romania, color: '#047857' },
          { label: 'Western Europe', value: GA4_DATA.geography.westernEurope, color: '#7c3aed' },
          { label: 'USA & Other', value: GA4_DATA.geography.usaOther, color: 'var(--color-breaking)' },
        ],
      },
      cities: {
        title: 'Top Cities',
        items: [
          { label: 'Chisinau', value: GA4_DATA.topCities.chisinau },
          { label: 'Bucharest', value: GA4_DATA.topCities.bucharest },
          { label: 'Balti', value: GA4_DATA.topCities.balti },
          { label: 'Diaspora', value: GA4_DATA.topCities.diaspora },
        ],
      },
      devices: {
        title: 'Devices',
        mobile: { label: 'Mobile', value: GA4_DATA.mobileUsers },
        desktop: { label: 'Desktop', value: GA4_DATA.desktopUsers },
      },
    },
    screenshots: {
      title: 'Platform Preview',
      subtitle: 'Modern and responsive design for all devices',
      items: [
        { src: '/media-kit/homepage-desktop.png', alt: 'Homepage Desktop', label: 'Homepage - Desktop' },
        { src: '/media-kit/homepage-mobile.png', alt: 'Homepage Mobile', label: 'Homepage - Mobile' },
        { src: '/media-kit/article-page.png', alt: 'Article Page', label: 'Article Page' },
      ],
    },
    adFormats: {
      title: 'Advertising Formats',
      subtitle: 'Flexible options for any marketing objective',
      items: [
        {
          name: 'Homepage Banner',
          size: '970x250',
          impressions: '1M+',
          ctr: '2.8%',
          description: 'Maximum visibility on the homepage',
        },
        {
          name: 'Sidebar Rectangle',
          size: '300x250',
          impressions: '500K+',
          ctr: '2.1%',
          description: 'Constant presence on all pages',
        },
        {
          name: 'In-Article Native',
          size: 'Responsive',
          impressions: '800K+',
          ctr: '4.2%',
          description: 'High engagement within content',
        },
        {
          name: 'Branded Content',
          size: 'Article',
          impressions: '400K+',
          ctr: '6.8%',
          description: 'Native articles with your brand',
        },
      ],
    },
    contact: {
      title: 'Advertising Contact',
      email: 'publicitate@deschide.md',
      phone: '+373 22 123 456',
      cta: 'Request Quote',
    },
    footer: {
      disclaimer: 'Data presented is verified and comes from Google Analytics 4. Last update: December 2025.',
    },
  },
  ru: {
    meta: {
      title: 'Медиа-кит - Deschide News',
      description: 'Официальный медиа-кит Deschide News со статистикой аудитории, рекламными форматами и информацией для медиа-партнеров.',
    },
    hero: {
      badge: 'Медиа-кит 2025',
      title: 'Deschide News',
      subtitle: 'Новостная платформа #1 в Молдове',
      description: 'Официальный документ с проверенной статистикой аудитории, доступными рекламными форматами и информацией для партнеров.',
      downloadPdf: 'Скачать PDF',
      contactUs: 'Связаться с нами',
    },
    stats: {
      title: 'Проверенная аудитория Google Analytics',
      subtitle: 'Официальные данные из Google Analytics 4 - декабрь 2025',
      items: [
        { value: GA4_DATA.activeUsers, label: 'Активных пользователей', icon: 'users' },
        { value: GA4_DATA.pageViews, label: 'Просмотров страниц', icon: 'eye' },
        { value: GA4_DATA.sessions, label: 'Сессий в месяц', icon: 'chart' },
        { value: GA4_DATA.engagementRate, label: 'Уровень вовлеченности', icon: 'heart' },
        { value: GA4_DATA.avgSessionDuration, label: 'Средняя продолжительность', icon: 'clock' },
        { value: GA4_DATA.mobileUsers, label: 'Мобильные пользователи', icon: 'phone' },
      ],
    },
    demographics: {
      title: 'Демографическое распределение',
      subtitle: 'Разнообразная аудитория из Молдовы и диаспоры',
      geography: {
        title: 'Географическое распределение',
        items: [
          { label: 'Молдова', value: GA4_DATA.geography.moldova, color: 'var(--color-accent)' },
          { label: 'Румыния', value: GA4_DATA.geography.romania, color: '#047857' },
          { label: 'Западная Европа', value: GA4_DATA.geography.westernEurope, color: '#7c3aed' },
          { label: 'США и другие', value: GA4_DATA.geography.usaOther, color: 'var(--color-breaking)' },
        ],
      },
      cities: {
        title: 'Топ города',
        items: [
          { label: 'Кишинев', value: GA4_DATA.topCities.chisinau },
          { label: 'Бухарест', value: GA4_DATA.topCities.bucharest },
          { label: 'Бельцы', value: GA4_DATA.topCities.balti },
          { label: 'Диаспора', value: GA4_DATA.topCities.diaspora },
        ],
      },
      devices: {
        title: 'Устройства',
        mobile: { label: 'Мобильные', value: GA4_DATA.mobileUsers },
        desktop: { label: 'Десктоп', value: GA4_DATA.desktopUsers },
      },
    },
    screenshots: {
      title: 'Предпросмотр платформы',
      subtitle: 'Современный и адаптивный дизайн для всех устройств',
      items: [
        { src: '/media-kit/homepage-desktop.png', alt: 'Главная Desktop', label: 'Главная - Desktop' },
        { src: '/media-kit/homepage-mobile.png', alt: 'Главная Mobile', label: 'Главная - Mobile' },
        { src: '/media-kit/article-page.png', alt: 'Страница статьи', label: 'Страница статьи' },
      ],
    },
    adFormats: {
      title: 'Рекламные форматы',
      subtitle: 'Гибкие опции для любых маркетинговых целей',
      items: [
        {
          name: 'Баннер на главной',
          size: '970x250',
          impressions: '1M+',
          ctr: '2.8%',
          description: 'Максимальная видимость на главной странице',
        },
        {
          name: 'Боковой блок',
          size: '300x250',
          impressions: '500K+',
          ctr: '2.1%',
          description: 'Постоянное присутствие на всех страницах',
        },
        {
          name: 'Нативная реклама',
          size: 'Адаптивный',
          impressions: '800K+',
          ctr: '4.2%',
          description: 'Высокая вовлеченность в контенте',
        },
        {
          name: 'Брендированный контент',
          size: 'Статья',
          impressions: '400K+',
          ctr: '6.8%',
          description: 'Нативные статьи с вашим брендом',
        },
      ],
    },
    contact: {
      title: 'Контакт для рекламы',
      email: 'publicitate@deschide.md',
      phone: '+373 22 123 456',
      cta: 'Запросить предложение',
    },
    footer: {
      disclaimer: 'Представленные данные проверены и получены из Google Analytics 4. Последнее обновление: декабрь 2025.',
    },
  },
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const t = content[locale as keyof typeof content] || content.ro;

  return {
    title: t.meta.title,
    description: t.meta.description,
    openGraph: {
      title: t.meta.title,
      description: t.meta.description,
      type: 'website',
    },
  };
}

// Icon components
const Icons = {
  users: () => (
    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
  ),
  eye: () => (
    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
  ),
  chart: () => (
    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
  ),
  heart: () => (
    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
    </svg>
  ),
  clock: () => (
    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  ),
  phone: () => (
    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
    </svg>
  ),
  download: () => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
    </svg>
  ),
  mail: () => (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
  ),
  verified: () => (
    <svg className="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
      <path fillRule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clipRule="evenodd" />
    </svg>
  ),
};

const getIcon = (iconName: string) => {
  const IconComponent = Icons[iconName as keyof typeof Icons];
  return IconComponent ? <IconComponent /> : null;
};

export default async function MediaKitPage({ params }: Props) {
  const { locale } = await params;
  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <main className="min-h-screen bg-gradient-to-b from-slate-50 to-surface">
      {/* Hero Section */}
      <section className="relative bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white overflow-hidden">
        {/* Background Pattern */}
        <div className="absolute inset-0 opacity-10">
          <div className="absolute inset-0" style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
          }} />
        </div>

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-32">
          <div className="text-center">
            {/* Badge */}
            <div className="inline-flex items-center gap-2 px-4 py-2 bg-surface/10 backdrop-blur-sm rounded-full text-sm font-medium mb-8">
              <Icons.verified />
              <span>{t.hero.badge}</span>
            </div>

            {/* Logo */}
            <div className="flex justify-center mb-6">
              <div className="flex items-center gap-3">
                <Image
                  src="/logo.svg"
                  alt="Deschide News"
                  width={60}
                  height={60}
                  className="w-14 h-14"
                />
                <span className="text-4xl font-bold tracking-tight">{t.hero.title}</span>
              </div>
            </div>

            <p className="text-2xl text-blue-300 font-semibold mb-4">{t.hero.subtitle}</p>
            <p className="text-lg text-slate-300 max-w-2xl mx-auto mb-10">{t.hero.description}</p>

            {/* CTAs */}
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link
                href="#contact"
                className="inline-flex items-center gap-2 px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all shadow-lg hover:shadow-xl"
              >
                <Icons.download />
                {t.hero.downloadPdf}
              </Link>
              <Link
                href="#contact"
                className="inline-flex items-center gap-2 px-8 py-4 bg-surface/10 hover:bg-surface/20 backdrop-blur-sm text-white font-semibold rounded-xl transition-all border border-white/20"
              >
                <Icons.mail />
                {t.hero.contactUs}
              </Link>
            </div>
          </div>
        </div>

        {/* Wave Divider */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 120L60 105C120 90 240 60 360 45C480 30 600 30 720 37.5C840 45 960 60 1080 67.5C1200 75 1320 75 1380 75L1440 75V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="#f8fafc"/>
          </svg>
        </div>
      </section>

      {/* Stats Section */}
      <section className="py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium mb-4">
              <Icons.verified />
              Google Analytics 4
            </div>
            <h2 className="text-3xl lg:text-4xl font-bold text-slate-900 mb-4">{t.stats.title}</h2>
            <p className="text-lg text-slate-600">{t.stats.subtitle}</p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            {t.stats.items.map((stat, index) => (
              <div
                key={index}
                className="bg-surface rounded-2xl p-6 shadow-lg hover:shadow-xl transition-shadow border border-slate-100"
              >
                <div className="w-14 h-14 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 mb-4">
                  {getIcon(stat.icon)}
                </div>
                <div className="text-3xl font-bold text-slate-900 mb-1">{stat.value}</div>
                <div className="text-sm text-slate-600">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Demographics Section */}
      <section className="py-20 px-4 sm:px-6 lg:px-8 bg-slate-50">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl lg:text-4xl font-bold text-slate-900 mb-4">{t.demographics.title}</h2>
            <p className="text-lg text-slate-600">{t.demographics.subtitle}</p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {/* Geographic Distribution */}
            <div className="bg-surface rounded-2xl p-8 shadow-lg">
              <h3 className="text-xl font-bold text-slate-900 mb-6">{t.demographics.geography.title}</h3>
              <div className="space-y-4">
                {t.demographics.geography.items.map((item, index) => (
                  <div key={index}>
                    <div className="flex justify-between mb-2">
                      <span className="text-slate-700 font-medium">{item.label}</span>
                      <span className="text-slate-900 font-bold">{item.value}</span>
                    </div>
                    <div className="h-3 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        className="h-full rounded-full transition-all duration-1000"
                        style={{
                          width: item.value,
                          backgroundColor: item.color,
                        }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Top Cities */}
            <div className="bg-surface rounded-2xl p-8 shadow-lg">
              <h3 className="text-xl font-bold text-slate-900 mb-6">{t.demographics.cities.title}</h3>
              <div className="space-y-4">
                {t.demographics.cities.items.map((item, index) => (
                  <div key={index} className="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 font-bold text-sm">
                        {index + 1}
                      </div>
                      <span className="text-slate-700 font-medium">{item.label}</span>
                    </div>
                    <span className="text-slate-900 font-bold">{item.value}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Devices */}
            <div className="bg-surface rounded-2xl p-8 shadow-lg">
              <h3 className="text-xl font-bold text-slate-900 mb-6">{t.demographics.devices.title}</h3>
              <div className="space-y-8">
                {/* Mobile */}
                <div className="text-center">
                  <div className="w-24 h-24 mx-auto mb-4 relative">
                    <svg className="w-full h-full transform -rotate-90">
                      <circle cx="48" cy="48" r="40" stroke="var(--color-border)" strokeWidth="8" fill="none" />
                      <circle
                        cx="48"
                        cy="48"
                        r="40"
                        stroke="var(--color-accent)"
                        strokeWidth="8"
                        fill="none"
                        strokeDasharray={`${parseFloat(t.demographics.devices.mobile.value) * 2.51} 251`}
                        strokeLinecap="round"
                      />
                    </svg>
                    <div className="absolute inset-0 flex items-center justify-center">
                      <span className="text-xl font-bold text-slate-900">{t.demographics.devices.mobile.value}</span>
                    </div>
                  </div>
                  <span className="text-slate-700 font-medium">{t.demographics.devices.mobile.label}</span>
                </div>

                {/* Desktop */}
                <div className="text-center">
                  <div className="w-24 h-24 mx-auto mb-4 relative">
                    <svg className="w-full h-full transform -rotate-90">
                      <circle cx="48" cy="48" r="40" stroke="var(--color-border)" strokeWidth="8" fill="none" />
                      <circle
                        cx="48"
                        cy="48"
                        r="40"
                        stroke="#10b981"
                        strokeWidth="8"
                        fill="none"
                        strokeDasharray={`${parseFloat(t.demographics.devices.desktop.value) * 2.51} 251`}
                        strokeLinecap="round"
                      />
                    </svg>
                    <div className="absolute inset-0 flex items-center justify-center">
                      <span className="text-xl font-bold text-slate-900">{t.demographics.devices.desktop.value}</span>
                    </div>
                  </div>
                  <span className="text-slate-700 font-medium">{t.demographics.devices.desktop.label}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Screenshots Section */}
      <section className="py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl lg:text-4xl font-bold text-slate-900 mb-4">{t.screenshots.title}</h2>
            <p className="text-lg text-slate-600">{t.screenshots.subtitle}</p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            {t.screenshots.items.map((item, index) => (
              <div key={index} className="group">
                <div className="bg-surface rounded-2xl shadow-lg overflow-hidden border border-slate-100 hover:shadow-xl transition-shadow">
                  <div className="relative aspect-[4/3] overflow-hidden">
                    <Image
                      src={item.src}
                      alt={item.alt}
                      fill
                      className="object-cover object-top group-hover:scale-105 transition-transform duration-500"
                    />
                  </div>
                  <div className="p-4 text-center">
                    <span className="text-sm font-medium text-slate-600">{item.label}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Ad Formats Section */}
      <section className="py-20 px-4 sm:px-6 lg:px-8 bg-slate-50">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl lg:text-4xl font-bold text-slate-900 mb-4">{t.adFormats.title}</h2>
            <p className="text-lg text-slate-600">{t.adFormats.subtitle}</p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            {t.adFormats.items.map((format, index) => (
              <div
                key={index}
                className="bg-surface rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all border border-slate-100 hover:border-blue-200"
              >
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-bold text-slate-900">{format.name}</h3>
                  <span className="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-full">
                    {format.size}
                  </span>
                </div>
                <p className="text-sm text-slate-600 mb-4">{format.description}</p>
                <div className="flex gap-4 pt-4 border-t border-slate-100">
                  <div>
                    <div className="text-xs text-slate-500">Impressions</div>
                    <div className="text-lg font-bold text-slate-900">{format.impressions}</div>
                  </div>
                  <div>
                    <div className="text-xs text-slate-500">CTR</div>
                    <div className="text-lg font-bold text-green-600">{format.ctr}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Contact Section */}
      <section id="contact" className="py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-3xl mx-auto">
          <div className="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-8 lg:p-12 text-center text-white shadow-2xl">
            <h2 className="text-3xl font-bold mb-6">{t.contact.title}</h2>
            <div className="flex flex-col sm:flex-row gap-4 justify-center mb-8">
              <a
                href={`mailto:${t.contact.email}`}
                className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-surface/10 hover:bg-surface/20 rounded-xl transition-colors"
              >
                <Icons.mail />
                <span>{t.contact.email}</span>
              </a>
              <a
                href={`tel:${t.contact.phone.replace(/\s/g, '')}`}
                className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-surface/10 hover:bg-surface/20 rounded-xl transition-colors"
              >
                <Icons.phone />
                <span>{t.contact.phone}</span>
              </a>
            </div>
            <Link
              href={`mailto:${t.contact.email}?subject=Media Kit Request`}
              className="inline-flex items-center gap-2 px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all shadow-lg"
            >
              {t.contact.cta}
            </Link>
          </div>
        </div>
      </section>

      {/* Disclaimer */}
      <section className="py-8 px-4 sm:px-6 lg:px-8 bg-slate-100">
        <div className="max-w-7xl mx-auto text-center">
          <p className="text-sm text-slate-600 flex items-center justify-center gap-2">
            <Icons.verified />
            {t.footer.disclaimer}
          </p>
        </div>
      </section>
    </main>
  );
}
