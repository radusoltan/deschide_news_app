# Setup Guide - Deschide News App

**Ultima actualizare**: 5 Noiembrie 2025
**Versiune**: 1.0

---

## 📋 Cerințe Sistem

### Software Necesar

| Software | Versiune Minimă | Versiune Recomandată | Status |
|----------|-----------------|----------------------|--------|
| **PHP** | 8.3 | 8.4 | ✅ Backend |
| **Composer** | 2.5+ | Latest | ✅ Backend |
| **Node.js** | 18.x | 20.x LTS | ✅ Frontend |
| **pnpm** | 8.x | Latest | ✅ Frontend |
| **PostgreSQL** | 15 | 17 | ✅ Database |
| **Redis** | 7.0+ | Latest | ✅ Cache |
| **Elasticsearch** | 8.x | 8.x | ✅ Search |
| **RabbitMQ** | 3.12+ | Latest | ⬜ Optional |
| **Symfony CLI** | Latest | Latest | ✅ Development |

---

## 🚀 Quick Start (Development)

### 1. Clone Repository

```bash
# Clone project (dacă ai access la repos separate)
git clone git@github.com:radusoltan/deschide_news_app_backend.git deschide_backend
git clone git@github.com:radusoltan/deschide_news_app_frontend.git deschide_frontend
```

### 2. Setup Backend

```bash
cd deschide_backend

# Install dependencies
composer install

# Configure environment
cp .env.example .env.local
nano .env.local  # Editează cu credențiale locale

# Generate JWT keys
symfony console lexik:jwt:generate-keypair

# Create database
symfony console doctrine:database:create

# Run migrations
symfony console doctrine:migrations:migrate

# (Optional) Load fixtures
symfony console doctrine:fixtures:load

# Start development server
symfony serve -d --port=8081
```

**Backend va rula pe**: `http://127.0.0.1:8081`

### 3. Setup Frontend

```bash
cd deschide_frontend

# Install dependencies
pnpm install

# Configure environment
cp .env.example .env.local
nano .env.local  # Editează cu API_URL

# Start development server
pnpm dev
```

**Frontend va rula pe**: `http://localhost:3005`

### 4. Verificare

```bash
# Test backend API
curl http://127.0.0.1:8081/api

# Test frontend
curl http://localhost:3005

# Check running ports
ss -tulpn | grep -E ":(3005|8081)"
```

---

## 🔧 Configurare Detaliată

### Backend Environment (.env.local)

```bash
###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=YOUR_RANDOM_SECRET_HERE
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
DATABASE_URL="postgresql://deschide_admin:YOUR_PASSWORD@127.0.0.1:5432/deschide?serverVersion=17&charset=utf8"
###< doctrine/doctrine-bundle ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=YOUR_SECURE_PASSPHRASE
###< lexik/jwt-authentication-bundle ###

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
###< nelmio/cors-bundle ###

###> symfony/messenger ###
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
# sau pentru RabbitMQ:
# MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/deschide_news_messages
###< symfony/messenger ###

###> elasticsearch ###
ELASTICSEARCH_HOST="https://localhost:9200"
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=YOUR_ES_PASSWORD
ELASTICSEARCH_VERIFY_SSL=0
###< elasticsearch ###

###> mercure ###
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
###< mercure ###

###> redis ###
REDIS_URL=redis://localhost:6379/1
###< redis ###
```

### Frontend Environment (.env.local)

```bash
# Server
PORT=3005

# API Configuration
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081
NEXT_PUBLIC_CDN_URL=http://127.0.0.1:8082

# Mercure Real-time
NEXT_PUBLIC_MERCURE_URL=http://localhost:3000/.well-known/mercure

# Localization
NEXT_PUBLIC_DEFAULT_LOCALE=ro
NEXT_PUBLIC_AVAILABLE_LOCALES=ro,en,ru

# Analytics (Optional)
# NEXT_PUBLIC_GA_ID=G-XXXXXXXXXX
```

