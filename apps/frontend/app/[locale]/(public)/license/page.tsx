import { Metadata } from 'next';

interface LicensePageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Content License Page
 *
 * Information about content licensing and usage rights
 * Premium design with clear sections
 */
export default async function LicensePage({ params }: LicensePageProps) {
  const { locale } = await params;

  const content = {
    ro: {
      title: 'Licență Conținut',
      subtitle: 'Informații despre drepturile de utilizare a conținutului',
      intro:
        'Această pagină explică drepturile și restricțiile legate de utilizarea conținutului publicat pe Deschide News.',
      sections: [
        {
          id: 'copyright',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          ),
          title: 'Notificare Drept de Autor',
          content: [
            'Tot conținutul publicat pe Deschide News, inclusiv articole, fotografii, grafice, videoclipuri și alte materiale multimedia, este protejat de legile drepturilor de autor și aparține Deschide News SRL sau creatorilor săi de conținut.',
            '© 2025 Deschide News SRL. Toate drepturile rezervate.',
            'Reproducerea, distribuirea sau utilizarea neautorizată a conținutului nostru constituie o încălcare a legilor drepturilor de autor și poate rezulta în acțiuni legale.',
          ],
        },
        {
          id: 'usage-rights',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          title: 'Drepturi de Utilizare',
          content: [
            'Vă sunt acordate următoarele drepturi limitate de utilizare:',
            '• Lectură personală: Puteți citi și vizualiza conținutul pentru uz personal, necomercial',
            '• Partajare pe social media: Puteți partaja link-uri către articolele noastre pe platforme de social media',
            '• Citare cu atribuire: Puteți cita fragmente scurte (până la 200 de cuvinte) din articolele noastre, cu condiția să includeți atribuirea clară și un link către articolul original',
            '• Arhivare personală: Puteți salva articole pentru lectură offline personală',
          ],
        },
        {
          id: 'attribution',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          ),
          title: 'Cerințe de Atribuire',
          content: [
            'Atunci când utilizați conținutul nostru în modul permis, trebuie să includeți următoarea atribuire:',
            'Format recomandat:',
            '"[Titlul Articolului]" de Deschide News, disponibil la [URL-ul complet]',
            'Exemplu:',
            '"Titlu exemplu articol" de Deschide News, disponibil la https://deschide.md/ro/article/titlu-exemplu',
            'Atribuirea trebuie să fie vizibilă și plasată în apropierea conținutului citat.',
          ],
        },
        {
          id: 'restrictions',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          ),
          title: 'Restricții de Utilizare Comercială',
          content: [
            'Următoarele utilizări sunt strict interzise fără permisiune scrisă explicită:',
            '• Republicare completă: Nu puteți republica articole complete pe alte site-uri sau publicații',
            '• Utilizare comercială: Nu puteți utiliza conținutul nostru pentru scopuri comerciale sau în materiale promoționale',
            '• Scraping automat: Nu puteți utiliza boți sau scripturi pentru a extrage conținut în mod automat',
            '• Modificare: Nu puteți modifica, adapta sau crea lucrări derivate din conținutul nostru',
            '• Vânzare: Nu puteți vinde sau sub-licenția conținutul nostru',
            '• Agregare: Nu puteți agrega conținutul nostru într-o bază de date sau colecție',
          ],
        },
        {
          id: 'third-party',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          ),
          title: 'Conținut Terță Parte',
          content: [
            'Articolele noastre pot include conținut de la terțe părți (fotografii de la agenții de presă, videoclipuri, infografice etc.).',
            'Conținutul terțelor părți este marcat corespunzător și rămâne proprietatea respectivilor creatori. Drepturile de utilizare pentru acest conținut pot diferi de politica noastră generală.',
            'Pentru utilizarea conținutului terțelor părți, trebuie să contactați direct deținătorii de drepturi respectivi.',
          ],
        },
        {
          id: 'permissions',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          ),
          title: 'Solicitarea Permisiunilor',
          content: [
            'Dacă doriți să utilizați conținutul nostru în moduri care depășesc drepturile limitate de mai sus, vă rugăm să solicitați permisiune în prealabil.',
            'Contactați-ne la:',
            '• Email: licensing@deschide.md',
            '• Subiect: Cerere de Licență - [Titlul Articolului/Tipul de Conținut]',
            'În cererea dumneavoastră, includeți:',
            '- Conținutul specific pe care doriți să-l utilizați',
            '- Scopul utilizării',
            '- Unde și cum va fi utilizat conținutul',
            '- Durata utilizării',
            'Vom răspunde cererilor în termen de 5 zile lucrătoare.',
          ],
        },
        {
          id: 'violations',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          title: 'Raportarea Încălcărilor',
          content: [
            'Dacă descoperiți utilizarea neautorizată a conținutului Deschide News, vă rugăm să ne informați imediat.',
            'Luăm în serios protecția drepturilor noastre de proprietate intelectuală și vom lua măsuri corespunzătoare împotriva încălcărilor.',
            'Pentru a raporta o încălcare:',
            '• Email: legal@deschide.md',
            '• Subiect: Raportare Încălcare Drepturi de Autor',
            'Includeți în raport:',
            '- Link-ul către conținutul original de pe Deschide News',
            '- Link-ul către utilizarea neautorizată',
            '- Orice informații relevante despre încălcare',
          ],
        },
      ],
    },
    en: {
      title: 'Content License',
      subtitle: 'Information about content usage rights',
      intro:
        'This page explains the rights and restrictions related to using content published on Deschide News.',
      sections: [
        {
          id: 'copyright',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          ),
          title: 'Copyright Notice',
          content: [
            'All content published on Deschide News, including articles, photographs, graphics, videos, and other multimedia materials, is protected by copyright laws and belongs to Deschide News SRL or its content creators.',
            '© 2025 Deschide News SRL. All rights reserved.',
            'Unauthorized reproduction, distribution, or use of our content constitutes a violation of copyright laws and may result in legal action.',
          ],
        },
        {
          id: 'usage-rights',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          title: 'Usage Rights',
          content: [
            'You are granted the following limited usage rights:',
            '• Personal reading: You may read and view content for personal, non-commercial use',
            '• Social media sharing: You may share links to our articles on social media platforms',
            '• Quotation with attribution: You may quote short excerpts (up to 200 words) from our articles, provided you include clear attribution and a link to the original article',
            '• Personal archiving: You may save articles for personal offline reading',
          ],
        },
        {
          id: 'attribution',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          ),
          title: 'Attribution Requirements',
          content: [
            'When using our content in permitted ways, you must include the following attribution:',
            'Recommended format:',
            '"[Article Title]" by Deschide News, available at [full URL]',
            'Example:',
            '"Example article title" by Deschide News, available at https://deschide.md/en/article/example-title',
            'Attribution must be visible and placed near the quoted content.',
          ],
        },
        {
          id: 'restrictions',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          ),
          title: 'Commercial Use Restrictions',
          content: [
            'The following uses are strictly prohibited without explicit written permission:',
            '• Full republication: You may not republish complete articles on other websites or publications',
            '• Commercial use: You may not use our content for commercial purposes or promotional materials',
            '• Automated scraping: You may not use bots or scripts to automatically extract content',
            '• Modification: You may not modify, adapt, or create derivative works from our content',
            '• Sale: You may not sell or sub-license our content',
            '• Aggregation: You may not aggregate our content into a database or collection',
          ],
        },
        {
          id: 'third-party',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          ),
          title: 'Third-Party Content',
          content: [
            'Our articles may include third-party content (photographs from press agencies, videos, infographics, etc.).',
            'Third-party content is appropriately marked and remains the property of respective creators. Usage rights for this content may differ from our general policy.',
            'For using third-party content, you must contact the respective rights holders directly.',
          ],
        },
        {
          id: 'permissions',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          ),
          title: 'Requesting Permissions',
          content: [
            'If you wish to use our content in ways that exceed the limited rights above, please request permission in advance.',
            'Contact us at:',
            '• Email: licensing@deschide.md',
            '• Subject: License Request - [Article Title/Content Type]',
            'In your request, include:',
            '- Specific content you wish to use',
            '- Purpose of use',
            '- Where and how the content will be used',
            '- Duration of use',
            'We will respond to requests within 5 business days.',
          ],
        },
        {
          id: 'violations',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          title: 'Reporting Violations',
          content: [
            'If you discover unauthorized use of Deschide News content, please inform us immediately.',
            'We take protection of our intellectual property rights seriously and will take appropriate action against violations.',
            'To report a violation:',
            '• Email: legal@deschide.md',
            '• Subject: Copyright Violation Report',
            'Include in your report:',
            '- Link to original content on Deschide News',
            '- Link to unauthorized use',
            '- Any relevant information about the violation',
          ],
        },
      ],
    },
    ru: {
      title: 'Лицензия Контента',
      subtitle: 'Информация о правах использования контента',
      intro:
        'Эта страница объясняет права и ограничения, связанные с использованием контента, опубликованного на Deschide News.',
      sections: [
        {
          id: 'copyright',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          ),
          title: 'Уведомление об Авторских Правах',
          content: [
            'Весь контент, опубликованный на Deschide News, включая статьи, фотографии, графику, видео и другие мультимедийные материалы, защищен законами об авторском праве и принадлежит Deschide News SRL или его создателям контента.',
            '© 2025 Deschide News SRL. Все права защищены.',
            'Несанкционированное воспроизведение, распространение или использование нашего контента является нарушением законов об авторском праве и может привести к судебным искам.',
          ],
        },
        {
          id: 'usage-rights',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          title: 'Права Использования',
          content: [
            'Вам предоставляются следующие ограниченные права использования:',
            '• Личное чтение: Вы можете читать и просматривать контент для личного, некоммерческого использования',
            '• Публикация в социальных сетях: Вы можете делиться ссылками на наши статьи в социальных сетях',
            '• Цитирование с атрибуцией: Вы можете цитировать короткие отрывки (до 200 слов) из наших статей при условии включения четкой атрибуции и ссылки на оригинальную статью',
            '• Личное архивирование: Вы можете сохранять статьи для личного чтения в оффлайн-режиме',
          ],
        },
        {
          id: 'attribution',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          ),
          title: 'Требования Атрибуции',
          content: [
            'При использовании нашего контента разрешенными способами вы должны включить следующую атрибуцию:',
            'Рекомендуемый формат:',
            '"[Название Статьи]" от Deschide News, доступно по адресу [полный URL]',
            'Пример:',
            '"Пример названия статьи" от Deschide News, доступно по адресу https://deschide.md/ru/article/primer-nazvanie',
            'Атрибуция должна быть видимой и размещена рядом с цитируемым контентом.',
          ],
        },
        {
          id: 'restrictions',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          ),
          title: 'Ограничения Коммерческого Использования',
          content: [
            'Следующие виды использования строго запрещены без явного письменного разрешения:',
            '• Полная перепубликация: Вы не можете перепубликовывать полные статьи на других сайтах или в публикациях',
            '• Коммерческое использование: Вы не можете использовать наш контент в коммерческих целях или рекламных материалах',
            '• Автоматический сбор: Вы не можете использовать ботов или скрипты для автоматического извлечения контента',
            '• Модификация: Вы не можете изменять, адаптировать или создавать производные работы из нашего контента',
            '• Продажа: Вы не можете продавать или сублицензировать наш контент',
            '• Агрегация: Вы не можете агрегировать наш контент в базу данных или коллекцию',
          ],
        },
        {
          id: 'third-party',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          ),
          title: 'Контент Третьих Лиц',
          content: [
            'Наши статьи могут включать контент третьих лиц (фотографии от информационных агентств, видео, инфографику и т.д.).',
            'Контент третьих лиц соответствующим образом помечен и остается собственностью соответствующих создателей. Права использования этого контента могут отличаться от нашей общей политики.',
            'Для использования контента третьих лиц вы должны напрямую связаться с соответствующими правообладателями.',
          ],
        },
        {
          id: 'permissions',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          ),
          title: 'Запрос Разрешений',
          content: [
            'Если вы хотите использовать наш контент способами, выходящими за рамки ограниченных прав, указанных выше, пожалуйста, запросите разрешение заранее.',
            'Свяжитесь с нами:',
            '• Email: licensing@deschide.md',
            '• Тема: Запрос лицензии - [Название статьи/Тип контента]',
            'В вашем запросе укажите:',
            '- Конкретный контент, который вы хотите использовать',
            '- Цель использования',
            '- Где и как будет использоваться контент',
            '- Продолжительность использования',
            'Мы ответим на запросы в течение 5 рабочих дней.',
          ],
        },
        {
          id: 'violations',
          icon: (
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          title: 'Сообщение о Нарушениях',
          content: [
            'Если вы обнаружите несанкционированное использование контента Deschide News, пожалуйста, немедленно сообщите нам.',
            'Мы серьезно относимся к защите наших прав интеллектуальной собственности и предпримем соответствующие действия против нарушений.',
            'Чтобы сообщить о нарушении:',
            '• Email: legal@deschide.md',
            '• Тема: Отчет о нарушении авторских прав',
            'Включите в отчет:',
            '- Ссылку на оригинальный контент на Deschide News',
            '- Ссылку на несанкционированное использование',
            '- Любую соответствующую информацию о нарушении',
          ],
        },
      ],
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <div className="min-h-screen bg-surface-sunken dark:bg-surface-dark">
      {/* Hero Section with Animated Background */}
      <div className="relative overflow-hidden bg-gradient-to-br from-brand-tomato-600 via-brand-red-600 to-brand-oxford-900 py-16 md:py-24">
        <div className="absolute inset-0">
          <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-10"></div>
          <div className="absolute top-10 left-10 w-72 h-72 bg-surface/10 rounded-full blur-3xl animate-pulse"></div>
          <div className="absolute bottom-10 right-10 w-96 h-96 bg-brand-mindaro-400/20 rounded-full blur-3xl animate-pulse" style={{ animationDelay: '1s' }}></div>
        </div>

        <div className="container mx-auto px-4 max-w-4xl relative z-10">
          <div className="text-center animate-fade-in-up">
            <div className="inline-flex items-center gap-2 bg-surface/20 backdrop-blur-sm px-6 py-3 rounded-full mb-6 shadow-lg">
              <svg className="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z" clipRule="evenodd" />
              </svg>
              <span className="text-white font-semibold">{t.subtitle}</span>
            </div>
            <h1 className="text-5xl md:text-6xl lg:text-7xl font-bold text-white mb-6 tracking-tight">
              {t.title}
            </h1>
            <p className="text-xl text-white/90 max-w-2xl mx-auto">
              {t.intro}
            </p>
          </div>
        </div>

        {/* Wave Divider */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg className="w-full h-16 fill-gray-50 dark:fill-gray-900" preserveAspectRatio="none" viewBox="0 0 1200 120">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z"></path>
          </svg>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-16 max-w-5xl">
        {/* Sections Grid */}
        <div className="space-y-8">
          {t.sections.map((section, index) => (
            <div
              key={section.id}
              id={section.id}
              className="group bg-surface dark:bg-surface-dark rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 overflow-hidden animate-fade-in"
              style={{ animationDelay: `${index * 100}ms` }}
            >
              {/* Gradient Top Bar */}
              <div className="h-2 bg-gradient-to-r from-brand-tomato-500 via-brand-red-600 to-brand-oxford-900"></div>

              <div className="p-8 md:p-10">
                {/* Header with Icon */}
                <div className="flex items-start gap-6 mb-6">
                  <div className="flex-shrink-0 w-16 h-16 rounded-2xl bg-gradient-to-br from-brand-tomato-500 to-brand-red-600 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-all duration-500">
                    {section.icon}
                  </div>
                  <div className="flex-1">
                    <h2 className="text-3xl md:text-4xl font-bold text-primary dark:text-primary-dark mb-2 group-hover:text-brand-tomato-500 dark:group-hover:text-brand-tomato-500 transition-colors duration-300">
                      {section.title}
                    </h2>
                    <div className="h-1 w-24 bg-gradient-to-r from-brand-tomato-500 to-transparent rounded-full"></div>
                  </div>
                </div>

                {/* Content */}
                <div className="space-y-4 ml-0 md:ml-22">
                  {section.content.map((paragraph, pIndex) => (
                    <p
                      key={pIndex}
                      className="text-primary dark:text-primary-dark leading-relaxed text-lg"
                    >
                      {paragraph}
                    </p>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Bottom CTA Card */}
        <div className="mt-16 bg-gradient-to-br from-brand-oxford-900 via-brand-oxford-800 to-brand-tomato-600 rounded-3xl shadow-2xl p-10 md:p-12 text-center relative overflow-hidden animate-fade-in-up">
          <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-5"></div>
          <div className="relative z-10">
            <div className="w-20 h-20 mx-auto mb-6 rounded-2xl bg-surface/20 backdrop-blur-sm flex items-center justify-center">
              <svg className="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 className="text-3xl md:text-4xl font-bold text-white mb-4">
              {locale === 'ro' && 'Întrebări despre licențiere?'}
              {locale === 'en' && 'Licensing questions?'}
              {locale === 'ru' && 'Вопросы о лицензировании?'}
            </h3>
            <p className="text-white/90 mb-8 text-lg max-w-2xl mx-auto">
              {locale === 'ro' && 'Contactați echipa noastră pentru cereri de licențiere sau permisiuni speciale.'}
              {locale === 'en' && 'Contact our team for licensing requests or special permissions.'}
              {locale === 'ru' && 'Свяжитесь с нашей командой для запросов на лицензирование или специальных разрешений.'}
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <a
                href="mailto:licensing@deschide.md"
                className="inline-flex items-center justify-center gap-3 bg-surface text-brand-oxford-900 font-semibold px-8 py-4 rounded-xl hover:bg-gray-100 transition-all duration-200 shadow-lg hover:shadow-2xl hover:scale-105"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                licensing@deschide.md
              </a>
              <a
                href="mailto:legal@deschide.md"
                className="inline-flex items-center justify-center gap-3 bg-brand-tomato-500 text-white font-semibold px-8 py-4 rounded-xl hover:bg-brand-tomato-600 transition-all duration-200 shadow-lg hover:shadow-2xl hover:scale-105"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                legal@deschide.md
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: LicensePageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Licență Conținut - Deschide News',
    en: 'Content License - Deschide News',
    ru: 'Лицензия Контента - Deschide News',
  };

  const descriptions = {
    ro: 'Informații despre drepturile de utilizare și restricții pentru conținutul publicat pe Deschide News.',
    en: 'Information about usage rights and restrictions for content published on Deschide News.',
    ru: 'Информация о правах использования и ограничениях для контента, опубликованного на Deschide News.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/license`,
      languages: {
        ro: '/license',
        en: '/en/license',
        ru: '/ru/license',
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
