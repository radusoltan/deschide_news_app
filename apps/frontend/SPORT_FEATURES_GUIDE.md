# Sport-Specific Features Guide

This guide explains the sport-specific features implementation for LiveText, including match tracking, score updates, event timeline, and live statistics.

## Overview

The sport features enable real-time tracking of sport events (football, basketball, tennis, etc.) with:
- **Live Score Tracking** - Real-time score updates via Mercure
- **Match Events Timeline** - Goals, cards, substitutions, VAR decisions
- **Match Statistics** - Possession, shots, fouls, pass accuracy
- **Match Status Management** - not_started, live, half_time, finished
- **Real-Time Updates** - Mercure integration for instant updates

---

## Backend Implementation

### Entities

#### `LiveTextSportMatch`
**Location**: `src/Entity/LiveTextSportMatch.php`

Tracks sport match details:
- Teams (home/away with logos)
- Live score
- Match status
- Current minute/period
- Venue and competition
- Statistics (JSON)
- Events collection

**Key Properties**:
```php
$sportType: 'football'|'basketball'|'tennis'|'handball'|'volleyball'|'rugby'|'hockey'|'other'
$status: 'not_started'|'live'|'half_time'|'finished'|'postponed'|'cancelled'
$homeScore, $awayScore: int
$currentMinute: ?int
$statistics: ?array (JSON)
```

#### `LiveTextMatchEvent`
**Location**: `src/Entity/LiveTextMatchEvent.php`

Tracks individual match events:
- Event type (goal, card, substitution, etc.)
- Team (home/away)
- Player names
- Minute (with extra time)
- Score after event
- Event icon for frontend

**Event Types**:
- Goals: `goal`, `penalty_goal`, `own_goal`, `missed_penalty`
- Cards: `yellow_card`, `red_card`, `second_yellow_card`
- Other: `substitution`, `var_check`, `var_goal_cancelled`, `var_penalty`, `injury`, `corner`, `offside`, etc.

### Repositories

#### `LiveTextSportMatchRepository`
**Location**: `src/Repository/LiveTextSportMatchRepository.php`

Queries:
- `findLiveMatches()` - All matches with status='live'
- `findBySportType(string $sportType)` - Filter by sport
- `findByCompetition(string $competition)` - Filter by competition
- `findUpcomingMatches(int $limit = 10)` - Upcoming matches ordered by scheduled time

#### `LiveTextMatchEventRepository`
**Location**: `src/Repository/LiveTextMatchEventRepository.php`

Queries:
- `findByMatch(LiveTextSportMatch $match)` - All events for a match
- `findByType(LiveTextSportMatch $match, string $type)` - Filter by event type
- `getGoalCountByTeam($match, $team)` - Count goals
- `getCardCountByTeam($match, $team, $cardType)` - Count cards
- `findLatestEvents($match, int $limit = 5)` - Recent events

### Service

#### `LiveTextSportService`
**Location**: `src/Service/LiveTextSportService.php`

Business logic methods:
- `updateScore($match, $homeScore, $awayScore)` - Update match score
- `updateMatchStatus($match, $status)` - Change match status
- `addMatchEvent($match, array $eventData)` - Add event (goal, card, etc.)
- `updateCurrentMinute($match, $minute)` - Update live minute
- `updateStatistics($match, array $stats)` - Update match statistics
- `getMatchTimeline($match)` - Get all events timeline
- `getMatchSummary($match)` - Get match summary with stats
- `getLiveMatches()` - Get all live matches
- `getUpcomingMatches($limit)` - Get upcoming matches

**All methods publish Mercure events automatically.**

### Controller

#### `LiveTextSportController`
**Location**: `src/Controller/LiveTextSportController.php`

API Endpoints:

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| PUT | `/api/sport_matches/{id}/score` | Update score | Editor |
| PUT | `/api/sport_matches/{id}/status` | Update status | Editor |
| POST | `/api/sport_matches/{id}/events` | Add event | Editor |
| PUT | `/api/sport_matches/{id}/minute` | Update minute | Editor |
| PUT | `/api/sport_matches/{id}/statistics` | Update stats | Editor |
| GET | `/api/sport_matches/{id}/timeline` | Get events | Public |
| GET | `/api/sport_matches/{id}/summary` | Get summary | Public |
| GET | `/api/sport_matches/live` | Get live matches | Public |
| GET | `/api/sport_matches/upcoming` | Get upcoming | Public |

