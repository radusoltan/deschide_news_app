Da, e posibil și e chiar o abordare sănătoasă 👍 Hai să o punem în ordine astfel încât:

* să poți lucra liniștit în **dev** (localhost, cu debug, hot reload),
* să ai o variantă de **prod-like** care rulează tot pe WSL,
* și varianta de prod să fie expusă în internet prin **ngrok**, dar fără să bagi Docker în ecuație.

Îți las mai jos un ghid pas cu pas.

---

## 1. Ce vrem să obținem

1. **Pe WSL ai două “profile”**:

    * `dev` – totul pe localhost, cu debug.
    * `prod` – totul rulează ca și cum ar fi în producție (APP_ENV=prod, fără debug), servit de Nginx.
2. **Backend Symfony** rulează de 2 ori (logic vorbind):

    * în dev: `symfony server:start` sau `php -S` sau `php-fpm + nginx dev`
    * în prod: `php-fpm` + `nginx` pe un port fix (ex. 8000 sau 8001)
3. **Frontend Next.js 16** rulează de 2 ori:

    * în dev: `next dev` pe port 3000
    * în prod: `next build && next start` pe port 3001, servit de Nginx
4. **ngrok** expune DOAR instanța de prod (ex. Nginx de pe WSL → ngrok → domeniu public).
5. **Fără Docker.**

---

## 2. Cum separăm config-ul de dev și prod

### 2.1. Symfony

În proiectul Symfony:

* `.env` – rămâne baza.
* `.env.local` – pentru **dev**.
* `.env.prod.local` – pentru **prod**.

Exemplu:

**.env.local** (dev):

```env
APP_ENV=dev
APP_DEBUG=1
DATABASE_URL="postgresql://symfony:symfony@127.0.0.1:5432/symfony_dev?serverVersion=16"
REDIS_URL=redis://127.0.0.1:6379
ELASTICSEARCH_HOST=127.0.0.1:9200
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

**.env.prod.local** (prod-like pe WSL):

```env
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL="postgresql://symfony:symfony@127.0.0.1:5432/symfony_prod?serverVersion=16"
REDIS_URL=redis://127.0.0.1:6379
ELASTICSEARCH_HOST=127.0.0.1:9200
CORS_ALLOW_ORIGIN='https://numele-tau.ngrok.app'
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
TRUSTED_HEADERS="x-forwarded-for,x-forwarded-proto,x-forwarded-port,x-forwarded-host"
```

Observații:

* poți avea chiar **două DB-uri** diferite în Postgres (symfony_dev și symfony_prod) ca să nu strici testele când dezvolți.
* la CORS pui domeniul ngrok pe care îl vei primi.

Rulare prod (pe WSL):

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear --env=prod
# apoi lași php-fpm să servească aplicația
```

---

### 2.2. Next.js 16

Next.js are deja 2 faze:

* dev → `next dev`
* prod → `next build && next start`

Tu doar trebuie să ai **2 seturi de variabile**:

* `.env.local` – pentru dev:

  ```env
  NEXT_PUBLIC_API_URL=http://localhost:8000 # API-ul Symfony în dev
  ```

* `.env.production` – pentru prod:

  ```env
  NEXT_PUBLIC_API_URL=https://numele-tau.ngrok.app/api
  NEXT_PUBLIC_APP_URL=https://numele-tau.ngrok.app
  ```

Și în `next.config.js` să citești din env, nu să hardcodezi.

Rulare prod (pe WSL):

```bash
npm run build
npm run start -- -p 3001
```

sau cu pnpm/yarn, cum folosești.

---

## 3. Nginx pe WSL – 2 servere virtuale

Pe WSL tu vrei ca Nginx să stea în față și să spună:

* dacă vine request pe 127.0.0.1:80 (sau 0.0.0.0:80) → du-l la Next prod (3001)
* dacă vine pe `/api` → du-l la Symfony (php-fpm:9000)

Un exemplu minimal:

```nginx
server {
    listen 80;
    server_name _;

    # frontend Next.js (prod) care rulează pe 3001
    location / {
        proxy_pass http://127.0.0.1:3001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # backend Symfony (prod) – dacă îl servești prin php-fpm
    location /api {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass  unix:/run/php/php8.3-fpm.sock; # adaptează versiunea
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Altă variantă e să pui Symfony pe alt vhost (ex. 8000) și să-l proxyezi.

Important: **ngrok va tunela către portul 80 al WSL**, deci ce răspunde Nginx acolo o să vadă lumea.

---

## 4. Ngrok peste WSL

Da, e perfect valid să expui din WSL către internet cu ngrok.

1. Instalezi ngrok în Windows sau în WSL (eu aș pune în WSL ca să fie mai “local”).
2. Faci auth:

   ```bash
   ngrok config add-authtoken <tokenul-tau>
   ```
3. Pornești un tunel HTTP spre portul pe care răspunde Nginx (80):

   ```bash
   ngrok http 80
   ```

Ngrok îți va da ceva de tipul:

* [https://1234abcd.ngrok.app](https://1234abcd.ngrok.app)

Ăsta e domeniul pe care îl pui în:

* CORS (Symfony)
* NEXT_PUBLIC_APP_URL
* eventual în setările de cookie dacă ai nevoie de secure.

### Domeniu rezervat ngrok

Dacă vrei să fie stabil (să nu se schimbe la fiecare pornire), în planurile plătite poți rezerva domeniu și pornești cu config yaml:

`~/.config/ngrok/ngrok.yml`:

```yaml
version: "2"
authtoken: <token>
tunnels:
  deschide-prod:
    proto: http
    addr: 80
    hostname: numele-tau.ngrok.app
```

Și apoi:

```bash
ngrok start deschide-prod
```

---

## 5. Ce trebuie să ai grijă cu Symfony + ngrok

1. **Trusted Proxies** – pentru că ngrok stă în fața ta, requestul vine cu `X-Forwarded-Proto: https`. Dacă nu îi spui lui Symfony că are un proxy în față, o să creadă că e HTTP și o să-ți facă redirecturi aiurea.
2. **CORS / API Platform** – în `api_platform.yaml` setezi allowed origins cu domeniul ngrok.
3. **HTTPS** – ngrok îți dă HTTPS, tu pe WSL rămâi HTTP, e ok.

Exemplu API Platform:

```yaml
# config/packages/api_platform.yaml
api_platform:
  cors:
    allow_origin: ['https://numele-tau.ngrok.app']
    allow_headers: ['Content-Type', 'Authorization']
    allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
    max_age: 3600
```

---

## 6. Fluxul tău zilnic

### Pentru DEV

* pornești Postgres, Redis, Elasticsearch (probabil rulează deja pe WSL)
* pornești Symfony în dev:

  ```bash
  symfony server:start -d
  # sau php -S ...
  ```
* pornești Next în dev:

  ```bash
  npm run dev
  ```
* lucrezi pe `http://localhost:3000` și `http://localhost:8000`

### Pentru PROD-like / test

* build frontend:

  ```bash
  npm run build
  npm run start -- -p 3001
  ```
* cleared cache la Symfony, APP_ENV=prod
* Nginx rulează
* pornești ngrok: `ngrok http 80`
* testezi din exterior pe domeniul ngrok

Important: să nu amesteci porturile. 3000/8000 le lași pentru dev, 3001/80 pentru prod.

---

## 7. Fără Docker, dar cu systemd (opțional)

Pentru că ești pe WSL 2 și ai systemd, poți face chiar și servicii:

* `/etc/systemd/system/next-prod.service`
* `/etc/systemd/system/symfony-worker.service`

Ex. pentru Next:

```ini
[Unit]
Description=Next.js prod
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/deschide_frontend
ExecStart=/usr/bin/npm run start -- -p 3001
Restart=always

[Install]
WantedBy=multi-user.target
```

Și-l pornești:

```bash
sudo systemctl enable --now next-prod
```

Așa ai un “prod” care stă mereu pornit pe WSL, iar dev-ul îl pornești manual.

---

## 8. Posibile probleme și soluții

1. **CORS din Next către Symfony prin ngrok**
   – verifică să fie același domeniu în toate locurile (API Platform, frontend env).
2. **URLs absolute în Next**
   – nu hardcoda `http://localhost:8000`, folosește env.
3. **Elasticsearch/Redis/Postgres**
   – pot fi aceleași instanțe pentru dev și prod, doar cu DB diferite, sau poți ține un singur cluster și să prefixezi indexurile.
4. **WSL accesat din afara PC-ului**
   – ngrok rezolvă asta, nu mai trebuie să expui WSL în LAN.
5. **Certificatul**
   – nu ai nevoie de SSL pe WSL, ngrok îți face TLS terminaton.

---

## 9. Rezumat scurt

* Da, e posibil.
* Ține două seturi de env (Symfony + Next).
* Rulează 2 procese pentru fiecare app: dev și prod.
* Nginx pe WSL e “fața” pentru prod.
* Ngrok tunel spre Nginx → ai domeniu public.
* Ajustezi CORS + trusted proxies.

Dacă vrei, în pasul următor îți pot scrie exact fișierele:

* `api_platform.yaml` cu CORS pentru ngrok
* `nginx.conf` complet (frontend + backend)
* `ngrok.yml` pentru tunelul stabil.
