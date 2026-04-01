/**
 * Analytics API Functions
 *
 * API calls for advanced analytics features
 */

import type {
  TrackEngagementPayload,
  TrackEngagementResponse,
  HeatmapData,
  FunnelData,
  EngagementSummary,
  TopPostsResponse,
  AbTest,
  AbTestSummary,
  AbTestListResponse,
  AbTestDetailResponse,
  CreateAbTestPayload,
  AssignedVariant,
  CalculateResultsResponse,
  ClearOldDataResponse,
} from '@/lib/types/analytics';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

// ============================================================================
// Engagement Tracking API
// ============================================================================

/**
 * Track engagement event
 */
export async function trackEngagement(
  payload: TrackEngagementPayload
): Promise<TrackEngagementResponse> {
  const response = await fetch(`${API_BASE_URL}/api/analytics/track`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to track engagement');
  }

  return response.json();
}

/**
 * Track post view (simplified)
 */
export async function trackView(postId: number): Promise<void> {
  await fetch(`${API_BASE_URL}/api/analytics/view`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ post_id: postId }),
    credentials: 'include',
  });
}

/**
 * Track post read (simplified)
 */
export async function trackRead(
  postId: number,
  timeSpent: number,
  scrollDepth: number
): Promise<void> {
  await fetch(`${API_BASE_URL}/api/analytics/read`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      post_id: postId,
      time_spent: timeSpent,
      scroll_depth: scrollDepth,
    }),
    credentials: 'include',
  });
}

/**
 * Track post click (simplified)
 */
export async function trackClick(postId: number, clickedElement: string): Promise<void> {
  await fetch(`${API_BASE_URL}/api/analytics/click`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      post_id: postId,
      clicked_element: clickedElement,
    }),
    credentials: 'include',
  });
}

/**
 * Track post share (simplified)
 */
export async function trackShare(postId: number, platform: string): Promise<void> {
  await fetch(`${API_BASE_URL}/api/analytics/share`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      post_id: postId,
      platform: platform,
    }),
    credentials: 'include',
  });
}

// ============================================================================
// Heatmap and Funnel API
// ============================================================================

/**
 * Get heatmap data for LiveText
 */
export async function getHeatmapData(
  liveTextId: number,
  authToken?: string
): Promise<HeatmapData> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(
    `${API_BASE_URL}/api/analytics/live-text/${liveTextId}/heatmap`,
    {
      headers,
      credentials: 'include',
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to fetch heatmap data');
  }

  return response.json();
}

/**
 * Get funnel data for LiveText
 */
export async function getFunnelData(
  liveTextId: number,
  authToken?: string
): Promise<FunnelData> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(
    `${API_BASE_URL}/api/analytics/live-text/${liveTextId}/funnel`,
    {
      headers,
      credentials: 'include',
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to fetch funnel data');
  }

  return response.json();
}

/**
 * Get top engaged posts
 */
export async function getTopEngagedPosts(
  liveTextId: number,
  limit: number = 10,
  authToken?: string
): Promise<TopPostsResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(
    `${API_BASE_URL}/api/analytics/live-text/${liveTextId}/top-posts?limit=${limit}`,
    {
      headers,
      credentials: 'include',
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to fetch top posts');
  }

  return response.json();
}

/**
 * Get engagement summary
 */
export async function getEngagementSummary(
  liveTextId: number,
  authToken?: string
): Promise<EngagementSummary> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(
    `${API_BASE_URL}/api/analytics/live-text/${liveTextId}/summary`,
    {
      headers,
      credentials: 'include',
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to fetch engagement summary');
  }

  return response.json();
}

// ============================================================================
// A/B Testing API
// ============================================================================

/**
 * Create A/B test
 */
export async function createAbTest(
  payload: CreateAbTestPayload,
  authToken: string
): Promise<{ success: boolean; test: AbTestSummary }> {
  const response = await fetch(`${API_BASE_URL}/api/ab-tests`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    body: JSON.stringify(payload),
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to create A/B test');
  }

  return response.json();
}

/**
 * List all A/B tests
 */
