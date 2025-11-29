# Audit Preparation Plan — Media Portal (Symfony API + Next.js)

**Project Type:** News Portal (CMS + Public Frontend)  
**Components:**
- Backend: Symfony 7.3 (API Platform, PostgreSQL, Redis)
- Frontend: Next.js 15 (CMS + Public Website)  
  **Objective:** Prepare project repositories, documentation, and environment for external technical audit  
  **Owner:** Software Architect / Technical Lead  
  **Version:** v1.0  
  **Date:** YYYY-MM-DD

---

## 1. Repository Structure & Access

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 1.1 | Separate Repositories | Ensure backend (Symfony) and frontend (Next.js) code are in separate Git repos | Architect / DevOps | Two read-only repositories shared with auditor | +2 days |
| 1.2 | Tag/Branch Selection | Identify branch/tag for audit (`main`, `release/x.y`) | Architect | Branch locked and documented | +2 days |
| 1.3 | Access Setup | Create read-only access for external auditor (GitHub/GitLab) | DevOps | Auditor can clone repos without write rights | +3 days |

---

## 2. Documentation & Overview

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 2.1 | `README.md` (Backend) | Include setup instructions, dependencies, and how to run locally | Backend Dev | Auditor can run `symfony serve` or Docker successfully | +4 days |
| 2.2 | `README.md` (Frontend) | Include setup instructions for Next.js (npm/pnpm run dev/build) | Frontend Dev | App runs on localhost:3000 with API connection | +4 days |
| 2.3 | `.env.example` files | Clean example for both apps (no secrets) | Architect | Environment variables clearly listed | +4 days |
| 2.4 | Business Overview | 1-page summary (goal, entities, roles, workflows) | Product Owner | Attached to audit package | +5 days |
| 2.5 | Scope Definition | Short summary of what’s **in/out of scope** for audit | Architect | Included in main document | +5 days |

---

## 3. Backend (Symfony + API Platform)

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 3.1 | `composer.json` Review | Clean up unused dependencies, ensure versions pinned | Backend Dev | composer install runs cleanly | +5 days |
| 3.2 | Config Audit Bundle | Export configs (`security.yaml`, `api_platform.yaml`, `doctrine.yaml`, `messenger.yaml`, `nelmio_cors.yaml`) | Backend Dev | Files collected under `/docs/configs/` | +5 days |
| 3.3 | Entities Overview | Document entities: Article, Category, Author, Media, Language | Backend Dev | Diagram in `/docs/entities.md` | +6 days |
| 3.4 | Migrations Export | Provide latest schema dump or migration snapshot | DevOps | File `/docs/schema.sql` generated | +6 days |
| 3.5 | OpenAPI Spec | Export `openapi.json` from API Platform | Backend Dev | File `/docs/openapi.json` committed | +6 days |
| 3.6 | Example Data | Provide sanitized DB dump (5–10 sample records) | Architect | `example_dump.sql` available | +6 days |
| 3.7 | Auth Flow Description | Document JWT / Session / Roles logic | Backend Dev | `/docs/auth_flow.md` created | +7 days |
| 3.8 | Media/Thumbnails Service | Include service class and storage flow (upload, crop, resize) | Backend Dev | `/docs/media_service.md` created | +7 days |

---

## 4. Frontend (Next.js)

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 4.1 | `package.json` & `next.config.js` Audit | Ensure dependencies, rewrites, and env vars are clear | Frontend Dev | Build runs locally with no warnings | +5 days |
| 4.2 | API Integration Doc | Describe how frontend calls Symfony API (fetch, SWR, Axios, etc.) | Frontend Dev | `/docs/api_integration.md` created | +6 days |
| 4.3 | i18n Setup | Export config for translations (`next-i18n-router`, locales, etc.) | Frontend Dev | `/docs/i18n_config.md` created | +6 days |
| 4.4 | Auth Flow UI | Describe how admin login and tokens are managed | Frontend Dev | `/docs/auth_ui.md` created | +7 days |
| 4.5 | Public Article Flow | Describe Article → Category → Article Detail route structure | Frontend Dev | `/docs/routes_public.md` created | +7 days |
| 4.6 | Admin CMS Flow | Describe CRUD for articles, media uploads, previews | Frontend Dev | `/docs/routes_admin.md` created | +7 days |

