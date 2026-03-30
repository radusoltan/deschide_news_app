import { Metadata } from 'next';

interface TermsPageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Terms of Service Page
 *
 * Comprehensive terms and conditions with premium styling
 * Legal framework for using Deschide News
 */
export default async function TermsPage({ params }: TermsPageProps) {
  const { locale } = await params;

  const content = {
    ro: {
      title: 'Termeni și Condiții',
      subtitle: 'Ultima actualizare: 12 Decembrie 2025',
      intro:
        'Vă rugăm să citiți cu atenție acești termeni și condiții înainte de a utiliza serviciile Deschide News. Accesând site-ul nostru, acceptați să respectați acești termeni.',
      toc: {
        title: 'Cuprins',
        items: [
          'Acceptarea termenilor',
          'Utilizarea site-ului',
          'Proprietate intelectuală',
          'Conținut utilizator',
          'Disclaimer',
          'Limitarea răspunderii',
          'Modificări ale termenilor',
          'Legea aplicabilă',
          'Contact',
        ],
      },
      sections: [
        {
          id: 'acceptance',
          title: '1. Acceptarea termenilor',
          content: [
            'Prin accesarea și utilizarea site-ului deschide.md ("Site-ul"), acceptați să fiți legat de acești Termeni și Condiții ("Termeni"), de toate legile și reglementările aplicabile, și sunteți de acord că sunteți responsabil pentru respectarea oricăror legi locale aplicabile.',
            'Dacă nu sunteți de acord cu oricare dintre acești termeni, vă este interzis să utilizați sau să accesați acest site. Materialele conținute în acest site sunt protejate de legile aplicabile privind drepturile de autor și mărcile comerciale.',
          ],
        },
        {
          id: 'use',
          title: '2. Utilizarea site-ului',
          content: [
            'Vă este acordată permisiunea de a utiliza Site-ul în scopuri personale, necomerciale. Nu puteți:',
            '• Modifica sau copia materialele de pe Site',
            '• Utiliza materialele în scopuri comerciale sau pentru orice afișare publică',
            '• Încerca să decompilați sau să faceți reverse engineering asupra oricărui software conținut pe Site',
            '• Elimina orice drepturi de autor sau alte notări de proprietate din materiale',
            '• Transfera materialele către o altă persoană sau să "oglindești" materialele pe orice alt server',
            'Această licență va înceta automat dacă încălcați oricare dintre aceste restricții și poate fi reziliată de Deschide News în orice moment.',
          ],
        },
        {
          id: 'intellectual-property',
          title: '3. Proprietate intelectuală',
          content: [
            'Tot conținutul de pe Site-ul Deschide News, inclusiv, dar fără a se limita la text, grafice, logo-uri, imagini, clipuri audio, clipuri video, descărcări digitale și compilări de date, este proprietatea Deschide News SRL sau a furnizorilor săi de conținut și este protejat de legile moldovenești și internaționale privind drepturile de autor.',
            'Marca comercială Deschide News și logo-urile asociate sunt mărci comerciale înregistrate ale Deschide News SRL. Toate celelalte mărci comerciale care nu sunt deținute de Deschide News care apar pe acest site sunt proprietatea proprietarilor lor respectivi.',
            'Folosirea neautorizată a oricărui material de pe acest site poate încălca legile privind drepturile de autor, legile privind mărcile comerciale, legile privind confidențialitatea și publicitatea și reglementările și statutele de comunicații.',
          ],
        },
        {
          id: 'user-content',
          title: '4. Conținut utilizator',
          content: [
            'Dacă trimiteți comentarii, feedback, sugestii sau alt conținut ("Conținut Utilizator") către Site, acordați Deschide News dreptul neexclusiv, perpetuu, irevocabil, complet plătit, fără redevențe, la nivel mondial, cu drept de sub-licențiere, de a utiliza, reproduce, modifica, adapta, publica, traduce, crea lucrări derivate, distribui și afișa astfel de Conținut în orice media.',
            'Sunteți singurul responsabil pentru Conținutul Utilizator pe care îl trimiteți. Nu puteți transmite Conținut Utilizator care:',
            '• Este ilegal, dăunător, amenințător, abuziv, hărțuitor, defăimător, vulgar, obscen sau invadează confidențialitatea altcuiva',
            '• Încalcă orice brevet, marcă comercială, secret comercial, drepturi de autor sau alte drepturi de proprietate',
            '• Conține viruși software sau orice alt cod computerizat conceput pentru a întrerupe, distruge sau limita funcționalitatea oricărui software sau hardware',
            'Deschide News își rezervă dreptul de a elimina orice Conținut Utilizator în orice moment, din orice motiv, fără notificare prealabilă.',
          ],
        },
        {
          id: 'disclaimer',
          title: '5. Disclaimer',
          content: [
            'Materialele de pe Site-ul Deschide News sunt furnizate "ca atare". Deschide News nu oferă garanții, exprese sau implicite, și prin prezenta declină și neagă toate celelalte garanții, inclusiv, fără limitare, garanțiile implicite sau condițiile de vandabilitate, adecvare pentru un anumit scop sau neîncălcarea proprietății intelectuale sau a altei încălcări a drepturilor.',
            'În plus, Deschide News nu garantează sau nu face nicio reprezentare privind acuratețea, rezultatele probabile sau fiabilitatea utilizării materialelor de pe Site-ul său sau în alt mod referitoare la astfel de materiale sau pe orice site-uri legate de acest site.',
            'Știrile și informațiile publicate pe Site sunt oferite cu bună credință și pentru uz general informațional numai. Deschide News nu este responsabil pentru nicio acțiune întreprinsă pe baza informațiilor publicate pe Site.',
          ],
        },
        {
          id: 'limitation',
          title: '6. Limitarea răspunderii',
          content: [
            'În niciun caz Deschide News sau furnizorii săi nu vor fi răspunzători pentru niciun fel de daune (inclusiv, fără limitare, daune pentru pierderea de date sau profit sau din cauza întreruperii afacerii) care rezultă din utilizarea sau incapacitatea de a utiliza materialele de pe Site-ul Deschide News, chiar dacă Deschide News sau un reprezentant autorizat Deschide News a fost notificat oral sau în scris despre posibilitatea unor astfel de daune.',
            'Deoarece unele jurisdicții nu permit limitări ale garanțiilor implicite sau limitări ale răspunderii pentru daunele consecutive sau incidentale, aceste limitări pot să nu se aplice în cazul dumneavoastră.',
            'Răspunderea noastră totală față de dumneavoastră pentru orice pretenții care decurg din sau în legătură cu acești Termeni nu va depăși 100 EUR.',
          ],
        },
        {
          id: 'modifications',
          title: '7. Modificări ale termenilor',
          content: [
            'Deschide News poate revizui acești Termeni și Condiții pentru Site-ul său în orice moment, fără notificare prealabilă. Prin utilizarea acestui site, acceptați să fiți legat de versiunea curentă a acestor Termeni și Condiții.',
            'Vom notifica utilizatorii despre orice modificări semnificative ale acestor Termeni prin publicarea unei notificări pe Site sau prin trimiterea unui e-mail către adresa de e-mail asociată contului dumneavoastră (dacă este cazul).',
            'Utilizarea continuă a Site-ului după publicarea modificărilor va constitui acceptarea dumneavoastră a acestor modificări.',
          ],
        },
        {
          id: 'governing-law',
          title: '8. Legea aplicabilă',
          content: [
            'Acești Termeni și Condiții sunt guvernate și interpretate în conformitate cu legile Republicii Moldova, și vă supuneți în mod irevocabil jurisdicției exclusive a instanțelor din Chișinău, Moldova.',
            'Orice litigii care decurg din sau în legătură cu acești Termeni vor fi soluționate în instanțele competente din Republica Moldova.',
            'Dacă oricare dintre dispozițiile acestor Termeni este considerată nevalidă sau neaplicabilă de către o instanță de jurisdicție competentă, dispozițiile rămase vor continua să fie pe deplin în vigoare și efect.',
          ],
        },
        {
          id: 'contact',
          title: '9. Contact',
          content: [
            'Pentru întrebări sau preocupări legate de acești Termeni și Condiții, vă rugăm să ne contactați:',
            '• Email: legal@deschide.md',
            '• Adresă: Deschide News SRL, Chișinău, Moldova',
            '• Telefon: +373 (informații de contact vor fi adăugate)',
            'Ne vom strădui să răspundem tuturor întrebărilor în cel mai scurt timp posibil.',
          ],
        },
      ],
    },
    en: {
      title: 'Terms and Conditions',
      subtitle: 'Last updated: December 12, 2025',
      intro:
        'Please read these terms and conditions carefully before using Deschide News services. By accessing our site, you agree to comply with these terms.',
      toc: {
        title: 'Table of Contents',
        items: [
          'Acceptance of terms',
          'Use of website',
          'Intellectual property',
          'User content',
          'Disclaimer',
          'Limitation of liability',
          'Changes to terms',
          'Governing law',
          'Contact',
        ],
      },
      sections: [
        {
          id: 'acceptance',
          title: '1. Acceptance of terms',
          content: [
            'By accessing and using the deschide.md website ("Website"), you accept to be bound by these Terms and Conditions ("Terms"), all applicable laws and regulations, and agree that you are responsible for compliance with any applicable local laws.',
            'If you do not agree with any of these terms, you are prohibited from using or accessing this site. The materials contained in this Website are protected by applicable copyright and trademark law.',
          ],
        },
        {
          id: 'use',
          title: '2. Use of website',
          content: [
            'You are granted permission to use the Website for personal, non-commercial purposes. You may not:',
            '• Modify or copy the materials on the Website',
            '• Use the materials for any commercial purpose or for any public display',
            '• Attempt to decompile or reverse engineer any software contained on the Website',
            '• Remove any copyright or other proprietary notations from the materials',
            '• Transfer the materials to another person or "mirror" the materials on any other server',
            'This license shall automatically terminate if you violate any of these restrictions and may be terminated by Deschide News at any time.',
          ],
        },
        {
          id: 'intellectual-property',
          title: '3. Intellectual property',
          content: [
            'All content on the Deschide News Website, including but not limited to text, graphics, logos, images, audio clips, video clips, digital downloads, and data compilations, is the property of Deschide News SRL or its content suppliers and protected by Moldovan and international copyright laws.',
            'The Deschide News trademark and associated logos are registered trademarks of Deschide News SRL. All other trademarks not owned by Deschide News that appear on this site are the property of their respective owners.',
            'Unauthorized use of any material on this site may violate copyright laws, trademark laws, privacy and publicity laws, and communications regulations and statutes.',
          ],
        },
        {
          id: 'user-content',
          title: '4. User content',
          content: [
            'If you submit comments, feedback, suggestions, or other content ("User Content") to the Website, you grant Deschide News the non-exclusive, perpetual, irrevocable, fully-paid, royalty-free, worldwide, sublicensable right to use, reproduce, modify, adapt, publish, translate, create derivative works from, distribute, and display such Content in any media.',
            'You are solely responsible for the User Content you submit. You may not transmit User Content that:',
            '• Is illegal, harmful, threatening, abusive, harassing, defamatory, vulgar, obscene, or invasive of another\'s privacy',
            '• Infringes any patent, trademark, trade secret, copyright, or other proprietary rights',
            '• Contains software viruses or any other computer code designed to interrupt, destroy, or limit the functionality of any software or hardware',
            'Deschide News reserves the right to remove any User Content at any time, for any reason, without prior notice.',
          ],
        },
        {
          id: 'disclaimer',
          title: '5. Disclaimer',
          content: [
            'The materials on Deschide News Website are provided "as is". Deschide News makes no warranties, expressed or implied, and hereby disclaims and negates all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.',
            'Further, Deschide News does not warrant or make any representations concerning the accuracy, likely results, or reliability of the use of the materials on its Website or otherwise relating to such materials or on any sites linked to this site.',
            'News and information published on the Website are offered in good faith and for general information purposes only. Deschide News is not responsible for any action taken based on information published on the Website.',
          ],
        },
        {
          id: 'limitation',
          title: '6. Limitation of liability',
          content: [
            'In no event shall Deschide News or its suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials on Deschide News Website, even if Deschide News or a Deschide News authorized representative has been notified orally or in writing of the possibility of such damage.',
            'Because some jurisdictions do not allow limitations on implied warranties, or limitations of liability for consequential or incidental damages, these limitations may not apply to you.',
            'Our total liability to you for any claims arising out of or related to these Terms will not exceed EUR 100.',
          ],
        },
        {
          id: 'modifications',
          title: '7. Changes to terms',
          content: [
            'Deschide News may revise these Terms and Conditions for its Website at any time without prior notice. By using this Website, you agree to be bound by the current version of these Terms and Conditions.',
            'We will notify users of any significant changes to these Terms by posting a notice on the Website or by sending an email to the email address associated with your account (if applicable).',
            'Continued use of the Website after posting of changes will constitute your acceptance of such changes.',
          ],
        },
        {
          id: 'governing-law',
          title: '8. Governing law',
          content: [
            'These Terms and Conditions are governed by and construed in accordance with the laws of the Republic of Moldova, and you irrevocably submit to the exclusive jurisdiction of the courts in Chișinău, Moldova.',
            'Any disputes arising from or relating to these Terms shall be resolved in the competent courts of the Republic of Moldova.',
            'If any provision of these Terms is deemed invalid or unenforceable by a court of competent jurisdiction, the remaining provisions shall continue in full force and effect.',
          ],
        },
        {
          id: 'contact',
          title: '9. Contact',
          content: [
            'For questions or concerns regarding these Terms and Conditions, please contact us:',
            '• Email: legal@deschide.md',
            '• Address: Deschide News SRL, Chișinău, Moldova',
            '• Phone: +373 (contact information to be added)',
            'We will strive to respond to all inquiries as soon as possible.',
          ],
        },
      ],
    },
    ru: {
      title: 'Условия Использования',
      subtitle: 'Последнее обновление: 12 декабря 2025',
      intro:
        'Пожалуйста, внимательно прочитайте эти условия перед использованием сервисов Deschide News. Получая доступ к нашему сайту, вы соглашаетесь соблюдать эти условия.',
      toc: {
        title: 'Содержание',
        items: [
          'Принятие условий',
          'Использование сайта',
          'Интеллектуальная собственность',
          'Контент пользователя',
          'Отказ от ответственности',
          'Ограничение ответственности',
          'Изменения условий',
          'Применимое право',
          'Контакты',
        ],
      },
      sections: [
        {
          id: 'acceptance',
          title: '1. Принятие условий',
          content: [
            'Получая доступ и используя веб-сайт deschide.md ("Сайт"), вы соглашаетесь соблюдать эти Условия ("Условия"), все применимые законы и нормативные акты, и соглашаетесь, что вы несете ответственность за соблюдение любых применимых местных законов.',
            'Если вы не согласны с любым из этих условий, вам запрещено использовать или получать доступ к этому сайту. Материалы, содержащиеся на этом Сайте, защищены применимым законодательством об авторском праве и товарных знаках.',
          ],
        },
        {
          id: 'use',
          title: '2. Использование сайта',
          content: [
            'Вам предоставляется разрешение на использование Сайта в личных, некоммерческих целях. Вы не можете:',
            '• Изменять или копировать материалы на Сайте',
            '• Использовать материалы в коммерческих целях или для любого публичного показа',
            '• Пытаться декомпилировать или реконструировать любое программное обеспечение, содержащееся на Сайте',
            '• Удалять любые авторские права или другие уведомления о правах собственности из материалов',
            '• Передавать материалы другому лицу или "зеркалировать" материалы на любом другом сервере',
            'Эта лицензия автоматически прекращается, если вы нарушите любое из этих ограничений, и может быть прекращена Deschide News в любое время.',
          ],
        },
        {
          id: 'intellectual-property',
          title: '3. Интеллектуальная собственность',
          content: [
            'Весь контент на сайте Deschide News, включая, но не ограничиваясь, текст, графику, логотипы, изображения, аудиоклипы, видеоклипы, цифровые загрузки и компиляции данных, является собственностью Deschide News SRL или его поставщиков контента и защищен молдавским и международным законодательством об авторском праве.',
            'Торговая марка Deschide News и связанные логотипы являются зарегистрированными торговыми марками Deschide News SRL. Все другие торговые марки, не принадлежащие Deschide News, которые появляются на этом сайте, являются собственностью их соответствующих владельцев.',
            'Несанкционированное использование любого материала на этом сайте может нарушать законы об авторском праве, законы о товарных знаках, законы о конфиденциальности и публичности, а также правила и уставы связи.',
          ],
        },
        {
          id: 'user-content',
          title: '4. Контент пользователя',
          content: [
            'Если вы отправляете комментарии, отзывы, предложения или другой контент ("Контент пользователя") на Сайт, вы предоставляете Deschide News неисключительное, постоянное, безотзывное, полностью оплаченное, бесплатное, всемирное, сублицензируемое право использовать, воспроизводить, изменять, адаптировать, публиковать, переводить, создавать производные работы, распространять и отображать такой Контент в любых медиа.',
            'Вы несете единоличную ответственность за Контент пользователя, который вы отправляете. Вы не можете передавать Контент пользователя, который:',
            '• Является незаконным, вредным, угрожающим, оскорбительным, преследующим, клеветническим, вульгарным, непристойным или вторгается в чью-либо конфиденциальность',
            '• Нарушает любой патент, торговую марку, коммерческую тайну, авторские права или другие права собственности',
            '• Содержит программные вирусы или любой другой компьютерный код, предназначенный для прерывания, уничтожения или ограничения функциональности любого программного или аппаратного обеспечения',
            'Deschide News оставляет за собой право удалить любой Контент пользователя в любое время, по любой причине, без предварительного уведомления.',
          ],
        },
        {
          id: 'disclaimer',
          title: '5. Отказ от ответственности',
          content: [
            'Материалы на сайте Deschide News предоставляются "как есть". Deschide News не дает никаких гарантий, явных или подразумеваемых, и настоящим отказывается и отрицает все другие гарантии, включая, без ограничений, подразумеваемые гарантии или условия товарности, пригодности для определенной цели или ненарушения прав интеллектуальной собственности или другого нарушения прав.',
            'Кроме того, Deschide News не гарантирует и не делает никаких заявлений относительно точности, вероятных результатов или надежности использования материалов на своем Сайте или иным образом в отношении таких материалов или на любых сайтах, связанных с этим сайтом.',
            'Новости и информация, опубликованные на Сайте, предлагаются добросовестно и только для общих информационных целей. Deschide News не несет ответственности за любые действия, предпринятые на основе информации, опубликованной на Сайте.',
          ],
        },
        {
          id: 'limitation',
          title: '6. Ограничение ответственности',
          content: [
            'Ни при каких обстоятельствах Deschide News или его поставщики не несут ответственности за любой ущерб (включая, без ограничений, убытки от потери данных или прибыли, или из-за прерывания бизнеса), возникающий в результате использования или невозможности использования материалов на Сайте Deschide News, даже если Deschide News или уполномоченный представитель Deschide News был уведомлен устно или письменно о возможности такого ущерба.',
            'Поскольку некоторые юрисдикции не допускают ограничений подразумеваемых гарантий или ограничений ответственности за косвенный или случайный ущерб, эти ограничения могут к вам не применяться.',
            'Наша общая ответственность перед вами за любые претензии, возникающие из или связанные с этими Условиями, не превысит 100 евро.',
          ],
        },
        {
          id: 'modifications',
          title: '7. Изменения условий',
          content: [
            'Deschide News может пересмотреть эти Условия для своего Сайта в любое время без предварительного уведомления. Используя этот Сайт, вы соглашаетесь соблюдать текущую версию этих Условий.',
            'Мы уведомим пользователей о любых значительных изменениях в этих Условиях, опубликовав уведомление на Сайте или отправив электронное письмо на адрес электронной почты, связанный с вашей учетной записью (если применимо).',
            'Продолжение использования Сайта после публикации изменений будет означать ваше принятие таких изменений.',
          ],
        },
        {
          id: 'governing-law',
          title: '8. Применимое право',
          content: [
            'Эти Условия регулируются и толкуются в соответствии с законами Республики Молдова, и вы безотзывно подчиняетесь исключительной юрисдикции судов в Кишиневе, Молдова.',
            'Любые споры, возникающие из или в связи с этими Условиями, будут разрешаться в компетентных судах Республики Молдова.',
            'Если какое-либо положение этих Условий будет признано недействительным или неисполнимым судом компетентной юрисдикции, оставшиеся положения останутся в полной силе.',
          ],
        },
        {
          id: 'contact',
          title: '9. Контакты',
          content: [
            'По вопросам или проблемам, связанным с этими Условиями, пожалуйста, свяжитесь с нами:',
            '• Email: legal@deschide.md',
            '• Адрес: Deschide News SRL, Кишинев, Молдова',
            '• Телефон: +373 (контактная информация будет добавлена)',
            'Мы постараемся ответить на все запросы как можно скорее.',
          ],
        },
      ],
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  return (
    <div className="min-h-screen bg-gradient-to-br from-[var(--color-surface-sunken)] via-[var(--color-surface-elevated)] to-[var(--color-surface-sunken)] dark:from-[var(--color-surface-dark)] dark:via-[var(--color-surface-elevated-dark)] dark:to-[var(--color-surface-dark)]">
      {/* Hero Section */}
      <div className="relative overflow-hidden bg-gradient-to-br from-[var(--color-surface-dark)] via-[var(--color-surface-dark)] to-[var(--color-surface-elevated-dark)] py-16 md:py-24">
        <div className="absolute inset-0 opacity-10">
          <div className="absolute inset-0 bg-[url('/grid.svg')]"></div>
        </div>
        <div className="absolute top-0 right-0 w-96 h-96 bg-[var(--color-accent)]/20 rounded-full blur-3xl"></div>
        <div className="absolute bottom-0 left-0 w-96 h-96 bg-[var(--color-breaking)]/20 rounded-full blur-3xl"></div>

        <div className="container mx-auto px-4 max-w-4xl relative z-10">
          <div className="text-center animate-fade-in-up">
            <div className="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm px-4 py-2 rounded-full mb-6">
              <svg className="w-5 h-5 text-[var(--color-accent)]" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
              </svg>
              <span className="text-white/90 text-sm font-medium">{t.subtitle}</span>
            </div>
            <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4">
              {t.title}
            </h1>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-12 max-w-4xl">
        {/* Introduction Alert */}
        <div className="bg-gradient-to-r from-[var(--color-accent)] to-[var(--color-breaking)] rounded-2xl shadow-xl p-8 mb-8 animate-fade-in">
          <div className="flex items-start gap-4">
            <div className="flex-shrink-0 w-12 h-12 bg-white rounded-lg flex items-center justify-center">
              <svg className="w-7 h-7 text-[var(--color-accent)]" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
              </svg>
            </div>
            <div>
              <h3 className="text-xl font-bold text-white mb-2">
                {locale === 'ro' && 'Important'}
                {locale === 'en' && 'Important'}
                {locale === 'ru' && 'Важно'}
              </h3>
              <p className="text-white/95 leading-relaxed">
                {t.intro}
              </p>
            </div>
          </div>
        </div>

        {/* Table of Contents */}
        <div className="bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-2xl shadow-lg p-8 mb-12 border-t-4 border-[var(--color-text-primary)] animate-fade-in stagger-1">
          <h2 className="text-2xl font-bold text-[var(--color-text-primary)] dark:text-white mb-6 flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[var(--color-surface-dark)] to-[var(--color-surface-elevated-dark)] flex items-center justify-center">
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h7" />
              </svg>
            </div>
            {t.toc.title}
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {t.toc.items.map((item, index) => (
              <a
                key={index}
                href={`#${t.sections[index].id}`}
                className="group flex items-start gap-3 p-3 rounded-lg hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-elevated-dark)] transition-all duration-200"
              >
                <span className="flex-shrink-0 w-7 h-7 rounded-md bg-[var(--color-text-primary)] text-white flex items-center justify-center text-sm font-bold group-hover:scale-110 transition-transform duration-200">
                  {index + 1}
                </span>
                <span className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary)] group-hover:text-[var(--color-text-primary)] dark:group-hover:text-[var(--color-accent)] font-medium transition-colors duration-200">
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
              className="group bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] rounded-2xl shadow-md hover:shadow-xl transition-all duration-500 overflow-hidden animate-fade-in"
              style={{ animationDelay: `${(index + 2) * 100}ms` }}
            >
              {/* Section Header with Gradient Bar */}
              <div className="h-2 bg-gradient-to-r from-[var(--color-text-primary)] via-[var(--color-accent)] to-[var(--color-breaking)] opacity-75 group-hover:opacity-100 transition-opacity duration-300"></div>

              <div className="p-8">
                <div className="flex items-start gap-4 mb-6">
                  <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-[var(--color-surface-dark)] to-[var(--color-accent)] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                    <span className="text-white font-bold text-xl">{index + 1}</span>
                  </div>
                  <h2 className="text-2xl md:text-3xl font-bold text-[var(--color-text-primary)] dark:text-white flex-1 pt-2">
                    {section.title}
                  </h2>
                </div>

                <div className="space-y-4 ml-16">
                  {section.content.map((paragraph, pIndex) => (
                    <p
                      key={pIndex}
                      className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary)] leading-relaxed"
                    >
                      {paragraph}
                    </p>
                  ))}
                </div>
              </div>
            </section>
          ))}
        </div>

        {/* Bottom Contact CTA */}
        <div className="mt-12 bg-gradient-to-br from-[var(--color-surface-dark)] via-[var(--color-surface-elevated-dark)] to-[var(--color-accent)] rounded-2xl shadow-2xl p-10 text-center relative overflow-hidden animate-fade-in-up">
          <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-10"></div>
          <div className="relative z-10">
            <h3 className="text-3xl font-bold text-white mb-4">
              {locale === 'ro' && 'Întrebări Legale?'}
              {locale === 'en' && 'Legal Questions?'}
              {locale === 'ru' && 'Юридические Вопросы?'}
            </h3>
            <p className="text-white/90 mb-8 text-lg max-w-2xl mx-auto">
              {locale === 'ro' && 'Contactați-ne pentru orice clarificări legate de termenii și condițiile noastre.'}
              {locale === 'en' && 'Contact us for any clarifications regarding our terms and conditions.'}
              {locale === 'ru' && 'Свяжитесь с нами для любых уточнений, касающихся наших условий.'}
            </p>
            <a
              href="mailto:legal@deschide.md"
              className="inline-flex items-center gap-3 bg-white text-[var(--color-text-primary)] font-semibold px-8 py-4 rounded-xl hover:bg-[var(--color-surface-sunken)] transition-all duration-200 shadow-lg hover:shadow-2xl hover:scale-105"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              legal@deschide.md
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
export async function generateMetadata({ params }: TermsPageProps): Promise<Metadata> {
  const { locale } = await params;

  const titles = {
    ro: 'Termeni și Condiții - Deschide News',
    en: 'Terms and Conditions - Deschide News',
    ru: 'Условия Использования - Deschide News',
  };

  const descriptions = {
    ro: 'Citiți termenii și condițiile noastre pentru a înțelege regulile și obligațiile legate de utilizarea Deschide News.',
    en: 'Read our terms and conditions to understand the rules and obligations related to using Deschide News.',
    ru: 'Прочитайте наши условия использования, чтобы понять правила и обязательства, связанные с использованием Deschide News.',
  };

  const title = titles[locale as keyof typeof titles] || titles.ro;
  const description = descriptions[locale as keyof typeof descriptions] || descriptions.ro;

  return {
    title,
    description,
    alternates: {
      canonical: `/${locale}/terms`,
      languages: {
        ro: '/terms',
        en: '/en/terms',
        ru: '/ru/terms',
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
