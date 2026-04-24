/**
 * Sport Types for LiveText
 *
 * Types for sport-specific features: matches, events, statistics
 */

export type SportType =
  | 'football'
  | 'basketball'
  | 'tennis'
  | 'handball'
  | 'volleyball'
  | 'rugby'
  | 'hockey'
  | 'other';

export type MatchStatus =
  | 'not_started'
  | 'live'
  | 'half_time'
  | 'finished'
  | 'postponed'
  | 'cancelled';

export type TeamSide = 'home' | 'away';

export type MatchEventType =
  | 'goal'
  | 'penalty_goal'
  | 'own_goal'
  | 'missed_penalty'
  | 'yellow_card'
  | 'red_card'
  | 'second_yellow_card'
  | 'substitution'
  | 'penalty_saved'
  | 'var_check'
  | 'var_goal_cancelled'
  | 'var_penalty'
  | 'injury'
  | 'injury_time'
  | 'kick_off'
  | 'half_time'
  | 'full_time'
  | 'corner'
  | 'free_kick'
  | 'offside'
  | 'other';

export type MatchEventIcon =
  | 'goal'
  | 'own-goal'
  | 'missed-penalty'
  | 'yellow-card'
  | 'red-card'
  | 'substitution'
  | 'var'
  | 'injury'
  | 'event';

export interface MatchStatistics {
  possession?: {
    home: number;
    away: number;
  };
  shots?: {
    home: number;
    away: number;
  };
  shotsOnTarget?: {
    home: number;
    away: number;
  };
  corners?: {
    home: number;
    away: number;
  };
  fouls?: {
    home: number;
    away: number;
  };
  offsides?: {
    home: number;
    away: number;
  };
  passes?: {
    home: number;
    away: number;
  };
  passAccuracy?: {
    home: number;
    away: number;
  };
  [key: string]: { home: number; away: number } | undefined;
}

export interface SportMatch {
  id: number;
  liveText: {
    id: number;
    title: string;
    slug: string;
  };
  sportType: SportType;
  homeTeam: string;
  awayTeam: string;
  homeTeamLogo?: string;
  awayTeamLogo?: string;
  homeScore: number;
  awayScore: number;
  status: MatchStatus;
  currentMinute?: number;
  currentPeriod?: string;
  venue?: string;
  competition?: string;
  scheduledStartTime?: string;
  actualStartTime?: string;
  endTime?: string;
  statistics?: MatchStatistics;
  events?: MatchEvent[];
  createdAt: string;
  updatedAt: string;
}

export interface MatchEvent {
  id: number;
  eventType: MatchEventType;
  eventIcon: MatchEventIcon;
  team: TeamSide;
  playerName?: string;
  secondPlayerName?: string;
  eventMinute: number;
  extraTimeMinute?: number;
  formattedMinute: string;
  scoreAfterEvent?: string;
  description?: string;
  metadata?: Record<string, unknown>;
  createdAt: string;
}

export interface MatchSummary {
  match_id: number;
  home_team: string;
  away_team: string;
  score: {
    home: number;
    away: number;
  };
  status: MatchStatus;
  current_minute?: number;
  statistics: {
    home: {
      goals: number;
      yellow_cards: number;
      red_cards: number;
    };
    away: {
      goals: number;
      yellow_cards: number;
      red_cards: number;
    };
  };
  latest_events: Array<{
    id: number;
    type: MatchEventType;
    icon: MatchEventIcon;
    team: TeamSide;
    player?: string;
    minute: string;
  }>;
}

export interface UpdateScoreRequest {
  home_score: number;
  away_score: number;
}

export interface UpdateStatusRequest {
  status: MatchStatus;
}

export interface AddEventRequest {
  eventType: MatchEventType;
  team: TeamSide;
  eventMinute: number;
  extraTimeMinute?: number;
  playerName?: string;
  secondPlayerName?: string;
  description?: string;
  metadata?: Record<string, unknown>;
}

export interface UpdateMinuteRequest {
  minute: number;
}

export interface UpdateStatisticsRequest {
  statistics: MatchStatistics;
}

/**
 * Mercure sport event types
 */
export interface SportScoreUpdateEvent {
  type: 'sport.score.updated';
  liveTextId: number;
  data: {
    match_id: number;
    home_team: string;
    away_team: string;
    home_score: number;
    away_score: number;
    status: MatchStatus;
    current_minute?: number;
  };
  timestamp: string;
}

export interface SportMatchStatusChangedEvent {
  type: 'sport.match.status_changed';
  liveTextId: number;
  data: {
    match_id: number;
    old_status: MatchStatus;
    new_status: MatchStatus;
    home_team: string;
    away_team: string;
    score: {
      home: number;
      away: number;
    };
  };
  timestamp: string;
}

export interface SportMatchEventEvent {
  type: 'sport.match.event';
  liveTextId: number;
  data: {
    match_id: number;
    event_id: number;
    event_type: MatchEventType;
    event_icon: MatchEventIcon;
    team: TeamSide;
    player_name?: string;
    second_player_name?: string;
    minute: string;
    score_after?: string;
    description?: string;
    current_score: {
      home: number;
      away: number;
    };
  };
  timestamp: string;
}

export interface SportMinuteUpdateEvent {
  type: 'sport.minute.updated';
  liveTextId: number;
  data: {
    match_id: number;
    current_minute: number;
    status: MatchStatus;
  };
  timestamp: string;
}

export interface SportStatisticsUpdateEvent {
  type: 'sport.statistics.updated';
  liveTextId: number;
  data: {
    match_id: number;
    statistics: MatchStatistics;
  };
  timestamp: string;
}

export type SportMercureEvent =
  | SportScoreUpdateEvent
  | SportMatchStatusChangedEvent
  | SportMatchEventEvent
  | SportMinuteUpdateEvent
  | SportStatisticsUpdateEvent;