---

## 🗄️ Database Setup

### PostgreSQL

```bash
# Create user
sudo -u postgres createuser -P deschide_admin
# Password: [enter your password]

# Create database
sudo -u postgres createdb -O deschide_admin deschide

# Or usando SQL:
sudo -u postgres psql
postgres=# CREATE USER deschide_admin WITH PASSWORD 'your_password';
postgres=# CREATE DATABASE deschide OWNER deschide_admin;
postgres=# \q

# Test connection
psql -h localhost -U deschide_admin -d deschide
```

### Run Migrations

```bash
cd deschide_backend

# Check status
symfony console doctrine:migrations:status

# Run all pending migrations
symfony console doctrine:migrations:migrate

# Verify schema
symfony console doctrine:schema:validate
```

---

## 🔍 Elasticsearch Setup

### Install Elasticsearch

```bash
# Ubuntu/Debian
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list
sudo apt update && sudo apt install elasticsearch

# Start service
sudo systemctl enable elasticsearch
sudo systemctl start elasticsearch

# Get password
sudo /usr/share/elasticsearch/bin/elasticsearch-reset-password -u elastic
```

### Create Indices

```bash
cd deschide_backend

# Create article indices (ro, en, ru)
symfony console app:elasticsearch:create-index

# Create image index
symfony console app:elasticsearch:create-image-index

# Index existing content
symfony console app:elasticsearch:index-articles
symfony console app:elasticsearch:index-images
```

---

## 🚀 Redis Setup

### Install Redis

```bash
# Ubuntu/Debian
sudo apt update && sudo apt install redis-server

# Start service
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Test connection
redis-cli ping
# Should return: PONG

# Check connection to DB 1
redis-cli -n 1 ping
```

### Configuration

Redis database 1 este folosit pentru:
- Session storage
- Cache pentru API responses
- Rate limiting
- Analytics counters

**Namespace**: `deschide_news:*`

---

## 📦 Optional Services

### RabbitMQ (Async Processing)

```bash
# Install
sudo apt install rabbitmq-server

# Enable management plugin
sudo rabbitmq-plugins enable rabbitmq_management

# Access: http://localhost:15672
# Default credentials: guest/guest

# Start workers
cd deschide_backend
symfony console messenger:consume async -vv
```

### Mercure (Real-time)

```bash
# Download Mercure
wget https://github.com/dunglas/mercure/releases/download/v0.15.0/mercure_0.15.0_Linux_x86_64.tar.gz
tar -xzf mercure_*.tar.gz

# Run Mercure
MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
./mercure run --config Caddyfile.dev
```

---

## 🎨 Frontend Development

### Install Dependencies

```bash
cd deschide_frontend
pnpm install
```

### Development Commands

```bash
# Start dev server (port 3005)
pnpm dev

# Build production
pnpm build

# Start production server
pnpm start

# Lint code
pnpm lint

# Type check
pnpm type-check  # (dacă e configurat)
```

### Environment Modes

```bash
# Development
pnpm dev

# Production build
pnpm build && pnpm start

# Preview build
pnpm build && pnpm preview
```

---

## 🔐 Security Setup

### Generate JWT Keys

```bash
cd deschide_backend

# Generate key pair
symfony console lexik:jwt:generate-keypair

# Keys vor fi generate în:
# config/jwt/private.pem
# config/jwt/public.pem

# IMPORTANT: Adaugă în .gitignore
echo "config/jwt/*.pem" >> .gitignore
```

### Set Correct Permissions

```bash
# Backend cache și logs
cd deschide_backend
chmod -R 777 var/cache var/log

# JWT keys
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem

# Uploads directory
chmod -R 775 public/uploads
```

---

## 📊 Import Data (Optional)

### From Newscoop CMS

