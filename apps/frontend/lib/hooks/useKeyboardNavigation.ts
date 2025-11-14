'use client';

import { useEffect, useCallback, RefObject } from 'react';

/**
 * Keyboard shortcuts configuration
 */
export interface KeyboardShortcut {
  key: string;
  ctrlKey?: boolean;
  shiftKey?: boolean;
  altKey?: boolean;
  metaKey?: boolean;
  handler: () => void;
  description: string;
}

/**
 * Options for keyboard navigation
 */
interface UseKeyboardNavigationOptions {
  /**
   * Enable keyboard shortcuts
   * @default true
   */
  enabled?: boolean;

  /**
   * Container element reference for scoped navigation
   */
  containerRef?: RefObject<HTMLElement>;

  /**
   * Prevent default behavior
   * @default true
   */
  preventDefault?: boolean;
}

/**
 * Custom hook for keyboard navigation and shortcuts
 *
 * @example
 * ```tsx
 * useKeyboardNavigation([
 *   {
 *     key: 'ArrowDown',
 *     handler: () => scrollToNextPost(),
 *     description: 'Navigate to next post'
 *   },
 *   {
 *     key: 'ArrowUp',
 *     handler: () => scrollToPreviousPost(),
 *     description: 'Navigate to previous post'
 *   },
 *   {
 *     key: 'm',
 *     ctrlKey: true,
 *     handler: () => toggleMute(),
 *     description: 'Toggle sound notifications'
 *   }
 * ]);
 * ```
 */
export function useKeyboardNavigation(
  shortcuts: KeyboardShortcut[],
  options: UseKeyboardNavigationOptions = {}
) {
  const { enabled = true, containerRef, preventDefault = true } = options;

  const handleKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (!enabled) return;

      // Don't trigger shortcuts when typing in input fields
      const target = event.target as HTMLElement;
      if (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.contentEditable === 'true'
      ) {
        return;
      }

      for (const shortcut of shortcuts) {
        const keyMatches = event.key.toLowerCase() === shortcut.key.toLowerCase();
        const ctrlMatches = shortcut.ctrlKey === undefined || shortcut.ctrlKey === event.ctrlKey;
        const shiftMatches = shortcut.shiftKey === undefined || shortcut.shiftKey === event.shiftKey;
        const altMatches = shortcut.altKey === undefined || shortcut.altKey === event.altKey;
        const metaMatches = shortcut.metaKey === undefined || shortcut.metaKey === event.metaKey;

        if (keyMatches && ctrlMatches && shiftMatches && altMatches && metaMatches) {
          if (preventDefault) {
            event.preventDefault();
          }
          shortcut.handler();
          break;
        }
      }
    },
    [shortcuts, enabled, preventDefault]
  );

  useEffect(() => {
    const element = containerRef?.current || document;

    element.addEventListener('keydown', handleKeyDown as EventListener);
    return () => element.removeEventListener('keydown', handleKeyDown as EventListener);
  }, [handleKeyDown, containerRef]);
}

/**
 * Get keyboard shortcut display string
 */
export function getShortcutDisplay(shortcut: KeyboardShortcut): string {
  const parts: string[] = [];

  if (shortcut.ctrlKey) parts.push('Ctrl');
  if (shortcut.shiftKey) parts.push('Shift');
  if (shortcut.altKey) parts.push('Alt');
  if (shortcut.metaKey) parts.push('Cmd');

  // Format key name
  let keyName = shortcut.key;
  if (keyName.startsWith('Arrow')) {
    keyName = keyName.replace('Arrow', '') + ' Arrow';
  }

  parts.push(keyName);

  return parts.join(' + ');
}
