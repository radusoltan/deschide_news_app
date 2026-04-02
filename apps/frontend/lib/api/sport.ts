/**
 * Sport API Functions
 *
 * API calls for sport-specific features in LiveText
 */

import type {
  SportMatch,
  MatchEvent,
  MatchSummary,
  UpdateScoreRequest,
  UpdateStatusRequest,
  AddEventRequest,
  UpdateMinuteRequest,
  UpdateStatisticsRequest,
} from '../types/sport';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * Get sport match by ID
 */
export async function getSportMatch(matchId: number): Promise<SportMatch> {
  const response = await fetch(`${API_URL}/api/live_text_sport_matches/${matchId}`, {
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch sport match: ${response.statusText}`);
  }

  return response.json();
}

/**
 * Get live matches
 */
export async function getLiveMatches(): Promise<SportMatch[]> {
  const response = await fetch(`${API_URL}/api/sport_matches/live`, {
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch live matches: ${response.statusText}`);
  }

  const data = await response.json();
  return data.matches || [];
}

/**
 * Get upcoming matches
 */
export async function getUpcomingMatches(limit: number = 10): Promise<SportMatch[]> {
  const response = await fetch(`${API_URL}/api/sport_matches/upcoming?limit=${limit}`, {
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch upcoming matches: ${response.statusText}`);
  }

  const data = await response.json();
  return data.matches || [];
}

/**
 * Update match score (requires authentication)
 */
export async function updateMatchScore(
  matchId: number,
  scoreData: UpdateScoreRequest,
  token: string
): Promise<void> {
  const response = await fetch(`${API_URL}/api/sport_matches/${matchId}/score`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(scoreData),
  });

  if (!response.ok) {
    throw new Error(`Failed to update score: ${response.statusText}`);
  }
}

/**
 * Update match status (requires authentication)
 */
export async function updateMatchStatus(
  matchId: number,
  statusData: UpdateStatusRequest,
  token: string
): Promise<void> {
  const response = await fetch(`${API_URL}/api/sport_matches/${matchId}/status`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(statusData),
  });

  if (!response.ok) {
    throw new Error(`Failed to update status: ${response.statusText}`);
  }
}

/**
 * Add match event (requires authentication)
 */
export async function addMatchEvent(
  matchId: number,
  eventData: AddEventRequest,
  token: string
): Promise<MatchEvent> {
  const response = await fetch(`${API_URL}/api/sport_matches/${matchId}/events`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(eventData),
  });

  if (!response.ok) {
    throw new Error(`Failed to add event: ${response.statusText}`);
  }

  const data = await response.json();
  return data.event;
}

/**
 * Update current minute (requires authentication)
 */
export async function updateCurrentMinute(
  matchId: number,
  minuteData: UpdateMinuteRequest,
  token: string
): Promise<void> {
  const response = await fetch(`${API_URL}/api/sport_matches/${matchId}/minute`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(minuteData),
  });

  if (!response.ok) {
    throw new Error(`Failed to update minute: ${response.statusText}`);
  }
}

/**
 * Update match statistics (requires authentication)
 */
export async function updateMatchStatistics(
  matchId: number,
  statsData: UpdateStatisticsRequest,
  token: string
): Promise<void> {
  const response = await fetch(`${API_URL}/api/sport_matches/${matchId}/statistics`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(statsData),
  });

  if (!response.ok) {
    throw new Error(`Failed to update statistics: ${response.statusText}`);
  }
}

/**
 * Get match timeline (all events)
 */
export async function getMatchTimeline(matchId: number): Promise<MatchEvent[]> {
  const response = await fetch(`${API_URL}/api/sport_matches/${matchId}/timeline`, {
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch timeline: ${response.statusText}`);
  }

  const data = await response.json();
  return data.events || [];
}

/**
 * Get match summary
 */
export async function getMatchSummary(matchId: number): Promise<MatchSummary> {
  const response = await fetch(`${API_URL}/api/sport_matches/${matchId}/summary`, {
    headers: {
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch match summary: ${response.statusText}`);
  }

  return response.json();
}