```bash
cd deschide_backend

# Configure Newscoop connection în .env.local
NEWSCOOP_DATABASE_URL="mysql://root:password@127.0.0.1:3306/newscoop"

# Import categories
symfony console app:import:categories

# Import authors
symfony console app:import:authors

# Import articles with relations
symfony console app:import:articles-with-relations

# Import images
symfony console app:import:images

# Generate thumbnails
symfony console app:import:generate-thumbnails

# Import translations (ru, en)
symfony console app:import:translations
```

### Sample Data

```bash
# Quick sample import (pentru testing)
symfony console app:sample-import
# Imports: 6 categories, 300 articles cu translations
```

---

## 🧪 Testing Setup

### Backend Tests

```bash
cd deschide_backend

# Install dev dependencies
composer install --dev

# Run all tests
vendor/bin/phpunit

# Run specific suite
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Functional

# With coverage
vendor/bin/phpunit --coverage-html var/coverage
```

### Frontend Tests

```bash
cd deschide_frontend

# Install test dependencies
pnpm install -D

# Run tests (când sunt configurate)
pnpm test

# E2E tests (când sunt configurate)
pnpm test:e2e
```

---

## 🔍 Troubleshooting

### Backend Issues

#### "Database connection failed"
```bash
# Verify PostgreSQL is running
sudo systemctl status postgresql

# Test connection
psql -h localhost -U deschide_admin -d deschide

# Check credentials în .env.local
```

#### "JWT keys not found"
```bash
# Regenerate keys
symfony console lexik:jwt:generate-keypair

# Verify permissions
ls -la config/jwt/
```

#### "Elasticsearch connection failed"
```bash
# Verify service
sudo systemctl status elasticsearch

# Test connection
curl -k https://localhost:9200 -u elastic:your_password

# Check credentials în .env.local
```

### Frontend Issues

#### "Cannot connect to API"
```bash
# Verify backend is running
curl http://127.0.0.1:8081/api

# Check NEXT_PUBLIC_API_URL în .env.local

# Check CORS configuration în backend
```

#### "Module not found"
```bash
# Clear cache și reinstall
rm -rf node_modules .next
pnpm install
pnpm dev
```

#### "Port 3005 already in use"
```bash
# Find process
lsof -i :3005

# Kill process
kill -9 <PID>

# Or use different port
PORT=3006 pnpm dev
```

---

## 📝 Daily Development Workflow

```bash
# Terminal 1 - Backend
cd deschide_backend
symfony serve -d --port=8081
# Optional: symfony console messenger:consume async -vv

# Terminal 2 - Frontend
cd deschide_frontend
pnpm dev

# Terminal 3 - Logs (optional)
cd deschide_backend
tail -f var/log/dev.log

# Access applications:
# Backend API: http://127.0.0.1:8081/api
# Frontend: http://localhost:3005
# API Docs: http://127.0.0.1:8081/api/docs.jsonld
```

---

## 🚀 Production Setup

Pentru setup production, vezi:
- **APPLICATIONS_ARCHITECTURE.md** - Infrastructure details
- **docs/infrastructure/** - Redis, cron, monitoring setup
- **Backend README** - Production commands
- **Frontend README** - Build și deployment

---

## 🔗 Link-uri Utile

- **Architecture**: [docs/architecture/README.md](./docs/architecture/README.md)
- **Backend Docs**: [deschide_backend/docs/](./deschide_backend/docs/)
- **Frontend Docs**: [deschide_frontend/docs/](./deschide_frontend/docs/)
- **Infrastructure**: [docs/infrastructure/](./docs/infrastructure/)
- **Features**: [docs/features/](./docs/features/)

---

## 📞 Ajutor

Pentru probleme sau întrebări:
1. Consultă documentația specifică în `docs/`
2. Verifică rapoartele de sprint în `archive/`
3. Review CLAUDE.md pentru ghidare AI assistant

---

**Ultima actualizare**: 5 Noiembrie 2025
