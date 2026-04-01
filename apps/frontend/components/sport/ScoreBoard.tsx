'use client';

import { type SportMatch, type MatchStatus } from '@/lib/types/sport';
import Image from 'next/image';

interface ScoreBoardProps {
  match: SportMatch;
  showDetails?: boolean;
}

/**
 * ScoreBoard component for displaying match score
 *
 * Features:
 * - Team names and logos
 * - Current score
 * - Match status badge
 * - Current minute (for live matches)
 * - Competition info
 */
export function ScoreBoard({ match, showDetails = true }: ScoreBoardProps) {
  const getStatusBadge = (status: MatchStatus) => {
    switch (status) {
      case 'live':
        return (
          <span className="px-3 py-1 text-xs font-bold text-white bg-red-600 rounded-full uppercase animate-pulse">
            <span className="inline-block w-2 h-2 mr-1 bg-surface rounded-full animate-ping"></span>
            LIVE
          </span>
        );
      case 'finished':
        return (
          <span className="px-3 py-1 text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gray-200 dark:bg-gray-700 rounded-full uppercase">
            Full Time
          </span>
        );
      case 'half_time':
        return (
          <span className="px-3 py-1 text-xs font-semibold text-orange-600 bg-orange-100 dark:bg-orange-900/30 rounded-full uppercase">
            Half Time
          </span>
        );
      case 'not_started':
        return (
          <span className="px-3 py-1 text-xs font-semibold text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 rounded-full uppercase">
            Upcoming
          </span>
        );
      case 'postponed':
        return (
          <span className="px-3 py-1 text-xs font-semibold text-yellow-600 bg-yellow-100 dark:bg-yellow-900/30 rounded-full uppercase">
            Postponed
          </span>
        );
      case 'cancelled':
        return (
          <span className="px-3 py-1 text-xs font-semibold text-red-600 bg-red-100 dark:bg-red-900/30 rounded-full uppercase">
            Cancelled
          </span>
        );
      default:
        return null;
    }
  };

  const getStatusColor = (status: MatchStatus) => {
    switch (status) {
      case 'live':
        return 'border-red-500 bg-red-50 dark:bg-red-900/10';
      case 'finished':
        return 'border-gray-300 dark:border-gray-600';
      case 'half_time':
        return 'border-orange-400 bg-orange-50 dark:bg-orange-900/10';
      default:
        return 'border-gray-300 dark:border-gray-600';
    }
  };

  return (
    <div className={`relative border-2 rounded-lg p-6 ${getStatusColor(match.status)}`}>
      {/* Status Badge */}
      <div className="absolute top-4 right-4">
        {getStatusBadge(match.status)}
      </div>

      {/* Competition Info */}
      {showDetails && match.competition && (
        <div className="mb-4">
          <p className="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
            {match.competition}
          </p>
          {match.venue && (
            <p className="text-xs text-secondary dark:text-secondary">
              {match.venue}
            </p>
          )}
        </div>
      )}

      {/* Score Display */}
      <div className="flex items-center justify-between gap-8">
        {/* Home Team */}
        <div className="flex-1 flex flex-col items-center space-y-3">
          {match.homeTeamLogo && (
            <div className="relative w-16 h-16 sm:w-20 sm:h-20">
              <Image
                src={match.homeTeamLogo}
                alt={`${match.homeTeam} logo`}
                fill
                className="object-contain"
              />
            </div>
          )}
          <p className="text-center text-lg sm:text-xl font-bold text-primary dark:text-gray-100">
            {match.homeTeam}
          </p>
        </div>

        {/* Score */}
        <div className="flex flex-col items-center space-y-2">
          <div className="flex items-center gap-4">
            <span className="text-4xl sm:text-5xl font-bold text-primary dark:text-gray-100">
              {match.homeScore}
            </span>
            <span className="text-3xl sm:text-4xl font-bold text-gray-400 dark:text-gray-600">
              -
            </span>
            <span className="text-4xl sm:text-5xl font-bold text-primary dark:text-gray-100">
              {match.awayScore}
            </span>
          </div>
          {/* Current Minute */}
          {match.status === 'live' && match.currentMinute !== null && (
            <div className="px-3 py-1 bg-gray-900 dark:bg-gray-700 text-white text-sm font-semibold rounded">
              {match.currentMinute}&apos;
            </div>
          )}
        </div>

        {/* Away Team */}
        <div className="flex-1 flex flex-col items-center space-y-3">
          {match.awayTeamLogo && (
            <div className="relative w-16 h-16 sm:w-20 sm:h-20">
              <Image
                src={match.awayTeamLogo}
                alt={`${match.awayTeam} logo`}
                fill
                className="object-contain"
              />
            </div>
          )}
          <p className="text-center text-lg sm:text-xl font-bold text-primary dark:text-gray-100">
            {match.awayTeam}
          </p>
        </div>
      </div>

      {/* Scheduled Time (for upcoming matches) */}
      {match.status === 'not_started' && match.scheduledStartTime && (
        <div className="mt-4 text-center">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            Kick-off: {new Date(match.scheduledStartTime).toLocaleString()}
          </p>
        </div>
      )}
    </div>
  );
}
