'use client';

import { type MatchStatistics } from '@/lib/types/sport';

interface MatchStatisticsProps {
  statistics: MatchStatistics;
  homeTeam: string;
  awayTeam: string;
}

interface StatBarProps {
  label: string;
  homeValue: number;
  awayValue: number;
  homeTeam: string;
  awayTeam: string;
  isPercentage?: boolean;
}

function StatBar({ label, homeValue, awayValue, homeTeam, awayTeam, isPercentage = false }: StatBarProps) {
  const total = homeValue + awayValue;
  const homePercentage = total > 0 ? (homeValue / total) * 100 : 50;
  const awayPercentage = total > 0 ? (awayValue / total) * 100 : 50;

  return (
    <div className="space-y-2">
      {/* Values and Label */}
      <div className="flex items-center justify-between text-sm">
        <span className="font-semibold text-blue-600 dark:text-blue-400">
          {homeValue}{isPercentage ? '%' : ''}
        </span>
        <span className="text-gray-700 dark:text-gray-300 font-medium">
          {label}
        </span>
        <span className="font-semibold text-red-600 dark:text-red-400">
          {awayValue}{isPercentage ? '%' : ''}
        </span>
      </div>

      {/* Progress Bar */}
      <div className="relative h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
        <div
          className="absolute left-0 top-0 h-full bg-blue-500 transition-all duration-500"
          style={{ width: `${homePercentage}%` }}
        ></div>
        <div
          className="absolute right-0 top-0 h-full bg-red-500 transition-all duration-500"
          style={{ width: `${awayPercentage}%` }}
        ></div>
      </div>
    </div>
  );
}

/**
 * MatchStatistics component for displaying match statistics
 *
 * Features:
 * - Visual stat bars for comparison
 * - Possession, shots, fouls, etc.
 * - Color-coded by team
 * - Responsive design
 */
export function MatchStatistics({ statistics, homeTeam, awayTeam }: MatchStatisticsProps) {
  if (!statistics || Object.keys(statistics).length === 0) {
    return (
      <div className="text-center py-8 text-gray-500 dark:text-gray-400">
        No statistics available
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
        Match Statistics
      </h3>

      {/* Team Names */}
      <div className="flex items-center justify-between mb-6">
        <span className="text-sm font-bold text-blue-600 dark:text-blue-400">
          {homeTeam}
        </span>
        <span className="text-sm font-bold text-red-600 dark:text-red-400">
          {awayTeam}
        </span>
      </div>

      {/* Statistics */}
      <div className="space-y-6">
        {/* Possession */}
        {statistics.possession && (
          <StatBar
            label="Possession"
            homeValue={statistics.possession.home}
            awayValue={statistics.possession.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
            isPercentage
          />
        )}

        {/* Shots */}
        {statistics.shots && (
          <StatBar
            label="Shots"
            homeValue={statistics.shots.home}
            awayValue={statistics.shots.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
          />
        )}

        {/* Shots on Target */}
        {statistics.shotsOnTarget && (
          <StatBar
            label="Shots on Target"
            homeValue={statistics.shotsOnTarget.home}
            awayValue={statistics.shotsOnTarget.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
          />
        )}

        {/* Corners */}
        {statistics.corners && (
          <StatBar
            label="Corners"
            homeValue={statistics.corners.home}
            awayValue={statistics.corners.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
          />
        )}

        {/* Fouls */}
        {statistics.fouls && (
          <StatBar
            label="Fouls"
            homeValue={statistics.fouls.home}
            awayValue={statistics.fouls.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
          />
        )}

        {/* Offsides */}
        {statistics.offsides && (
          <StatBar
            label="Offsides"
            homeValue={statistics.offsides.home}
            awayValue={statistics.offsides.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
          />
        )}

        {/* Passes */}
        {statistics.passes && (
          <StatBar
            label="Passes"
            homeValue={statistics.passes.home}
            awayValue={statistics.passes.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
          />
        )}

        {/* Pass Accuracy */}
        {statistics.passAccuracy && (
          <StatBar
            label="Pass Accuracy"
            homeValue={statistics.passAccuracy.home}
            awayValue={statistics.passAccuracy.away}
            homeTeam={homeTeam}
            awayTeam={awayTeam}
            isPercentage
          />
        )}
      </div>
    </div>
  );
}
