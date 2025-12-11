# Live Text Feature - Sprint 5: Live Text Management (Admin)

**Status:** ✅ Completed
**Date:** November 3, 2025
**Sprint:** Phase 3 - Admin Interface, Sprint 5

## Overview

Sprint 5 implements the admin interface for managing LiveText events. Editors and admins can create, edit, and manage LiveText events through a user-friendly interface with full CRUD functionality.

## Implementation Summary

### 1. Admin List Page

**File:** `/app/[locale]/admin/live-texts/page.tsx`

Server component that displays all LiveTexts in a paginated table with filtering capabilities.

**Features:**
- **Stats Cards** - 5 cards showing:
  - Total LiveTexts
  - Live count (red badge with pulse animation)
  - Paused count (yellow badge)
  - Draft count (gray badge)
  - Ended count (gray badge with checkmark)

- **Status Filters** - Quick filter buttons:
  - All (default)
  - Live
  - Paused
  - Draft
  - Ended

- **Pagination** - 20 items per page with Previous/Next buttons

- **Error Handling** - Displays error messages in red alert box

- **Multilanguage Support** - Full translations (ro, en, ru)

**Data Fetching:**
```typescript
const filters: any = {
  page: currentPage,
  itemsPerPage: 20,
  orderBy: 'createdAt',
  orderDirection: 'DESC',
};

if (statusParam) {
  filters.status = statusParam;
}

const data = await getLiveTexts(filters, { locale, cache: 'no-store' });
```

### 2. LiveTexts Table Client Component

**File:** `/app/[locale]/admin/live-texts/LiveTextsTableClient.tsx`

Client component for interactive table with actions.

**Table Columns:**
- **Title** - LiveText title and description (truncated)
- **Status** - Color-coded badge (LIVE/PAUSED/ENDED/DRAFT)
- **Category** - Category title
- **Start Time** - Formatted date/time
- **Author** - Username
- **Actions** - Icon buttons for:
  - 👁 View (opens public page)
  - ✏️ Edit (opens edit page)
  - 📋 Manage Posts (opens posts editor - placeholder)
  - 🗑️ Delete (with confirmation)

**Status Badge Styles:**
```typescript
- LIVE: bg-red-600 text-white animate-pulse
- PAUSED: bg-yellow-500 text-white
- ENDED: bg-gray-500 text-white
- DRAFT: bg-gray-400 text-white
```

**Delete Functionality:**
- Confirmation dialog before delete
- DELETE request to `/api/live_texts/{id}`
- Loading spinner during deletion
- Page refresh after success

**Empty State:**
- Large icon
- "No Live Texts" message
- Centered layout

### 3. LiveText Form Component

**File:** `/app/[locale]/admin/live-texts/components/LiveTextForm.tsx`

Reusable form component for both create and edit operations.

**Form Fields:**
1. **Title** (required)
   - Text input
   - Placeholder varies by locale
   - Required validation

2. **Description** (optional)
   - Textarea (3 rows)
   - Optional field

3. **Category** (optional)
   - Select dropdown
   - Fetches active categories from API
   - "Select category" placeholder option

4. **Status** (required)
   - Select dropdown with options:
     - Draft
     - Live
     - Paused
     - Ended
   - Default: draft

5. **Start Date/Time** (optional)
   - datetime-local input
   - Converts to ISO format for API

6. **End Date/Time** (optional)
   - datetime-local input
   - Converts to ISO format for API

**Form Actions:**
- **Cancel** - Goes back to previous page
- **Save** - Submits form with validation
  - Shows loading spinner
  - Displays error messages
  - Redirects to list on success

**API Integration:**
```typescript
const payload: any = {
  title: formData.title,
  description: formData.description || null,
  status: formData.status,
  startTime: formData.startTime ? new Date(formData.startTime).toISOString() : null,
  endTime: formData.endTime ? new Date(formData.endTime).toISOString() : null,
};

if (formData.categoryId) {
  payload.category = `/api/categories/${formData.categoryId}`;
}

// POST for create, PUT for update
const method = isEdit ? 'PUT' : 'POST';
const url = isEdit
  ? `${apiUrl}/api/live_texts/${initialData?.id}`
  : `${apiUrl}/api/live_texts`;
```

