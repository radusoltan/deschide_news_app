# Sprint 9: Templates & Customization - Implementation Complete

**Date**: 2025-11-03
**Status**: ✅ Completed
**Sprint**: Phase 4: Advanced Features (Sprint 9/9)

## Overview

Sprint 9 implements a template system for Live Text posts, allowing customization of colors, layouts, and features based on predefined templates. The system includes 4 built-in templates (Breaking News, Sport, Conference, Election) with JSON-based configuration for complete customization.

## Features Implemented

### 1. Backend: TemplateType Enum

**File**: `src/Enum/TemplateType.php`

Enum with 4 template types:
- `BREAKING_NEWS` 🚨 - For urgent news with red theme
- `SPORT` ⚽ - For sports events with green theme
- `CONFERENCE` 🎤 - For conferences with blue theme
- `ELECTION` 🗳️ - For elections with purple theme

**Features**:
- `getLabel($locale)` - Returns localized label (ro/en/ru)
- `getDescription($locale)` - Returns localized description
- `getIcon()` - Returns emoji icon

### 2. Backend: LiveTextTemplate Entity

**File**: `src/Entity/LiveTextTemplate.php`

Properties:
- `name` - Template display name
- `description` - Template description
- `type` - TemplateType enum
- `config` - JSON configuration object
- `isSystem` - Flag for system-provided templates
- `createdAt`, `updatedAt` - Timestamps

**Config Structure**:
```json
{
  "colors": {
    "primary": "#ef4444",
    "secondary": "#dc2626",
    "accent": "#b91c1c",
    "background": "#fef2f2",
    "text": "#7f1d1d"
  },
  "layout": {
    "headerStyle": "banner|bold|minimal",
    "postStyle": "full|card|compact",
    "showTimeline": true,
    "sidebarPosition": "left|right"
  },
  "features": {
    "enableReactions": true,
    "enableKeyPoints": true,
    "enableTimeline": true,
    "autoRefresh": true,
    "refreshInterval": 30
  }
}
```

**API Operations**:
- `GET /api/live_text_templates` - List all templates (public)
- `GET /api/live_text_templates/{id}` - Get single template (public)
- `POST /api/live_text_templates` - Create template (ADMIN only)
- `PUT/PATCH /api/live_text_templates/{id}` - Update template (ADMIN only)
- `DELETE /api/live_text_templates/{id}` - Delete template (ADMIN only)

**Helper Methods**:
- `getConfigValue($path, $default)` - Get nested config value using dot notation
- `setConfigValue($path, $value)` - Set nested config value

### 3. Backend: LiveTextTemplateRepository

**File**: `src/Repository/LiveTextTemplateRepository.php`

Custom query methods:
- `findByType(TemplateType $type)` - Find templates by type
- `findSystemTemplates()` - Find system-provided templates
- `findCustomTemplates()` - Find user-created templates

### 4. Backend: Template Seeder Command

**File**: `src/Command/SeedLiveTextTemplatesCommand.php`

**Usage**:
```bash
# Seed templates (fails if already exist)
symfony console app:seed-livetext-templates

# Force reseed (removes existing)
symfony console app:seed-livetext-templates --force
```

**Predefined Templates**:

