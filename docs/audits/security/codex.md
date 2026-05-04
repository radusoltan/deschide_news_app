## 1. SUMAR EXECUTIV

  Am inspectat direct configurațiile și codul din backend și frontend, plus comportamentul la runtime
  pe instanța locală analizată la data de 29 martie 2026. Am citit explicit fișierele de securitate,
  auth, CORS, JWT, rate limiting, Mercure, Sentry, fluxurile email/AI și endpoint-urile publice.

  Nota generală: 3/10
  Verdict: nu este gata pentru producție.

  Problemele care blochează lansarea sunt clare:

  - managementul secretelor este sub nivelul minim acceptabil pentru o redacție;
  - controlul de acces pe coada de press release este rupt;
  - instanța analizată rulează backend-ul în dev și expune diagnostică internă public;
  - există SSRF server-side în frontend;
  - există endpoint-uri publice de test pentru Mercure și porturi de management expuse;
  - guvernanța agenților AI este prea permisivă pentru un mediu cu conținut editorial și surse
    sensibile.

  Ce este bine:

  - refresh token-urile sunt single_use în apps/backend/config/packages/
    gesdinet_jwt_refresh_token.yaml:1;
  - backend-ul are send_default_pii: false în apps/backend/config/packages/sentry.yaml:1;
  - PostgreSQL și Redis ascultă local, nu public, pe hostul analizat;
  - Elasticsearch, pe hostul analizat, nu este “fără autentificare”: la 29 martie 2026
    https://localhost:9200 a răspuns 401 Unauthorized.

  Limitări reale ale auditului:

  - docker-compose*.yml este lipsă — risc de vizibilitate operațională;
  - supervisor/*.conf este lipsă — risc de vizibilitate asupra workerilor;
  - apps/frontend/middleware.ts este lipsă; există doar apps/frontend/middleware.ts.deprecated, iar
    logica activă este în apps/frontend/proxy.ts;
  - composer audit și pnpm audit nu au putut fi validate complet în sandbox din cauza erorilor DNS/
    EAI_AGAIN, deci riscul de supply chain rămâne deschis.

  ## 2. VULNERABILITĂȚI CRITICE

  | ID | Severitate | OWASP | Localizare | Descriere | Exploit | Impact | Fix |
  |---|---|---|---|---|---|---|---|
  | VULN-001 | Critical | A02 / A05 | .env:1, apps/backend/.env.local:2, apps/backend/
  NEW_CREDENTIALS.txt:11, .claude/settings.local.json:42, .claude/settings.local.json:340 | Secrete
  reale sunt stocate în clar în workspace: Zoho access/refresh tokens, DB password, JWT passphrase,
  Mercure secret, Elastic password, DSN Sentry, plus credențiale de producție în fișierul local
  Claude. În plus, git ls-files confirmă fișiere versionate cu credențiale: apps/backend/.env.backup.*
  și apps/backend/NEW_CREDENTIALS.txt. Fișierul .claude/settings.local.json este world-readable (644).
  | Orice compromis local, CI, agent malițios sau colaborator cu acces la host poate citi inboxul
  Zoho, baza de date, poate abuza Mercure sau poate pătrunde în instanțe de producție. Dacă
  artefactele versionate au fost împinse vreodată, compromisul iese din host și intră în istoricul
  git. | Compromitere totală: exfiltrare surse, citire emailuri redacționale, acces DB, abuz JWT/
  Mercure, compromitere Sentry și pivot către infrastructură. | Rotație imediată a tuturor secretelor,
  eliminarea artefactelor versionate, mutare în secret manager, permisiuni 600/700, rescriere istoric
  git dacă aceste fișiere au fost publicate. Vezi snippet C1. |
  | VULN-002 | Critical | A01 | apps/backend/src/Entity/PressRelease.php:30, apps/backend/config/
  packages/security.yaml:85, apps/backend/config/packages/security.yaml:93, apps/backend/src/Entity/
  User.php:175 | PressRelease nu are reguli security per operațiune. Pentru că ruta nu este inclusă în
  allowlist-ul admin/editor, cade pe regula generală ^/api -> ROLE_USER. Cum fiecare utilizator
  primește implicit ROLE_USER, orice utilizator autentificat poate lista, modifica, șterge și aproba
  press release-uri. | Un cont banal poate face GET /api/press_releases, PATCH /api/press_releases/
  {id} și POST /api/press_releases/{id}/approve. Asta permite sabotaj editorial, manipularea stării
  cozii și creare neautorizată de articole din conținutul inboxului de presă. | Ruptură severă de
  integritate editorială și confidențialitate. Un cont compromis de redactor junior sau un cont de
  test devine suficient pentru a afecta fluxul de publicare. | Introdu securitate explicită per
  operațiune și voter pentru aprobare/ștergere. Scoate câmpurile sensibile din press:write. Vezi
  snippet C2. |
  | VULN-003 | Critical | A02 / A07 | apps/frontend/lib/auth/session.ts:16, apps/frontend/lib/auth/
  session.ts:28, apps/frontend/lib/auth/session-edge.ts:9, apps/frontend/app/api/auth/token/
  route.ts:4, apps/frontend/.env.local:1 | Frontend-ul folosește un fallback public cunoscut pentru
  SESSION_SECRET. Mai grav, comentariul spune “encrypted cookies”, dar codul folosește SignJWT cu
  HS256, deci cookie-ul este semnat, nu criptat. Payload-ul include accessToken și refreshToken. Dacă
  SESSION_SECRET lipsește în deployment, toate sesiunile sunt semnate cu o cheie cunoscută. | Dacă un
  atacator obține o sesiune sau un refresh token din loguri, backup-uri, browser compromis sau host
  compromis, poate fabrica cookie-uri valide și le poate prelungi. Endpoint-ul /api/auth/token
  transformă sesiunea într-un bearer token reutilizabil. | Hijack persistent de sesiune pentru admin/
  editor, păstrarea accesului după incidente parțiale și creșterea impactului oricărei scurgeri de
  cookie. | Elimină fallback-ul, oprește aplicația dacă SESSION_SECRET lipsește, mută token-urile în
  storage server-side sau JWE, și evită endpoint-ul care returnează tokenul brut. Vezi snippet C3. |

  Remedieri concrete pentru vulnerabilitățile critice

  C1 — scoaterea secretelor din repo și din artefactele AI:

  chmod 700 /var/www/deschide_news_app/.claude
  chmod 600 /var/www/deschide_news_app/.claude/settings.local.json

  git rm --cached \
    apps/backend/.env.backup.20251129_131417 \
    apps/backend/.env.backup.20251220_133824 \
    apps/backend/.env.backup.20251220_134000 \
    apps/backend/NEW_CREDENTIALS.txt

  # /etc/deschide/mercure.env
  MERCURE_PUBLISHER_JWT_KEY=...rotated...
  MERCURE_SUBSCRIBER_JWT_KEY=...rotated...

  # systemd
  EnvironmentFile=/etc/deschide/mercure.env

  C2 — închiderea RBAC pe PressRelease:

  #[ApiResource(
      operations: [
          new GetCollection(
              security: "is_granted('ROLE_EDITOR') or is_granted('ROLE_ADMIN')",
              normalizationContext: ['groups' => ['press:read']],
          ),
          new Get(
              security: "is_granted('ROLE_EDITOR') or is_granted('ROLE_ADMIN')",
              normalizationContext: ['groups' => ['press:read', 'press:detail']],
          ),
          new Patch(
              security: "is_granted('ROLE_EDITOR') or is_granted('ROLE_ADMIN')",
              denormalizationContext: ['groups' => ['press:write']],
              normalizationContext: ['groups' => ['press:read']],
          ),
          new Post(
              uriTemplate: '/press_releases/{id}/approve',
              security: "is_granted('ROLE_EDITOR') or is_granted('ROLE_ADMIN')",
              processor: PressReleaseApproveProcessor::class,
          ),
          new Delete(
              security: "is_granted('ROLE_EDITOR') or is_granted('ROLE_ADMIN')",
          ),
      ],
  )]

  C3 — secret obligatoriu și oprire dacă lipsește:

  const sessionSecret = process.env.SESSION_SECRET;

  if (!sessionSecret || sessionSecret.length < 32) {
    throw new Error('SESSION_SECRET must be set and at least 32 characters long');
  }

  const key = new TextEncoder().encode(sessionSecret);

  type SessionPayload = {
    sid: string;
    user: { username: string; roles: string[] };
    expiresAt: Date;
  };

  // accessToken / refreshToken rămân în Redis, nu în cookie

  ## 3. VULNERABILITĂȚI HIGH

  | ID | Severitate | OWASP | Localizare | Descriere | Exploit | Impact | Fix |
  |---|---|---|---|---|---|---|---|
  | VULN-004 | High | A05 | apps/backend/.env.local:2, apps/backend/config/packages/security.yaml:50,
  apps/backend/config/packages/security.yaml:53, apps/backend/src/Controller/HealthController.php:24,
  apps/backend/src/Controller/MetricsController.php:22, apps/backend/src/Service/
  HealthCheckService.php:78, apps/backend/src/Service/HealthCheckService.php:145 | Instanța analizată
  rulează în APP_ENV=dev, iar /api/health* și /metrics sunt publice. La runtime, /api/health a răspuns
  cu starea internă a DB/Redis/ES și headere X-Debug-Token, X-Debug-Token-Link. Health service
  întoarce și mesajele brute de eroare. | Atacatorul poate face recon intern fără autentificare:
  dependențe, timeout-uri, servicii căzute, topologie, plus token-uri de profiler. | Scade dramatic
  costul unui atac țintit, accelerează pivotarea și scurgerile operaționale în timpul unui incident. |
  Rulează public doar cu APP_ENV=prod, APP_DEBUG=0; restricționează /metrics și /api/health*; nu
  returna erori brute. Vezi snippet H1. |
  | VULN-005 | High | A05 / A01 | apps/backend/config/packages/security.yaml:69, apps/backend/src/
  Controller/LiveText/TestMercureController.php:29, apps/backend/src/Controller/LiveText/
  TestMercureController.php:55 | Endpoint-urile de test Mercure sunt publice. Am confirmat la runtime
  că POST /api/live-texts/test-mercure/1?count=999 răspunde 200 fără autentificare și publică
  eveniment. /api/live-texts/mercure-info expune hub URL și topic pattern. | Oricine poate spam-a
  topicurile de live text și poate crea trafic fals sau zgomot operațional. | Degradare live coverage,
  zgomot în dashboard-uri, posibilă afectare a credibilității în timpul evenimentelor critice. |
  Elimină aceste rute în producție sau protejează-le strict cu ROLE_ADMIN și flag de mediu. Vezi
  snippet H2. |
  | VULN-006 | High | A10 | apps/frontend/app/api/images/upload-from-url/route.ts:24 | POST /api/imag
  es/upload-from-url face fetch() server-side pe orice URL valid sintactic înainte să verifice conten
  t-type. Nu există allowlist, blocare RFC1918, blocare metadata endpoints, blocare redirect, validare
  DNS/IP. | Un utilizator autentificat poate forța serverul să acceseze http://127.0.0.1:15672,
  http://localhost:3000, http://169.254.169.254/... sau alte servicii interne. Chiar dacă răspunsul nu
  e imagine, cererea SSRF s-a produs deja. | Scanare internă, atingere de servicii de management, piv
  ot spre metadate cloud sau servicii interne. | Permite doar https, rezolvă DNS și blochează loopbac
  k/private/link-local, interzice redirecturile și ideal folosește allowlist de domenii. Vezi snippet
  H3. |
  | VULN-007 | High | A05 | Observație runtime 29.03.2026: 0.0.0.0:15672, *:5672, *:3000, *:9200,
  *:3005, 0.0.0.0:8082; plus scripts/start-all-services.sh:167, scripts/start-all-services.sh:192 |
  Suprafața de atac este prea largă. RabbitMQ Management UI este public accesibil, AMQP este expus,
  Mercure este public, Elasticsearch ascultă public, CDN și Next ascultă public. Scriptul operațional
  menționează guest/guest pentru RabbitMQ și anonymous pentru Mercure. | Atacatorii pot enumera
  servicii, lovi UI-ul de management, abuza AMQP/Mercure și folosi expunerea pentru brute force sau
  DoS. Nu am validat login-ul RabbitMQ pentru a evita efecte secundare, dar expunerea e deja
  inacceptabilă. | Suprafață inutilă pentru DDoS, abuz operațional și recon. În context mass-media,
  asta invită incidente “ieftine” în zile cu miză politică. | Leagă serviciile interne doar pe
  loopback/VPN, pune firewall strict și scoate UI-urile din internetul public. Vezi snippet H4. |
  | VULN-008 | High | A08 | apps/backend/src/Command/FetchPressEmailsCommand.php:246, apps/backend/
  src/MessageHandler/TranslateArticleHandler.php:100, apps/backend/src/Service/
  TranslationResultProcessor.php:97, apps/backend/src/Service/TranslationResultProcessor.php:170 |
  Pipeline-ul AI/email are două probleme concrete. isWhitelisted() folosește str_contains, deci un
  expeditor de forma press@gov.md.evil.tld poate trece. În paralel, conținutul articolului ajunge brut
  în promptul Gemini, iar ieșirea modelului se salvează în DB fără sanitizare HTML. Excepțiile includ
  primele 500 caractere din output. | Un atacator poate trimite emailuri craftate care trec allowlist-
  ul superficial și pot polua coada, iar prompt injection-ul poate altera traducerile sau împinge
  conținut manipulat spre editori. În codul inspectat, emailurile ajung în review, nu se publică
  direct, dar lanțul de integritate este slab. | Compromiterea integrității editoriale, social
  engineering intern și scurgeri în loguri. | Validare strictă a domeniului expeditor + SPF/DKIM/
  DMARC, carantină pentru input AI, sanitizare HTML la output și eliminarea fragmentelor brute din
  erori/loguri. Vezi snippet H5. |
  | VULN-009 | High | A05 / A07 | apps/backend/src/EventSubscriber/RateLimiterSubscriber.php:34, apps/
  backend/src/EventSubscriber/RateLimiterSubscriber.php:46, apps/backend/config/packages/prod/
  framework.yaml:1, apps/frontend/app/api/revalidate/route.ts:89 | Rate limiting-ul backend este
  dezactivat complet în dev și sare peste 127.0.0.1. În spate de Nginx/Cloudflare, fără
  trusted_proxies, aplicația poate interpreta greșit IP-ul clientului. Webhook-ul de revalidare își
  bazează rata pe x-forwarded-for/x-real-ip, adică pe headere spoofabile. | Brute force, flood și
  bypass de throttling prin proxy sau header injection. | Slăbește controlul abuzului exact în zonele
  care contează: login, scrieri, revalidări. | Configurează trusted_proxies/trusted_headers, scoate
  bypass-ul de localhost din codul public și folosește limitare shared (Redis) pentru webhook și auth.
  Vezi snippet H6. |

  Remedieri concrete pentru vulnerabilitățile High

  H1 — închiderea diagnosticului public:

  APP_ENV=prod
  APP_DEBUG=0

  # security.yaml
  access_control:
    - { path: ^/api/health/(live|ready)$, roles: PUBLIC_ACCESS, ips: [127.0.0.1, ::1] }
    - { path: ^/api/health, roles: ROLE_ADMIN }
    - { path: ^/metrics, roles: ROLE_ADMIN }

  return [
      'status' => 'unhealthy',
      'duration_ms' => round($duration, 2),
      'message' => 'Dependency check failed',
      // fără $e->getMessage() în răspunsul public
  ];

  H2 — eliminarea endpoint-urilor de test din producție:

  #[Route('/test-mercure/{liveTextId}', methods: ['POST'])]
  #[IsGranted('ROLE_ADMIN')]
  public function testMercure(...) { ... }

  if ($_ENV['APP_ENV'] === 'prod') {
      throw $this->createNotFoundException();
  }

  H3 — blocare SSRF:

  import dns from 'node:dns/promises';
  import ipaddr from 'ipaddr.js';

  function isPrivate(address: string): boolean {
    const addr = ipaddr.parse(address);
    return addr.range() !== 'unicast';
  }

  if (imageUrl.protocol !== 'https:') {
    return NextResponse.json({ error: 'Only HTTPS URLs are allowed' }, { status: 400 });
  }

  const records = await dns.lookup(imageUrl.hostname, { all: true });
  if (records.some(r => isPrivate(r.address))) {
    return NextResponse.json({ error: 'Private or local addresses are forbidden' }, { status: 400 });
  }

  const imageResponse = await fetch(imageUrl.toString(), { redirect: 'error' });

  H4 — închiderea porturilor interne:

  # RabbitMQ
  listeners.tcp.default = 127.0.0.1:5672
  management.tcp.ip = 127.0.0.1
  management.tcp.port = 15672
  loopback_users.guest = true

  # Elasticsearch
  network.host: 127.0.0.1
  xpack.security.enabled: true

  mercure {
    publisher_jwt {$MERCURE_PUBLISHER_JWT_KEY}
    subscriber_jwt {$MERCURE_SUBSCRIBER_JWT_KEY}
    # scoate "anonymous" în producție
  }

  H5 — allowlist strict și sanitizare AI:

  private function isWhitelisted(string $fromAddress): bool
  {
      $domain = strtolower(substr(strrchr($fromAddress, '@') ?: '', 1));

      return in_array($domain, self::WHITELIST_DOMAINS, true);
  }

  $content = $tData['content'] ?? '';
  $content = $this->htmlSanitizer->sanitize($content);
  $translationRepo->translate($article, 'content', $locale, $content);

  H6 — trusted proxies și limitare reală:

  # framework.yaml
  framework:
    trusted_proxies: '%env(TRUSTED_PROXIES)%'
    trusted_headers: [ 'x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port' ]

  // RateLimiterSubscriber.php
  // elimină complet acest bypass din traficul HTTP public
  // if ($clientIp === '127.0.0.1' || $clientIp === '::1') { return; }

  ## 4. VULNERABILITĂȚI MEDIUM & LOW

  | ID | Severitate | OWASP | Localizare | Descriere | Exploit | Impact | Fix |
  |---|---|---|---|---|---|---|---|
  | VULN-010 | Medium | A02 | apps/backend/src/EventListener/MercureJwtCookieListener.php:38 | Cookie-
  ul mercureAuthorization este setat cu withSecure(false). În producție HTTPS, asta nu trebuie să
  existe deloc în codul implicit. | Pe un deployment greșit sau hibrid HTTP, cookie-ul poate fi expus
  inutil. | Crește impactul oricărui sniffing sau downgrade operațional. | Setează Secure=true și
  SameSite=Strict condiționat de mediu sigur. Vezi M1. |
  | VULN-011 | Medium | A03 / A05 | apps/frontend/next.config.mjs:83, apps/frontend/
  next.config.mjs:148 | CSP-ul frontend permite unsafe-inline și unsafe-eval, iar dangerouslyAllowSVG
  este activ. Asta slăbește apărarea XSS exact pe o platformă care procesează conținut editorial
  bogat. | Dacă apare un sink XSS în altă parte, CSP-ul actual îl amortizează slab. | Crește
  severitatea oricărei injecții de conținut în UI. | Elimină unsafe-eval, minimizează unsafe-inline,
  dezactivează SVG dacă nu e absolut necesar. Vezi M1. |
  | VULN-012 | Medium | A03 | apps/frontend/app/[locale]/admin/press-queue/page.tsx:262 | Admin queue
  redă pr.content prin dangerouslySetInnerHTML fără a apela sanitizer-ul local. În fluxul actual,
  parserul email escape-uiește HTML-ul, deci exploatabilitatea curentă e parțial redusă, dar sink-ul
  rămâne periculos. | O viitoare sursă de date nesanitarizată sau o migrare de conținut poate
  transforma instant acest sink într-un stored XSS. | Suprafață latentă de takeover pentru conturile
  editoriale. | Folosește createSafeHtml() înainte de randare. Vezi M2. |
  | VULN-013 | Medium | A05 | apps/backend/config/packages/nelmio_cors.yaml:21 | /api/embed permite
  allow_origin: ['*']. Pentru embed public e tentant, dar deschide consumul cross-origin total fără
  allowlist de parteneri. | Orice site poate integra și abuza endpoint-ul embed. | Crește suprafața
  pentru scraping, trafic artificial și integrare necontrolată. | Allowlist strict pentru domenii
  partenere sau token de embed semnat. Vezi M1. |
  | VULN-014 | Medium | A05 / A07 | apps/frontend/.env.local:26, apps/frontend/app/api/revalidate/
  route.ts:17, apps/frontend/app/api/revalidate/route.ts:200 | Secretul de revalidare este static și
  banal în workspace, iar GET /api/revalidate confirmă dacă mecanismul este configurat. | Dacă aceeași
  practică ajunge în producție, un secret slab sau scurs permite invalidări de cache și zgomot
  operațional. | Degradare de performanță, churn de cache și semnalizare utilă pentru atacatori. |
  Secret lung, generat random, fără endpoint GET informativ public. Vezi M2. |
  | VULN-015 | Medium | A01 / A05 | apps/frontend/app/actions/auth.ts:45, apps/frontend/lib/auth/
  csrf.ts:153, apps/frontend/lib/auth/rate-limit.ts:245 | Frontend-ul are module de CSRF și rate
  limiting, dar acțiunea de login nu le folosește. Practic, măsurile există pe hârtie, nu pe traseul
  critic. | Crește spațiul pentru abuz automatizat asupra autentificării frontend. | Slăbire a
  controlului pe login și alte server actions sensibile. | Aplică wrapper-ele efectiv pe acțiunile
  sensibile. Vezi M2. |
  | VULN-016 | Low | A09 / A05 | apps/frontend/sentry.client.config.ts:3, apps/frontend/
  sentry.server.config.ts:3 | Frontend-ul pornește Sentry Replay cu replaysOnErrorSampleRate: 1.0 și
  replaysSessionSampleRate: 0.1. Pentru un newsroom, replay-ul poate surprinde conținut editorial
  intern, workflow de moderare și date sensibile afișate în UI. | Într-un incident sau bug de UI,
  sesiuni interne pot fi trimise către un terț. | Risc de scurgere indirectă de conținut și
  comportament editorial. | Oprește replay-ul pe admin sau global până există redaction/masking
  riguros. Vezi M3. |

  Remedieri concrete pentru vulnerabilitățile Medium & Low

  M1 — cookie Mercure, CSP și CORS:

  Cookie::create('mercureAuthorization')
      ->withValue($mercureJwt)
      ->withPath('/.well-known/mercure')
      ->withHttpOnly(true)
      ->withSecure(true)
      ->withSameSite('strict');

  images: {
    dangerouslyAllowSVG: false,
  },
  headers: [
    {
      key: 'Content-Security-Policy',
      value: [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "object-src 'none'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
      ].join('; '),
    },
  ]

  '^/api/embed':
    allow_origin: ['https://partner1.example', 'https://partner2.example']

  M2 — sanitizare, webhook și protecții pe server actions:

  import { createSafeHtml } from '@/lib/sanitize';

  <div dangerouslySetInnerHTML={createSafeHtml(pr.content)} />

  export async function GET() {
    return NextResponse.json({ error: 'Not found' }, { status: 404 });
  }

  export const login = withRateLimit(
    withCsrfProtection(async (state: LoginFormState, formData: FormData) => {
      // login logic
      return { message: 'Login successful' };
    }),
    RATE_LIMITS.AUTH
  );

  M3 — Sentry replay minim:

  Sentry.init({
    dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,
    environment: process.env.NODE_ENV,
    tracesSampleRate: 0.1,
    replaysOnErrorSampleRate: 0.0,
    replaysSessionSampleRate: 0.0,
  });

  ## 5. PLAN DE ACȚIUNE

  Imediat, înainte de orice lansare publică

  - Rotește toate secretele din .env, apps/backend/.env.local, apps/backend/
    NEW_CREDENTIALS.txt, .claude/settings.local.json și orice serviciu dependent: Zoho, PostgreSQL,
    JWT, Mercure, Elasticsearch, Sentry, revalidate secret.
  - Elimină artefactele sensibile din git și din workspace; dacă au fost împinse, rescrie istoricul și
    tratează incidentul ca scurgere confirmată.
  - Închide imediat RBAC-ul pe PressRelease, dezactivează public /api/live-texts/test-mercure*, /api/
    live-texts/mercure-info, /metrics și /api/health*.
  - Forțează APP_ENV=prod și APP_DEBUG=0 pe orice instanță accesibilă extern.
  - Impune SESSION_SECRET obligatoriu și scoate token-urile backend din cookie-ul frontend.
  - Închide porturile publice de management și servicii interne: 15672, 5672, 3000, 9200, 8082, 3005
    dacă nu sunt terminate corect în reverse proxy/WAF.
  - Repară SSRF-ul din upload-from-url.

  Termen scurt, luna 1

  - Introdu allowlist strict de expeditori pentru email ingest, plus verificare SPF/DKIM/DMARC și
    carantină pentru inputuri care intră în pipeline AI.
  - Adaugă sanitizare HTML la outputul Gemini și review editorial obligatoriu pentru traducerile
    generate.
  - Configurează trusted_proxies, trusted_hosts, rate limiting shared cu Redis și teste reale de
    brute-force.
  - Oprește Sentry Replay pe admin și redu logging-ul care conține output brut de la modele.
  - Pune agenții AI pe least privilege: read-only implicit, write doar per task, fără secrete în
    fișiere locale din repo.

  Termen mediu, Q2-Q3

  - Introdu secret manager centralizat, rotație automată și break-glass access.
  - Implementare MFA phishing-resistant pentru toți editorii și adminii, preferabil WebAuthn/FIDO2.
  - Segmentează infrastructura pe roluri: editorial, ingest, AI workers, search, messaging.
  - Construiește playbook de incident response pentru defacement, DDoS politic și compromiterea
    fluxului editorial.
  - SBOM, audit de dependențe în CI, semnare build-uri și controale de supply chain.

  ## 6. CHECKLIST PRE-LANSARE

  | Control | Status |
  |---|---|
  | Toate secretele au fost rotite și scoase din repo/workspace | NU |
  | Backend public rulează în APP_ENV=prod și APP_DEBUG=0 | NU |
  | /metrics și /api/health* sunt restricționate | NU |
  | Endpoint-urile Mercure de test sunt dezactivate în producție | NU |
  | PressRelease este inaccesibil pentru ROLE_USER | NU |
  | SESSION_SECRET este obligatoriu și unic pe mediu | NU |
  | Cookie-urile sensibile sunt Secure și minimizate | NU |
  | SSRF pe upload-from-url este blocat | NU |
  | Porturile 15672/5672/3000/9200/8082 sunt nepublice | NU |
  | Allowlist email este validat strict pe domeniu + SPF/DKIM/DMARC | NU |
  | Trusted proxies/hosts sunt configurate pentru Cloudflare/Nginx | NU |
  | Sentry Replay este dezactivat pentru suprafețele sensibile | NU |
  | Elasticsearch are autentificare activă pe hostul analizat | DA |
  | PostgreSQL și Redis sunt locale, nu publice, pe hostul analizat | DA |
  | Audit de dependențe composer/pnpm este curat | NU, neconfirmat din cauza limitărilor de rețea |

  ## 7. RECOMANDĂRI SPECIFICE MASS-MEDIA

  - Pune site-ul public în spatele Cloudflare cu Full (strict), WAF activ, rate limiting pe zonele
    publice și reguli anti-bot dedicate pentru zile cu miză politică. Pentru Moldova, amenințarea DDoS
    politic și valurile de scraping/agregare ostilă nu sunt ipotetice.
  - Rupe complet fluxul “email -> sistem” de fluxul “publicare”. Inputul din email trebuie să intre
    într-o zonă de carantină, să fie marcat ca neîncredere și să nu poată ajunge la publicare fără doi
    pași umani separați.
  - Pentru surse jurnalistice, nu lăsa Sentry Replay, loguri brute sau agenți AI să vadă tot.
    Segmentează datele, maschează PII, limitează rolurile și pune politică explicită de minimizare a
    datelor.
  - Pentru redacție, impune MFA cu chei hardware, nu doar parole și OTP. Spear phishing-ul pe editori
    este unul dintre cele mai probabile scenarii de compromitere.
  - Pentru defacement editorial, implementează rollback rapid al homepage-ului, snapshot-uri de
    conținut, semnare a publish jobs și alertare pe modificări neobișnuite ale articolelor,
    categoriilor și short links.
  - Pentru integritatea conținutului generat AI, păstrează audit trail: input original, output model,
    operator uman, diferențe aprobate și motivul publicării.

  ## 8. OPINIE PROFESIONALĂ SINCERĂ

  Proiectul are o bază tehnică bună: stack modern, refresh token rotation corectă, unele headere de
  securitate, Redis/PostgreSQL locale și Elasticsearch autentificat pe hostul inspectat. Nu am găsit
  în traseele inspectate dovezi concrete de SQL injection brută sau de scurgere PII implicită prin
  backend Sentry.

  Dar asta nu schimbă verdictul. Problemele reale nu sunt “micro-buguri”; sunt erori de disciplină
  operațională și control de acces. Pentru o aplicație media, asta este mai grav decât un bug clasic.
  Un atacator nu are nevoie de exploit sofisticat când găsește secrete în clar, endpoint-uri de test
  publice, o coadă editorială expusă oricărui ROLE_USER și servicii de management la vedere.

  Comparativ cu un baseline sănătos din industrie pentru newsroom-uri serioase, starea actuală este
  sub minimul acceptabil pentru go-live. Dacă aceste probleme sunt remediate corect, proiectul poate
  deveni solid. În starea actuală, însă, lansarea ar însemna să publicați cu ușa tehnică întredeschisă
  exact într-un domeniu unde adversarii sunt motivați, persistenți și foarte puțin subtili.