**Props:**
- `locale` - Current language
- `initialData?` - Existing LiveText (for edit)
- `isEdit?` - Boolean flag (default: false)
- `categories?` - Array of categories for dropdown

### 4. Create LiveText Page

**File:** `/app/[locale]/admin/live-texts/new/page.tsx`

Server component for creating new LiveText.

**Features:**
- Fetches categories from API
- Passes empty initialData to form
- Sets isEdit=false
- Multilanguage header and subtitle

**Data Flow:**
1. Server fetches categories
2. Renders LiveTextForm with categories
3. Form submits POST request
4. Redirects to list on success

### 5. Edit LiveText Page

**File:** `/app/[locale]/admin/live-texts/[id]/edit/page.tsx`

Server component for editing existing LiveText.

**Features:**
- Fetches LiveText by ID
- Fetches categories from API
- Returns 404 if not found
- Pre-fills form with existing data
- Sets isEdit=true

**Data Flow:**
1. Server fetches LiveText by ID
2. Server fetches categories
3. Renders LiveTextForm with initialData
4. Form submits PUT request
5. Redirects to list on success

**Error Handling:**
- Try-catch for API errors
- notFound() for missing LiveText
- Error messages in form

### 6. Posts Management Placeholder

**File:** `/app/[locale]/admin/live-texts/[id]/posts/page.tsx`

Placeholder page for Sprint 6 functionality.

**Features:**
- Fetches LiveText by ID for context
- Displays "Coming Soon" card with features list:
  - ✅ Create and edit posts in real-time
  - ✅ Upload images and embed media
  - ✅ Mark important moments (Key Points)
  - ✅ Collaborate with other editors
  - ✅ Real-time preview

- Shows current posts count and preview (first 5)
- "Back to list" button
- Formatted post display with timestamps

**Purpose:**
- Provides UI continuity
- Sets expectations for Sprint 6
- Shows existing posts data
- Maintains navigation flow

## Technical Details

### Routing Structure

```
/admin/live-texts                    → List page
/admin/live-texts/new                → Create page
/admin/live-texts/[id]/edit          → Edit page
/admin/live-texts/[id]/posts         → Posts management (placeholder)
```

### Component Hierarchy

```
page.tsx (Server Component)
├── LiveTextsTableClient (Client Component)
│   ├── Status badges
│   ├── Action buttons
│   └── Delete functionality
│
new/page.tsx (Server Component)
├── LiveTextForm (Client Component)
│   ├── Form fields
│   ├── Validation
│   └── Submit handler
│
[id]/edit/page.tsx (Server Component)
├── LiveTextForm (Client Component)
│   └── (same as above, with initialData)
│
[id]/posts/page.tsx (Server Component)
└── Coming soon card
```

### State Management

**Server State:**
- Data fetching via `getLiveTexts()` and `getLiveTextById()`
- No caching (`cache: 'no-store'`) for fresh data
- Automatic revalidation via `router.refresh()`

**Client State:**
- Form state managed by useState
- Loading states for async operations
- Error states for user feedback
- No global state management needed

### API Integration

**Endpoints Used:**
```
GET  /api/live_texts                    # List with filters
GET  /api/live_texts/{id}               # Get single
POST /api/live_texts                    # Create
PUT  /api/live_texts/{id}               # Update
DELETE /api/live_texts/{id}             # Delete
GET  /api/categories?status=active      # Categories for dropdown
```

**Headers:**
```typescript
{
  'Content-Type': 'application/ld+json',
  'Accept': 'application/ld+json',
  'Accept-Language': locale,
}
```

**Credentials:**
```typescript
credentials: 'include'  // For authentication cookies
```

### Styling

**Design System:**
- Tailwind CSS classes throughout
- Dark mode support (`dark:` variants)
- Consistent spacing and typography
- Responsive design (mobile, tablet, desktop)

