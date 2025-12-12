# Ghid Testare Production Mode - Deschide News pe WSL

**Scop**: Testarea aplicației în production mode pe WSL înainte de deploy pe server real.
**Durată estimată**: 15-20 minute
**Prerequisite**: Serviciile de bază rulează (PostgreSQL, Redis, Elasticsearch, PHP-FPM, Nginx)

---

## Cuprins

1. [Oprire Servere Development](#1-oprire-servere-development)
2. [Configurare Environment Production](#2-configurare-environment-production)
3. [Build și Cache Warmup](#3-build-și-cache-warmup)
4. [Configurare Nginx](#4-configurare-nginx)
5. [Start Aplicații Production](#5-start-aplicații-production)
6. [Verificare și Testare](#6-verificare-și-testare)
7. [Revenire la Development Mode](#7-revenire-la-development-mode)

**Notă**: Mercure este partajat cu instanța existentă pe port 3000 (folosită și de pm_ai).

---

## 1. Oprire Servere Development

```bash
# 1.1 Oprește Symfony development server
cd /var/www/deschide_news_app/apps/backend
symfony server:stop

# Verifică că s-a oprit
symfony server:status
# Trebuie să afișeze: No local web server is running

# 1.2 Oprește Next.js development server
# Găsește și oprește procesele next dev
pkill -f "next dev" || true
pkill -f "next-server" || true

# Verifică că s-a oprit
ps aux | grep -E "next dev|next-server" | grep -v grep
# Nu trebuie să afișeze nimic

# 1.3 Verifică că porturile sunt libere
ss -tulpn | grep -E ":(3005|8081)"
# Trebuie să fie gol sau să nu arate procesele next/symfony
```

---

## 2. Configurare Environment Production

### 2.1 Backend Symfony

```bash
cd /var/www/deschide_news_app/apps/backend

# Backup configurație actuală
cp .env.local .env.local.dev.backup

# Editează pentru production
nano .env.local
```

**Modifică următoarele valori în `.env.local`:**

```bash
###> symfony/framework-bundle ###
APP_ENV=prod
APP_DEBUG=0
###< symfony/framework-bundle ###

###> mercure ###
# Partajare instanță Mercure existentă (port 3000)
# IMPORTANT: Folosește topics cu prefix "deschide/" pentru izolare
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=your_mercure_jwt_secret
###< mercure ###
```

### 2.2 Frontend Next.js

```bash
cd /var/www/deschide_news_app/apps/frontend

# Backup configurație actuală
cp .env.local .env.local.dev.backup

# Editează pentru production
nano .env.local
```

**Modifică/adaugă următoarele valori în `.env.local`:**

```bash
# Production mode
NODE_ENV=production

# API Configuration (va trece prin Nginx)
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081

# Mercure (partajat pe port 3000)
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure
```

---

## 3. Build și Cache Warmup

### 3.1 Backend - Cache Warmup

```bash
cd /var/www/deschide_news_app/apps/backend

# Generează .env.local.php optimizat pentru production
composer dump-env prod

# Clear și warmup cache
APP_ENV=prod php bin/console cache:clear --no-warmup
APP_ENV=prod php bin/console cache:warmup

# Optimizează autoloader
composer dump-autoload --optimize --classmap-authoritative

# Setează permisiuni corecte
sudo chown -R www-data:www-data var/
sudo chmod -R 775 var/cache var/log

# Verifică că totul e OK
APP_ENV=prod php bin/console about
```

### 3.2 Frontend - Production Build

```bash
cd /var/www/deschide_news_app/apps/frontend

# Build pentru production
pnpm build

# Verifică că build-ul s-a creat
ls -la .next/
ls -la .next/standalone/ 2>/dev/null || echo "Standalone mode nu e activat în next.config"

# Dacă folosești standalone mode, copiază fișierele statice
if [ -d ".next/standalone" ]; then
    cp -r public .next/standalone/
    cp -r .next/static .next/standalone/.next/
    echo "Standalone files copied successfully"
fi
```

---

## 4. Configurare Nginx

### 4.1 Crează Configurație Nginx pentru Production Testing

```bash
sudo nano /etc/nginx/sites-available/deschide-prod-test
```

```nginx
# =====================================================
# Deschide News - Production Testing Configuration
# =====================================================

# Upstream pentru Next.js (PM2)
upstream deschide_frontend {
    server 127.0.0.1:3005;
    keepalive 32;
}

# Upstream pentru Symfony (PHP-FPM)
upstream deschide_backend {
    server unix:/run/php/php8.4-fpm.sock;
}

# =====================================================
# Frontend Server - Port 3080
# =====================================================
server {
    listen 3080;
    server_name localhost 127.0.0.1;

    # Logging
    access_log /var/log/deschide/nginx-frontend-access.log;
    error_log /var/log/deschide/nginx-frontend-error.log warn;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Next.js Static Assets
    location /_next/static {
        alias /var/www/deschide_news_app/apps/frontend/.next/static;
        expires 365d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Public folder
    location ~ ^/(images|fonts|icons|favicon\.ico) {
        root /var/www/deschide_news_app/apps/frontend/public;
        expires 30d;
        access_log off;
    }

    # Mercure proxy (instanță partajată pe port 3000)
    location /.well-known/mercure {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_buffering off;
        proxy_cache off;
    }

    # Next.js Application
    location / {
        proxy_pass http://deschide_frontend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}

# =====================================================
# Backend API Server - Port 8080
# =====================================================
server {
    listen 8080;
    server_name localhost 127.0.0.1;

    root /var/www/deschide_news_app/apps/backend/public;
    index index.php;

    # Logging
    access_log /var/log/deschide/nginx-api-access.log;
    error_log /var/log/deschide/nginx-api-error.log warn;

    # Client body size pentru uploads
    client_max_body_size 20M;

    # Gzip
    gzip on;
    gzip_types application/json application/ld+json;

    # Uploads/CDN
    location /uploads {
        alias /var/www/deschide_news_app/apps/backend/public/uploads;
        expires 365d;
        add_header Cache-Control "public, immutable";
        add_header Access-Control-Allow-Origin "*";
        access_log off;
    }

    # PHP-FPM
    location ~ ^/index\.php(/|$) {
        fastcgi_pass deschide_backend;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param APP_ENV prod;
        fastcgi_param APP_DEBUG 0;

        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;

        internal;
    }

    # Route all requests through index.php
    location / {
        try_files $uri /index.php$is_args$args;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
}
```

### 4.2 Enable și Test Nginx

```bash
# Enable site
sudo ln -sf /etc/nginx/sites-available/deschide-prod-test /etc/nginx/sites-enabled/

# Test configurație
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

# Verifică porturile
ss -tulpn | grep -E ":(3080|8080)"
```

---

## 5. Start Aplicații Production

### 5.1 Configurare PM2 pentru Frontend

```bash
cd /var/www/deschide_news_app/apps/frontend

# Crează ecosystem.config.js pentru production
cat > ecosystem.config.js << 'EOF'
module.exports = {
  apps: [
    {
      name: 'deschide-frontend-prod',
      script: 'node_modules/.bin/next',
      args: 'start -p 3005',
      cwd: '/var/www/deschide_news_app/apps/frontend',
      instances: 2,
      exec_mode: 'cluster',
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        PORT: 3005,
      },
      error_file: '/var/log/deschide/nextjs-error.log',
      out_file: '/var/log/deschide/nextjs-out.log',
      merge_logs: true,
      autorestart: true,
    },
  ],
}
EOF
```

### 5.2 Start Frontend cu PM2

```bash
cd /var/www/deschide_news_app/apps/frontend

# Start aplicația
pm2 start ecosystem.config.js

# Verifică status
pm2 status

# Verifică loguri
pm2 logs deschide-frontend-prod --lines 20

# Salvează configurația PM2
pm2 save
```

### 5.3 Verifică că Backend folosește PHP-FPM

Backend-ul Symfony va fi servit direct de Nginx + PHP-FPM (nu mai avem nevoie de `symfony serve`).

```bash
# Verifică că PHP-FPM rulează
sudo systemctl status php8.4-fpm

# Restart PHP-FPM pentru a prelua noua configurație
sudo systemctl restart php8.4-fpm
```

---

## 6. Verificare și Testare

### 6.1 Test Endpoints

```bash
echo "=== Test Backend API (port 8080) ==="
curl -s http://127.0.0.1:8080/api | head -20

echo ""
echo "=== Test Frontend (port 3080) ==="
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://127.0.0.1:3080

echo ""
echo "=== Test Mercure (port 3000 - partajat) ==="
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost:3000/.well-known/mercure

echo ""
echo "=== PM2 Status ==="
pm2 status
```

### 6.2 Test în Browser

Deschide în browser:
- **Frontend**: http://localhost:3080
- **API**: http://localhost:8080/api
- **API Docs**: http://localhost:8080/api/docs

### 6.3 Health Check Complet

```bash
#!/bin/bash
echo "=========================================="
echo "Deschide News - Production Test Check"
echo "=========================================="

# Check services
echo -e "\n[Services]"
systemctl is-active php8.4-fpm && echo "✓ PHP-FPM: active" || echo "✗ PHP-FPM: inactive"
systemctl is-active nginx && echo "✓ Nginx: active" || echo "✗ Nginx: inactive"
systemctl is-active mercure 2>/dev/null && echo "✓ Mercure: active" || echo "✓ Mercure: running (pm_ai instance)"
pm2 pid deschide-frontend-prod > /dev/null && echo "✓ PM2 Frontend: active" || echo "✗ PM2 Frontend: inactive"

# Check endpoints
echo -e "\n[Endpoints]"
curl -s -o /dev/null -w "Backend API: %{http_code}\n" http://127.0.0.1:8080/api
curl -s -o /dev/null -w "Frontend: %{http_code}\n" http://127.0.0.1:3080
curl -s -o /dev/null -w "Mercure: %{http_code}\n" http://localhost:3000/.well-known/mercure

# Check logs for errors
echo -e "\n[Recent Errors]"
tail -5 /var/log/deschide/nginx-api-error.log 2>/dev/null || echo "No API errors"
tail -5 /var/log/deschide/nextjs-error.log 2>/dev/null || echo "No Frontend errors"

echo -e "\n=========================================="
```

---

## 7. Revenire la Development Mode

Când vrei să revii la development mode:

```bash
# 7.1 Oprește serviciile production
pm2 stop deschide-frontend-prod
pm2 delete deschide-frontend-prod
# Notă: Mercure rămâne activ (partajat cu pm_ai)

# 7.2 Disable Nginx site production
sudo rm /etc/nginx/sites-enabled/deschide-prod-test
sudo systemctl reload nginx

# 7.3 Restaurează .env.local pentru development
cd /var/www/deschide_news_app/apps/backend
cp .env.local.dev.backup .env.local
rm -f .env.local.php  # Șterge env compilat
php bin/console cache:clear

cd /var/www/deschide_news_app/apps/frontend
cp .env.local.dev.backup .env.local

# 7.4 Start servere development
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081

cd /var/www/deschide_news_app/apps/frontend
pnpm dev &

# 7.5 Verifică
symfony server:status
curl http://127.0.0.1:8081/api
curl http://localhost:3005
```

---

## Troubleshooting

### Frontend nu pornește
```bash
# Verifică logurile PM2
pm2 logs deschide-frontend-prod --lines 50

# Verifică că build-ul există
ls -la /var/www/deschide_news_app/apps/frontend/.next/

# Rebuild dacă e necesar
cd /var/www/deschide_news_app/apps/frontend
pnpm build
```

### Backend returnează 500
```bash
# Verifică logurile Symfony
tail -50 /var/www/deschide_news_app/apps/backend/var/log/prod.log

# Verifică permisiunile
sudo chown -R www-data:www-data /var/www/deschide_news_app/apps/backend/var/

# Clear cache
cd /var/www/deschide_news_app/apps/backend
sudo -u www-data php bin/console cache:clear --env=prod
```

### Mercure nu răspunde
```bash
# Verifică dacă Mercure rulează (instanța pm_ai)
ps aux | grep mercure

# Verifică portul 3000
ss -tulpn | grep :3000

# Test endpoint
curl http://localhost:3000/.well-known/mercure

# Verifică logurile (dacă sunt disponibile)
cat /var/www/pm_ai/var/mercure/mercure.log 2>/dev/null | tail -20
```

**Notă**: Mercure este partajat cu pm_ai. Dacă nu funcționează, verifică că instanța pm_ai este activă.

---

## Rezumat Porturi Production Test

| Serviciu | Port | URL |
|----------|------|-----|
| Frontend (Nginx) | 3080 | http://localhost:3080 |
| Backend API (Nginx) | 8080 | http://localhost:8080/api |
| Frontend (PM2 direct) | 3005 | http://localhost:3005 |
| Mercure (partajat) | 3000 | http://localhost:3000 |

---

**Document creat**: 11 Decembrie 2025
**Versiune**: 1.0
**Scop**: Testare Production Mode pe WSL
