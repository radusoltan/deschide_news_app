# Demo Data Population Plan

This document outlines the steps to populate the Deschide News App database with demonstration data for preproduction testing.

## Prerequisites

*   Backend application must be running or accessible via command line.
*   Database (PostgreSQL) must be accessible.
*   Elasticsearch and Redis should be running (recommended).

## 1. Reset and Populate Database

We use the standard Symfony fixtures to generate realistic testing data.

> [!WARNING]
> This command will truncate all existing data in the database!

Run the following command in the `apps/backend` directory:

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### What this generates:

1.  **Users**
    *   `admin` (Password: `password`) - Roles: `ROLE_ADMIN`, `ROLE_USER`
    *   `editor` (Password: `password`) - Roles: `ROLE_EDITOR`, `ROLE_USER`
    *   `user` (Password: `password`) - Roles: `ROLE_USER`

2.  **Articles**
    *   ~80 Articles total.
    *   **Languages**: Translations generated for Romanian (default), English, and Russian.
    *   **Statuses**:
        *   ~80% `PUBLISHED`
        *   ~15% `SUBMITTED`
        *   ~5% `NEW`
    *   **Badges**: Breaking News (~10%), Alert (~10%), Flash (~10%).
    *   **Features**: ~12 Featured articles.
    *   **Content**: Generated using Faker (localized).

3.  **Live Texts**
    *   ~10 Live Text sessions.
    *   Status mix: `DRAFT`, `LIVE`, `PAUSED`, `ENDED`.
    *   Includes posts, authors, and collaborators.

4.  **Categories & Authors**
    *   8 Categories.
    *   12 Authors.

5.  **Images & Media**
    *   **Source**: ~40 Placeholder images downloaded from `dummyimage.com` (requires Internet).
    *   **Thumbnails**: Automatically generated for 4 profiles (~160 files total):
        *   `article_thumbnail` (16:9)
        *   `article_card` (3:2)
        *   `article_square` (1:1)
        *   `article_hero` (8:3)
    *   **Associations**: Every article has at least 1 image (featured).
    *   **Metadata**: Multilanguage Alt texts, Captions, and Descriptions.

6.  **Important Articles List**
    *   **Content**: 5 Randomly selected `PUBLISHED` articles.
    *   **Function**: Pinned to the "Important" / "Hero" section of the Homepage.
    *   **Ordering**: Fixed positions 1 through 5.


## 2. Verify Data Population

After running the fixtures, verify the data:

1.  **Login to Admin Panel**:
    *   Go to `/[locale]/admin`
    *   Login with `admin` / `password`
    *   Check the Dashboard counters.

2.  **Check Frontend**:
    *   Homepage should display featured articles.
    *   Menu should populate with categories.

## 3. Troubleshooting

*   **"Table not found"**: Run migrations first.
    ```bash
    php bin/console doctrine:migrations:migrate --no-interaction
    ```
*   **"Elasticsearch error"**: Ensure Elastic is running or populate ignoring search index (articles might not appear in search).
    *   To reindex manually:
        ```bash
        php bin/console fos:elastica:populate
        ```
