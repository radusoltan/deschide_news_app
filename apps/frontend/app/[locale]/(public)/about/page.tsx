import { Metadata } from 'next';

interface AboutPageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * About Page
 *
 * Displays information about Deschide News
 * Mission, values, team, and contact information
 */
export default async function AboutPage({ params }: AboutPageProps) {
  const { locale } = await params;

  const content = {
    ro: {
      title: 'Despre Noi',
      subtitle: 'Ziarul digital independent pentru Moldova',
      mission: {
        title: 'Misiunea Noastră',
        content:
          'Deschide News este o platformă de știri independentă, dedicată furnizării de informații obiective, verificate și relevante pentru cetățenii Moldovei. Credem în puterea jurnalismului de calitate și ne străduim să oferim cititorilor noștri o perspectivă echilibrată asupra evenimentelor curente.',
      },
      values: {
        title: 'Valorile Noastre',
        items: [
          {
            title: 'Independență',
            description:
              'Suntem independenți editorial și nu primim influențe din partea organizațiilor politice sau a altor grupuri de interese.',
          },
          {
            title: 'Transparență',
            description:
              'Credem în transparență și responsabilitate. Sursele noastre sunt verificate, iar informațiile sunt prezentate cu claritate.',
          },
          {
            title: 'Obiectivitate',
            description:
              'Ne străduim să prezentăm toate părțile unei povești și să oferim cititorilor informațiile necesare pentru a-și forma propriile opinii.',
          },
          {
            title: 'Calitate',
            description:
              'Jurnaliștii noștri sunt profesioniști dedicați care respectă cele mai înalte standarde etice și profesionale.',
          },
        ],
      },
      team: {
        title: 'Echipa Noastră',
        description:
          'Echipa Deschide News este formată din jurnaliști experimentați, editori, fotografi și specialiști în multimedia, toți dedicați aducerii celor mai importante știri către publicul nostru.',
      },
      contact: {
        title: 'Contactează-ne',
        description: 'Avem întotdeauna plăcere să auzim de la cititorii noștri.',
        email: 'redactie@deschide.md',
        address: 'Chișinău, Moldova',
      },
    },
    en: {
      title: 'About Us',
      subtitle: 'Independent digital newspaper for Moldova',
      mission: {
        title: 'Our Mission',
        content:
          'Deschide News is an independent news platform dedicated to providing objective, verified, and relevant information to the citizens of Moldova. We believe in the power of quality journalism and strive to offer our readers a balanced perspective on current events.',
      },
      values: {
        title: 'Our Values',
        items: [
          {
            title: 'Independence',
            description:
              'We are editorially independent and do not accept influence from political organizations or other interest groups.',
          },
          {
            title: 'Transparency',
            description:
              'We believe in transparency and accountability. Our sources are verified, and information is presented clearly.',
          },
          {
            title: 'Objectivity',
            description:
              'We strive to present all sides of a story and provide readers with the information they need to form their own opinions.',
          },
          {
            title: 'Quality',
            description:
              'Our journalists are dedicated professionals who uphold the highest ethical and professional standards.',
          },
        ],
      },
      team: {
        title: 'Our Team',
        description:
          'The Deschide News team consists of experienced journalists, editors, photographers, and multimedia specialists, all dedicated to bringing the most important news to our audience.',
      },
      contact: {
        title: 'Contact Us',
        description: 'We always love to hear from our readers.',
        email: 'redactie@deschide.md',
        address: 'Chișinău, Moldova',
      },
    },
    ru: {
      title: 'О Нас',
      subtitle: 'Независимая цифровая газета для Молдовы',
      mission: {
        title: 'Наша Миссия',
        content:
          'Deschide News - это независимая новостная платформа, посвященная предоставлению объективной, проверенной и актуальной информации гражданам Молдовы. Мы верим в силу качественной журналистики и стремимся предложить нашим читателям сбалансированную перспективу текущих событий.',
      },
      values: {
        title: 'Наши Ценности',
        items: [
          {
            title: 'Независимость',
            description:
              'Мы редакционно независимы и не принимаем влияние от политических организаций или других групп интересов.',
          },
          {
            title: 'Прозрачность',
            description:
              'Мы верим в прозрачность и ответственность. Наши источники проверены, а информация представлена четко.',
          },
          {
            title: 'Объективность',
            description:
              'Мы стремимся представить все стороны истории и предоставить читателям информацию, необходимую для формирования собственного мнения.',
          },
          {
            title: 'Качество',
            description:
              'Наши журналисты - преданные профессионалы, которые придерживаются самых высоких этических и профессиональных стандартов.',
          },
        ],
      },
      team: {
        title: 'Наша Команда',
        description:
          'Команда Deschide News состоит из опытных журналистов, редакторов, фотографов и специалистов по мультимедиа, все они посвятили себя донесению самых важных новостей до нашей аудитории.',
      },
      contact: {
        title: 'Свяжитесь с Нами',
        description: 'Мы всегда рады услышать от наших читателей.',
        email: 'redactie@deschide.md',
        address: 'Кишинев, Молдова',
      },
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <div className="container mx-auto px-4 py-8 max-w-4xl">
      {/* Page Header */}
      <div className="mb-12 text-center">
        <h1 className="text-5xl font-bold text-gray-900 dark:text-white mb-4">{t.title}</h1>
        <p className="text-xl text-gray-600 dark:text-gray-400">{t.subtitle}</p>
      </div>

      {/* Mission Section */}
      <section className="mb-12">
        <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-4">
          {t.mission.title}
        </h2>
        <p className="text-lg text-gray-700 dark:text-gray-300 leading-relaxed">
          {t.mission.content}
        </p>
      </section>

      {/* Values Section */}
      <section className="mb-12">
        <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-6">
          {t.values.title}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {t.values.items.map((value, index) => (
            <div
              key={index}
              className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-brand-tomato-500"
            >
              <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-3">
                {value.title}
              </h3>
              <p className="text-gray-700 dark:text-gray-300">{value.description}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Team Section */}
      <section className="mb-12">
        <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-4">
          {t.team.title}
        </h2>
        <p className="text-lg text-gray-700 dark:text-gray-300 leading-relaxed">
          {t.team.description}
        </p>
      </section>

      {/* Contact Section */}
      <section className="bg-gray-100 dark:bg-gray-800 rounded-lg p-8">
        <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-4">
          {t.contact.title}
        </h2>
        <p className="text-lg text-gray-700 dark:text-gray-300 mb-6">
          {t.contact.description}
        </p>
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <svg
              className="w-6 h-6 text-brand-tomato-500"
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
            <a
              href={`mailto:${t.contact.email}`}
              className="text-lg text-brand-tomato-500 hover:text-brand-tomato-600"
            >
              {t.contact.email}
            </a>
          </div>
          <div className="flex items-center gap-3">
            <svg
              className="w-6 h-6 text-brand-tomato-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
              />
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
              />
            </svg>
            <span className="text-lg text-gray-700 dark:text-gray-300">
              {t.contact.address}
            </span>
          </div>
        </div>
      </section>
    </div>
  );
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: AboutPageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Despre Noi - Deschide News',
    en: 'About Us - Deschide News',
    ru: 'О Нас - Deschide News',
  };

  const descriptions = {
    ro: 'Aflați mai multe despre Deschide News, misiunea noastră, valorile și echipa care aduce știrile către dumneavoastră.',
    en: 'Learn more about Deschide News, our mission, values, and the team that brings you the news.',
    ru: 'Узнайте больше о Deschide News, нашей миссии, ценностях и команде, которая приносит вам новости.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/about`,
      languages: {
        ro: '/about',
        en: '/en/about',
        ru: '/ru/about',
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