export async function listAbTests(
  status?: string,
  authToken?: string
): Promise<AbTestListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const url = status
    ? `${API_BASE_URL}/api/ab-tests?status=${status}`
    : `${API_BASE_URL}/api/ab-tests`;

  const response = await fetch(url, {
    headers,
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to list A/B tests');
  }

  return response.json();
}

/**
 * Get A/B test details
 */
export async function getAbTest(
  testId: number,
  authToken?: string
): Promise<AbTestDetailResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(`${API_BASE_URL}/api/ab-tests/${testId}`, {
    headers,
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to fetch A/B test');
  }

  return response.json();
}

/**
 * Start A/B test
 */
export async function startAbTest(
  testId: number,
  authToken: string
): Promise<{ success: boolean; test: AbTestSummary }> {
  const response = await fetch(`${API_BASE_URL}/api/ab-tests/${testId}/start`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to start A/B test');
  }

  return response.json();
}

/**
 * Pause A/B test
 */
export async function pauseAbTest(
  testId: number,
  authToken: string
): Promise<{ success: boolean; test: AbTestSummary }> {
  const response = await fetch(`${API_BASE_URL}/api/ab-tests/${testId}/pause`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to pause A/B test');
  }

  return response.json();
}

/**
 * Complete A/B test
 */
export async function completeAbTest(
  testId: number,
  winnerVariant: string | null,
  authToken: string
): Promise<{ success: boolean; test: AbTestSummary }> {
  const response = await fetch(`${API_BASE_URL}/api/ab-tests/${testId}/complete`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    body: JSON.stringify({ winner_variant: winnerVariant }),
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to complete A/B test');
  }

  return response.json();
}

/**
 * Get assigned variant (public endpoint)
 */
export async function getAssignedVariant(testId: number): Promise<AssignedVariant> {
  const response = await fetch(`${API_BASE_URL}/api/ab-tests/${testId}/variant`, {
    headers: {
      'Content-Type': 'application/json',
    },
    credentials: 'include', // Important for session management
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to get assigned variant');
  }

  return response.json();
}

/**
 * Calculate A/B test results
 */
export async function calculateAbTestResults(
  testId: number,
  authToken: string
): Promise<CalculateResultsResponse> {
  const response = await fetch(`${API_BASE_URL}/api/ab-tests/${testId}/calculate`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to calculate results');
  }

  return response.json();
}

/**
 * Add LiveText to A/B test
 */
export async function addLiveTextToTest(
  testId: number,
  liveTextId: number,
  authToken: string
): Promise<{ success: boolean; test: AbTestSummary }> {
  const response = await fetch(`${API_BASE_URL}/api/ab-tests/${testId}/live-texts`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    body: JSON.stringify({ livetext_id: liveTextId }),
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to add LiveText to test');
  }

  return response.json();
}

/**
 * Remove LiveText from A/B test
 */
export async function removeLiveTextFromTest(
  testId: number,
  liveTextId: number,
  authToken: string
): Promise<{ success: boolean; test: AbTestSummary }> {
  const response = await fetch(
    `${API_BASE_URL}/api/ab-tests/${testId}/live-texts/${liveTextId}`,
    {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${authToken}`,
      },
      credentials: 'include',
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to remove LiveText from test');
  }

  return response.json();
}

/**
 * Get running tests for a LiveText
 */
export async function getTestsByLiveText(
  liveTextId: number,
  authToken?: string
): Promise<AbTestListResponse> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(
    `${API_BASE_URL}/api/ab-tests/live-text/${liveTextId}`,
    {
      headers,
      credentials: 'include',
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to fetch tests');
  }

  return response.json();
}

// ============================================================================
// Admin API
// ============================================================================

/**
 * Clear old engagement data (GDPR compliance)
 */
export async function clearOldEngagements(
  days: number,
  authToken: string
): Promise<ClearOldDataResponse> {
  const response = await fetch(
    `${API_BASE_URL}/api/analytics/clear-old?days=${days}`,
    {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${authToken}`,
      },
      credentials: 'include',
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'Failed to clear old data');
  }

  return response.json();
}
