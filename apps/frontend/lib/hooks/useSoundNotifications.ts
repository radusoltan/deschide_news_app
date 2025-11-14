'use client';

import { useEffect, useState } from 'react';
import { getSoundManager, type NotificationSoundType } from '@/lib/utils/soundNotifications';

/**
 * Custom hook for managing sound notifications
 *
 * @example
 * ```tsx
 * const { isEnabled, toggle, volume, setVolume, play } = useSoundNotifications();
 *
 * <button onClick={toggle}>
 *   {isEnabled ? 'Mute' : 'Unmute'}
 * </button>
 * ```
 */
export function useSoundNotifications() {
  const soundManager = getSoundManager();

  const [isEnabled, setIsEnabled] = useState<boolean>(soundManager.isEnabled());
  const [volume, setVolumeState] = useState<number>(soundManager.getVolume());

  // Sync with localStorage changes (in case of multi-tab)
  useEffect(() => {
    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'livetext_sound_config') {
        setIsEnabled(soundManager.isEnabled());
        setVolumeState(soundManager.getVolume());
      }
    };

    window.addEventListener('storage', handleStorageChange);
    return () => window.removeEventListener('storage', handleStorageChange);
  }, [soundManager]);

  /**
   * Toggle sound notifications on/off
   */
  const toggle = (): boolean => {
    const newState = soundManager.toggle();
    setIsEnabled(newState);
    return newState;
  };

  /**
   * Enable sound notifications
   */
  const enable = (): void => {
    soundManager.enable();
    setIsEnabled(true);
  };

  /**
   * Disable sound notifications
   */
  const disable = (): void => {
    soundManager.disable();
    setIsEnabled(false);
  };

  /**
   * Set volume (0.0 to 1.0)
   */
  const setVolume = (newVolume: number): void => {
    soundManager.setVolume(newVolume);
    setVolumeState(newVolume);
  };

  /**
   * Play notification sound
   */
  const play = (type: NotificationSoundType = 'normal'): void => {
    soundManager.play(type);
  };

  /**
   * Test sound
   */
  const test = (type: NotificationSoundType = 'normal'): void => {
    soundManager.test(type);
  };

  return {
    isEnabled,
    volume,
    toggle,
    enable,
    disable,
    setVolume,
    play,
    test
  };
}
