import { useEffect, useRef, useState } from 'react';

interface LockInfo {
  locked: boolean;
  lockedBy?: {
    id: number;
    firstName: string;
    lastName: string;
    email: string;
  };
  lockedAt?: string;
  expiresAt?: string;
  isLockedByCurrentUser?: boolean;
}

interface UseArticleLockOptions {
  articleId: number;
  autoAcquire?: boolean;
  heartbeatInterval?: number; // milliseconds
  onLockAcquired?: () => void;
  onLockFailed?: (error: Error) => void;
  onLockLost?: () => void;
}

export function useArticleLock({
  articleId,
  autoAcquire = true,
  heartbeatInterval = 60000, // 1 minute
  onLockAcquired,
  onLockFailed,
  onLockLost,
}: UseArticleLockOptions) {
  const [lockInfo, setLockInfo] = useState<LockInfo | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  const heartbeatIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const hasLockRef = useRef(false);

  // Check if article is locked
  const checkLock = async () => {
    try {
      const response = await fetch(`/api/articles/${articleId}/lock/check`);
      if (!response.ok) {
        throw new Error('Failed to check lock status');
      }
      const data = await response.json();
      setLockInfo(data);
      return data;
    } catch (err) {
      console.error('Failed to check lock:', err);
      setError(err as Error);
      return null;
    }
  };

  // Acquire lock
  const acquireLock = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/articles/${articleId}/lock`, {
        method: 'POST',
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ message: 'Failed to acquire lock' }));
        console.error('Lock acquisition failed:', {
          status: response.status,
          statusText: response.statusText,
          errorData,
        });
        throw new Error(errorData.message || errorData.error || errorData['hydra:description'] || 'Failed to acquire lock');
      }

      const lockData = await response.json();
      hasLockRef.current = true;

      // Set lock info as acquired by current user
      setLockInfo({
        locked: true,
        isLockedByCurrentUser: true,
        lockedAt: lockData.lockedAt,
        expiresAt: lockData.expiresAt,
      });

      // Start heartbeat
      startHeartbeat();

      if (onLockAcquired) {
        onLockAcquired();
      }

      return true;
    } catch (err) {
      console.error('Failed to acquire lock:', err);
      setError(err as Error);
      if (onLockFailed) {
        onLockFailed(err as Error);
      }
      return false;
    } finally {
      setIsLoading(false);
    }
  };

  // Release lock
  const releaseLock = async () => {
    if (!hasLockRef.current) return;

    try {
      stopHeartbeat();

      await fetch(`/api/articles/${articleId}/lock`, {
        method: 'DELETE',
      });

      hasLockRef.current = false;
      setLockInfo(null);
    } catch (err) {
      console.error('Failed to release lock:', err);
    }
  };

  // Send heartbeat to keep lock alive
  const sendHeartbeat = async () => {
    if (!hasLockRef.current) return;

    try {
      const response = await fetch(`/api/articles/${articleId}/lock/heartbeat`, {
        method: 'POST',
      });

      if (!response.ok) {
        // Lock might have been lost
        stopHeartbeat();
        hasLockRef.current = false;
        setLockInfo(null);
        if (onLockLost) {
          onLockLost();
        }
      }
    } catch (err) {
      console.error('Heartbeat failed:', err);
    }
  };

  // Start heartbeat interval
  const startHeartbeat = () => {
    stopHeartbeat(); // Clear any existing interval
    heartbeatIntervalRef.current = setInterval(sendHeartbeat, heartbeatInterval);
  };

  // Stop heartbeat interval
  const stopHeartbeat = () => {
    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current);
      heartbeatIntervalRef.current = null;
    }
  };

  // Auto-acquire lock on mount if enabled
  useEffect(() => {
    if (autoAcquire) {
      acquireLock();
    } else {
      checkLock();
    }

    // Cleanup on unmount
    return () => {
      stopHeartbeat();
      if (hasLockRef.current) {
        releaseLock();
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [articleId, autoAcquire]);

  // Handle page unload (release lock)
  useEffect(() => {
    const handleBeforeUnload = () => {
      if (hasLockRef.current) {
        // Use sendBeacon for reliable cleanup on page unload
        const url = `/api/articles/${articleId}/lock`;
        navigator.sendBeacon(url, new Blob([JSON.stringify({})], { type: 'application/json' }));
      }
    };

    window.addEventListener('beforeunload', handleBeforeUnload);

    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, [articleId]);

  return {
    lockInfo,
    isLoading,
    error,
    isLocked: lockInfo?.locked ?? false,
    isLockedByCurrentUser: lockInfo?.isLockedByCurrentUser ?? false,
    isLockedByOther: lockInfo?.locked && !lockInfo?.isLockedByCurrentUser,
    acquireLock,
    releaseLock,
    checkLock,
  };
}
