# Documentation Directory

This directory contains comprehensive documentation for the Deschide News backend API, prepared for external audit and developer onboarding.

## 📋 Document Index

### Project Overview & Planning

| Document | Description | Target Audience |
|----------|-------------|-----------------|
| **BUSINESS_OVERVIEW.md** | Complete project overview including goals, architecture, and stakeholders | External auditor, new developers |
| **AUDIT_SCOPE.md** | Detailed scope definition for external audit (in/out of scope) | External auditor, project manager |
| **audit_prepare.md** | Original audit preparation plan with checklist | Development team |
| **audit_checklist.md** | Current progress tracking for audit preparation | Development team |

### API & Backend Documentation

| Document | Description | Technical Level |
|----------|-------------|-----------------|
| **openapi.jsonld** | Complete API specification (Hydra/JSON-LD format, 104KB) | Developers, auditor |
| **api_entrypoint.json** | API entrypoint with available resources | Developers |
| **schema.sql** | Database schema (2,803 lines) | Database admin, auditor |

### Architecture & Design

| Document | Description | Technical Level |
|----------|-------------|-----------------|
| **entities.md** | Entity relationship diagram and descriptions | Developers, auditor |
| **auth_flow.md** | JWT authentication & authorization flow | Security auditor, developers |
| **media_service.md** | Image upload and thumbnail generation system | Developers, auditor |
| **multilanguage_model.md** | Gedmo Translatable implementation (ro/en/ru) | Developers, auditor |
| **workflow_articles.md** | Article lifecycle (draft → published → archived) | Content team, developers |
| **roles_permissions.md** | Complete access control matrix by role | Security auditor, admin |

### Configuration Files

| Directory/File | Description | Purpose |
|----------------|-------------|---------|
| **configs/** | Exported YAML configurations | Audit review |
| ├── security.yaml | Symfony security configuration | Security audit |
| ├── api_platform.yaml | API Platform settings | API review |
| ├── doctrine.yaml | Database ORM configuration | Database review |
| ├── messenger.yaml | Message queue configuration | Async processing review |
| └── nelmio_cors.yaml | CORS policy | Security audit |

### Sample Data

| Directory/File | Description | Purpose |
|----------------|-------------|---------|
| **examples/** | Sample data exports | Testing, demo |
| └── sample_data.sql | Sanitized database records (1,000 lines) | Local setup, testing |

---

## 🎯 Quick Navigation

### For External Auditor

**Start here:**
1. **BUSINESS_OVERVIEW.md** - Understand the project
2. **AUDIT_SCOPE.md** - Know what's in/out of scope
3. **openapi.jsonld** - API specification
4. **schema.sql** - Database structure
5. **auth_flow.md** - Security implementation
6. **roles_permissions.md** - Access control

**Security Focus:**
- auth_flow.md
- roles_permissions.md
- configs/security.yaml
- configs/nelmio_cors.yaml

**Architecture Review:**
- entities.md
- multilanguage_model.md
- workflow_articles.md
- media_service.md

### For New Developers

**Onboarding Path:**
1. **BUSINESS_OVERVIEW.md** - Project context
2. **entities.md** - Data model
3. **multilanguage_model.md** - Translation system
4. **workflow_articles.md** - Article management
5. **auth_flow.md** - Authentication
6. **openapi.jsonld** - API endpoints

**Common Tasks:**
- Create new entity? → entities.md
- Add translation? → multilanguage_model.md
- Implement auth? → auth_flow.md, roles_permissions.md
- Upload images? → media_service.md

### For Content Team

**Editorial Workflow:**
- workflow_articles.md - Complete article lifecycle
- roles_permissions.md - What each role can do

---

## 📊 Documentation Status

**Completion**: ~85% (34/40 planned documents)

### ✅ Completed

- [x] Business overview
- [x] Audit scope definition
- [x] API specification export
- [x] Database schema export
- [x] Backend configurations
- [x] Authentication flow
- [x] Entity relationships
- [x] Media/thumbnail service
- [x] Multilanguage model
- [x] Article workflow
- [x] Roles & permissions matrix
- [x] Sample data
- [x] Audit checklist

### ⏳ Remaining (Optional)

- [ ] Postman collection
- [ ] Architecture diagram (UML/Draw.io)
- [ ] Known issues list
- [ ] Test user credentials
- [ ] CI/CD pipeline documentation
- [ ] Hosting overview

---

## 🔄 Update Frequency

| Document Type | Update Frequency | Responsibility |
|---------------|------------------|----------------|
| API Spec (openapi.jsonld) | On major API changes | Backend lead |
| Database Schema (schema.sql) | After migrations | Backend lead |
| Entity Docs (entities.md) | On entity changes | Backend dev |
| Auth Flow (auth_flow.md) | On security changes | Security lead |
| Roles Matrix (roles_permissions.md) | On permission changes | Product owner |
| Workflow (workflow_articles.md) | On process changes | Product owner |

---

## 📝 Document Conventions

### File Naming

- **UPPERCASE.md** - Project-wide documents (BUSINESS_OVERVIEW, AUDIT_SCOPE)
- **lowercase_snake.md** - Technical documentation (auth_flow, entities)
- **configs/** - Configuration files (YAML)
- **examples/** - Sample data (SQL, JSON)

### Markdown Format

- Use headers (H1 → H6) for structure
- Include table of contents for long docs
- Use code blocks with language hints
- Include diagrams (ASCII art, Mermaid, or image links)
- Add "Last Updated" date at bottom

### Code Examples

```php
// Always include language hint
public function example(): void
{
    // Well-commented code
}
```

---

## 🔗 Related Documentation

### Root Project Files

- `/var/www/deschide_news_app/CLAUDE.md` - Main project documentation
- `/var/www/deschide_news_app/README.md` - Project setup guide
- `/var/www/deschide_news_app/DEVELOPMENT_ENVIRONMENT.md` - Environment setup

### Backend Root Files

- `/var/www/deschide_news_app/deschide_backend/README.md` - Backend setup
- `/var/www/deschide_news_app/deschide_backend/CLAUDE.md` - Backend-specific guide (if exists)

### Frontend Documentation

- `/var/www/deschide_news_app/deschide_frontend/docs/` - Frontend documentation
  - api_integration.md
  - i18n_config.md
  - auth_ui.md
  - routes_public.md
  - routes_admin.md

---

## 🆘 Support

**Questions about documentation:**
- Check BUSINESS_OVERVIEW.md for project context
- Check AUDIT_SCOPE.md for audit boundaries
- Check specific technical docs for implementation details

**Need to update documentation:**
- Follow conventions above
- Update "Last Updated" date
- Commit with descriptive message
- Notify team in communication channel

---

## ⚠️ Important Notes

### For Auditor

1. **No Production Access**: All documentation is based on development environment
2. **Sample Data Only**: `examples/sample_data.sql` is sanitized and safe
3. **Secrets Removed**: All `.env.example` files have placeholder values only
4. **Code Freeze**: Code should be frozen during audit period

### For Developers

1. **Keep Docs Updated**: Update relevant docs when making changes
2. **No Secrets**: Never commit real credentials or secrets
3. **Review Before Commit**: Ensure documentation is accurate
4. **Ask if Unclear**: Better to ask than to document incorrectly

---

**Last Updated**: November 5, 2025
**Maintained By**: Development Team
**Version**: 1.0
**Status**: Ready for External Audit
