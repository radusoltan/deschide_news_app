/**
 * Sound Notifications Utility
 *
 * Handles audio notifications for LiveText posts
 */

export type NotificationSoundType = 'normal' | 'keypoint' | 'breaking';

/**
 * Sound notification configuration
 */
interface SoundConfig {
  volume: number; // 0.0 to 1.0
  enabled: boolean;
}

/**
 * Default sound configuration
 */
const DEFAULT_CONFIG: SoundConfig = {
  volume: 0.5,
  enabled: false // Disabled by default, user must opt-in
};

/**
 * Sound notification manager class
 */
class SoundNotificationManager {
  private config: SoundConfig;
  private audioContext: AudioContext | null = null;
  private sounds: Map<NotificationSoundType, AudioBuffer | null> = new Map();

  // Sound URLs - using data URIs for simple beep sounds
  // In production, replace with actual audio files
  private soundUrls: Record<NotificationSoundType, string> = {
    normal: '/sounds/notification-normal.mp3',
    keypoint: '/sounds/notification-keypoint.mp3',
    breaking: '/sounds/notification-breaking.mp3'
  };

  constructor() {
    this.config = this.loadConfig();

    if (typeof window !== 'undefined') {
      this.initializeAudioContext();
    }
  }

  /**
   * Initialize Web Audio API context
   */
  private initializeAudioContext(): void {
    try {
      const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
      this.audioContext = new AudioContextClass();
    } catch (error) {
      console.error('Failed to initialize AudioContext:', error);
    }
  }

  /**
   * Load configuration from localStorage
   */
  private loadConfig(): SoundConfig {
    if (typeof window === 'undefined') {
      return DEFAULT_CONFIG;
    }

    try {
      const stored = localStorage.getItem('livetext_sound_config');
      if (stored) {
        return { ...DEFAULT_CONFIG, ...JSON.parse(stored) };
      }
    } catch (error) {
      console.error('Failed to load sound config:', error);
    }

    return DEFAULT_CONFIG;
  }

  /**
   * Save configuration to localStorage
   */
  private saveConfig(): void {
    if (typeof window === 'undefined') return;

    try {
      localStorage.setItem('livetext_sound_config', JSON.stringify(this.config));
    } catch (error) {
      console.error('Failed to save sound config:', error);
    }
  }

  /**
   * Generate simple beep sound using Web Audio API
   * Fallback if audio files are not available
   */
  private generateBeep(frequency: number, duration: number): void {
    if (!this.audioContext) return;

    const oscillator = this.audioContext.createOscillator();
    const gainNode = this.audioContext.createGain();

    oscillator.connect(gainNode);
    gainNode.connect(this.audioContext.destination);

    oscillator.frequency.value = frequency;
    oscillator.type = 'sine';

    gainNode.gain.setValueAtTime(this.config.volume, this.audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + duration);

    oscillator.start(this.audioContext.currentTime);
    oscillator.stop(this.audioContext.currentTime + duration);
  }

  /**
   * Play notification sound
   */
  public async play(type: NotificationSoundType = 'normal'): Promise<void> {
    if (!this.config.enabled) {
      return;
    }

    // Resume audio context if suspended (required by some browsers)
    if (this.audioContext && this.audioContext.state === 'suspended') {
      await this.audioContext.resume();
    }

    // Try to play audio file first
    try {
      const audio = new Audio(this.soundUrls[type]);
      audio.volume = this.config.volume;
      await audio.play();
    } catch (error) {
      // Fallback to generated beep
      console.warn('Failed to play audio file, using beep fallback:', error);
      this.playBeepFallback(type);
    }
  }

  /**
   * Play beep fallback sound
   */
  private playBeepFallback(type: NotificationSoundType): void {
    const frequencies: Record<NotificationSoundType, number> = {
      normal: 440,    // A4 note
      keypoint: 880,  // A5 note (higher pitch)
      breaking: 660   // E5 note (urgent)
    };

    const durations: Record<NotificationSoundType, number> = {
      normal: 0.15,
      keypoint: 0.25,
      breaking: 0.3
    };

    this.generateBeep(frequencies[type], durations[type]);

    // For breaking/keypoint, play double beep
    if (type === 'breaking' || type === 'keypoint') {
      setTimeout(() => {
        this.generateBeep(frequencies[type], durations[type]);
      }, 200);
    }
  }

  /**
   * Enable sound notifications
   */
  public enable(): void {
    this.config.enabled = true;
    this.saveConfig();
  }

  /**
   * Disable sound notifications
   */
  public disable(): void {
    this.config.enabled = false;
    this.saveConfig();
  }

  /**
   * Toggle sound notifications
   */
  public toggle(): boolean {
    this.config.enabled = !this.config.enabled;
    this.saveConfig();
    return this.config.enabled;
  }

  /**
   * Check if sound notifications are enabled
   */
  public isEnabled(): boolean {
    return this.config.enabled;
  }

  /**
   * Set volume (0.0 to 1.0)
   */
  public setVolume(volume: number): void {
    this.config.volume = Math.max(0, Math.min(1, volume));
    this.saveConfig();
  }

  /**
   * Get current volume
   */
  public getVolume(): number {
    return this.config.volume;
  }

  /**
   * Test sound
   */
  public test(type: NotificationSoundType = 'normal'): void {
    const wasEnabled = this.config.enabled;
    this.config.enabled = true;
    this.play(type);
    this.config.enabled = wasEnabled;
  }
}

// Singleton instance
let soundManager: SoundNotificationManager | null = null;

/**
 * Get sound notification manager instance
 */
export function getSoundManager(): SoundNotificationManager {
  if (!soundManager) {
    soundManager = new SoundNotificationManager();
  }
  return soundManager;
}

/**
 * Play notification sound (convenience function)
 */
export function playNotificationSound(type: NotificationSoundType = 'normal'): void {
  getSoundManager().play(type);
}

/**
 * Check if sound notifications are enabled
 */
export function isSoundEnabled(): boolean {
  return getSoundManager().isEnabled();
}

/**
 * Toggle sound notifications
 */
export function toggleSound(): boolean {
  return getSoundManager().toggle();
}
