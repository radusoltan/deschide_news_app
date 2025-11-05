'use client';

import { Bell, BellOff, Volume2, VolumeX } from 'lucide-react';
import { useSoundNotifications } from '@/lib/hooks/useSoundNotifications';

interface NotificationSettingsProps {
  /**
   * Custom className
   */
  className?: string;
}

/**
 * Notification settings component with sound controls
 *
 * Features:
 * - Toggle sound notifications on/off
 * - Volume control slider
 * - Test sound button
 * - Visual feedback
 *
 * @example
 * ```tsx
 * <NotificationSettings />
 * ```
 */
export function NotificationSettings({ className = '' }: NotificationSettingsProps) {
  const { isEnabled, volume, toggle, setVolume, test } = useSoundNotifications();

  return (
    <div className={`bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 ${className}`}>
      <div className="space-y-4">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            {isEnabled ? (
              <Bell className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            ) : (
              <BellOff className="h-5 w-5 text-gray-400" />
            )}
            <h3 className="font-medium text-gray-900 dark:text-gray-100">
              Sound Notifications
            </h3>
          </div>

          {/* Toggle Switch */}
          <button
            onClick={toggle}
            className={`
              relative inline-flex h-6 w-11 items-center rounded-full transition-colors
              ${isEnabled ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}
            `}
            aria-label={isEnabled ? 'Disable sound notifications' : 'Enable sound notifications'}
          >
            <span
              className={`
                inline-block h-4 w-4 transform rounded-full bg-white transition-transform
                ${isEnabled ? 'translate-x-6' : 'translate-x-1'}
              `}
            />
          </button>
        </div>

        {/* Description */}
        <p className="text-sm text-gray-600 dark:text-gray-400">
          {isEnabled
            ? 'You will hear a sound when new posts are published'
            : 'Enable to get audio alerts for new posts'}
        </p>

        {/* Volume Control */}
        {isEnabled && (
          <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-600 dark:text-gray-400">Volume</span>
              <span className="font-medium text-gray-900 dark:text-gray-100">
                {Math.round(volume * 100)}%
              </span>
            </div>

            <div className="flex items-center gap-3">
              <VolumeX className="h-4 w-4 text-gray-400 flex-shrink-0" />
              <input
                type="range"
                min="0"
                max="100"
                value={volume * 100}
                onChange={(e) => setVolume(parseInt(e.target.value) / 100)}
                className="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer
                  [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4
                  [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-blue-600
                  [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4
                  [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-blue-600 [&::-moz-range-thumb]:border-0"
                aria-label="Volume control"
              />
              <Volume2 className="h-4 w-4 text-gray-600 dark:text-gray-400 flex-shrink-0" />
            </div>
          </div>
        )}

        {/* Test Buttons */}
        {isEnabled && (
          <div className="space-y-2">
            <div className="text-xs text-gray-500 dark:text-gray-500">Test sounds:</div>
            <div className="flex gap-2">
              <button
                onClick={() => test('normal')}
                className="flex-1 px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
              >
                Normal
              </button>
              <button
                onClick={() => test('keypoint')}
                className="flex-1 px-3 py-2 text-sm bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-900/30 transition-colors"
              >
                Key Point
              </button>
              <button
                onClick={() => test('breaking')}
                className="flex-1 px-3 py-2 text-sm bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-300 rounded hover:bg-red-200 dark:hover:bg-red-900/30 transition-colors"
              >
                Breaking
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

/**
 * Compact notification toggle button (for toolbar)
 */
export function NotificationToggle({ className = '' }: NotificationSettingsProps) {
  const { isEnabled, toggle } = useSoundNotifications();

  return (
    <button
      onClick={toggle}
      className={`
        p-2 rounded-lg transition-colors
        ${isEnabled
          ? 'bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400'
          : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'}
        hover:bg-opacity-80
        ${className}
      `}
      title={isEnabled ? 'Disable sound notifications' : 'Enable sound notifications'}
      aria-label={isEnabled ? 'Disable sound notifications' : 'Enable sound notifications'}
    >
      {isEnabled ? (
        <Bell className="h-5 w-5" />
      ) : (
        <BellOff className="h-5 w-5" />
      )}
    </button>
  );
}
