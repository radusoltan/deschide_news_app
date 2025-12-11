# UI/UX Templates Reference

This document describes the UI templates used as design references for the Deschide News App.

## Important Note

UI template files are **NOT** included in the repository to keep it lightweight (~230MB saved).
They are listed in `.gitignore` under the `layouts/` directory.

## Referenced Templates

### 1. Flowbite Admin Dashboard

Used as reference for the **Admin Panel** design.

- **Source:** [GitHub - themesberg/flowbite-admin-dashboard](https://github.com/themesberg/flowbite-admin-dashboard)
- **License:** MIT
- **Features Used:**
  - Dashboard layout structure
  - Sidebar navigation patterns
  - Data tables design
  - Form components styling
  - Dark mode implementation

### 2. TailNews Template

Used as reference for the **Public News Portal** design.

- **Source:** Commercial template (internal reference)
- **Features Used:**
  - Article card layouts
  - Category navigation
  - Hero section designs
  - Typography patterns for news content
  - Mobile-responsive news grid

## Local Installation (Optional)

If you need these templates for reference during development:

```bash
# Create layouts directory (gitignored)
mkdir -p layouts
cd layouts

# Clone Flowbite Admin Dashboard
git clone https://github.com/themesberg/flowbite-admin-dashboard

# Other templates - obtain from appropriate sources
```

## Actual Implementation

Our production implementation uses:

| Technology | Purpose |
|------------|---------|
| **Tailwind CSS 4** | Utility-first CSS framework |
| **Shadcn/UI** | Accessible React component library |
| **Radix UI** | Headless UI primitives |
| **Custom Components** | Project-specific components |

### Component Locations

- **Admin Components:** `apps/frontend/components/admin/`
- **Public Components:** `apps/frontend/components/`
- **UI Primitives:** `apps/frontend/components/ui/`

### Design System Documentation

For detailed component documentation, see:
- `apps/frontend/docs/ui/` - UI component guides
- `apps/frontend/components/ui/` - Shadcn/UI components

## Why Templates Are Not Committed

1. **Size:** ~230MB of assets not needed in version control
2. **Reference Only:** Templates are for inspiration, not direct usage
3. **Licensing:** Keeps license compliance clear
4. **Performance:** Faster clone/pull operations
5. **CI/CD:** Smaller deployment packages

## Related Documentation

- [Design Principles](../architecture/design-principles.md) - Architectural guidelines
- [Frontend Setup](../../apps/frontend/docs/setup/) - Development environment