### Mercure Events

#### Published Events (via `LiveTextNotificationService`):

**1. Score Update**
```json
{
  "type": "sport.score.updated",
  "liveTextId": 123,
  "data": {
    "match_id": 1,
    "home_team": "Team A",
    "away_team": "Team B",
    "home_score": 2,
    "away_score": 1,
    "status": "live",
    "current_minute": 67
  }
}
```

**2. Match Status Changed**
```json
{
  "type": "sport.match.status_changed",
  "liveTextId": 123,
  "data": {
    "match_id": 1,
    "old_status": "not_started",
    "new_status": "live",
    "score": {"home": 0, "away": 0}
  }
}
```

**3. Match Event (Goal, Card, etc.)**
```json
{
  "type": "sport.match.event",
  "liveTextId": 123,
  "data": {
    "match_id": 1,
    "event_id": 10,
    "event_type": "goal",
    "event_icon": "goal",
    "team": "home",
    "player_name": "John Doe",
    "minute": "45+2",
    "score_after": "2-1",
    "current_score": {"home": 2, "away": 1}
  }
}
```

**4. Minute Update**
```json
{
  "type": "sport.minute.updated",
  "liveTextId": 123,
  "data": {
    "match_id": 1,
    "current_minute": 75,
    "status": "live"
  }
}
```

**5. Statistics Update**
```json
{
  "type": "sport.statistics.updated",
  "liveTextId": 123,
  "data": {
    "match_id": 1,
    "statistics": {
      "possession": {"home": 55, "away": 45},
      "shots": {"home": 12, "away": 8}
    }
  }
}
```

---

## Frontend Implementation

### Types

**Location**: `lib/types/sport.ts`

TypeScript types for sport features:
- `SportType` - Sport types (football, basketball, etc.)
- `MatchStatus` - Match statuses
- `MatchEventType` - Event types
- `SportMatch` - Match data structure
- `MatchEvent` - Event data structure
- `MatchStatistics` - Statistics structure
- `SportMercureEvent` - Mercure event types

### API Functions

**Location**: `lib/api/sport.ts`

Functions for API calls:
- `getSportMatch(matchId)` - Get match by ID
- `getLiveMatches()` - Get all live matches
- `getUpcomingMatches(limit)` - Get upcoming matches
- `updateMatchScore(matchId, data, token)` - Update score (auth required)
- `updateMatchStatus(matchId, data, token)` - Update status (auth required)
- `addMatchEvent(matchId, data, token)` - Add event (auth required)
- `updateCurrentMinute(matchId, data, token)` - Update minute (auth required)
- `updateMatchStatistics(matchId, data, token)` - Update stats (auth required)
- `getMatchTimeline(matchId)` - Get events timeline
- `getMatchSummary(matchId)` - Get match summary

### Components

#### **ScoreBoard**
**Location**: `components/sport/ScoreBoard.tsx`

Displays match score and status.

**Features**:
- Team names and logos
- Current score (large font)
- Status badge (LIVE, Finished, etc.) with animation
- Current minute for live matches
- Competition and venue info
- Scheduled time for upcoming matches

**Props**:
```typescript
interface ScoreBoardProps {
  match: SportMatch;
  showDetails?: boolean; // Show competition/venue
}
```

**Usage**:
```tsx
import { ScoreBoard } from '@/components/sport/ScoreBoard';

<ScoreBoard match={sportMatch} showDetails={true} />
```

#### **MatchTimeline**
**Location**: `components/sport/MatchTimeline.tsx`

Displays chronological event timeline.

**Features**:
- Vertical timeline with events
- Event icons (goal ⚽, cards 🟨🟥, substitution 🔄)
- Color-coded by team (blue for home, red for away)
- Player names
- Event minute with extra time (e.g., "45+2'")
- Score after each event
- Event descriptions

**Props**:
```typescript
interface MatchTimelineProps {
  events: MatchEvent[];
  homeTeam: string;
  awayTeam: string;
}
```

