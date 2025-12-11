# Archive Admin Components

This directory contains React components for the admin archive management dashboard.

## Components

### ArchiveStats

A client-side component that displays archive statistics in a responsive grid of gradient cards.

**Features:**
- Real-time statistics fetching from backend API
- Auto-refresh capability with loading states
- Error handling with retry functionality
- Responsive grid layout (1 column mobile, 2 columns tablet, 4 columns desktop)
- Gradient card design matching admin dashboard pattern
- Multilingual support (Romanian, English, Russian)

**Props:**
```typescript
interface ArchiveStatsProps {
  token: string;           // JWT authentication token
  onRefresh?: () => void;  // Optional callback after refresh
}
```

**API Endpoint:**
```
GET /api/admin/archive/stats
Authorization: Bearer {token}
```

**API Response:**
```json
{
  "total_articles": 85000,
  "archived_articles": 65000,
  "archive_percentage": 76.47,
  "by_reason": {
    "old_content": 60000,
    "legal_request": 500,
    "duplicate": 1000
  },
  "archived_this_month": 150,
  "archived_this_year": 2500
}
```

**Usage:**
```tsx
import { ArchiveStats } from '@/components/admin/archive';

export default async function ArchivePage() {
  const token = await getAccessToken();

  return (
    <div>
      <ArchiveStats token={token} />
    </div>
  );
}
```

### ArchivedArticlesList

Component for displaying and managing archived articles.

### BulkArchiveForm

Component for bulk archiving operations.

## Design Pattern

All components follow the admin dashboard design pattern:
- Gradient backgrounds (from-{color}-50 to-{color}-100)
- Border colors (border-{color}-200)
- Icon backgrounds (bg-{color}-200)
- Text colors (text-{color}-700 for labels, text-{color}-900 for values)
- Rounded corners (rounded-lg)
- Shadow effects (shadow, hover:shadow-md)

## Color Scheme

| Card | Gradient | Usage |
|------|----------|-------|
| Amber | amber-50 to amber-100 | Total archived articles |
| Green | green-50 to green-100 | Monthly/yearly stats |
| Blue | blue-50 to blue-100 | Percentages and ratios |
| Purple | purple-50 to purple-100 | Categorical breakdowns |

## Testing

See `ArchiveStats.example.tsx` for usage examples and integration patterns.