#### 1. Breaking News Template
- **Colors**: Red theme (#ef4444)
- **Layout**: Banner header, full post style
- **Features**: All enabled, 30s refresh
- **Use case**: Urgent breaking news coverage

#### 2. Sport Event Template
- **Colors**: Green theme (#10b981)
- **Layout**: Bold header, compact post style
- **Features**: All enabled, 15s refresh (fastest)
- **Use case**: Live sports with score tracking

#### 3. Conference Template
- **Colors**: Blue theme (#3b82f6)
- **Layout**: Minimal header, card post style
- **Features**: All enabled, 60s refresh
- **Use case**: Conferences and speeches

#### 4. Election Template
- **Colors**: Purple theme (#8b5cf6)
- **Layout**: Bold header, full post style
- **Features**: All enabled, 45s refresh
- **Use case**: Election coverage with results

### 5. Backend: LiveText Entity Update

**File**: `src/Entity/LiveText.php`

Added template relation:
```php
#[ORM\ManyToOne(targetEntity: LiveTextTemplate::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
#[Groups(['livetext:read', 'livetext:write', 'livetext:detail'])]
#[MaxDepth(1)]
private ?LiveTextTemplate $template = null;
```

- Nullable (existing LiveTexts don't require templates)
- ON DELETE SET NULL (template deletion doesn't cascade)
- Serialized in API responses

### 6. Backend: Database Migration

**File**: `migrations/Version20251103113738.php`

Creates:
- `live_text_templates` table
- `idx_template_type` index
- Foreign key column in `live_texts` table

### 7. Backend: Security Configuration

**File**: `config/packages/security.yaml`

Added public access:
```yaml
# Public READ access for templates
- { path: ^/api/live_text_templates, roles: PUBLIC_ACCESS, methods: [GET] }
```

### 8. Frontend: Template Types

**File**: `lib/types/livetext.ts`

New types:
- `TemplateType` - 'breaking_news' | 'sport' | 'conference' | 'election'
- `TemplateConfig` - Complete config structure with colors, layout, features
- `LiveTextTemplate` - Full template interface

Updated `LiveText` interface:
```typescript
template: LiveTextTemplate | null;
```

### 9. Frontend: API Function

**File**: `lib/api/livetext.ts`

New function:
```typescript
export async function getLiveTextTemplates(
  options: FetchOptions = {}
): Promise<any[]>
```

Fetches all available templates from `/api/live_text_templates`.

### 10. Frontend: TemplateSelector Component

**File**: `components/live-text/TemplateSelector.tsx`

**Features**:
- Fetches all available templates on mount
- Displays templates as interactive cards
- Shows template icon, name, description
- Color preview (3 main colors)
- Feature badges (reactions, timeline, auto-refresh)
- "No template" option for default styling
- Active state highlighting
- Multilanguage support (ro/en/ru)
- Dark mode support

**Usage**:
```tsx
<TemplateSelector
  selectedTemplateId={selectedId}
  onSelectTemplate={(template) => setSelectedTemplate(template)}
  locale="ro"
/>
```

### 11. Frontend: LiveTextViewer Customization

**File**: `app/[locale]/live/[slug]/components/LiveTextViewer.tsx`

**Template Integration**:
1. Extracts template configuration from LiveText
2. Uses template colors for:
   - Live status badge (primary color)
   - Key point badges (primary color)
   - Key point post borders (primary color)
   - Key point backgrounds (background color)
   - Jump-to-post highlight (primary color, box shadow)

**Default Behavior**:
- If no template: Uses default red theme
- Falls back gracefully for missing templates

**Example**:
```typescript
// Extract colors
const colors = template?.config?.colors || defaultColors;

// Apply to status badge
<span style={{ backgroundColor: colors.primary }}>
  LIVE
</span>

// Apply to key point border
<div style={{ borderLeftColor: colors.primary }}>
  ...
</div>
```

## API Endpoints

### GET `/api/live_text_templates`

Get all available templates.

**Response**: `200 OK`
```json
{
  "@context": "/api/contexts/LiveTextTemplate",
  "@type": "Collection",
  "totalItems": 4,
  "member": [
    {
      "@id": "/api/live_text_templates/1",
      "id": 1,
      "name": "Breaking News",
      "description": "For urgent breaking news coverage...",
      "type": "breaking_news",
      "config": { ... },
      "isSystem": true
    }
  ]
}
```

### GET `/api/live_text_templates/{id}`

Get single template details.

**Response**: `200 OK`
```json
{
  "@id": "/api/live_text_templates/1",
  "id": 1,
  "name": "Breaking News",
  "type": "breaking_news",
  "config": {
    "colors": {
      "primary": "#ef4444",
      "secondary": "#dc2626",
      "accent": "#b91c1c",
      "background": "#fef2f2",
      "text": "#7f1d1d"
    },
    "layout": { ... },
    "features": { ... }
  }
}
```

### POST `/api/live_text_templates` (ADMIN)

Create a new template.

**Request**:
```json
{
  "name": "Custom Template",
  "description": "My custom template",
  "type": "breaking_news",
  "config": { ... }
}
```

## Template Color Schemes

| Template | Primary | Secondary | Accent | Background | Text |
|----------|---------|-----------|--------|------------|------|
| Breaking News | #ef4444 (red-500) | #dc2626 (red-600) | #b91c1c (red-700) | #fef2f2 (red-50) | #7f1d1d (red-900) |
| Sport Event | #10b981 (green-500) | #059669 (green-600) | #047857 (green-700) | #ecfdf5 (green-50) | #064e3b (green-900) |
| Conference | #3b82f6 (blue-500) | #2563eb (blue-600) | #1d4ed8 (blue-700) | #eff6ff (blue-50) | #1e3a8a (blue-900) |
| Election | #8b5cf6 (purple-500) | #7c3aed (purple-600) | #6d28d9 (purple-700) | #faf5ff (purple-50) | #4c1d95 (purple-900) |

## User Flow

### Selecting a Template (Future: Admin Panel)

1. Admin creates/edits LiveText
2. Opens template selector in form
3. Views available templates with previews
4. Clicks desired template card
5. Template is associated with LiveText
6. Saves LiveText

### Viewing Customized LiveText

1. User visits `/live/{slug}`
2. Frontend fetches LiveText with template relation
3. LiveTextViewer extracts template colors
4. Applies colors to:
   - Status badges
   - Key point indicators
   - Borders and backgrounds
   - Highlights
5. User sees consistent themed experience

## Multilanguage Support

Template names and descriptions translated:

**Breaking News**:
- ro: "Știri de Ultimă Oră"
- en: "Breaking News"
- ru: "Срочные новости"

**Sport Event**:
- ro: "Eveniment Sportiv"
- en: "Sport Event"
- ru: "Спортивное событие"

**Conference**:
- ro: "Conferință"
- en: "Conference"
- ru: "Конференция"

**Election**:
- ro: "Alegeri"
- en: "Election"
- ru: "Выборы"

## Security & Validation

1. **Public Read Access**: Anyone can view templates (needed for frontend)
2. **Admin Write Access**: Only admins can create/edit/delete templates
3. **System Templates**: `isSystem=true` templates are protected
4. **Nullable Relation**: Templates can be deleted without affecting LiveTexts
5. **Config Validation**: JSON schema validation via Symfony
6. **Enum Validation**: Only valid template types allowed

## Database Schema

```sql
CREATE TABLE live_text_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type VARCHAR(255) NOT NULL, -- enum
    config JSON NOT NULL,
    is_system BOOLEAN DEFAULT false NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_template_type ON live_text_templates(type);

-- Added to live_texts table
ALTER TABLE live_texts ADD COLUMN template_id INTEGER;
ALTER TABLE live_texts ADD CONSTRAINT fk_template
    FOREIGN KEY (template_id) REFERENCES live_text_templates(id)
    ON DELETE SET NULL;
```

## Testing

### Backend

```bash
# Verify templates were seeded
curl http://127.0.0.1:8081/api/live_text_templates | jq '.totalItems'
# Output: 4

# Get specific template
curl http://127.0.0.1:8081/api/live_text_templates/1 | jq '{id, name, type}'
# Output: {"id": 1, "name": "Breaking News", "type": "breaking_news"}

# View colors
curl http://127.0.0.1:8081/api/live_text_templates/2 | jq '.config.colors'
```

### Frontend

Navigate to: `http://localhost:3005/ro/live/{slug}`

**Template Selector (future admin integration)**:
1. Render TemplateSelector component
2. Verify 4 templates + "No template" option load
3. Click each template - verify selection highlight
4. Check color preview circles
5. Verify feature badges
6. Test dark mode

**LiveText Viewer**:
1. Open LiveText with template assigned
2. Verify LIVE badge uses template primary color
3. Create key point post
4. Verify key point badge uses primary color
5. Verify key point border uses primary color
6. Verify background uses template background color
7. Click timeline item - verify highlight uses primary color

## Known Limitations

1. **No UI for Template Assignment**: Admin panel doesn't exist yet - templates can only be assigned via API
2. **Limited Layout Options**: `headerStyle` and `postStyle` configured but not applied
3. **No Real-time Template Changes**: Changing template requires page refresh
4. **No Template Preview**: Can't preview template on LiveText before applying
5. **No Custom Templates UI**: Users can't create custom templates via UI (API only)
6. **No Template Analytics**: Can't see which templates are most popular

## Performance Considerations

1. **Eager Loading**: Template loaded with LiveText to prevent N+1
2. **Pagination Disabled**: Templates endpoint has `paginationEnabled: false` (small dataset)
3. **Index on Type**: Fast filtering by template type
4. **JSON Config**: Flexible but larger payload than normalized tables
5. **Client-side Caching**: API function uses default cache strategy

## Future Enhancements

- Admin panel for template management
- Template preview before assignment
- Custom template builder (drag-and-drop colors)
- Apply layout settings (headerStyle, postStyle)
- Template analytics dashboard
- Import/export templates (JSON)
- Template duplication
- Template versioning
- A/B testing templates
- User preferences (remember favorite template)

## Files Created/Modified

### Backend

**Created**:
1. `src/Enum/TemplateType.php` (93 lines)
2. `src/Entity/LiveTextTemplate.php` (224 lines)
3. `src/Repository/LiveTextTemplateRepository.php` (58 lines)
4. `src/Command/SeedLiveTextTemplatesCommand.php` (213 lines)
5. `migrations/Version20251103113738.php` (auto-generated)

**Modified**:
6. `src/Entity/LiveText.php` - Added `template` property and methods
7. `config/packages/security.yaml` - Added public access for templates

### Frontend

**Created**:
8. `components/live-text/TemplateSelector.tsx` (198 lines)

**Modified**:
9. `lib/types/livetext.ts` - Added template types
10. `lib/api/livetext.ts` - Added `getLiveTextTemplates()` function
11. `app/[locale]/live/[slug]/components/LiveTextViewer.tsx` - Template customization

### Total Lines of Code
- **Backend**: ~588 lines
- **Frontend**: ~230 lines
- **Net Addition**: ~818 lines of production code

## Conclusion

Sprint 9 successfully implements a complete template system with:

✅ 4 predefined templates (Breaking News, Sport, Conference, Election)
✅ JSON-based configuration for colors, layout, features
✅ Template seeder command for easy setup
✅ Template selector component with preview
✅ Live customization in LiveText viewer
✅ Multilanguage support (ro/en/ru)
✅ Dark mode support
✅ Public API access for templates
✅ Admin-only template management
✅ Flexible architecture for custom templates

The template system provides a foundation for customizable Live Text experiences!

## Related Documentation

- [Sprint 7: Key Points & Timeline](./live-text-sprint7-completed.md)
- [Sprint 8: Reactions & Engagement](./live-text-sprint8-completed.md)
- [Live Text Roadmap](./live-text-roadmap.md)