**Usage**:
```tsx
import { MatchTimeline } from '@/components/sport/MatchTimeline';

<MatchTimeline
  events={matchEvents}
  homeTeam="Team A"
  awayTeam="Team B"
/>
```

#### **MatchStatistics**
**Location**: `components/sport/MatchStatistics.tsx`

Displays match statistics with visual bars.

**Features**:
- Visual stat bars for comparison
- Possession, shots, fouls, corners, etc.
- Color-coded by team
- Percentage and absolute values
- Responsive design

**Props**:
```typescript
interface MatchStatisticsProps {
  statistics: MatchStatistics;
  homeTeam: string;
  awayTeam: string;
}
```

**Usage**:
```tsx
import { MatchStatistics } from '@/components/sport/MatchStatistics';

<MatchStatistics
  statistics={match.statistics}
  homeTeam="Team A"
  awayTeam="Team B"
/>
```

---

## Usage Examples

### Example 1: Display Sport Match in LiveText

```tsx
// app/[locale]/live/[slug]/page.tsx
import { ScoreBoard } from '@/components/sport/ScoreBoard';
import { MatchTimeline } from '@/components/sport/MatchTimeline';
import { MatchStatistics } from '@/components/sport/MatchStatistics';

export default function LiveTextPage({ liveText }) {
  // Check if this LiveText has a sport match
  if (liveText.sportMatch) {
    return (
      <div className="space-y-8">
        {/* Score Board */}
        <ScoreBoard match={liveText.sportMatch} showDetails={true} />

        {/* Match Timeline */}
        {liveText.sportMatch.events && (
          <MatchTimeline
            events={liveText.sportMatch.events}
            homeTeam={liveText.sportMatch.homeTeam}
            awayTeam={liveText.sportMatch.awayTeam}
          />
        )}

        {/* Match Statistics */}
        {liveText.sportMatch.statistics && (
          <MatchStatistics
            statistics={liveText.sportMatch.statistics}
            homeTeam={liveText.sportMatch.homeTeam}
            awayTeam={liveText.sportMatch.awayTeam}
          />
        )}

        {/* Regular LiveText Posts */}
        <LiveTextPostsList posts={liveText.posts} />
      </div>
    );
  }

  // Regular LiveText without sport match
  return <LiveTextPostsList posts={liveText.posts} />;
}
```

### Example 2: Real-Time Score Updates

```tsx
// Hook for handling sport Mercure events
import { useMercureSubscription } from '@/lib/hooks/useMercureSubscription';

function LiveSportMatch({ liveTextId, initialMatch }) {
  const [match, setMatch] = useState(initialMatch);

  // Subscribe to Mercure events
  const { lastEvent } = useMercureSubscription(liveTextId);

  useEffect(() => {
    if (!lastEvent) return;

    // Handle score update
    if (lastEvent.type === 'sport.score.updated') {
      setMatch(prev => ({
        ...prev,
        homeScore: lastEvent.data.home_score,
        awayScore: lastEvent.data.away_score,
        currentMinute: lastEvent.data.current_minute,
        status: lastEvent.data.status
      }));
    }

    // Handle new event
    if (lastEvent.type === 'sport.match.event') {
      setMatch(prev => ({
        ...prev,
        events: [...(prev.events || []), lastEvent.data],
        homeScore: lastEvent.data.current_score.home,
        awayScore: lastEvent.data.current_score.away
      }));
    }

    // Handle status change
    if (lastEvent.type === 'sport.match.status_changed') {
      setMatch(prev => ({
        ...prev,
        status: lastEvent.data.new_status
      }));
    }
  }, [lastEvent]);

  return <ScoreBoard match={match} />;
}
```

### Example 3: Admin - Add Goal Event

```tsx
// Admin component for adding match events
async function addGoal(matchId: number, team: 'home' | 'away', playerName: string, minute: number) {
  const token = getAuthToken(); // Get JWT token

  await addMatchEvent(matchId, {
    eventType: 'goal',
    team,
    eventMinute: minute,
    playerName
  }, token);

  // Event will be broadcast via Mercure automatically
  // Frontend will receive event and update UI
}
```

---

## Database Schema

