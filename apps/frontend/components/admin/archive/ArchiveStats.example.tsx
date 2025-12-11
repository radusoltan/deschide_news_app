/**
 * ArchiveStats Component - Usage Example
 *
 * This example shows how to integrate the ArchiveStats component
 * in an admin dashboard page.
 */

import { ArchiveStats } from './ArchiveStats';

// Example 1: Basic usage in a server component page
// File: app/[locale]/admin/archive/page.tsx

import { getAccessToken } from '@/lib/auth/session';

export default async function ArchiveDashboardPage() {
  const token = await getAccessToken();

  if (!token) {
    return <div>Please log in</div>;
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold mb-8">Archive Management</h1>

      {/* Archive Statistics */}
      <ArchiveStats token={token} />

      {/* Other archive components below */}
    </div>
  );
}

// Example 2: With refresh callback
export function ArchiveDashboardWithCallback() {
  const handleStatsRefresh = () => {
    console.log('Archive stats refreshed');
    // Optionally trigger other refreshes or analytics events
  };

  return (
    <ArchiveStats
      token="your-jwt-token"
      onRefresh={handleStatsRefresh}
    />
  );
}

// Example 3: API Response Format (for backend reference)
/*
GET /api/admin/archive/stats
Authorization: Bearer {token}

Response:
{
  "total_articles": 85000,
  "archived_articles": 65000,
  "archive_percentage": 76.47,
  "by_reason": {
    "old_content": 60000,
    "legal_request": 500,
    "duplicate": 1000,
    "quality_issues": 2500,
    "outdated": 1000
  },
  "archived_this_month": 150,
  "archived_this_year": 2500,
  "oldest_archived": {
    "id": 1,
    "title": "Very Old Article",
    "archived_at": "2020-01-15T10:30:00Z",
    "reason": "old_content"
  },
  "most_recent_archived": {
    "id": 999,
    "title": "Recently Archived",
    "archived_at": "2025-11-29T14:20:00Z",
    "reason": "duplicate"
  }
}
*/