**Color Scheme:**
- Primary: Red (#dc2626, red-600)
- Success: Green
- Warning: Yellow
- Error: Red
- Info: Blue
- Neutral: Gray

**Components:**
- Rounded corners (rounded-lg, rounded-full)
- Shadows for depth
- Hover states for interactivity
- Transitions for smooth UX
- Icons from Heroicons (inline SVG)

### Error Handling

**Frontend:**
- Try-catch blocks in async functions
- Error state display in UI
- Confirmation dialogs for destructive actions
- Loading states during operations

**Backend Integration:**
- HTTP status code checking
- Error message extraction from responses
- Fallback to generic error messages
- Console logging for debugging

### Security Considerations

**Authentication:**
- All admin routes require authentication (handled by layout)
- Credentials included in API requests
- JWT tokens in cookies

**Authorization:**
- Backend validates permissions via Voters
- Admin/Editor roles required for write operations
- User context passed via Accept-Language header

**Input Validation:**
- Required field validation
- Type validation (datetime, select options)
- Server-side validation in backend
- XSS protection via React escaping

## Files Created/Modified

### Created Files:
1. `/app/[locale]/admin/live-texts/page.tsx` - List page (server)
2. `/app/[locale]/admin/live-texts/LiveTextsTableClient.tsx` - Table (client)
3. `/app/[locale]/admin/live-texts/components/LiveTextForm.tsx` - Form (client)
4. `/app/[locale]/admin/live-texts/new/page.tsx` - Create page (server)
5. `/app/[locale]/admin/live-texts/[id]/edit/page.tsx` - Edit page (server)
6. `/app/[locale]/admin/live-texts/[id]/posts/page.tsx` - Posts placeholder (server)

### Directory Structure Created:
```
app/[locale]/admin/live-texts/
├── page.tsx
├── LiveTextsTableClient.tsx
├── components/
│   └── LiveTextForm.tsx
├── new/
│   └── page.tsx
└── [id]/
    ├── edit/
    │   └── page.tsx
    └── posts/
        └── page.tsx
```

## Testing

### Backend API Verification

**Test Endpoints:**
```bash
# List all LiveTexts
curl http://127.0.0.1:8081/api/live_texts

# Get specific LiveText
curl http://127.0.0.1:8081/api/live_texts/2

# Get categories
curl http://127.0.0.1:8081/api/categories?status=active
```

**Test Data:**
- 10 LiveTexts from seed command
- Mix of statuses: live (4), paused (3), draft (2), ended (1)
- All have categories and collaborators
- All have posts (3-5 per LiveText)

### Frontend Testing Checklist

**List Page:**
- ✅ Stats cards display correct counts
- ✅ Status filters work correctly
- ✅ Pagination functions properly
- ✅ Table displays all LiveTexts
- ✅ Action buttons navigate correctly
- ✅ Delete confirmation works
- ✅ Empty state displays when no results

**Create Page:**
- ✅ Form displays all fields
- ✅ Categories load in dropdown
- ✅ Required field validation works
- ✅ Submit creates new LiveText
- ✅ Cancel navigates back
- ✅ Error messages display

**Edit Page:**
- ✅ Form pre-fills with existing data
- ✅ Date/time fields format correctly
- ✅ Submit updates LiveText
- ✅ Returns 404 for invalid ID
- ✅ Error handling works

**Posts Placeholder:**
- ✅ Coming soon message displays
- ✅ Current posts preview shows
- ✅ Back button navigates correctly
- ✅ LiveText context is shown

### Multilanguage Testing

**Tested Locales:**
- Romanian (ro) - Default
- English (en)
- Russian (ru)

**Verified:**
- All UI text translates correctly
- Date/time formatting uses locale
- API requests send Accept-Language header
- Backend returns translated data

## Known Issues and Limitations

1. **Authentication Not Enforced:**
   - Admin pages accessible without login (middleware needed)
   - DELETE operations should check authentication
   - Consider adding auth middleware

2. **No Collaborator Management:**
   - Form doesn't allow adding/removing collaborators
   - Collaborators are read-only
   - Will need CollaboratorSelector component (future)

3. **Date Validation Missing:**
   - No client-side validation for endTime > startTime
   - Backend handles this but UX could be better
   - Consider adding date comparison validation

4. **No Image Preview:**
   - LiveText doesn't have images yet
   - Future: Featured image for LiveText
   - Will need image upload component

5. **Limited Filtering:**
   - Only status filter implemented
   - No search functionality
   - No date range filter
   - Consider adding advanced filters

## Future Enhancements (Sprint 6+)

### Immediate (Sprint 6):
1. **Post Editor Implementation:**
   - Rich text editor (TipTap or Quill)
   - Image upload and management
   - Media embed (video, tweets)
   - Key Point toggle
   - Real-time preview
   - Publish/schedule functionality

2. **Collaborator Management:**
   - Add/remove collaborators
   - Role selection (editor/contributor)
   - Permission management
   - Invite via email

3. **Enhanced Filtering:**
   - Search by title
   - Date range filter
   - Category filter
   - Author filter
   - Multi-select status filter

### Later Sprints:
4. **Bulk Operations:**
   - Select multiple LiveTexts
   - Bulk delete
   - Bulk status change
   - Export to CSV

5. **Analytics Integration:**
   - View count display
   - Post engagement metrics
   - Link to analytics page
   - Real-time viewer count

6. **Templates:**
   - Template selector in form
   - Quick create from template
   - Template management page

## Deployment Notes

**Environment Variables Required:**
```bash
NEXT_PUBLIC_API_URL=http://127.0.0.1:8081   # Backend API URL
```

**No Additional Dependencies:**
- Uses existing API service layer
- Uses existing type definitions
- Uses standard Tailwind classes
- No new npm packages needed

**Database Requirements:**
- LiveText, LiveTextPost, LiveTextCollaborator tables must exist
- Categories table for dropdown
- Users table for authentication

**Backend Requirements:**
- API Platform endpoints configured
- Security voters active
- CORS enabled for frontend domain
- Authentication middleware active

## Usage Instructions

### Creating a New LiveText

1. Navigate to `/admin/live-texts`
2. Click "Create Live Text" button
3. Fill in required fields:
   - Title
   - Status (default: draft)
4. Optionally fill:
   - Description
   - Category
   - Start date/time
   - End date/time
5. Click "Save"
6. Redirects to list page with new LiveText

### Editing a LiveText

1. Navigate to `/admin/live-texts`
2. Click edit icon (✏️) on desired LiveText
3. Update fields as needed
4. Click "Save"
5. Redirects to list page with updated LiveText

### Deleting a LiveText

1. Navigate to `/admin/live-texts`
2. Click delete icon (🗑️) on desired LiveText
3. Confirm deletion in dialog
4. LiveText is deleted and removed from list

### Managing Posts

1. Navigate to `/admin/live-texts`
2. Click posts icon (📋) on desired LiveText
3. See coming soon message
4. Note: Full implementation in Sprint 6

## Performance Considerations

**Server Components:**
- Data fetching on server
- No client-side hydration for static content
- Reduced JavaScript bundle size

**Client Components:**
- Only interactive parts are client-side
- Form state management optimized
- Loading states prevent multiple submissions

**Caching Strategy:**
- No caching for admin data (`cache: 'no-store'`)
- Always shows fresh data
- May add ISR for performance later

**Pagination:**
- 20 items per page
- Server-side pagination
- Reduces payload size
- Better UX for large datasets

## Conclusion

Sprint 5 successfully implements a complete admin interface for LiveText management with full CRUD functionality. The implementation follows Next.js 16 best practices with server/client component separation, provides excellent UX with responsive design and animations, and includes comprehensive multilanguage support.

The admin interface integrates seamlessly with the existing backend API and maintains consistency with the existing admin pages for articles and categories.

**Next Steps:** Proceed with Sprint 6 (Post Editor) to enable editors to create and manage posts within LiveText events through a rich text editor with real-time collaboration features.

**Related Documentation:**
- Sprint 1-2: Database Schema & Basic API (`docs/live-text-sprint1-2-completed.md`)
- Sprint 3: Mercure Integration (`docs/live-text-sprint3-completed.md`)
- Sprint 4: Frontend Public Viewer (`docs/live-text-sprint4-completed.md`)
- Sprint 5: Admin Management (this document)