### `live_text_sport_matches` table

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| live_text_id | INT | FK to live_texts (unique) |
| sport_type | VARCHAR(50) | Sport type |
| home_team | VARCHAR(255) | Home team name |
| away_team | VARCHAR(255) | Away team name |
| home_team_logo | VARCHAR(500) | Logo URL |
| away_team_logo | VARCHAR(500) | Logo URL |
| home_score | INT | Home team score |
| away_score | INT | Away team score |
| status | VARCHAR(30) | Match status |
| current_minute | INT | Current minute |
| current_period | VARCHAR(50) | Current period/quarter |
| venue | VARCHAR(255) | Venue name |
| competition | VARCHAR(255) | Competition name |
| scheduled_start_time | DATETIME | Scheduled start |
| actual_start_time | DATETIME | Actual start |
| end_time | DATETIME | End time |
| statistics | JSON | Match statistics |
| created_at | DATETIME | Created timestamp |
| updated_at | DATETIME | Updated timestamp |

### `live_text_match_events` table

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| sport_match_id | INT | FK to live_text_sport_matches |
| event_type | VARCHAR(50) | Event type |
| team | VARCHAR(10) | Team (home/away) |
| player_name | VARCHAR(255) | Player name |
| second_player_name | VARCHAR(255) | Second player (substitution) |
| event_minute | INT | Event minute |
| extra_time_minute | INT | Extra time minute |
| score_after_event | VARCHAR(20) | Score after event |
| description | TEXT | Event description |
| metadata | JSON | Additional metadata |
| created_at | DATETIME | Created timestamp |

---

## Testing

### Backend Testing

```bash
# Create a sport match
curl -X POST http://127.0.0.1:8081/api/live_text_sport_matches \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "liveText": "/api/live_texts/1",
    "sportType": "football",
    "homeTeam": "Team A",
    "awayTeam": "Team B",
    "competition": "Premier League",
    "scheduledStartTime": "2025-11-05T20:00:00Z"
  }'

# Update score
curl -X PUT http://127.0.0.1:8081/api/sport_matches/1/score \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"home_score": 1, "away_score": 0}'

# Add goal event
curl -X POST http://127.0.0.1:8081/api/sport_matches/1/events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "eventType": "goal",
    "team": "home",
    "eventMinute": 23,
    "playerName": "John Doe"
  }'

# Get match summary
curl http://127.0.0.1:8081/api/sport_matches/1/summary

# Get live matches
curl http://127.0.0.1:8081/api/sport_matches/live
```

---

## Best Practices

### 1. **Auto-Update Score on Goals**
The service automatically updates the match score when goal events are added. No need to manually update score after adding goal event.

### 2. **Use Mercure for Real-Time**
All score/status/event updates automatically publish Mercure events. Frontend subscribes to live updates.

### 3. **Event Icons**
Use `getEventIcon()` method on `LiveTextMatchEvent` to get standardized icon identifier for frontend.

### 4. **Statistics Format**
Store statistics as JSON with standardized keys:
```json
{
  "possession": {"home": 55, "away": 45},
  "shots": {"home": 12, "away": 8},
  "shotsOnTarget": {"home": 5, "away": 3},
  "corners": {"home": 6, "away": 4}
}
```

### 5. **Status Flow**
Typical match status flow:
1. `not_started` → User creates match
2. `live` → Match starts (sets actualStartTime)
3. `half_time` → Half-time break
4. `live` → Second half starts
5. `finished` → Match ends (sets endTime)

---

## Performance Considerations

1. **Eager Loading**: Repository queries use `leftJoin` + `addSelect` to prevent N+1 queries
2. **Indexing**: Database indexes on `sport_match_id`, `status`, `scheduled_start_time`
3. **Mercure**: Events published async, doesn't block request
4. **Caching**: Consider caching match summary for high-traffic matches

---

## Future Enhancements

- [ ] Auto-calculate statistics from events (shots from corners, etc.)
- [ ] Player roster management
- [ ] Formation display (4-4-2, 4-3-3, etc.)
- [ ] Video highlights integration
- [ ] Live commentary AI
- [ ] Betting odds integration
- [ ] Fantasy sports integration

---

**Last Updated**: 2025-11-03
**Status**: Production Ready ✅
