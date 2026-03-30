import { Metadata } from 'next';

interface TeamPageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Team Page
 *
 * Displays the Deschide News team with premium card design
 * Leadership, editorial, and technical teams
 */
export default async function TeamPage({ params }: TeamPageProps) {
  const { locale } = await params;

  const content = {
    ro: {
      title: 'Echipa Noastră',
      subtitle: 'Profesioniști dedicați jurnalismului de calitate',
      intro:
        'Echipa Deschide News reunește jurnaliști experimentați, editori, specialiști tehnici și creatori de conținut multimedia, toți dedicați aducerii celor mai importante știri către publicul nostru.',
      leadership: {
        title: 'Leadership',
        members: [
          {
            name: 'Elena Popescu',
            role: 'Editor-in-Chief',
            bio: 'Cu peste 15 ani de experiență în jurnalism, Elena coordonează echipa editorială și asigură standardele de calitate ale publicației.',
          },
          {
            name: 'Andrei Ciobanu',
            role: 'Managing Editor',
            bio: 'Andrei supervizează operațiunile zilnice și coordonează fluxul de publicare a conținutului.',
          },
          {
            name: 'Maria Ionescu',
            role: 'Chief Content Officer',
            bio: 'Maria dezvoltă strategia de conținut și asigură relevanța și diversitatea subiectelor abordate.',
          },
        ],
      },
      editorial: {
        title: 'Echipa Editorială',
        members: [
          {
            name: 'Victor Stanciu',
            role: 'Senior Editor - Politică',
            bio: 'Specialist în jurnalism politic cu acoperire națională și internațională.',
          },
          {
            name: 'Ana Dumitru',
            role: 'Senior Editor - Economie',
            bio: 'Expert în analiză economică și finanțe, cu focus pe dezvoltarea Moldovei.',
          },
          {
            name: 'Mihai Radu',
            role: 'Senior Editor - Societate',
            bio: 'Jurnalist de investigație specializat în probleme sociale și drepturi civile.',
          },
          {
            name: 'Cristina Pavel',
            role: 'Editor - Cultură',
            bio: 'Pasionată de artă și cultură, promovează valorile culturale moldovenești.',
          },
          {
            name: 'Alexandru Marin',
            role: 'Editor - Sport',
            bio: 'Reporter sportiv cu experiență în acoperirea evenimentelor majore.',
          },
          {
            name: 'Irina Vasilescu',
            role: 'Photo Editor',
            bio: 'Coordonează echipa de fotografi și asigură calitatea imaginilor publicate.',
          },
        ],
      },
      technical: {
        title: 'Echipa Tehnică',
        members: [
          {
            name: 'Gabriel Luca',
            role: 'Technical Director',
            bio: 'Arhitect software cu experiență în platforme de știri la scară largă.',
          },
          {
            name: 'Diana Popa',
            role: 'Lead Developer',
            bio: 'Dezvoltator full-stack specializat în performanță și scalabilitate.',
          },
          {
            name: 'Sergiu Moraru',
            role: 'DevOps Engineer',
            bio: 'Asigură infrastructura și disponibilitatea platformei 24/7.',
          },
        ],
      },
    },
    en: {
      title: 'Our Team',
      subtitle: 'Professionals dedicated to quality journalism',
      intro:
        'The Deschide News team brings together experienced journalists, editors, technical specialists, and multimedia content creators, all dedicated to bringing the most important news to our audience.',
      leadership: {
        title: 'Leadership',
        members: [
          {
            name: 'Elena Popescu',
            role: 'Editor-in-Chief',
            bio: 'With over 15 years of experience in journalism, Elena leads the editorial team and ensures the publication\'s quality standards.',
          },
          {
            name: 'Andrei Ciobanu',
            role: 'Managing Editor',
            bio: 'Andrei oversees daily operations and coordinates the content publishing workflow.',
          },
          {
            name: 'Maria Ionescu',
            role: 'Chief Content Officer',
            bio: 'Maria develops content strategy and ensures relevance and diversity of covered topics.',
          },
        ],
      },
      editorial: {
        title: 'Editorial Team',
        members: [
          {
            name: 'Victor Stanciu',
            role: 'Senior Editor - Politics',
            bio: 'Political journalism specialist with national and international coverage.',
          },
          {
            name: 'Ana Dumitru',
            role: 'Senior Editor - Economy',
            bio: 'Expert in economic analysis and finance, focusing on Moldova\'s development.',
          },
          {
            name: 'Mihai Radu',
            role: 'Senior Editor - Society',
            bio: 'Investigative journalist specialized in social issues and civil rights.',
          },
          {
            name: 'Cristina Pavel',
            role: 'Editor - Culture',
            bio: 'Passionate about art and culture, promoting Moldovan cultural values.',
          },
          {
            name: 'Alexandru Marin',
            role: 'Editor - Sports',
            bio: 'Sports reporter with experience covering major events.',
          },
          {
            name: 'Irina Vasilescu',
            role: 'Photo Editor',
            bio: 'Coordinates the photography team and ensures quality of published images.',
          },
        ],
      },
      technical: {
        title: 'Technical Team',
        members: [
          {
            name: 'Gabriel Luca',
            role: 'Technical Director',
            bio: 'Software architect with experience in large-scale news platforms.',
          },
          {
            name: 'Diana Popa',
            role: 'Lead Developer',
            bio: 'Full-stack developer specialized in performance and scalability.',
          },
          {
            name: 'Sergiu Moraru',
            role: 'DevOps Engineer',
            bio: 'Ensures infrastructure and platform availability 24/7.',
          },
        ],
      },
    },
    ru: {
      title: 'Наша Команда',
      subtitle: 'Профессионалы, посвятившие себя качественной журналистике',
      intro:
        'Команда Deschide News объединяет опытных журналистов, редакторов, технических специалистов и создателей мультимедийного контента, все они посвятили себя донесению самых важных новостей до нашей аудитории.',
      leadership: {
        title: 'Руководство',
        members: [
          {
            name: 'Елена Попеску',
            role: 'Главный редактор',
            bio: 'Имея более 15 лет опыта в журналистике, Елена руководит редакционной командой и обеспечивает стандарты качества издания.',
          },
          {
            name: 'Андрей Чобану',
            role: 'Управляющий редактор',
            bio: 'Андрей контролирует ежедневные операции и координирует процесс публикации контента.',
          },
          {
            name: 'Мария Ионеску',
            role: 'Директор по контенту',
            bio: 'Мария разрабатывает контент-стратегию и обеспечивает актуальность и разнообразие освещаемых тем.',
          },
        ],
      },
      editorial: {
        title: 'Редакционная Команда',
        members: [
          {
            name: 'Виктор Станчу',
            role: 'Старший редактор - Политика',
            bio: 'Специалист по политической журналистике с национальным и международным охватом.',
          },
          {
            name: 'Ана Думитру',
            role: 'Старший редактор - Экономика',
            bio: 'Эксперт по экономическому анализу и финансам с фокусом на развитие Молдовы.',
          },
          {
            name: 'Михай Раду',
            role: 'Старший редактор - Общество',
            bio: 'Журналист-расследователь, специализирующийся на социальных вопросах и гражданских правах.',
          },
          {
            name: 'Кристина Павел',
            role: 'Редактор - Культура',
            bio: 'Увлечена искусством и культурой, продвигает молдавские культурные ценности.',
          },
          {
            name: 'Александру Марин',
            role: 'Редактор - Спорт',
            bio: 'Спортивный репортер с опытом освещения крупных событий.',
          },
          {
            name: 'Ирина Василеску',
            role: 'Фоторедактор',
            bio: 'Координирует команду фотографов и обеспечивает качество опубликованных изображений.',
          },
        ],
      },
      technical: {
        title: 'Техническая Команда',
        members: [
          {
            name: 'Габриэль Лука',
            role: 'Технический директор',
            bio: 'Программный архитектор с опытом работы на крупномасштабных новостных платформах.',
          },
          {
            name: 'Диана Попа',
            role: 'Ведущий разработчик',
            bio: 'Full-stack разработчик, специализирующийся на производительности и масштабируемости.',
          },
          {
            name: 'Сергиу Морару',
            role: 'DevOps инженер',
            bio: 'Обеспечивает инфраструктуру и доступность платформы 24/7.',
          },
        ],
      },
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <div className="min-h-screen bg-gradient-to-br from-[var(--color-surface-sunken)] via-[var(--color-surface-elevated)] to-[var(--color-surface-sunken)] dark:from-[var(--color-surface-dark)] dark:via-[var(--color-surface-elevated-dark)] dark:to-[var(--color-surface-dark)]">
      {/* Hero Section with Premium Gradient */}
      <div className="relative overflow-hidden bg-gradient-to-br from-[var(--color-surface-dark)] via-[var(--color-surface-elevated-dark)] to-[var(--color-accent)] py-20 md:py-28">
        <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-10"></div>
        <div className="absolute inset-0 bg-gradient-to-t from-[var(--color-surface-dark)]/50 to-transparent"></div>

        <div className="container mx-auto px-4 max-w-6xl relative z-10">
          <div className="text-center animate-fade-in-up">
            <h1 className="text-5xl md:text-6xl lg:text-7xl font-bold text-white mb-6 tracking-tight">
              {t.title}
            </h1>
            <p className="text-xl md:text-2xl text-white/90 max-w-3xl mx-auto leading-relaxed">
              {t.subtitle}
            </p>
          </div>
        </div>

        {/* Decorative Bottom Wave */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg className="w-full h-16 fill-[var(--color-surface-sunken)] dark:fill-[var(--color-surface-dark)]" preserveAspectRatio="none" viewBox="0 0 1200 120">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z"></path>
          </svg>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-16 max-w-6xl">
        {/* Introduction */}
        <div className="max-w-4xl mx-auto text-center mb-20 animate-fade-in stagger-1">
          <p className="text-lg md:text-xl text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary)] leading-relaxed">
            {t.intro}
          </p>
        </div>

        {/* Leadership Section */}
        <section className="mb-20 animate-fade-in-up stagger-2">
          <div className="text-center mb-12">
            <div className="inline-block">
              <h2 className="text-4xl md:text-5xl font-bold text-[var(--color-text-primary)] dark:text-white mb-2 relative">
                {t.leadership.title}
                <div className="absolute -bottom-2 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[var(--color-accent)] to-transparent"></div>
              </h2>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {t.leadership.members.map((member, index) => (
              <div
                key={index}
                className="group relative bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 overflow-hidden hover:-translate-y-2"
              >
                {/* Premium Gradient Border */}
                <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-accent)] via-[var(--color-breaking)] to-[var(--color-surface-dark)] opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div className="absolute inset-[2px] bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-2xl"></div>

                {/* Card Content */}
                <div className="relative p-8">
                  {/* Avatar Placeholder with Gradient */}
                  <div className="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-[var(--color-accent)] to-[var(--color-breaking)] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-500">
                    <span className="text-3xl font-bold text-white">
                      {member.name.split(' ').map(n => n[0]).join('')}
                    </span>
                  </div>

                  <h3 className="text-2xl font-bold text-[var(--color-text-primary)] dark:text-white mb-2 text-center group-hover:text-[var(--color-accent)] transition-colors duration-300">
                    {member.name}
                  </h3>

                  <p className="text-[var(--color-accent)] font-semibold text-center mb-4 uppercase tracking-wide text-sm">
                    {member.role}
                  </p>

                  <div className="h-1 w-16 bg-gradient-to-r from-[var(--color-accent)] to-[var(--color-breaking)] mx-auto mb-4 rounded-full"></div>

                  <p className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary)] text-center leading-relaxed">
                    {member.bio}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Editorial Team Section */}
        <section className="mb-20 animate-fade-in-up stagger-3">
          <div className="text-center mb-12">
            <div className="inline-block">
              <h2 className="text-4xl md:text-5xl font-bold text-[var(--color-text-primary)] dark:text-white mb-2 relative">
                {t.editorial.title}
                <div className="absolute -bottom-2 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[var(--color-text-primary)] to-transparent"></div>
              </h2>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {t.editorial.members.map((member, index) => (
              <div
                key={index}
                className="group bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border-l-4 border-[var(--color-text-primary)] hover:border-[var(--color-accent)] hover:-translate-y-1"
              >
                <div className="p-6">
                  {/* Avatar with Oxford Blue */}
                  <div className="w-20 h-20 mx-auto mb-4 rounded-full bg-gradient-to-br from-[var(--color-surface-dark)] to-[var(--color-surface-elevated-dark)] flex items-center justify-center shadow-md group-hover:shadow-lg transition-shadow duration-300">
                    <span className="text-2xl font-bold text-white">
                      {member.name.split(' ').map(n => n[0]).join('')}
                    </span>
                  </div>

                  <h3 className="text-xl font-bold text-[var(--color-text-primary)] dark:text-white mb-1 text-center group-hover:text-[var(--color-text-primary)] dark:group-hover:text-[var(--color-accent)] transition-colors duration-300">
                    {member.name}
                  </h3>

                  <p className="text-[var(--color-text-primary)] dark:text-[var(--color-accent)] font-medium text-center mb-3 text-sm">
                    {member.role}
                  </p>

                  <p className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary)] text-center text-sm leading-relaxed">
                    {member.bio}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Technical Team Section */}
        <section className="animate-fade-in-up stagger-4">
          <div className="text-center mb-12">
            <div className="inline-block">
              <h2 className="text-4xl md:text-5xl font-bold text-[var(--color-text-primary)] dark:text-white mb-2 relative">
                {t.technical.title}
                <div className="absolute -bottom-2 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[var(--color-breaking)] to-transparent"></div>
              </h2>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            {t.technical.members.map((member, index) => (
              <div
                key={index}
                className="group relative bg-gradient-to-br from-[var(--color-surface-sunken)] to-[var(--color-surface-elevated)] dark:from-[var(--color-surface-elevated-dark)] dark:to-[var(--color-surface-dark)] rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 overflow-hidden hover:-translate-y-2 border border-[var(--color-border)] dark:border-[var(--color-border)]"
              >
                <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-surface-dark)]/5 to-[var(--color-breaking)]/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                <div className="relative p-8">
                  {/* Tech Icon Style Avatar */}
                  <div className="w-24 h-24 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-[var(--color-surface-dark)] to-[var(--color-breaking)] flex items-center justify-center shadow-lg group-hover:rotate-6 transition-transform duration-500">
                    <span className="text-3xl font-bold text-white">
                      {member.name.split(' ').map(n => n[0]).join('')}
                    </span>
                  </div>

                  <h3 className="text-2xl font-bold text-[var(--color-text-primary)] dark:text-white mb-2 text-center">
                    {member.name}
                  </h3>

                  <p className="text-[var(--color-breaking)] dark:text-[var(--color-accent)] font-semibold text-center mb-4 uppercase tracking-wide text-sm">
                    {member.role}
                  </p>

                  <div className="h-1 w-16 bg-gradient-to-r from-[var(--color-surface-dark)] to-[var(--color-breaking)] mx-auto mb-4 rounded-full"></div>

                  <p className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary)] text-center leading-relaxed">
                    {member.bio}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </section>
      </div>
    </div>
  );
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: TeamPageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Echipa Noastră - Deschide News',
    en: 'Our Team - Deschide News',
    ru: 'Наша Команда - Deschide News',
  };

  const descriptions = {
    ro: 'Cunosc echipa Deschide News - jurnaliști experimentați, editori și specialiști tehnici dedicați aducerii celor mai importante știri.',
    en: 'Meet the Deschide News team - experienced journalists, editors, and technical specialists dedicated to bringing you the most important news.',
    ru: 'Познакомьтесь с командой Deschide News - опытными журналистами, редакторами и техническими специалистами, посвятившими себя донесению самых важных новостей.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/team`,
      languages: {
        ro: '/team',
        en: '/en/team',
        ru: '/ru/team',
      },
    },
    openGraph: {
      title,
      description,
      locale: locale === 'ro' ? 'ro_RO' : locale === 'en' ? 'en_US' : 'ru_RU',
      type: 'website',
    },
  };
}
