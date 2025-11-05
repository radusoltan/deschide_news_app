'use client';

import { useEffect, useRef, useCallback } from 'react';

/**
 * Options for focus management
 */
interface UseFocusManagementOptions {
  /**
   * Auto-focus on mount
   * @default false
   */
  autoFocus?: boolean;

  /**
   * Return focus to triggering element on unmount
   * @default false
   */
  returnFocus?: boolean;

  /**
   * Trap focus within container
   * @default false
   */
  trapFocus?: boolean;
}

/**
 * Result of useFocusManagement hook
 */
interface UseFocusManagementResult<T extends HTMLElement> {
  /**
   * Ref to attach to container element
   */
  containerRef: React.RefObject<T | null>;

  /**
   * Focus first focusable element
   */
  focusFirst: () => void;

  /**
   * Focus last focusable element
   */
  focusLast: () => void;

  /**
   * Focus next focusable element
   */
  focusNext: () => void;

  /**
   * Focus previous focusable element
   */
  focusPrevious: () => void;
}

/**
 * Selector for focusable elements
 */
const FOCUSABLE_SELECTOR = [
  'a[href]',
  'area[href]',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  'button:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
  'audio[controls]',
  'video[controls]',
  '[contenteditable]:not([contenteditable="false"])'
].join(', ');

/**
 * Custom hook for focus management and accessibility
 *
 * @example
 * ```tsx
 * function Modal() {
 *   const { containerRef, focusFirst } = useFocusManagement<HTMLDivElement>({
 *     autoFocus: true,
 *     trapFocus: true,
 *     returnFocus: true
 *   });
 *
 *   return (
 *     <div ref={containerRef}>
 *       <h2>Modal Title</h2>
 *       <button onClick={close}>Close</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function useFocusManagement<T extends HTMLElement = HTMLElement>(
  options: UseFocusManagementOptions = {}
): UseFocusManagementResult<T> {
  const { autoFocus = false, returnFocus = false, trapFocus = false } = options;

  const containerRef = useRef<T>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);

  /**
   * Get all focusable elements within container
   */
  const getFocusableElements = useCallback((): HTMLElement[] => {
    if (!containerRef.current) return [];

    return Array.from(
      containerRef.current.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)
    ).filter((el) => {
      // Filter out hidden elements
      return el.offsetParent !== null;
    });
  }, []);

  /**
   * Focus first focusable element
   */
  const focusFirst = useCallback(() => {
    const elements = getFocusableElements();
    if (elements.length > 0) {
      elements[0].focus();
    }
  }, [getFocusableElements]);

  /**
   * Focus last focusable element
   */
  const focusLast = useCallback(() => {
    const elements = getFocusableElements();
    if (elements.length > 0) {
      elements[elements.length - 1].focus();
    }
  }, [getFocusableElements]);

  /**
   * Focus next focusable element
   */
  const focusNext = useCallback(() => {
    const elements = getFocusableElements();
    const currentIndex = elements.indexOf(document.activeElement as HTMLElement);

    if (currentIndex < elements.length - 1) {
      elements[currentIndex + 1].focus();
    } else if (trapFocus) {
      // Wrap to first element
      elements[0]?.focus();
    }
  }, [getFocusableElements, trapFocus]);

  /**
   * Focus previous focusable element
   */
  const focusPrevious = useCallback(() => {
    const elements = getFocusableElements();
    const currentIndex = elements.indexOf(document.activeElement as HTMLElement);

    if (currentIndex > 0) {
      elements[currentIndex - 1].focus();
    } else if (trapFocus) {
      // Wrap to last element
      elements[elements.length - 1]?.focus();
    }
  }, [getFocusableElements, trapFocus]);

  /**
   * Handle Tab key for focus trap
   */
  const handleKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (!trapFocus || event.key !== 'Tab') return;

      const elements = getFocusableElements();
      if (elements.length === 0) return;

      const firstElement = elements[0];
      const lastElement = elements[elements.length - 1];

      if (event.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstElement) {
          event.preventDefault();
          lastElement.focus();
        }
      } else {
        // Tab
        if (document.activeElement === lastElement) {
          event.preventDefault();
          firstElement.focus();
        }
      }
    },
    [trapFocus, getFocusableElements]
  );

  // Auto-focus on mount
  useEffect(() => {
    if (autoFocus) {
      // Store previous focus
      previousFocusRef.current = document.activeElement as HTMLElement;

      // Focus first element after a small delay (for rendering)
      const timer = setTimeout(focusFirst, 10);
      return () => clearTimeout(timer);
    }
  }, [autoFocus, focusFirst]);

  // Return focus on unmount
  useEffect(() => {
    return () => {
      if (returnFocus && previousFocusRef.current) {
        previousFocusRef.current.focus();
      }
    };
  }, [returnFocus]);

  // Setup focus trap
  useEffect(() => {
    if (!trapFocus) return;

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [trapFocus, handleKeyDown]);

  return {
    containerRef,
    focusFirst,
    focusLast,
    focusNext,
    focusPrevious
  };
}
