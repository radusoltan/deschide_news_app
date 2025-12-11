# Gemini Code AI - Project Context: Deschide News App

**This document provides a comprehensive overview for the Gemini Code AI assistant, detailing the project's architecture, technologies, and development conventions.**

---

## 1. Project Overview

**Deschide News App** is a high-performance, multilanguage news platform built on a modern, decoupled architecture. It consists of a Symfony backend providing a RESTful API and a Next.js frontend for the user interface.

-   **Monorepo:** The project is structured as a monorepo, with the backend and frontend applications located in the `apps/` directory.
-   **Multilanguage:** Supports Romanian (ro, default), English (en), and Russian (ru).
-   **Backend:** A **Symfony 7.3** (PHP 8.4) application serves a **JSON-LD/Hydra** REST API using the **API Platform** framework. It handles all business logic, data persistence, and authentication.
-   **Frontend:** A **Next.js 16** (React 19, TypeScript) application consumes the backend API. It uses Server-Side Rendering (SSR) for performance and SEO and features a rich user interface with TailwindCSS.
-   **Core Services:** The application stack relies on several key services:
    -   **PostgreSQL 17:** Primary database.
    -   **Redis:** Caching and session storage.
    -   **Elasticsearch:** Full-text search capabilities.
    -   **Mercure:** Real-time updates for features like "Live Text".
    -   **RabbitMQ:** Asynchronous task processing.

## 2. Development Environment

### Backend (Symfony API)

-   **Location:** `apps/backend/`
-   **Dependencies:** Managed with **Composer**. Install using `composer install`.
-   **Running the dev server:**
    ```bash
    cd apps/backend
    symfony serve -d --port=8081
    ```
    The API is then available at `http://127.0.0.1:8081/api`.

-   **Database:**
    -   Create the database: `symfony console doctrine:database:create`
    -   Run migrations: `symfony console doctrine:migrations:migrate`
    -   Load fixtures: `symfony console doctrine:fixtures:load`

### Frontend (Next.js App)

-   **Location:** `apps/frontend/`
-   **Dependencies:** Managed with **pnpm**. Install using `pnpm install`.
-   **Running the dev server:**
    ```bash
    cd apps/frontend
    pnpm dev
    ```
    The frontend is then available at `http://localhost:3005`.

## 3. Building & Testing

### Backend

-   **Code Style Check (dry-run):**
    ```bash
    # From apps/backend/
    vendor/bin/php-cs-fixer fix --dry-run
    ```
-   **Static Analysis (PHPStan):**
    ```bash
    # From apps/backend/
    vendor/bin/phpstan analyse
    ```
-   **Unit & Integration Tests (PHPUnit):**
    ```bash
    # From apps/backend/
    # Ensure .env.test.local is configured
    vendor/bin/phpunit
    ```
    The CI runs tests using `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover=coverage.xml`.

### Frontend

-   **Linting (ESLint):**
    ```bash
    # From apps/frontend/
    pnpm lint
    ```
-   **Unit & Integration Tests (Jest):**
    ```bash
    # From apps/frontend/
    pnpm test
    ```
    The CI uses `pnpm test:ci`.

-   **Production Build:**
    ```bash
    # From apps/frontend/
    pnpm build
    ```

## 4. Key Architectural Concepts

-   **API-First:** The backend is a pure API, completely decoupled from the frontend. This allows for independent development and deployment and enables the API to be used by other clients (e.g., mobile apps) in the future.
-   **State Providers/Processors:** The backend heavily uses API Platform's custom state providers and processors (`src/State/`) to manage complex read (GET) and write (POST/PUT) operations, apply business logic, and interact with services like cache and search.
-   **Gedmo Translatable:** Multilanguage content is managed using the Gedmo Translatable extension for Doctrine, which stores translations in a separate table (e.g., `article_translation`).
-   **Async Workers:** Long-running tasks like thumbnail generation and search indexing are handled asynchronously via RabbitMQ and Symfony Messenger workers.
-   **Real-time with Mercure:** Live features (e.g., live blogging, breaking news banners) are powered by server-sent events (SSE) pushed from the backend via a Mercure hub.
-   **Image & Thumbnail Generation:** Images are uploaded via the backend, and a series of thumbnails for different use cases (e.g., `hero_big`, `card_small`) are generated automatically in the background.

## 5. Development Conventions

-   **Git Flow:** The project uses a Git Flow branching model (`main`, `develop`, `feature/**`, `bugfix/**`, etc.). Pull requests are used for merging features into `develop` and `main`.
-   **CI/CD:** GitHub Actions are configured for both backend and frontend (`/.github/workflows/`). The workflows automatically run linting, static analysis, and tests on every push and pull request.
-   **Code Style:**
    -   **Backend:** Follows PSR-12, enforced by `php-cs-fixer`. Configuration is in `.php-cs-fixer.dist.php`.
    -   **Frontend:** Enforced by ESLint. Configuration is in `eslint.config.mjs`.
-   **Commits:** Follow conventional commit standards where possible to maintain a clean history.
-   **Documentation:** Centralized project documentation is in the `/docs` directory. Application-specific documentation is located in `apps/backend/docs/` and `apps/frontend/docs/`.
