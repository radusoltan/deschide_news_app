# Short Links Admin Panel

Complete admin interface for managing short links with comprehensive analytics.

## Features

- ✅ **List Management**: View, search, and paginate short links
- ✅ **Create Links**: Generate short links with optional custom codes
- ✅ **Statistics**: Detailed analytics with charts and visualizations
- ✅ **Copy to Clipboard**: Quick copy functionality for short URLs
- ✅ **Delete**: Safe deletion with confirmation modal
- ✅ **Dark Mode**: Full dark mode support
- ✅ **Responsive**: Mobile-friendly design
- ✅ **Romanian**: Complete Romanian localization

## Pages

### 1. List Page
**URL**: `/ro/admin/short-links`
- Displays all short links in a table
- Shows total clicks, average clicks, and total links
- Pagination support (30 items per page)
- Actions: View stats, Delete

### 2. Create Page
**URL**: `/ro/admin/short-links/new`
- Form to create new short link
- Fields: Original URL (required), Custom Code (optional), Title (optional)
- Shows success message with generated link
- Copy to clipboard functionality

### 3. Statistics Page
**URL**: `/ro/admin/short-links/[id]/stats`
- Key metrics: Total clicks, 30-day clicks, sources, countries
- Charts:
  - Clicks over time (area chart)
  - Device types (pie chart)
  - Top referrers (bar chart)
  - Top countries (table with percentages)

## Components

### ShortLinksTable
Displays short links in a responsive table with:
- Code, Title, Original URL, Short URL
- Click count badge
- Copy to clipboard button
- Delete action

### CreateShortLinkForm
Form for creating new short links:
- URL validation
- Custom code pattern validation
- Success screen with copy functionality
- Error handling

### DeleteShortLinkModal
Confirmation modal for safe deletion:
- Warning message
- Loading state
- Auto-refresh on success

### Chart Components
- **ClicksOverTimeChart**: 30-day trend visualization
- **DeviceTypesChart**: Device distribution pie chart
- **TopReferrersChart**: Top 10 traffic sources bar chart
- **TopCountriesTable**: Top 15 countries with percentages

## API Integration

All components use the `lib/api/short-links.ts` API client:

```typescript
import { getShortLinks, createShortLink, deleteShortLink, getShortLinkStats } from '@/lib/api/short-links';
```

## Navigation

Menu item added to admin sidebar:
- Icon: Link icon
- Label: "Linkuri Scurte"
- Position: Between "Important Articles" and "Archive"

## Development

### Running the dev server
```bash
cd /var/www/deschide_news_app/apps/frontend
pnpm dev
```

### Access URLs
- List: http://localhost:3005/ro/admin/short-links
- Create: http://localhost:3005/ro/admin/short-links/new
- Stats: http://localhost:3005/ro/admin/short-links/1/stats (example)

### Backend Requirements
Ensure the Symfony backend is running:
```bash
cd /var/www/deschide_news_app/apps/backend
symfony serve -d --port=8081
```

## Testing

### Manual Testing Checklist
1. Navigate to short links list page
2. Click "Creează Link Scurt" button
3. Fill in original URL and submit
4. Copy generated short URL
5. Return to list and verify new link appears
6. Click "Statistici" to view charts
7. Test delete functionality
8. Test dark mode toggle
9. Test responsive design on mobile

### API Testing
Test backend endpoints:
```bash
# List short links
curl -H "Authorization: Bearer YOUR_TOKEN" http://127.0.0.1:8081/api/short_links

# Create short link
curl -X POST http://127.0.0.1:8081/api/short_links \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"originalUrl": "https://deschide.md/ro/test", "code": "test123"}'

# Get statistics
curl -H "Authorization: Bearer YOUR_TOKEN" http://127.0.0.1:8081/api/short_links/1/stats
```

## Styling

All components use Tailwind CSS with:
- Flowbite React components (Button, Badge, Modal, etc.)
- Dark mode variants (`dark:` prefix)
- Responsive breakpoints (`md:`, `lg:`)
- Consistent color scheme (blue primary, green success, red danger)

## Files Structure

```
short-links/
├── page.tsx                              # Main list page
├── new/
│   └── page.tsx                          # Create page
├── [id]/
│   └── stats/
│       ├── page.tsx                      # Statistics page
│       └── components/
│           ├── ClicksOverTimeChart.tsx
│           ├── DeviceTypesChart.tsx
│           ├── TopReferrersChart.tsx
│           └── TopCountriesTable.tsx
└── components/
    ├── ShortLinksTable.tsx
    ├── CreateShortLinkForm.tsx
    └── DeleteShortLinkModal.tsx
```

## Dependencies

- `recharts` - Charts library
- `flowbite-react` - UI components
- `next` - Framework
- `react` - UI library

All dependencies are already installed in the project.

## Notes

- All text is in Romanian
- Uses server components for data fetching
- Client components for interactive features
- Authentication via JWT tokens from session
- Error boundaries for graceful failures
- Loading states with spinners
- Empty states with helpful messages

---

**Status**: ✅ Complete and ready for testing