---

## 5. Infrastructure & Deployment

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 5.1 | Docker Compose (if exists) | Export `docker-compose.yml` for API, Postgres, Redis | DevOps | Verified containers build/run | +6 days |
| 5.2 | Nginx Config | Export `nginx.conf` for both API and Next.js | DevOps | Files in `/docs/nginx/` | +6 days |
| 5.3 | CI/CD Pipeline | Export `.gitlab-ci.yml` or GitHub Actions | DevOps | Pipelines documented in `/docs/ci_cd.md` | +7 days |
| 5.4 | Hosting Overview | Describe production hosting (server specs, domain, SSL, backup) | DevOps | `/docs/hosting_overview.md` created | +7 days |

---

## 6. Security & Privacy

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 6.1 | CORS Policy Review | Provide `nelmio_cors.yaml` or equivalent | Backend Dev | File attached | +5 days |
| 6.2 | Roles & Permissions Matrix | Define system roles (Admin, Editor, Author, Reader) | Architect | `/docs/roles_permissions.md` created | +6 days |
| 6.3 | Auth Tokens & Cookies | Describe how tokens and cookies are used (JWT / HttpOnly) | Backend Dev | `/docs/auth_tokens.md` created | +6 days |
| 6.4 | Sensitive Data Handling | Confirm no API returns private info unintentionally | Backend Dev | Verification checklist complete | +7 days |
| 6.5 | Rate Limiting & Security Headers | If used, document configuration | Backend Dev | Included in `/docs/security.md` | +7 days |

---

## 7. Editorial & SEO Features

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 7.1 | Article Workflow Doc | Define statuses: New, Submitted, Published, Breaking | Architect | `/docs/workflow_articles.md` created | +6 days |
| 7.2 | SEO & Sitemap | Export sitemap / RSS logic or routes | Frontend Dev | `/docs/seo_sitemap.md` created | +7 days |
| 7.3 | Thumbnails Profiles | Define thumbnail profiles and crop logic | Architect | `/docs/thumbnails_profiles.md` created | +7 days |
| 7.4 | Multilanguage Content Model | Describe how translations are stored (Gedmo/JSONB) | Backend Dev | `/docs/multilanguage_model.md` created | +7 days |

---

## 8. Final Audit Package

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 8.1 | `/docs/audit_package.zip` | Package all documents, dumps, configs | Architect | Archive ready to send to auditor | +8 days |
| 8.2 | Validation Checklist | Confirm all above tasks are done | Architect | `/docs/audit_checklist.md` signed | +8 days |

---

## 9. Optional (Recommended)

| # | Deliverable | Description | Responsible | Definition of Done | Deadline |
|---|--------------|-------------|--------------|--------------------|-----------|
| 9.1 | Postman Collection | Export API requests (Articles, Categories, Auth) | Backend Dev | `/docs/postman_collection.json` | +7 days |
| 9.2 | Architecture Diagram | UML or Draw.io diagram of backend modules | Architect | `/docs/architecture_diagram.png` | +7 days |
| 9.3 | Known Issues | List of bugs or in-progress features | Architect | `/docs/known_issues.md` | +7 days |
| 9.4 | Test Users | Demo credentials per role | Architect | `/docs/test_users.md` | +7 days |

---

## ✅ Definition of Done (Global)

- All deliverables available in `/docs/` folder of each repo
- All `.env.example` files cleaned and verified
- Application can be run locally (backend + frontend) by an external auditor
- Documentation consistent with current project state
- Audit package compressed and shared with external party

---

**Prepared by:** Software Architect  
**Approved by:** Project Manager / Product Owner  
**Version:** v1.0  

