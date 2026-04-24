import { Metadata } from 'next';

interface GDPRPageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * GDPR Compliance Page
 *
 * Comprehensive GDPR compliance information
 * Premium design with structured data rights sections
 */
export default async function GDPRPage({ params }: GDPRPageProps) {
  const { locale } = await params;

  const content = {
    ro: {
      title: 'Conformitate GDPR',
      subtitle: 'Protecția Datelor cu Caracter Personal',
      intro:
        'Deschide News este angajat să protejeze drepturile dumneavoastră privind confidențialitatea datelor în conformitate cu Regulamentul General privind Protecția Datelor (GDPR) și legislația Republicii Moldova.',
      toc: {
        title: 'Cuprins',
        items: [
          'Operator de date',
          'Baza legală',
          'Drepturile subiectului',
          'Perioade de stocare',
          'Transferuri internaționale',
          'Responsabil cu protecția datelor',
          'Procedura de plângere',
        ],
      },
      sections: [
        {
          id: 'controller',
          title: '1. Operator de Date',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          ),
          content: [
            'Operatorul de date pentru prelucrarea datelor dumneavoastră personale este:',
            'Nume companie: Deschide News SRL',
            'Adresă: Chișinău, Moldova',
            'Email: dpo@deschide.md',
            'Telefon: +373 (informații de contact vor fi adăugate)',
            'Suntem responsabili pentru modul în care datele dumneavoastră personale sunt colectate, utilizate și protejate.',
          ],
        },
        {
          id: 'legal-basis',
          title: '2. Baza Legală pentru Prelucrare',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          ),
          content: [
            'Prelucrăm datele dumneavoastră personale pe baza următoarelor temeiuri legale:',
            'Consimțământ: Atunci când v-ați dat consimțământul explicit pentru anumite prelucrări (de exemplu, newsletter)',
            'Interese legitime: Pentru furnizarea serviciilor noastre de știri și îmbunătățirea experienței utilizatorului',
            'Executarea unui contract: Când este necesar pentru furnizarea serviciilor solicitate',
            'Obligații legale: Când suntem obligați prin lege să prelucrăm datele',
            'Puteți retrage consimțământul în orice moment, fără a afecta legalitatea prelucrării bazate pe consimțământ înainte de retragere.',
          ],
        },
        {
          id: 'rights',
          title: '3. Drepturile Subiectului de Date',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
          ),
          content: [
            'În conformitate cu GDPR, aveți următoarele drepturi:',
          ],
          subsections: [
            {
              title: 'Dreptul de Acces (Art. 15 GDPR)',
              content: 'Aveți dreptul să obțineți confirmarea că prelucrăm datele dumneavoastră și să solicitați o copie a acestora.',
            },
            {
              title: 'Dreptul la Rectificare (Art. 16 GDPR)',
              content: 'Puteți solicita corectarea datelor inexacte sau incomplete.',
            },
            {
              title: 'Dreptul la Ștergere - "Dreptul de a fi uitat" (Art. 17 GDPR)',
              content: 'În anumite circumstanțe, puteți solicita ștergerea datelor dumneavoastră personale.',
            },
            {
              title: 'Dreptul la Restricționarea Prelucrării (Art. 18 GDPR)',
              content: 'Puteți solicita limitarea modului în care folosim datele dumneavoastră.',
            },
            {
              title: 'Dreptul la Portabilitatea Datelor (Art. 20 GDPR)',
              content: 'Puteți solicita transferul datelor dumneavoastră către un alt furnizor de servicii.',
            },
            {
              title: 'Dreptul la Opoziție (Art. 21 GDPR)',
              content: 'Puteți vă opune prelucrării datelor dumneavoastră în anumite situații.',
            },
            {
              title: 'Drepturi legate de Decizie Automată și Profilare (Art. 22 GDPR)',
              content: 'Aveți dreptul să nu fiți supus unei decizii bazate exclusiv pe prelucrare automată.',
            },
          ],
        },
        {
          id: 'retention',
          title: '4. Perioade de Păstrare a Datelor',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          content: [
            'Păstrăm datele dumneavoastră personale doar pentru perioada necesară îndeplinirii scopurilor pentru care au fost colectate:',
            'Date de utilizare (analytics): 26 de luni',
            'Date de cont: Pe durata activității contului + 30 de zile după închidere',
            'Date newsletter: Până la retragerea consimțământului',
            'Cookies: Conform setărilor browser-ului (maximum 24 de luni)',
            'Backup-uri: Maximum 90 de zile',
            'După expirarea perioadelor de păstrare, datele sunt șterse sau anonimizate în mod securizat.',
          ],
        },
        {
          id: 'transfers',
          title: '5. Transferuri Internaționale de Date',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          content: [
            'În anumite cazuri, datele dumneavoastră pot fi transferate în afara Republicii Moldova sau a Uniunii Europene.',
            'Transferurile internaționale sunt efectuate doar când:',
            '• Țara destinație asigură un nivel adecvat de protecție conform Comisiei Europene',
            '• Sunt implementate garanții adecvate (Clauze Contractuale Standard)',
            '• Aveți consimțământul explicit',
            'Servicii care pot implica transferuri:',
            '• Google Analytics (SUA) - Clauzele Contractuale Standard',
            '• Cloudflare CDN (Global) - Certificat Privacy Shield',
            '• AWS (Hosting) - Garanții adecvate',
          ],
        },
        {
          id: 'dpo',
          title: '6. Responsabil cu Protecția Datelor (DPO)',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          ),
          content: [
            'Am desemnat un Responsabil cu Protecția Datelor (DPO) pentru a supraveghea conformitatea cu GDPR.',
            'Puteți contacta DPO-ul nostru pentru:',
            '• Întrebări despre prelucrarea datelor dumneavoastră',
            '• Exercitarea drepturilor GDPR',
            '• Plângeri legate de protecția datelor',
            '• Cereri de informații despre securitatea datelor',
            'Date de contact DPO:',
            '• Email: dpo@deschide.md',
            '• Adresă: Deschide News SRL, Chișinău, Moldova',
            'DPO-ul va răspunde în termen de 5 zile lucrătoare și va rezolva cererea în maximum 30 de zile.',
          ],
        },
        {
          id: 'complaints',
          title: '7. Procedura de Plângere',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          content: [
            'Dacă considerați că drepturile dumneavoastră GDPR au fost încălcate, aveți dreptul să depuneți o plângere.',
            'Procedură internă:',
            '1. Contactați DPO-ul nostru la dpo@deschide.md',
            '2. Descrieți problema în detaliu',
            '3. Vom investiga și răspunde în 30 de zile',
            'Autoritate de supraveghere:',
            'Dacă nu sunteți mulțumit de răspunsul nostru, puteți depune o plângere la:',
            'Centrul Național pentru Protecția Datelor cu Caracter Personal',
            'Adresă: bd. Ștefan cel Mare și Sfânt 124, MD-2001, Chișinău, Moldova',
            'Telefon: +373 22 250-486',
            'Email: office@datepersonale.md',
            'Website: www.datepersonale.md',
            'Aveți dreptul la un remediu efectiv și puteți iniția acțiuni legale dacă considerați necesar.',
          ],
        },
      ],
    },
    en: {
      title: 'GDPR Compliance',
      subtitle: 'Personal Data Protection',
      intro:
        'Deschide News is committed to protecting your privacy rights in accordance with the General Data Protection Regulation (GDPR) and the legislation of the Republic of Moldova.',
      toc: {
        title: 'Table of Contents',
        items: [
          'Data controller',
          'Legal basis',
          'Data subject rights',
          'Retention periods',
          'International transfers',
          'Data protection officer',
          'Complaint procedure',
        ],
      },
      sections: [
        {
          id: 'controller',
          title: '1. Data Controller',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          ),
          content: [
            'The data controller for processing your personal data is:',
            'Company name: Deschide News SRL',
            'Address: Chișinău, Moldova',
            'Email: dpo@deschide.md',
            'Phone: +373 (contact information to be added)',
            'We are responsible for how your personal data is collected, used, and protected.',
          ],
        },
        {
          id: 'legal-basis',
          title: '2. Legal Basis for Processing',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          ),
          content: [
            'We process your personal data based on the following legal grounds:',
            'Consent: When you have given explicit consent for certain processing (e.g., newsletter)',
            'Legitimate interests: For providing our news services and improving user experience',
            'Contract performance: When necessary to provide requested services',
            'Legal obligations: When required by law to process data',
            'You can withdraw consent at any time, without affecting the lawfulness of processing based on consent before withdrawal.',
          ],
        },
        {
          id: 'rights',
          title: '3. Data Subject Rights',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
          ),
          content: [
            'In accordance with GDPR, you have the following rights:',
          ],
          subsections: [
            {
              title: 'Right of Access (Art. 15 GDPR)',
              content: 'You have the right to obtain confirmation that we process your data and to request a copy of it.',
            },
            {
              title: 'Right to Rectification (Art. 16 GDPR)',
              content: 'You can request correction of inaccurate or incomplete data.',
            },
            {
              title: 'Right to Erasure - "Right to be Forgotten" (Art. 17 GDPR)',
              content: 'In certain circumstances, you can request deletion of your personal data.',
            },
            {
              title: 'Right to Restriction of Processing (Art. 18 GDPR)',
              content: 'You can request limitation of how we use your data.',
            },
            {
              title: 'Right to Data Portability (Art. 20 GDPR)',
              content: 'You can request transfer of your data to another service provider.',
            },
            {
              title: 'Right to Object (Art. 21 GDPR)',
              content: 'You can object to processing of your data in certain situations.',
            },
            {
              title: 'Rights related to Automated Decision-Making and Profiling (Art. 22 GDPR)',
              content: 'You have the right not to be subject to a decision based solely on automated processing.',
            },
          ],
        },
        {
          id: 'retention',
          title: '4. Data Retention Periods',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          content: [
            'We retain your personal data only for the period necessary to fulfill the purposes for which it was collected:',
            'Usage data (analytics): 26 months',
            'Account data: Duration of account activity + 30 days after closure',
            'Newsletter data: Until consent withdrawal',
            'Cookies: According to browser settings (maximum 24 months)',
            'Backups: Maximum 90 days',
            'After retention periods expire, data is securely deleted or anonymized.',
          ],
        },
        {
          id: 'transfers',
          title: '5. International Data Transfers',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          content: [
            'In certain cases, your data may be transferred outside the Republic of Moldova or the European Union.',
            'International transfers are made only when:',
            '• The destination country ensures adequate protection level according to European Commission',
            '• Appropriate safeguards are implemented (Standard Contractual Clauses)',
            '• You have given explicit consent',
            'Services that may involve transfers:',
            '• Google Analytics (USA) - Standard Contractual Clauses',
            '• Cloudflare CDN (Global) - Privacy Shield certification',
            '• AWS (Hosting) - Adequate safeguards',
          ],
        },
        {
          id: 'dpo',
          title: '6. Data Protection Officer (DPO)',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          ),
          content: [
            'We have appointed a Data Protection Officer (DPO) to oversee GDPR compliance.',
            'You can contact our DPO for:',
            '• Questions about processing your data',
            '• Exercising GDPR rights',
            '• Data protection complaints',
            '• Data security information requests',
            'DPO contact details:',
            '• Email: dpo@deschide.md',
            '• Address: Deschide News SRL, Chișinău, Moldova',
            'The DPO will respond within 5 business days and resolve requests within 30 days maximum.',
          ],
        },
        {
          id: 'complaints',
          title: '7. Complaint Procedure',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          content: [
            'If you believe your GDPR rights have been violated, you have the right to lodge a complaint.',
            'Internal procedure:',
            '1. Contact our DPO at dpo@deschide.md',
            '2. Describe the issue in detail',
            '3. We will investigate and respond within 30 days',
            'Supervisory authority:',
            'If you are not satisfied with our response, you can lodge a complaint with:',
            'National Center for Personal Data Protection',
            'Address: bd. Ștefan cel Mare și Sfânt 124, MD-2001, Chișinău, Moldova',
            'Phone: +373 22 250-486',
            'Email: office@datepersonale.md',
            'Website: www.datepersonale.md',
            'You have the right to an effective remedy and can initiate legal action if necessary.',
          ],
        },
      ],
    },
    ru: {
      title: 'Соответствие GDPR',
      subtitle: 'Защита Персональных Данных',
      intro:
        'Deschide News обязуется защищать ваши права на конфиденциальность в соответствии с Общим регламентом по защите данных (GDPR) и законодательством Республики Молдова.',
      toc: {
        title: 'Содержание',
        items: [
          'Контроллер данных',
          'Правовая основа',
          'Права субъекта данных',
          'Периоды хранения',
          'Международные передачи',
          'Сотрудник по защите данных',
          'Процедура жалоб',
        ],
      },
      sections: [
        {
          id: 'controller',
          title: '1. Контроллер Данных',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          ),
          content: [
            'Контроллером данных для обработки ваших персональных данных является:',
            'Название компании: Deschide News SRL',
            'Адрес: Кишинев, Молдова',
            'Email: dpo@deschide.md',
            'Телефон: +373 (контактная информация будет добавлена)',
            'Мы несем ответственность за то, как ваши персональные данные собираются, используются и защищаются.',
          ],
        },
        {
          id: 'legal-basis',
          title: '2. Правовая Основа для Обработки',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          ),
          content: [
            'Мы обрабатываем ваши персональные данные на основании следующих правовых оснований:',
            'Согласие: Когда вы дали явное согласие на определенную обработку (например, рассылка)',
            'Законные интересы: Для предоставления наших новостных услуг и улучшения пользовательского опыта',
            'Исполнение договора: Когда необходимо для предоставления запрошенных услуг',
            'Юридические обязательства: Когда требуется по закону обрабатывать данные',
            'Вы можете отозвать согласие в любое время, не влияя на законность обработки на основе согласия до отзыва.',
          ],
        },
        {
          id: 'rights',
          title: '3. Права Субъекта Данных',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
          ),
          content: [
            'В соответствии с GDPR у вас есть следующие права:',
          ],
          subsections: [
            {
              title: 'Право на Доступ (Ст. 15 GDPR)',
              content: 'Вы имеете право получить подтверждение того, что мы обрабатываем ваши данные, и запросить их копию.',
            },
            {
              title: 'Право на Исправление (Ст. 16 GDPR)',
              content: 'Вы можете запросить исправление неточных или неполных данных.',
            },
            {
              title: 'Право на Удаление - "Право быть забытым" (Ст. 17 GDPR)',
              content: 'В определенных обстоятельствах вы можете запросить удаление ваших персональных данных.',
            },
            {
              title: 'Право на Ограничение Обработки (Ст. 18 GDPR)',
              content: 'Вы можете запросить ограничение того, как мы используем ваши данные.',
            },
            {
              title: 'Право на Переносимость Данных (Ст. 20 GDPR)',
              content: 'Вы можете запросить передачу ваших данных другому поставщику услуг.',
            },
            {
              title: 'Право на Возражение (Ст. 21 GDPR)',
              content: 'Вы можете возражать против обработки ваших данных в определенных ситуациях.',
            },
            {
              title: 'Права, связанные с Автоматизированным Принятием Решений и Профилированием (Ст. 22 GDPR)',
              content: 'Вы имеете право не подвергаться решению, основанному исключительно на автоматизированной обработке.',
            },
          ],
        },
        {
          id: 'retention',
          title: '4. Периоды Хранения Данных',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          content: [
            'Мы храним ваши персональные данные только в течение периода, необходимого для выполнения целей, для которых они были собраны:',
            'Данные использования (аналитика): 26 месяцев',
            'Данные учетной записи: Срок активности учетной записи + 30 дней после закрытия',
            'Данные рассылки: До отзыва согласия',
            'Cookies: В соответствии с настройками браузера (максимум 24 месяца)',
            'Резервные копии: Максимум 90 дней',
            'После истечения периодов хранения данные безопасно удаляются или анонимизируются.',
          ],
        },
        {
          id: 'transfers',
          title: '5. Международные Передачи Данных',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          content: [
            'В определенных случаях ваши данные могут быть переданы за пределы Республики Молдова или Европейского Союза.',
            'Международные передачи осуществляются только когда:',
            '• Страна назначения обеспечивает адекватный уровень защиты согласно Европейской Комиссии',
            '• Реализованы соответствующие меры защиты (Стандартные Договорные Оговорки)',
            '• Вы дали явное согласие',
            'Сервисы, которые могут включать передачи:',
            '• Google Analytics (США) - Стандартные Договорные Оговорки',
            '• Cloudflare CDN (Глобально) - Сертификация Privacy Shield',
            '• AWS (Хостинг) - Адекватные меры защиты',
          ],
        },
        {
          id: 'dpo',
          title: '6. Сотрудник по Защите Данных (DPO)',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          ),
          content: [
            'Мы назначили Сотрудника по Защите Данных (DPO) для надзора за соблюдением GDPR.',
            'Вы можете связаться с нашим DPO для:',
            '• Вопросов об обработке ваших данных',
            '• Осуществления прав GDPR',
            '• Жалоб по защите данных',
            '• Запросов информации о безопасности данных',
            'Контактные данные DPO:',
            '• Email: dpo@deschide.md',
            '• Адрес: Deschide News SRL, Кишинев, Молдова',
            'DPO ответит в течение 5 рабочих дней и разрешит запросы в течение максимум 30 дней.',
          ],
        },
        {
          id: 'complaints',
          title: '7. Процедура Подачи Жалоб',
          icon: (
            <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          content: [
            'Если вы считаете, что ваши права GDPR были нарушены, вы имеете право подать жалобу.',
            'Внутренняя процедура:',
            '1. Свяжитесь с нашим DPO по адресу dpo@deschide.md',
            '2. Подробно опишите проблему',
            '3. Мы проведем расследование и ответим в течение 30 дней',
            'Надзорный орган:',
            'Если вы не удовлетворены нашим ответом, вы можете подать жалобу в:',
            'Национальный центр по защите персональных данных',
            'Адрес: bd. Ștefan cel Mare și Sfânt 124, MD-2001, Кишинев, Молдова',
            'Телефон: +373 22 250-486',
            'Email: office@datepersonale.md',
            'Сайт: www.datepersonale.md',
            'Вы имеете право на эффективное средство правовой защиты и можете инициировать судебные разбирательства при необходимости.',
          ],
        },
      ],
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
      {/* Hero Section with EU Flag Colors */}
      <div className="relative overflow-hidden bg-gradient-to-br from-blue-900 via-blue-800 to-blue-700 py-20 md:py-28">
        <div className="absolute inset-0">
          <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-10"></div>
          {/* Star pattern reminiscent of EU flag */}
          <div className="absolute inset-0 flex items-center justify-center opacity-5">
            {[...Array(12)].map((_, i) => (
              <div
                key={i}
                className="absolute w-8 h-8 text-yellow-300"
                style={{
                  transform: `rotate(${i * 30}deg) translateY(-120px)`,
                }}
              >
                ★
              </div>
            ))}
          </div>
        </div>

        <div className="container mx-auto px-4 max-w-5xl relative z-10">
          <div className="text-center animate-fade-in-up">
            <div className="inline-flex items-center gap-3 bg-surface/20 backdrop-blur-sm px-6 py-3 rounded-full mb-8 shadow-lg">
              <svg className="w-6 h-6 text-yellow-300" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
              </svg>
              <span className="text-white font-semibold text-lg">{t.subtitle}</span>
            </div>
            <h1 className="text-5xl md:text-6xl lg:text-7xl font-bold text-white mb-6 tracking-tight">
              {t.title}
            </h1>
            <p className="text-xl md:text-2xl text-white/90 max-w-3xl mx-auto leading-relaxed">
              {t.intro}
            </p>
          </div>
        </div>

        {/* Wave Divider */}
        <div className="absolute bottom-0 left-0 right-0">
          <svg className="w-full h-20 fill-gray-50 dark:fill-gray-900" preserveAspectRatio="none" viewBox="0 0 1200 120">
            <path d="M985.66,92.83C906.67,72,823.78,31,743.84,14.19c-82.26-17.34-168.06-16.33-250.45.39-57.84,11.73-114,31.07-172,41.86A600.21,600.21,0,0,1,0,27.35V120H1200V95.8C1132.19,118.92,1055.71,111.31,985.66,92.83Z"></path>
          </svg>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-16 max-w-5xl">
        {/* Quick Info Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
          <div className="bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl p-6 text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in">
            <div className="text-4xl mb-3">🇪🇺</div>
            <h3 className="text-xl font-bold mb-2">GDPR</h3>
            <p className="text-white/90 text-sm">Regulament UE 2016/679</p>
          </div>
          <div className="bg-gradient-to-br from-green-600 to-green-700 rounded-2xl p-6 text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in stagger-1">
            <div className="text-4xl mb-3">🛡️</div>
            <h3 className="text-xl font-bold mb-2">
              {locale === 'ro' && 'Protecție'}
              {locale === 'en' && 'Protection'}
              {locale === 'ru' && 'Защита'}
            </h3>
            <p className="text-white/90 text-sm">
              {locale === 'ro' && 'Date personale securizate'}
              {locale === 'en' && 'Personal data secured'}
              {locale === 'ru' && 'Защищенные персональные данные'}
            </p>
          </div>
          <div className="bg-gradient-to-br from-purple-600 to-purple-700 rounded-2xl p-6 text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in stagger-2">
            <div className="text-4xl mb-3">⚖️</div>
            <h3 className="text-xl font-bold mb-2">
              {locale === 'ro' && 'Drepturi'}
              {locale === 'en' && 'Rights'}
              {locale === 'ru' && 'Права'}
            </h3>
            <p className="text-white/90 text-sm">
              {locale === 'ro' && '7 drepturi garantate'}
              {locale === 'en' && '7 guaranteed rights'}
              {locale === 'ru' && '7 гарантированных прав'}
            </p>
          </div>
        </div>

        {/* Table of Contents */}
        <div className="bg-surface dark:bg-surface-dark rounded-2xl shadow-xl p-8 mb-12 border-t-4 border-blue-600 animate-fade-in stagger-3">
          <h2 className="text-3xl font-bold text-primary dark:text-primary-dark mb-6 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-600 to-blue-700 flex items-center justify-center">
              <svg className="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h7" />
              </svg>
            </div>
            {t.toc.title}
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {t.toc.items.map((item, index) => (
              <a
                key={index}
                href={`#${t.sections[index].id}`}
                className="group flex items-center gap-3 p-4 rounded-xl hover:bg-blue-50 dark:hover:bg-gray-750 transition-all duration-200 border border-transparent hover:border-blue-200 dark:hover:border-blue-800"
              >
                <span className="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center text-sm font-bold group-hover:scale-110 transition-transform duration-200">
                  {index + 1}
                </span>
                <span className="text-primary dark:text-primary-dark group-hover:text-blue-600 dark:group-hover:text-blue-400 font-medium transition-colors duration-200">
                  {item}
                </span>
              </a>
            ))}
          </div>
        </div>

        {/* Sections */}
        <div className="space-y-8">
          {t.sections.map((section, index) => (
            <section
              key={section.id}
              id={section.id}
              className="bg-surface dark:bg-surface-dark rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 overflow-hidden animate-fade-in"
              style={{ animationDelay: `${(index + 4) * 100}ms` }}
            >
              {/* Gradient Header */}
              <div className="h-2 bg-gradient-to-r from-blue-600 via-purple-600 to-blue-600"></div>

              <div className="p-8 md:p-10">
                {/* Title with Icon */}
                <div className="flex items-start gap-5 mb-8">
                  <div className="flex-shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center text-white shadow-lg">
                    {section.icon}
                  </div>
                  <div className="flex-1">
                    <h2 className="text-3xl md:text-4xl font-bold text-primary dark:text-primary-dark mb-2">
                      {section.title}
                    </h2>
                    <div className="h-1 w-32 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full"></div>
                  </div>
                </div>

                {/* Content */}
                <div className="space-y-4 ml-0 md:ml-19">
                  {section.content.map((paragraph, pIndex) => (
                    <p
                      key={pIndex}
                      className="text-primary dark:text-primary-dark leading-relaxed text-lg"
                    >
                      {paragraph}
                    </p>
                  ))}

                  {/* Subsections (for Rights section) */}
                  {section.subsections && (
                    <div className="mt-8 space-y-6">
                      {section.subsections.map((subsection, sIndex) => (
                        <div
                          key={sIndex}
                          className="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-gray-750 dark:to-gray-750 rounded-xl p-6 border-l-4 border-blue-600"
                        >
                          <h4 className="text-xl font-bold text-primary dark:text-primary-dark mb-3">
                            {subsection.title}
                          </h4>
                          <p className="text-primary dark:text-primary-dark leading-relaxed">
                            {subsection.content}
                          </p>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </section>
          ))}
        </div>

        {/* Bottom DPO Contact Card */}
        <div className="mt-16 bg-gradient-to-br from-blue-900 via-purple-900 to-blue-800 rounded-3xl shadow-2xl p-10 md:p-14 text-center relative overflow-hidden animate-fade-in-up">
          <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-5"></div>
          <div className="relative z-10">
            <div className="w-24 h-24 mx-auto mb-8 rounded-3xl bg-surface/20 backdrop-blur-sm flex items-center justify-center">
              <svg className="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <h3 className="text-4xl md:text-5xl font-bold text-white mb-6">
              {locale === 'ro' && 'Contactați DPO-ul'}
              {locale === 'en' && 'Contact DPO'}
              {locale === 'ru' && 'Свяжитесь с DPO'}
            </h3>
            <p className="text-white/90 mb-8 text-xl max-w-2xl mx-auto leading-relaxed">
              {locale === 'ro' && 'Pentru orice întrebări legate de protecția datelor sau pentru a-ți exercita drepturile GDPR.'}
              {locale === 'en' && 'For any data protection questions or to exercise your GDPR rights.'}
              {locale === 'ru' && 'По любым вопросам защиты данных или для осуществления ваших прав GDPR.'}
            </p>
            <a
              href="mailto:dpo@deschide.md"
              className="inline-flex items-center gap-4 bg-surface text-blue-900 font-bold px-10 py-5 rounded-2xl hover:bg-gray-100 transition-all duration-200 shadow-2xl hover:shadow-3xl hover:scale-105"
            >
              <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <span className="text-xl">dpo@deschide.md</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}

/**
 * Generate metadata for SEO
 */
export async function generateMetadata({ params }: GDPRPageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Conformitate GDPR - Deschide News',
    en: 'GDPR Compliance - Deschide News',
    ru: 'Соответствие GDPR - Deschide News',
  };

  const descriptions = {
    ro: 'Informații complete despre conformitatea GDPR, drepturile dumneavoastră privind protecția datelor și cum să le exercitați.',
    en: 'Complete information about GDPR compliance, your data protection rights and how to exercise them.',
    ru: 'Полная информация о соответствии GDPR, ваших правах на защиту данных и о том, как их осуществить.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/gdpr`,
      languages: {
        'ro-MD': '/ro/gdpr',
        ro: '/ro/gdpr',
        en: '/en/gdpr',
        ru: '/ru/gdpr',
        'x-default': '/ro/gdpr',
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
