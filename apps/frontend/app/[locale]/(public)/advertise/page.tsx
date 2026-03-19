import { Metadata } from 'next';
import { AnimatedStats, BenefitIcon } from './components/AnimatedStats';

interface AdvertisePageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Advertise/Media Kit Page - Premium B2B
 *
 * World-class page for potential advertisers, media buyers, and agencies
 * Conveys professionalism, credibility, and ROI potential
 */
export default async function AdvertisePage({ params }: AdvertisePageProps) {
  const { locale } = await params;

  const content = {
    ro: {
      hero: {
        title: 'Conectează-te cu Audiența care Contează',
        subtitle: 'Publicitate pe Deschide News',
        description:
          'Ajunge la 120K+ cititori activi lunari din Moldova și Diaspora. Platformă premium pentru branduri care doresc impact real în piața moldovenească.',
        cta: 'Descarcă Media Kit',
        contact: 'Contactează Echipa',
      },
      stats: {
        title: 'Impact Măsurabil',
        items: [
          { value: '120K+', label: 'Utilizatori Activi Lunar', icon: 'users' },
          { value: '225K+', label: 'Vizualizări Pagini', icon: 'eye' },
          { value: '200K+', label: 'Sesiuni Lunare', icon: 'chart' },
          { value: '49%', label: 'Rată Engagement', icon: 'heart' },
          { value: '1:37', label: 'Timp Mediu pe Site', icon: 'clock' },
          { value: '66%', label: 'Utilizatori Mobile', icon: 'phone' },
        ],
      },
      audience: {
        title: 'Audiență Premium',
        description: 'Ajunge la cei care iau decizii și influențează piața',
        demographics: [
          {
            title: 'Distribuție Geografică',
            items: [
              { label: 'Moldova', value: '59%' },
              { label: 'România', value: '11%' },
              { label: 'Europa de Vest', value: '16%' },
              { label: 'SUA & Altele', value: '14%' },
            ],
          },
          {
            title: 'Top Orașe',
            items: [
              { label: 'Chișinău', value: '66%' },
              { label: 'București', value: '5%' },
              { label: 'Bălți', value: '2%' },
              { label: 'Diaspora', value: '27%' },
            ],
          },
        ],
        highlights: [
          { label: 'Dispozitive Mobile', value: '66%' },
          { label: 'Peak Hours', value: '15:00-21:00' },
        ],
        interests: ['Politică', 'Social', 'Extern', 'Economic'],
      },
      placements: {
        title: 'Opțiuni de Plasare',
        subtitle: 'Alege formatul potrivit pentru obiectivele tale',
        items: [
          {
            name: 'Homepage Banner',
            size: '970x250',
            description: 'Vizibilitate maximă pe pagina principală',
            impressions: '1M+',
            ctr: '2.8%',
          },
          {
            name: 'Sidebar Rectangle',
            size: '300x250',
            description: 'Prezență constantă pe toate paginile',
            impressions: '500K+',
            ctr: '2.1%',
          },
          {
            name: 'In-Article Native',
            size: 'Responsive',
            description: 'Engagement ridicat în conținut',
            impressions: '800K+',
            ctr: '4.2%',
          },
          {
            name: 'Video Pre-roll',
            size: '15-30 sec',
            description: 'Impact vizual pentru conținut video',
            impressions: '300K+',
            ctr: '5.5%',
          },
          {
            name: 'Newsletter Sponsored',
            size: 'Email',
            description: 'Direct în inbox-ul abonaților',
            impressions: '150K',
            ctr: '12%',
          },
          {
            name: 'Branded Content',
            size: 'Article',
            description: 'Articole native cu brandul tău',
            impressions: '400K+',
            ctr: '6.8%',
          },
        ],
      },
      packages: {
        title: 'Pachete de Publicitate',
        subtitle: 'Soluții flexibile pentru orice buget',
        items: [
          {
            name: 'Standard',
            price: '€500',
            period: '/lună',
            description: 'Perfect pentru startup-uri',
            features: [
              'Sidebar banner (300x250)',
              '100,000 impresii garantate',
              'Raport analitic de bază',
              '1 creativitate publicitară',
              'Suport email',
            ],
            featured: false,
          },
          {
            name: 'Premium',
            price: '€1,500',
            period: '/lună',
            description: 'Cel mai popular pentru branduri',
            features: [
              'Homepage + Sidebar banner',
              '500,000 impresii garantate',
              'Plasare in-article',
              'Dashboard analitic detaliat',
              '3 creativități publicitare',
              'Testare A/B',
              'Suport prioritar',
            ],
            featured: true,
          },
          {
            name: 'Enterprise',
            price: 'Custom',
            period: '',
            description: 'Soluții complete pentru corporații',
            features: [
              'Toate plasările premium',
              'Impresii nelimitate',
              'Articole branded content',
              'Sponsorizare newsletter',
              'Video pre-roll',
              'Account manager dedicat',
              'Integrări custom',
              'API raportare real-time',
            ],
            featured: false,
          },
        ],
      },
      benefits: {
        title: 'De Ce Deschide News',
        items: [
          {
            title: 'Sursă de Știri de Încredere',
            description: 'Prezență stabilă pe piață din 2010',
            icon: 'shield',
          },
          {
            title: 'Engagement Ridicat',
            description: 'CTR mediu de 3.5% peste media industriei',
            icon: 'chart',
          },
          {
            title: 'Mediu Brand-Safe',
            description: 'Conținut verificat și standard jurnalistic înalt',
            icon: 'check',
          },
          {
            title: 'Raportare Transparentă',
            description: 'Dashboard în timp real cu metrici detaliate',
            icon: 'graph',
          },
          {
            title: 'Campanii Flexibile',
            description: 'Oricând poți ajusta sau opri campania',
            icon: 'settings',
          },
          {
            title: 'Expertiză Locală',
            description: 'Înțelegem perfect piața din Moldova',
            icon: 'location',
          },
        ],
      },
      testimonials: {
        title: 'Ce Spun Partenerii Noștri',
        items: [
          {
            quote:
              'Campania pe Deschide News a adus rezultate remarcabile. CTR-ul a fost cu 40% mai mare decât pe alte platforme.',
            author: 'Elena Popescu',
            role: 'Marketing Director',
            company: 'Moldova Agroindbank',
          },
          {
            quote:
              'Echipa Deschide este profesionistă și răspunde rapid. Rapoartele detaliate ne-au ajutat să optimizăm campania în timp real.',
            author: 'Andrei Ionescu',
            role: 'CEO',
            company: 'TechStore.md',
          },
          {
            quote:
              'Am văzut o creștere semnificativă în brand awareness după 3 luni de colaborare. Recomand cu încredere!',
            author: 'Maria Volkov',
            role: 'Brand Manager',
            company: 'Orange Moldova',
          },
        ],
      },
      contact: {
        title: 'Hai să Discutăm',
        description: 'Echipa noastră este pregătită să creeze o strategie personalizată pentru brandul tău',
        email: 'publicitate@deschide.md',
        phone: '+373 22 123 456',
        cta: 'Contactează-ne',
        mediaKit: 'Descarcă Media Kit (PDF)',
      },
      faq: {
        title: 'Întrebări Frecvente',
        items: [
          {
            q: 'Care este durata minimă a unei campanii?',
            a: 'Durata minimă este de 1 lună. Oferim flexibilitate și poți prelungi sau ajusta campania oricând.',
          },
          {
            q: 'Ce formate de creativități acceptați?',
            a: 'Acceptăm JPG, PNG, GIF, HTML5, și video (MP4). Echipa noastră poate ajuta și cu producția creativităților.',
          },
          {
            q: 'Cum asigurați siguranța brandului?',
            a: 'Avem standarde jurnalistice stricte și toate articolele sunt verificate. Poți specifica categorii excluse pentru plasarea ta.',
          },
          {
            q: 'Pot targeta categorii specifice?',
            a: 'Da! Poți alege categorii specifice (politică, economie, tehnologie, etc.) sau demografie specifică.',
          },
          {
            q: 'Care sunt termenii de plată?',
            a: 'Plata se face în avans lunar prin transfer bancar. Oferim facturi fiscale și contract oficial.',
          },
        ],
      },
    },
    en: {
      hero: {
        title: 'Connect with the Audience that Matters',
        subtitle: 'Advertise on Deschide News',
        description:
          'Reach 120K+ active monthly readers from Moldova and the Diaspora. Premium platform for brands seeking real impact in the Moldovan market.',
        cta: 'Download Media Kit',
        contact: 'Contact Team',
      },
      stats: {
        title: 'Measurable Impact',
        items: [
          { value: '120K+', label: 'Monthly Active Users', icon: 'users' },
          { value: '225K+', label: 'Page Views', icon: 'eye' },
          { value: '200K+', label: 'Monthly Sessions', icon: 'chart' },
          { value: '49%', label: 'Engagement Rate', icon: 'heart' },
          { value: '1:37', label: 'Avg. Time on Site', icon: 'clock' },
          { value: '66%', label: 'Mobile Users', icon: 'phone' },
        ],
      },
      audience: {
        title: 'Premium Audience',
        description: 'Reach decision-makers who influence the market',
        demographics: [
          {
            title: 'Geographic Distribution',
            items: [
              { label: 'Moldova', value: '59%' },
              { label: 'Romania', value: '11%' },
              { label: 'Western Europe', value: '16%' },
              { label: 'USA & Other', value: '14%' },
            ],
          },
          {
            title: 'Top Cities',
            items: [
              { label: 'Chisinau', value: '66%' },
              { label: 'Bucharest', value: '5%' },
              { label: 'Balti', value: '2%' },
              { label: 'Diaspora', value: '27%' },
            ],
          },
        ],
        highlights: [
          { label: 'Mobile Devices', value: '66%' },
          { label: 'Peak Hours', value: '15:00-21:00' },
        ],
        interests: ['Politics', 'Social', 'International', 'Economy'],
      },
      placements: {
        title: 'Placement Options',
        subtitle: 'Choose the right format for your goals',
        items: [
          {
            name: 'Homepage Banner',
            size: '970x250',
            description: 'Maximum visibility on main page',
            impressions: '1M+',
            ctr: '2.8%',
          },
          {
            name: 'Sidebar Rectangle',
            size: '300x250',
            description: 'Constant presence on all pages',
            impressions: '500K+',
            ctr: '2.1%',
          },
          {
            name: 'In-Article Native',
            size: 'Responsive',
            description: 'High engagement within content',
            impressions: '800K+',
            ctr: '4.2%',
          },
          {
            name: 'Video Pre-roll',
            size: '15-30 sec',
            description: 'Visual impact for video content',
            impressions: '300K+',
            ctr: '5.5%',
          },
          {
            name: 'Newsletter Sponsored',
            size: 'Email',
            description: 'Direct to subscriber inbox',
            impressions: '150K',
            ctr: '12%',
          },
          {
            name: 'Branded Content',
            size: 'Article',
            description: 'Native articles with your brand',
            impressions: '400K+',
            ctr: '6.8%',
          },
        ],
      },
      packages: {
        title: 'Advertising Packages',
        subtitle: 'Flexible solutions for any budget',
        items: [
          {
            name: 'Standard',
            price: '€500',
            period: '/month',
            description: 'Perfect for startups',
            features: [
              'Sidebar banner (300x250)',
              '100,000 guaranteed impressions',
              'Basic analytics report',
              '1 ad creative',
              'Email support',
            ],
            featured: false,
          },
          {
            name: 'Premium',
            price: '€1,500',
            period: '/month',
            description: 'Most popular for brands',
            features: [
              'Homepage + Sidebar banner',
              '500,000 guaranteed impressions',
              'In-article placement',
              'Detailed analytics dashboard',
              '3 ad creatives',
              'A/B testing',
              'Priority support',
            ],
            featured: true,
          },
          {
            name: 'Enterprise',
            price: 'Custom',
            period: '',
            description: 'Complete solutions for corporations',
            features: [
              'All premium placements',
              'Unlimited impressions',
              'Branded content articles',
              'Newsletter sponsorship',
              'Video pre-roll',
              'Dedicated account manager',
              'Custom integrations',
              'Real-time reporting API',
            ],
            featured: false,
          },
        ],
      },
      benefits: {
        title: 'Why Deschide News',
        items: [
          {
            title: 'Trusted News Source',
            description: 'Stable market presence since 2010',
            icon: 'shield',
          },
          {
            title: 'High Engagement',
            description: '3.5% average CTR above industry standard',
            icon: 'chart',
          },
          {
            title: 'Brand-Safe Environment',
            description: 'Verified content and high journalistic standards',
            icon: 'check',
          },
          {
            title: 'Transparent Reporting',
            description: 'Real-time dashboard with detailed metrics',
            icon: 'graph',
          },
          {
            title: 'Flexible Campaigns',
            description: 'Adjust or pause campaign anytime',
            icon: 'settings',
          },
          {
            title: 'Local Expertise',
            description: 'We understand the Moldova market perfectly',
            icon: 'location',
          },
        ],
      },
      testimonials: {
        title: 'What Our Partners Say',
        items: [
          {
            quote:
              'The campaign on Deschide News delivered remarkable results. CTR was 40% higher than other platforms.',
            author: 'Elena Popescu',
            role: 'Marketing Director',
            company: 'Moldova Agroindbank',
          },
          {
            quote:
              'The Deschide team is professional and responsive. Detailed reports helped us optimize in real-time.',
            author: 'Andrei Ionescu',
            role: 'CEO',
            company: 'TechStore.md',
          },
          {
            quote:
              'We saw significant brand awareness growth after 3 months of collaboration. Highly recommend!',
            author: 'Maria Volkov',
            role: 'Brand Manager',
            company: 'Orange Moldova',
          },
        ],
      },
      contact: {
        title: "Let's Talk",
        description: 'Our team is ready to create a personalized strategy for your brand',
        email: 'publicitate@deschide.md',
        phone: '+373 22 123 456',
        cta: 'Contact Us',
        mediaKit: 'Download Media Kit (PDF)',
      },
      faq: {
        title: 'Frequently Asked Questions',
        items: [
          {
            q: 'What is the minimum campaign duration?',
            a: 'Minimum duration is 1 month. We offer flexibility and you can extend or adjust anytime.',
          },
          {
            q: 'What creative formats do you accept?',
            a: 'We accept JPG, PNG, GIF, HTML5, and video (MP4). Our team can also help with creative production.',
          },
          {
            q: 'How do you ensure brand safety?',
            a: 'We have strict journalistic standards and all articles are verified. You can specify excluded categories for your placement.',
          },
          {
            q: 'Can I target specific categories?',
            a: 'Yes! You can choose specific categories (politics, economy, technology, etc.) or specific demographics.',
          },
          {
            q: 'What are the payment terms?',
            a: 'Payment is made monthly in advance via bank transfer. We provide fiscal invoices and official contract.',
          },
        ],
      },
    },
    ru: {
      hero: {
        title: 'Свяжитесь с Аудиторией, которая Важна',
        subtitle: 'Реклама на Deschide News',
        description:
          'Достигните 120K+ активных месячных читателей из Молдовы и Диаспоры. Премиум платформа для брендов, стремящихся к реальному воздействию на молдавском рынке.',
        cta: 'Скачать Медиа-Кит',
        contact: 'Связаться с Командой',
      },
      stats: {
        title: 'Измеримое Воздействие',
        items: [
          { value: '120K+', label: 'Активных Пользователей', icon: 'users' },
          { value: '225K+', label: 'Просмотров Страниц', icon: 'eye' },
          { value: '200K+', label: 'Сессий в Месяц', icon: 'chart' },
          { value: '49%', label: 'Уровень Вовлечённости', icon: 'heart' },
          { value: '1:37', label: 'Среднее Время на Сайте', icon: 'clock' },
          { value: '66%', label: 'Мобильных Пользователей', icon: 'phone' },
        ],
      },
      audience: {
        title: 'Премиум Аудитория',
        description: 'Достигните лиц, принимающих решения и влияющих на рынок',
        demographics: [
          {
            title: 'Географическое Распределение',
            items: [
              { label: 'Молдова', value: '59%' },
              { label: 'Румыния', value: '11%' },
              { label: 'Западная Европа', value: '16%' },
              { label: 'США и Другие', value: '14%' },
            ],
          },
          {
            title: 'Топ Города',
            items: [
              { label: 'Кишинёв', value: '66%' },
              { label: 'Бухарест', value: '5%' },
              { label: 'Бельцы', value: '2%' },
              { label: 'Диаспора', value: '27%' },
            ],
          },
        ],
        highlights: [
          { label: 'Мобильные Устройства', value: '66%' },
          { label: 'Пиковые Часы', value: '15:00-21:00' },
        ],
        interests: ['Политика', 'Общество', 'Международные', 'Экономика'],
      },
      placements: {
        title: 'Варианты Размещения',
        subtitle: 'Выберите правильный формат для ваших целей',
        items: [
          {
            name: 'Баннер на Главной',
            size: '970x250',
            description: 'Максимальная видимость на главной странице',
            impressions: '1M+',
            ctr: '2.8%',
          },
          {
            name: 'Боковой Прямоугольник',
            size: '300x250',
            description: 'Постоянное присутствие на всех страницах',
            impressions: '500K+',
            ctr: '2.1%',
          },
          {
            name: 'Native в Статье',
            size: 'Responsive',
            description: 'Высокая вовлеченность в контенте',
            impressions: '800K+',
            ctr: '4.2%',
          },
          {
            name: 'Видео Pre-roll',
            size: '15-30 сек',
            description: 'Визуальное воздействие для видео контента',
            impressions: '300K+',
            ctr: '5.5%',
          },
          {
            name: 'Спонсорство Newsletter',
            size: 'Email',
            description: 'Прямо в почтовый ящик подписчиков',
            impressions: '150K',
            ctr: '12%',
          },
          {
            name: 'Брендированный Контент',
            size: 'Статья',
            description: 'Native статьи с вашим брендом',
            impressions: '400K+',
            ctr: '6.8%',
          },
        ],
      },
      packages: {
        title: 'Рекламные Пакеты',
        subtitle: 'Гибкие решения для любого бюджета',
        items: [
          {
            name: 'Стандарт',
            price: '€500',
            period: '/месяц',
            description: 'Идеально для стартапов',
            features: [
              'Боковой баннер (300x250)',
              '100,000 гарантированных показов',
              'Базовый аналитический отчет',
              '1 креатив',
              'Email поддержка',
            ],
            featured: false,
          },
          {
            name: 'Премиум',
            price: '€1,500',
            period: '/месяц',
            description: 'Самый популярный для брендов',
            features: [
              'Главная + Боковой баннер',
              '500,000 гарантированных показов',
              'Размещение в статье',
              'Детальная аналитическая панель',
              '3 креатива',
              'A/B тестирование',
              'Приоритетная поддержка',
            ],
            featured: true,
          },
          {
            name: 'Корпоративный',
            price: 'Custom',
            period: '',
            description: 'Полные решения для корпораций',
            features: [
              'Все премиум размещения',
              'Неограниченные показы',
              'Брендированный контент статьи',
              'Спонсорство newsletter',
              'Видео pre-roll',
              'Выделенный менеджер',
              'Кастомные интеграции',
              'API отчетности в реальном времени',
            ],
            featured: false,
          },
        ],
      },
      benefits: {
        title: 'Почему Deschide News',
        items: [
          {
            title: 'Надежный Источник Новостей',
            description: 'Стабильное присутствие на рынке с 2010 года',
            icon: 'shield',
          },
          {
            title: 'Высокая Вовлеченность',
            description: 'Средний CTR 3.5% выше отраслевого стандарта',
            icon: 'chart',
          },
          {
            title: 'Безопасная Среда для Бренда',
            description: 'Проверенный контент и высокие журналистские стандарты',
            icon: 'check',
          },
          {
            title: 'Прозрачная Отчетность',
            description: 'Панель в реальном времени с детальными метриками',
            icon: 'graph',
          },
          {
            title: 'Гибкие Кампании',
            description: 'Корректируйте или останавливайте кампанию в любое время',
            icon: 'settings',
          },
          {
            title: 'Локальная Экспертиза',
            description: 'Мы прекрасно понимаем рынок Молдовы',
            icon: 'location',
          },
        ],
      },
      testimonials: {
        title: 'Что Говорят Наши Партнеры',
        items: [
          {
            quote:
              'Кампания на Deschide News показала замечательные результаты. CTR был на 40% выше, чем на других платформах.',
            author: 'Елена Попеску',
            role: 'Директор по Маркетингу',
            company: 'Moldova Agroindbank',
          },
          {
            quote:
              'Команда Deschide профессиональна и отзывчива. Детальные отчеты помогли нам оптимизировать в реальном времени.',
            author: 'Андрей Ионеску',
            role: 'CEO',
            company: 'TechStore.md',
          },
          {
            quote:
              'Мы увидели значительный рост узнаваемости бренда после 3 месяцев сотрудничества. Очень рекомендую!',
            author: 'Мария Волков',
            role: 'Бренд-Менеджер',
            company: 'Orange Moldova',
          },
        ],
      },
      contact: {
        title: 'Давайте Поговорим',
        description: 'Наша команда готова создать персонализированную стратегию для вашего бренда',
        email: 'publicitate@deschide.md',
        phone: '+373 22 123 456',
        cta: 'Свяжитесь с Нами',
        mediaKit: 'Скачать Медиа-Кит (PDF)',
      },
      faq: {
        title: 'Часто Задаваемые Вопросы',
        items: [
          {
            q: 'Какова минимальная продолжительность кампании?',
            a: 'Минимальная продолжительность - 1 месяц. Мы предлагаем гибкость, и вы можете продлить или скорректировать в любое время.',
          },
          {
            q: 'Какие форматы креативов вы принимаете?',
            a: 'Мы принимаем JPG, PNG, GIF, HTML5 и видео (MP4). Наша команда также может помочь с производством креативов.',
          },
          {
            q: 'Как вы обеспечиваете безопасность бренда?',
            a: 'У нас строгие журналистские стандарты, и все статьи проверяются. Вы можете указать исключенные категории для вашего размещения.',
          },
          {
            q: 'Могу ли я таргетировать определенные категории?',
            a: 'Да! Вы можете выбрать определенные категории (политика, экономика, технологии и т.д.) или определенную демографию.',
          },
          {
            q: 'Каковы условия оплаты?',
            a: 'Оплата производится ежемесячно авансом банковским переводом. Мы предоставляем фискальные счета и официальный договор.',
          },
        ],
      },
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-100 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
      {/* Hero Section - Premium Dark Gradient */}
      <section className="relative overflow-hidden bg-gradient-to-br from-slate-900 via-brand-oxford to-slate-900 text-white">
        {/* Animated Background Elements */}
        <div className="absolute inset-0 overflow-hidden opacity-20">
          <div className="absolute top-0 right-0 w-96 h-96 bg-brand-tomato-500 rounded-full blur-3xl animate-pulse"></div>
          <div className="absolute bottom-0 left-0 w-96 h-96 bg-blue-500 rounded-full blur-3xl animate-pulse delay-1000"></div>
        </div>

        <div className="container mx-auto px-4 py-24 md:py-32 relative z-10">
          <div className="max-w-4xl mx-auto text-center">
            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 mb-6 animate-fade-in">
              <svg
                className="w-4 h-4 text-brand-tomato-400"
                fill="currentColor"
                viewBox="0 0 24 24"
              >
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
              </svg>
              <span className="text-sm font-semibold">{t.hero.subtitle}</span>
            </div>

            <h1 className="text-5xl md:text-7xl font-black mb-6 leading-tight animate-slide-up">
              {t.hero.title}
            </h1>

            <p className="text-xl md:text-2xl text-slate-300 mb-10 leading-relaxed animate-slide-up delay-200">
              {t.hero.description}
            </p>

            <div className="flex flex-col sm:flex-row gap-4 justify-center animate-slide-up delay-400">
              <a
                href="#contact"
                className="group relative px-8 py-4 bg-brand-tomato-500 hover:bg-brand-tomato-600 rounded-lg font-bold text-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-brand-tomato-500/50"
              >
                <span className="relative z-10">{t.hero.cta}</span>
                <div className="absolute inset-0 bg-gradient-to-r from-brand-tomato-600 to-red-600 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
              </a>

              <a
                href="#contact"
                className="px-8 py-4 bg-white/10 hover:bg-white/20 backdrop-blur-sm border-2 border-white/30 rounded-lg font-bold text-lg transition-all duration-300 hover:scale-105"
              >
                {t.hero.contact}
              </a>
            </div>
          </div>
        </div>

        {/* Bottom Wave */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg
            className="w-full h-16 fill-slate-50 dark:fill-slate-950"
            viewBox="0 0 1200 120"
            preserveAspectRatio="none"
          >
            <path d="M0,60 C200,100 400,20 600,60 C800,100 1000,20 1200,60 L1200,120 L0,120 Z"></path>
          </svg>
        </div>
      </section>

      {/* Stats Section - Animated Counters */}
      <section className="py-20 bg-white dark:bg-slate-900">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl md:text-5xl font-black text-center mb-16 text-gray-900 dark:text-white">
            {t.stats.title}
          </h2>

          <AnimatedStats items={t.stats.items} />
        </div>
      </section>

      {/* Audience Section */}
      <section className="py-20 bg-gradient-to-br from-slate-100 to-white dark:from-slate-950 dark:to-slate-900">
        <div className="container mx-auto px-4">
          <div className="max-w-5xl mx-auto">
            <div className="text-center mb-16">
              <h2 className="text-4xl md:text-5xl font-black mb-4 text-gray-900 dark:text-white">
                {t.audience.title}
              </h2>
              <p className="text-xl text-gray-600 dark:text-gray-400">{t.audience.description}</p>
            </div>

            {/* Demographics Grid */}
            <div className="grid md:grid-cols-2 gap-8 mb-12">
              {t.audience.demographics.map((demo, index) => (
                <div
                  key={index}
                  className="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1"
                >
                  <h3 className="text-2xl font-bold mb-6 text-gray-900 dark:text-white">
                    {demo.title}
                  </h3>
                  <div className="space-y-4">
                    {demo.items.map((item, idx) => (
                      <div key={idx} className="flex justify-between items-center">
                        <span className="text-gray-700 dark:text-gray-300 font-medium">
                          {item.label}
                        </span>
                        <div className="flex items-center gap-3">
                          <div className="w-32 h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div
                              className="h-full bg-gradient-to-r from-brand-tomato-500 to-red-600 rounded-full transition-all duration-1000"
                              style={{ width: item.value }}
                            ></div>
                          </div>
                          <span className="text-xl font-bold text-brand-tomato-500 w-12">
                            {item.value}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </div>

            {/* Highlights */}
            <div className="grid md:grid-cols-2 gap-6 mb-12">
              {t.audience.highlights.map((highlight, index) => (
                <div
                  key={index}
                  className="bg-gradient-to-br from-brand-tomato-500 to-red-600 text-white rounded-xl p-6 shadow-lg"
                >
                  <div className="text-sm font-semibold opacity-90 mb-1">{highlight.label}</div>
                  <div className="text-3xl font-black">{highlight.value}</div>
                </div>
              ))}
            </div>

            {/* Interests */}
            <div className="flex flex-wrap justify-center gap-3">
              {t.audience.interests.map((interest, index) => (
                <span
                  key={index}
                  className="px-6 py-3 bg-white dark:bg-slate-800 rounded-full font-semibold text-gray-800 dark:text-gray-200 shadow-md hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                >
                  {interest}
                </span>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Placements Section */}
      <section className="py-20 bg-white dark:bg-slate-900">
        <div className="container mx-auto px-4">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-black mb-4 text-gray-900 dark:text-white">
              {t.placements.title}
            </h2>
            <p className="text-xl text-gray-600 dark:text-gray-400">{t.placements.subtitle}</p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
            {t.placements.items.map((placement, index) => (
              <div
                key={index}
                className="group relative bg-gradient-to-br from-white to-slate-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-slate-200 dark:border-slate-700 overflow-hidden"
              >
                {/* Gradient Border Effect */}
                <div className="absolute inset-0 bg-gradient-to-br from-brand-tomato-500/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-2xl"></div>

                <div className="relative z-10">
                  <div className="flex items-start justify-between mb-4">
                    <h3 className="text-2xl font-bold text-gray-900 dark:text-white">
                      {placement.name}
                    </h3>
                    <span className="px-3 py-1 bg-brand-tomato-100 dark:bg-brand-tomato-900/30 text-brand-tomato-700 dark:text-brand-tomato-400 rounded-full text-sm font-bold">
                      {placement.size}
                    </span>
                  </div>

                  <p className="text-gray-600 dark:text-gray-400 mb-6">{placement.description}</p>

                  <div className="flex gap-4">
                    <div className="flex-1">
                      <div className="text-sm text-gray-500 dark:text-gray-500 mb-1">
                        Impressions
                      </div>
                      <div className="text-2xl font-bold text-gray-900 dark:text-white">
                        {placement.impressions}
                      </div>
                    </div>
                    <div className="flex-1">
                      <div className="text-sm text-gray-500 dark:text-gray-500 mb-1">Avg CTR</div>
                      <div className="text-2xl font-bold text-brand-tomato-500">
                        {placement.ctr}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Packages Section - Glassmorphism */}
      <section className="py-20 bg-gradient-to-br from-slate-100 to-white dark:from-slate-950 dark:to-slate-900 relative overflow-hidden">
        {/* Background Pattern */}
        <div className="absolute inset-0 opacity-5">
          <div
            className="absolute inset-0"
            style={{
              backgroundImage:
                'radial-gradient(circle at 1px 1px, rgb(0 0 0 / 0.15) 1px, transparent 0)',
              backgroundSize: '40px 40px',
            }}
          ></div>
        </div>

        <div className="container mx-auto px-4 relative z-10">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-black mb-4 text-gray-900 dark:text-white">
              {t.packages.title}
            </h2>
            <p className="text-xl text-gray-600 dark:text-gray-400">{t.packages.subtitle}</p>
          </div>

          <div className="grid md:grid-cols-3 gap-8 max-w-7xl mx-auto">
            {t.packages.items.map((pkg, index) => (
              <div
                key={index}
                className={`relative group ${
                  pkg.featured ? 'md:-mt-4 md:mb-4' : ''
                } transition-all duration-300`}
              >
                {/* Most Popular Badge */}
                {pkg.featured && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-20">
                    <div className="px-6 py-2 bg-gradient-to-r from-brand-tomato-500 to-red-600 text-white rounded-full font-bold text-sm shadow-lg">
                      MOST POPULAR
                    </div>
                  </div>
                )}

                <div
                  className={`relative h-full backdrop-blur-sm rounded-3xl p-8 transition-all duration-300 hover:-translate-y-2 ${
                    pkg.featured
                      ? 'bg-gradient-to-br from-brand-tomato-500 to-red-600 text-white shadow-2xl hover:shadow-brand-tomato-500/50'
                      : 'bg-white/60 dark:bg-slate-800/60 border-2 border-slate-200 dark:border-slate-700 hover:border-brand-tomato-300 dark:hover:border-brand-tomato-700 shadow-xl hover:shadow-2xl'
                  }`}
                >
                  <div className="mb-6">
                    <h3
                      className={`text-3xl font-black mb-2 ${
                        pkg.featured ? 'text-white' : 'text-gray-900 dark:text-white'
                      }`}
                    >
                      {pkg.name}
                    </h3>
                    <p
                      className={`text-sm ${
                        pkg.featured ? 'text-white/90' : 'text-gray-600 dark:text-gray-400'
                      }`}
                    >
                      {pkg.description}
                    </p>
                  </div>

                  <div className="mb-8">
                    <div className="flex items-baseline gap-2">
                      <span
                        className={`text-5xl font-black ${
                          pkg.featured ? 'text-white' : 'text-gray-900 dark:text-white'
                        }`}
                      >
                        {pkg.price}
                      </span>
                      {pkg.period && (
                        <span
                          className={`text-xl ${
                            pkg.featured ? 'text-white/80' : 'text-gray-600 dark:text-gray-400'
                          }`}
                        >
                          {pkg.period}
                        </span>
                      )}
                    </div>
                  </div>

                  <ul className="space-y-4 mb-8">
                    {pkg.features.map((feature, idx) => (
                      <li key={idx} className="flex items-start gap-3">
                        <svg
                          className={`w-6 h-6 flex-shrink-0 mt-0.5 ${
                            pkg.featured ? 'text-white' : 'text-brand-tomato-500'
                          }`}
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={3}
                            d="M5 13l4 4L19 7"
                          />
                        </svg>
                        <span
                          className={`${
                            pkg.featured ? 'text-white' : 'text-gray-700 dark:text-gray-300'
                          }`}
                        >
                          {feature}
                        </span>
                      </li>
                    ))}
                  </ul>

                  <a
                    href="#contact"
                    className={`block w-full py-4 rounded-xl font-bold text-center transition-all duration-300 hover:scale-105 ${
                      pkg.featured
                        ? 'bg-white text-brand-tomato-600 hover:bg-slate-100 shadow-lg'
                        : 'bg-brand-tomato-500 text-white hover:bg-brand-tomato-600 shadow-md hover:shadow-xl'
                    }`}
                  >
                    {locale === 'ro' ? 'Alege Pachetul' : locale === 'en' ? 'Choose Package' : 'Выбрать Пакет'}
                  </a>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Benefits Section */}
      <section className="py-20 bg-white dark:bg-slate-900">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl md:text-5xl font-black text-center mb-16 text-gray-900 dark:text-white">
            {t.benefits.title}
          </h2>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
            {t.benefits.items.map((benefit, index) => (
              <div
                key={index}
                className="group relative bg-gradient-to-br from-slate-50 to-white dark:from-slate-800 dark:to-slate-900 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-slate-200 dark:border-slate-700"
              >
                <div className="mb-6">
                  <div className="w-16 h-16 bg-gradient-to-br from-brand-tomato-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                    <BenefitIcon icon={benefit.icon} />
                  </div>
                </div>

                <h3 className="text-xl font-bold mb-3 text-gray-900 dark:text-white">
                  {benefit.title}
                </h3>
                <p className="text-gray-600 dark:text-gray-400">{benefit.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Testimonials Section */}
      <section className="py-20 bg-gradient-to-br from-slate-900 via-brand-oxford to-slate-900 text-white relative overflow-hidden">
        <div className="absolute inset-0 opacity-10">
          <div
            className="absolute inset-0"
            style={{
              backgroundImage: 'url("data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="1"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
            }}
          ></div>
        </div>

        <div className="container mx-auto px-4 relative z-10">
          <h2 className="text-4xl md:text-5xl font-black text-center mb-16">{t.testimonials.title}</h2>

          <div className="grid md:grid-cols-3 gap-8 max-w-7xl mx-auto">
            {t.testimonials.items.map((testimonial, index) => (
              <div
                key={index}
                className="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20 hover:bg-white/15 transition-all duration-300 hover:-translate-y-1"
              >
                <div className="mb-6">
                  <svg
                    className="w-10 h-10 text-brand-tomato-400 opacity-50"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
                  </svg>
                </div>

                <p className="text-lg mb-6 leading-relaxed">&quot;{testimonial.quote}&quot;</p>

                <div className="border-t border-white/20 pt-6">
                  <div className="font-bold text-lg">{testimonial.author}</div>
                  <div className="text-sm text-slate-300">{testimonial.role}</div>
                  <div className="text-sm text-brand-tomato-400 font-semibold">
                    {testimonial.company}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Contact Section */}
      <section id="contact" className="py-20 bg-white dark:bg-slate-900">
        <div className="container mx-auto px-4">
          <div className="max-w-4xl mx-auto">
            <div className="text-center mb-12">
              <h2 className="text-4xl md:text-5xl font-black mb-4 text-gray-900 dark:text-white">
                {t.contact.title}
              </h2>
              <p className="text-xl text-gray-600 dark:text-gray-400">{t.contact.description}</p>
            </div>

            <div className="bg-gradient-to-br from-slate-50 to-white dark:from-slate-800 dark:to-slate-900 rounded-3xl p-12 shadow-2xl border border-slate-200 dark:border-slate-700">
              <div className="grid md:grid-cols-2 gap-8 mb-10">
                <a
                  href={`mailto:${t.contact.email}`}
                  className="group flex items-center gap-4 p-6 bg-white dark:bg-slate-800 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-slate-200 dark:border-slate-700"
                >
                  <div className="w-14 h-14 bg-gradient-to-br from-brand-tomato-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <svg
                      className="w-7 h-7 text-white"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                      />
                    </svg>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 dark:text-gray-400 mb-1">Email</div>
                    <div className="font-bold text-gray-900 dark:text-white group-hover:text-brand-tomato-500 transition-colors">
                      {t.contact.email}
                    </div>
                  </div>
                </a>

                <a
                  href={`tel:${t.contact.phone.replace(/\s/g, '')}`}
                  className="group flex items-center gap-4 p-6 bg-white dark:bg-slate-800 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-slate-200 dark:border-slate-700"
                >
                  <div className="w-14 h-14 bg-gradient-to-br from-brand-tomato-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <svg
                      className="w-7 h-7 text-white"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                      />
                    </svg>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 dark:text-gray-400 mb-1">Telefon</div>
                    <div className="font-bold text-gray-900 dark:text-white group-hover:text-brand-tomato-500 transition-colors">
                      {t.contact.phone}
                    </div>
                  </div>
                </a>
              </div>

              <div className="flex flex-col sm:flex-row gap-4">
                <a
                  href={`mailto:${t.contact.email}`}
                  className="flex-1 py-4 px-6 bg-gradient-to-r from-brand-tomato-500 to-red-600 hover:from-brand-tomato-600 hover:to-red-700 text-white rounded-xl font-bold text-center transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-2xl hover:shadow-brand-tomato-500/50"
                >
                  {t.contact.cta}
                </a>

                <a
                  href="#"
                  className="flex-1 py-4 px-6 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border-2 border-slate-300 dark:border-slate-600 text-gray-900 dark:text-white rounded-xl font-bold text-center transition-all duration-300 hover:scale-105 shadow-md hover:shadow-lg"
                >
                  {t.contact.mediaKit}
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* FAQ Section */}
      <section className="py-20 bg-gradient-to-br from-slate-100 to-white dark:from-slate-950 dark:to-slate-900">
        <div className="container mx-auto px-4">
          <div className="max-w-4xl mx-auto">
            <h2 className="text-4xl md:text-5xl font-black text-center mb-16 text-gray-900 dark:text-white">
              {t.faq.title}
            </h2>

            <div className="space-y-4">
              {t.faq.items.map((item, index) => (
                <details
                  key={index}
                  className="group bg-white dark:bg-slate-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 border border-slate-200 dark:border-slate-700"
                >
                  <summary className="flex items-center justify-between p-6 cursor-pointer list-none">
                    <span className="text-lg font-bold text-gray-900 dark:text-white pr-4">
                      {item.q}
                    </span>
                    <svg
                      className="w-6 h-6 text-brand-tomato-500 flex-shrink-0 transition-transform group-open:rotate-180"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={3}
                        d="M19 9l-7 7-7-7"
                      />
                    </svg>
                  </summary>
                  <div className="px-6 pb-6">
                    <p className="text-gray-600 dark:text-gray-400 leading-relaxed">{item.a}</p>
                  </div>
                </details>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Final CTA Section */}
      <section className="py-20 bg-gradient-to-br from-slate-900 via-brand-oxford to-slate-900 text-white relative overflow-hidden">
        <div className="absolute inset-0 overflow-hidden opacity-20">
          <div className="absolute top-0 left-0 w-96 h-96 bg-brand-tomato-500 rounded-full blur-3xl animate-pulse"></div>
          <div className="absolute bottom-0 right-0 w-96 h-96 bg-blue-500 rounded-full blur-3xl animate-pulse delay-1000"></div>
        </div>

        <div className="container mx-auto px-4 relative z-10">
          <div className="max-w-3xl mx-auto text-center">
            <h2 className="text-4xl md:text-5xl font-black mb-6">
              {locale === 'ro'
                ? 'Gata să Începi?'
                : locale === 'en'
                  ? 'Ready to Start?'
                  : 'Готовы Начать?'}
            </h2>
            <p className="text-xl text-slate-300 mb-10">
              {locale === 'ro'
                ? 'Contactează echipa noastră astăzi și hai să creăm o campanie de succes împreună'
                : locale === 'en'
                  ? "Contact our team today and let's create a successful campaign together"
                  : 'Свяжитесь с нашей командой сегодня, и давайте создадим успешную кампанию вместе'}
            </p>

            <a
              href="#contact"
              className="inline-block px-10 py-5 bg-brand-tomato-500 hover:bg-brand-tomato-600 rounded-xl font-bold text-lg transition-all duration-300 hover:scale-105 shadow-2xl hover:shadow-brand-tomato-500/50"
            >
              {t.contact.cta}
            </a>
          </div>
        </div>
      </section>
    </div>
  );
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: AdvertisePageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Publicitate - Deschide News | Media Kit',
    en: 'Advertise - Deschide News | Media Kit',
    ru: 'Реклама - Deschide News | Медиа-Кит',
  };

  const descriptions = {
    ro: 'Ajunge la 2.5M+ cititori lunari din Moldova. Soluții premium de publicitate pentru branduri care doresc impact real. Descarcă Media Kit-ul nostru.',
    en: 'Reach 2.5M+ monthly readers from Moldova. Premium advertising solutions for brands seeking real impact. Download our Media Kit.',
    ru: 'Достигните 2.5M+ месячных читателей из Молдовы. Премиум рекламные решения для брендов, стремящихся к реальному воздействию. Скачайте наш Медиа-Кит.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/advertise`,
      languages: {
        ro: '/advertise',
        en: '/en/advertise',
        ru: '/ru/advertise',
      },
    },
    openGraph: {
      title,
      description,
      locale: locale === 'ro' ? 'ro_RO' : locale === 'en' ? 'en_US' : 'ru_RU',
      type: 'website',
      siteName: 'Deschide News',
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
    },
    keywords: [
      'publicitate',
      'advertise',
      'реклама',
      'media kit',
      'медиа-кит',
      'Moldova advertising',
      'Moldovan news',
      'brand awareness',
      'digital marketing',
      'native advertising',
    ],
  };
}
