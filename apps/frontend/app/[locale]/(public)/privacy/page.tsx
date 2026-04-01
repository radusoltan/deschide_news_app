import { Metadata } from 'next';

interface PrivacyPageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Privacy Policy Page
 *
 * Comprehensive privacy policy with table of contents
 * Premium design with smooth navigation
 */
export default async function PrivacyPage({ params }: PrivacyPageProps) {
  const { locale } = await params;

  const content = {
    ro: {
      title: 'Politica de Confidențialitate',
      subtitle: 'Ultima actualizare: 12 Decembrie 2025',
      intro:
        'La Deschide News, confidențialitatea dumneavoastră este o prioritate. Această politică explică cum colectăm, utilizăm și protejăm informațiile personale.',
      toc: {
        title: 'Cuprins',
        items: [
          'Informații generale',
          'Ce date colectăm',
          'Cum folosim datele',
          'Cookies și tehnologii similare',
          'Servicii terțe',
          'Securitatea datelor',
          'Drepturile dumneavoastră',
          'Contact',
        ],
      },
      sections: [
        {
          id: 'general',
          title: '1. Informații generale',
          content: [
            'Deschide News SRL ("noi", "nostru" sau "Deschide News") operează site-ul web deschide.md. Această pagină vă informează cu privire la politicile noastre referitoare la colectarea, utilizarea și divulgarea datelor cu caracter personal atunci când utilizați serviciul nostru.',
            'Utilizăm datele dumneavoastră pentru a furniza și îmbunătăți serviciul. Prin utilizarea serviciului, acceptați colectarea și utilizarea informațiilor în conformitate cu această politică.',
          ],
        },
        {
          id: 'data-collection',
          title: '2. Ce date colectăm',
          content: [
            'Colectăm următoarele tipuri de informații:',
            '• Date de utilizare: Informații despre modul în care accesați și utilizați serviciul, inclusiv adresa IP, tipul de browser, paginile vizitate, timpul petrecut pe pagini și alte statistici.',
            '• Cookies și date de urmărire: Folosim cookies și tehnologii similare pentru a urmări activitatea pe serviciul nostru.',
            '• Date de cont (dacă aplicabil): Nume, adresă de email și parolă când creați un cont.',
            '• Date de newsletter: Adresa de email dacă vă abonați la newsletter-ul nostru.',
          ],
        },
        {
          id: 'data-usage',
          title: '3. Cum folosim datele',
          content: [
            'Utilizăm datele colectate pentru următoarele scopuri:',
            '• Pentru a furniza și menține serviciul nostru',
            '• Pentru a vă notifica despre modificări ale serviciului',
            '• Pentru a vă permite să participați la funcții interactive ale serviciului',
            '• Pentru a oferi suport clienților',
            '• Pentru a analiza și îmbunătăți serviciul',
            '• Pentru a monitoriza utilizarea serviciului',
            '• Pentru a detecta, preveni și aborda problemele tehnice',
            '• Pentru a vă trimite newsletter-uri și materiale promoționale (doar dacă v-ați dat acordul)',
          ],
        },
        {
          id: 'cookies',
          title: '4. Cookies și tehnologii similare',
          content: [
            'Folosim cookies și tehnologii similare pentru a urmări activitatea pe serviciul nostru și a păstra anumite informații.',
            'Tipuri de cookies pe care le folosim:',
            '• Cookies de sesiune: Cookies temporare care expiră când închideți browser-ul',
            '• Cookies persistente: Rămân pe dispozitivul dumneavoastră pentru o perioadă determinată',
            '• Cookies de performanță: Colectează informații despre modul în care utilizați site-ul',
            '• Cookies funcționale: Permit site-ului să își amintească alegerile dumneavoastră',
            'Puteți instrui browser-ul să refuze toate cookies sau să indice când este trimis un cookie. Cu toate acestea, dacă nu acceptați cookies, este posibil să nu puteți utiliza anumite părți ale serviciului nostru.',
          ],
        },
        {
          id: 'third-party',
          title: '5. Servicii terțe',
          content: [
            'Putem utiliza servicii terțe pentru a analiza utilizarea serviciului nostru:',
            '• Google Analytics: Serviciu de analiză web care urmărește și raportează traficul site-ului',
            '• Platforme de social media: Pentru partajarea conținutului',
            '• Servicii de publicitate: Pentru afișarea de publicitate relevantă',
            'Aceste servicii terțe au propriile politici de confidențialitate. Vă recomandăm să le consultați.',
          ],
        },
        {
          id: 'security',
          title: '6. Securitatea datelor',
          content: [
            'Securitatea datelor dumneavoastră este importantă pentru noi, dar rețineți că nicio metodă de transmisie prin internet sau metodă de stocare electronică nu este 100% sigură.',
            'Implementăm măsuri de securitate tehnice și organizatorice pentru a proteja datele dumneavoastră:',
            '• Criptare SSL/TLS pentru transmisia datelor',
            '• Stocarea securizată a datelor',
            '• Acces restricționat la datele personale',
            '• Backup-uri regulate',
            '• Monitorizare continuă a securității',
          ],
        },
        {
          id: 'rights',
          title: '7. Drepturile dumneavoastră',
          content: [
            'Aveți următoarele drepturi în ceea ce privește datele dumneavoastră personale:',
            '• Dreptul de acces: Puteți solicita o copie a datelor personale pe care le deținem despre dumneavoastră',
            '• Dreptul la rectificare: Puteți solicita corectarea datelor inexacte',
            '• Dreptul la ștergere: Puteți solicita ștergerea datelor dumneavoastră personale',
            '• Dreptul la restricționare: Puteți solicita restricționarea prelucrării datelor',
            '• Dreptul la portabilitate: Puteți solicita transferul datelor către alt operator',
            '• Dreptul la opoziție: Puteți vă opune prelucrării datelor pentru anumite scopuri',
            'Pentru a exercita aceste drepturi, vă rugăm să ne contactați la adresa de email indicată mai jos.',
          ],
        },
        {
          id: 'contact',
          title: '8. Contact',
          content: [
            'Dacă aveți întrebări despre această Politică de Confidențialitate, vă rugăm să ne contactați:',
            '• Email: privacy@deschide.md',
            '• Adresă: Chișinău, Moldova',
            'Ne angajăm să răspundem tuturor solicitărilor în maximum 30 de zile.',
          ],
        },
      ],
    },
    en: {
      title: 'Privacy Policy',
      subtitle: 'Last updated: December 12, 2025',
      intro:
        'At Deschide News, your privacy is a priority. This policy explains how we collect, use, and protect personal information.',
      toc: {
        title: 'Table of Contents',
        items: [
          'General information',
          'What data we collect',
          'How we use data',
          'Cookies and similar technologies',
          'Third-party services',
          'Data security',
          'Your rights',
          'Contact',
        ],
      },
      sections: [
        {
          id: 'general',
          title: '1. General information',
          content: [
            'Deschide News SRL ("we", "our" or "Deschide News") operates the deschide.md website. This page informs you of our policies regarding the collection, use and disclosure of personal data when you use our service.',
            'We use your data to provide and improve the service. By using the service, you agree to the collection and use of information in accordance with this policy.',
          ],
        },
        {
          id: 'data-collection',
          title: '2. What data we collect',
          content: [
            'We collect the following types of information:',
            '• Usage data: Information about how you access and use the service, including IP address, browser type, pages visited, time spent on pages, and other statistics.',
            '• Cookies and tracking data: We use cookies and similar technologies to track activity on our service.',
            '• Account data (if applicable): Name, email address, and password when you create an account.',
            '• Newsletter data: Email address if you subscribe to our newsletter.',
          ],
        },
        {
          id: 'data-usage',
          title: '3. How we use data',
          content: [
            'We use the collected data for the following purposes:',
            '• To provide and maintain our service',
            '• To notify you about changes to our service',
            '• To allow you to participate in interactive features of our service',
            '• To provide customer support',
            '• To analyze and improve the service',
            '• To monitor service usage',
            '• To detect, prevent and address technical issues',
            '• To send you newsletters and promotional materials (only if you have given consent)',
          ],
        },
        {
          id: 'cookies',
          title: '4. Cookies and similar technologies',
          content: [
            'We use cookies and similar technologies to track activity on our service and store certain information.',
            'Types of cookies we use:',
            '• Session cookies: Temporary cookies that expire when you close your browser',
            '• Persistent cookies: Remain on your device for a specified period',
            '• Performance cookies: Collect information about how you use the site',
            '• Functional cookies: Allow the site to remember your choices',
            'You can instruct your browser to refuse all cookies or indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our service.',
          ],
        },
        {
          id: 'third-party',
          title: '5. Third-party services',
          content: [
            'We may use third-party services to analyze the use of our service:',
            '• Google Analytics: Web analytics service that tracks and reports website traffic',
            '• Social media platforms: For content sharing',
            '• Advertising services: To display relevant advertising',
            'These third-party services have their own privacy policies. We recommend that you review them.',
          ],
        },
        {
          id: 'security',
          title: '6. Data security',
          content: [
            'The security of your data is important to us, but remember that no method of transmission over the internet or method of electronic storage is 100% secure.',
            'We implement technical and organizational security measures to protect your data:',
            '• SSL/TLS encryption for data transmission',
            '• Secure data storage',
            '• Restricted access to personal data',
            '• Regular backups',
            '• Continuous security monitoring',
          ],
        },
        {
          id: 'rights',
          title: '7. Your rights',
          content: [
            'You have the following rights regarding your personal data:',
            '• Right of access: You can request a copy of the personal data we hold about you',
            '• Right to rectification: You can request correction of inaccurate data',
            '• Right to erasure: You can request deletion of your personal data',
            '• Right to restriction: You can request restriction of data processing',
            '• Right to portability: You can request transfer of data to another controller',
            '• Right to object: You can object to data processing for certain purposes',
            'To exercise these rights, please contact us at the email address below.',
          ],
        },
        {
          id: 'contact',
          title: '8. Contact',
          content: [
            'If you have questions about this Privacy Policy, please contact us:',
            '• Email: privacy@deschide.md',
            '• Address: Chișinău, Moldova',
            'We are committed to responding to all requests within 30 days.',
          ],
        },
      ],
    },
    ru: {
      title: 'Политика Конфиденциальности',
      subtitle: 'Последнее обновление: 12 декабря 2025',
      intro:
        'В Deschide News ваша конфиденциальность является приоритетом. Эта политика объясняет, как мы собираем, используем и защищаем личную информацию.',
      toc: {
        title: 'Содержание',
        items: [
          'Общая информация',
          'Какие данные мы собираем',
          'Как мы используем данные',
          'Cookies и подобные технологии',
          'Сторонние сервисы',
          'Безопасность данных',
          'Ваши права',
          'Контакты',
        ],
      },
      sections: [
        {
          id: 'general',
          title: '1. Общая информация',
          content: [
            'Deschide News SRL ("мы", "наш" или "Deschide News") управляет веб-сайтом deschide.md. Эта страница информирует вас о наших политиках в отношении сбора, использования и раскрытия персональных данных при использовании нашего сервиса.',
            'Мы используем ваши данные для предоставления и улучшения сервиса. Используя сервис, вы соглашаетесь на сбор и использование информации в соответствии с этой политикой.',
          ],
        },
        {
          id: 'data-collection',
          title: '2. Какие данные мы собираем',
          content: [
            'Мы собираем следующие типы информации:',
            '• Данные об использовании: Информация о том, как вы получаете доступ и используете сервис, включая IP-адрес, тип браузера, посещенные страницы, время на страницах и другую статистику.',
            '• Cookies и данные отслеживания: Мы используем cookies и подобные технологии для отслеживания активности в нашем сервисе.',
            '• Данные учетной записи (если применимо): Имя, адрес электронной почты и пароль при создании учетной записи.',
            '• Данные рассылки: Адрес электронной почты, если вы подписываетесь на нашу рассылку.',
          ],
        },
        {
          id: 'data-usage',
          title: '3. Как мы используем данные',
          content: [
            'Мы используем собранные данные для следующих целей:',
            '• Для предоставления и поддержания нашего сервиса',
            '• Для уведомления вас об изменениях в нашем сервисе',
            '• Для предоставления возможности участвовать в интерактивных функциях нашего сервиса',
            '• Для предоставления клиентской поддержки',
            '• Для анализа и улучшения сервиса',
            '• Для мониторинга использования сервиса',
            '• Для выявления, предотвращения и решения технических проблем',
            '• Для отправки вам рассылок и рекламных материалов (только с вашего согласия)',
          ],
        },
        {
          id: 'cookies',
          title: '4. Cookies и подобные технологии',
          content: [
            'Мы используем cookies и подобные технологии для отслеживания активности в нашем сервисе и хранения определенной информации.',
            'Типы cookies, которые мы используем:',
            '• Сеансовые cookies: Временные cookies, которые истекают при закрытии браузера',
            '• Постоянные cookies: Остаются на вашем устройстве в течение определенного периода',
            '• Cookies производительности: Собирают информацию о том, как вы используете сайт',
            '• Функциональные cookies: Позволяют сайту запоминать ваш выбор',
            'Вы можете настроить свой браузер на отклонение всех cookies или указание, когда cookie отправляется. Однако, если вы не принимаете cookies, вы можете не иметь возможности использовать некоторые части нашего сервиса.',
          ],
        },
        {
          id: 'third-party',
          title: '5. Сторонние сервисы',
          content: [
            'Мы можем использовать сторонние сервисы для анализа использования нашего сервиса:',
            '• Google Analytics: Сервис веб-аналитики, который отслеживает и сообщает о трафике сайта',
            '• Платформы социальных сетей: Для обмена контентом',
            '• Рекламные сервисы: Для показа релевантной рекламы',
            'Эти сторонние сервисы имеют свои собственные политики конфиденциальности. Мы рекомендуем вам ознакомиться с ними.',
          ],
        },
        {
          id: 'security',
          title: '6. Безопасность данных',
          content: [
            'Безопасность ваших данных важна для нас, но помните, что ни один метод передачи через интернет или метод электронного хранения не является на 100% безопасным.',
            'Мы применяем технические и организационные меры безопасности для защиты ваших данных:',
            '• SSL/TLS шифрование для передачи данных',
            '• Безопасное хранение данных',
            '• Ограниченный доступ к персональным данным',
            '• Регулярные резервные копии',
            '• Постоянный мониторинг безопасности',
          ],
        },
        {
          id: 'rights',
          title: '7. Ваши права',
          content: [
            'У вас есть следующие права в отношении ваших персональных данных:',
            '• Право доступа: Вы можете запросить копию персональных данных, которые мы храним о вас',
            '• Право на исправление: Вы можете запросить исправление неточных данных',
            '• Право на удаление: Вы можете запросить удаление ваших персональных данных',
            '• Право на ограничение: Вы можете запросить ограничение обработки данных',
            '• Право на переносимость: Вы можете запросить передачу данных другому контроллеру',
            '• Право на возражение: Вы можете возражать против обработки данных для определенных целей',
            'Для осуществления этих прав, пожалуйста, свяжитесь с нами по адресу электронной почты ниже.',
          ],
        },
        {
          id: 'contact',
          title: '8. Контакты',
          content: [
            'Если у вас есть вопросы об этой Политике Конфиденциальности, пожалуйста, свяжитесь с нами:',
            '• Email: privacy@deschide.md',
            '• Адрес: Кишинев, Молдова',
            'Мы обязуемся ответить на все запросы в течение 30 дней.',
          ],
        },
      ],
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <div className="min-h-screen bg-surface-sunken dark:bg-surface-dark">
      {/* Hero Section */}
      <div className="bg-gradient-to-br from-brand-oxford-900 via-brand-oxford-800 to-brand-oxford-700 py-16 md:py-24 relative overflow-hidden">
        <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-5"></div>
        <div className="container mx-auto px-4 max-w-4xl relative z-10">
          <div className="text-center animate-fade-in-up">
            <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4">
              {t.title}
            </h1>
            <p className="text-lg text-white/80">{t.subtitle}</p>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-12 max-w-4xl">
        {/* Introduction */}
        <div className="bg-surface dark:bg-surface-dark rounded-2xl shadow-lg p-8 mb-8 border-l-4 border-brand-tomato-500 animate-fade-in">
          <p className="text-lg text-primary dark:text-primary-dark leading-relaxed">
            {t.intro}
          </p>
        </div>

        {/* Table of Contents */}
        <div className="bg-gradient-to-br from-brand-oxford-50 to-surface dark:from-gray-800 dark:to-gray-750 rounded-2xl shadow-md p-8 mb-12 animate-fade-in stagger-1">
          <h2 className="text-2xl font-bold text-primary dark:text-primary-dark mb-6 flex items-center gap-3">
            <svg className="w-8 h-8 text-brand-tomato-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h7" />
            </svg>
            {t.toc.title}
          </h2>
          <nav className="space-y-3">
            {t.toc.items.map((item, index) => (
              <a
                key={index}
                href={`#${t.sections[index].id}`}
                className="group flex items-start gap-3 text-primary dark:text-primary-dark hover:text-brand-tomato-500 dark:hover:text-brand-tomato-500 transition-colors duration-200"
              >
                <span className="flex-shrink-0 w-6 h-6 rounded-full bg-brand-tomato-500 text-white flex items-center justify-center text-sm font-bold group-hover:scale-110 transition-transform duration-200">
                  {index + 1}
                </span>
                <span className="underline-animate font-medium">{item}</span>
              </a>
            ))}
          </nav>
        </div>

        {/* Sections */}
        <div className="space-y-8">
          {t.sections.map((section, index) => (
            <section
              key={section.id}
              id={section.id}
              className="bg-surface dark:bg-surface-dark rounded-2xl shadow-md p-8 hover:shadow-lg transition-all duration-300 animate-fade-in"
              style={{ animationDelay: `${(index + 2) * 100}ms` }}
            >
              <div className="flex items-start gap-4 mb-6">
                <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-brand-tomato-500 to-brand-red-600 flex items-center justify-center shadow-md">
                  <span className="text-white font-bold text-lg">{index + 1}</span>
                </div>
                <h2 className="text-2xl md:text-3xl font-bold text-primary dark:text-primary-dark flex-1">
                  {section.title}
                </h2>
              </div>

              <div className="space-y-4 ml-14">
                {section.content.map((paragraph, pIndex) => (
                  <p
                    key={pIndex}
                    className="text-primary dark:text-primary-dark leading-relaxed"
                  >
                    {paragraph}
                  </p>
                ))}
              </div>
            </section>
          ))}
        </div>

        {/* Bottom CTA */}
        <div className="mt-12 bg-gradient-to-br from-brand-tomato-500 to-brand-red-600 rounded-2xl shadow-xl p-8 text-center animate-fade-in-up">
          <h3 className="text-2xl font-bold text-white mb-4">
            {locale === 'ro' && 'Aveți întrebări?'}
            {locale === 'en' && 'Have questions?'}
            {locale === 'ru' && 'Есть вопросы?'}
          </h3>
          <p className="text-white/90 mb-6">
            {locale === 'ro' && 'Contactați-ne pentru orice clarificări legate de confidențialitate.'}
            {locale === 'en' && 'Contact us for any privacy-related clarifications.'}
            {locale === 'ru' && 'Свяжитесь с нами для любых уточнений, связанных с конфиденциальностью.'}
          </p>
          <a
            href="mailto:privacy@deschide.md"
            className="inline-flex items-center gap-2 bg-surface text-brand-tomato-500 font-semibold px-8 py-3 rounded-lg hover:bg-gray-100 transition-colors duration-200 shadow-lg hover:shadow-xl"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            privacy@deschide.md
          </a>
        </div>
      </div>
    </div>
  );
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: PrivacyPageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Politica de Confidențialitate - Deschide News',
    en: 'Privacy Policy - Deschide News',
    ru: 'Политика Конфиденциальности - Deschide News',
  };

  const descriptions = {
    ro: 'Citiți politica noastră de confidențialitate pentru a înțelege cum colectăm, utilizăm și protejăm datele dumneavoastră personale.',
    en: 'Read our privacy policy to understand how we collect, use, and protect your personal data.',
    ru: 'Прочитайте нашу политику конфиденциальности, чтобы понять, как мы собираем, используем и защищаем ваши персональные данные.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/privacy`,
      languages: {
        ro: '/privacy',
        en: '/en/privacy',
        ru: '/ru/privacy',
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
