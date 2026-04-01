'use client';

import { type MatchEvent, type MatchEventIcon, type TeamSide } from '@/lib/types/sport';
import {
  Circle,
  AlertCircle,
  ArrowRightLeft,
  Square,
  CheckCircle,
  Shield,
  Activity,
} from 'lucide-react';

interface MatchTimelineProps {
  events: MatchEvent[];
  homeTeam: string;
  awayTeam: string;
}

/**
 * MatchTimeline component for displaying match events
 *
 * Features:
 * - Chronological event list
 * - Event icons (goal, card, substitution, etc.)
 * - Team indicators
 * - Event descriptions
 * - Score progression
 */
export function MatchTimeline({ events, homeTeam, awayTeam }: MatchTimelineProps) {
  const getEventIcon = (icon: MatchEventIcon, team: TeamSide) => {
    const isHome = team === 'home';
    const baseClasses = "w-8 h-8 p-1.5 rounded-full";

    switch (icon) {
      case 'goal':
        return (
          <div className={`${baseClasses} ${isHome ? 'bg-blue-100 text-blue-600' : 'bg-red-100 text-red-600'}`}>
            <Circle className="w-full h-full fill-current" />
          </div>
        );
      case 'own-goal':
        return (
          <div className={`${baseClasses} bg-gray-100 text-gray-600`}>
            <Circle className="w-full h-full" />
          </div>
        );
      case 'yellow-card':
        return (
          <div className={`${baseClasses} bg-yellow-100`}>
            <div className="w-full h-full bg-yellow-500 rounded-sm"></div>
          </div>
        );
      case 'red-card':
        return (
          <div className={`${baseClasses} bg-red-100`}>
            <div className="w-full h-full bg-red-600 rounded-sm"></div>
          </div>
        );
      case 'substitution':
        return (
          <div className={`${baseClasses} bg-green-100 text-green-600`}>
            <ArrowRightLeft className="w-full h-full" />
          </div>
        );
      case 'var':
        return (
          <div className={`${baseClasses} bg-purple-100 text-purple-600`}>
            <Shield className="w-full h-full" />
          </div>
        );
      case 'injury':
        return (
          <div className={`${baseClasses} bg-orange-100 text-orange-600`}>
            <AlertCircle className="w-full h-full" />
          </div>
        );
      case 'missed-penalty':
        return (
          <div className={`${baseClasses} bg-gray-100 text-gray-600`}>
            <Circle className="w-full h-full" />
          </div>
        );
      default:
        return (
          <div className={`${baseClasses} bg-gray-100 text-gray-600`}>
            <Activity className="w-full h-full" />
          </div>
        );
    }
  };

  const getEventLabel = (event: MatchEvent) => {
    switch (event.eventType) {
      case 'goal':
      case 'penalty_goal':
        return '⚽ Goal';
      case 'own_goal':
        return '⚽ Own Goal';
      case 'missed_penalty':
        return '❌ Penalty Missed';
      case 'yellow_card':
        return '🟨 Yellow Card';
      case 'red_card':
      case 'second_yellow_card':
        return '🟥 Red Card';
      case 'substitution':
        return '🔄 Substitution';
      case 'var_check':
        return '🎥 VAR Check';
      case 'var_goal_cancelled':
        return '❌ Goal Cancelled (VAR)';
      case 'var_penalty':
        return '✅ Penalty (VAR)';
      case 'injury':
        return '🚑 Injury';
      case 'kick_off':
        return '⚽ Kick Off';
      case 'half_time':
        return '⏸️ Half Time';
      case 'full_time':
        return '⏹️ Full Time';
      default:
        return '• Event';
    }
  };

  if (events.length === 0) {
    return (
      <div className="text-center py-8 text-secondary dark:text-gray-400">
        No events yet
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h3 className="text-xl font-bold text-primary dark:text-gray-100 mb-4">
        Match Timeline
      </h3>

      <div className="relative">
        {/* Timeline line */}
        <div className="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>

        {/* Events */}
        <div className="space-y-6">
          {events.map((event) => {
            const isHome = event.team === 'home';
            const teamName = isHome ? homeTeam : awayTeam;

            return (
              <div key={event.id} className="relative flex items-start gap-4">
                {/* Minute */}
                <div className="w-12 flex-shrink-0 text-right">
                  <span className="text-sm font-bold text-primary dark:text-gray-100">
                    {event.formattedMinute}&apos;
                  </span>
                </div>

                {/* Event Icon */}
                <div className="relative z-10 flex-shrink-0">
                  {getEventIcon(event.eventIcon, event.team)}
                </div>

                {/* Event Details */}
                <div className="flex-1 pb-4">
                  <div className={`p-4 rounded-lg ${isHome ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-red-50 dark:bg-red-900/20'}`}>
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-semibold text-primary dark:text-gray-100">
                        {getEventLabel(event)}
                      </span>
                      {event.scoreAfterEvent && (
                        <span className="text-sm font-bold text-primary dark:text-primary-dark px-2 py-1 bg-surface dark:bg-surface-dark rounded">
                          {event.scoreAfterEvent}
                        </span>
                      )}
                    </div>

                    <p className="text-sm text-primary dark:text-primary-dark mb-1">
                      <span className="font-medium">{teamName}</span>
                      {event.playerName && (
                        <> - <span className="font-semibold">{event.playerName}</span></>
                      )}
                    </p>

                    {event.secondPlayerName && (
                      <p className="text-sm text-gray-600 dark:text-gray-400">
                        ↔️ {event.secondPlayerName}
                      </p>
                    )}

                    {event.description && (
                      <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
                        {event.description}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
