'use client';

import { useEffect, useState } from 'react';

interface ReadingProgressBarProps {
  /**
   * Optional CSS selector for the content element to track.
   * If not provided, tracks the entire document.
   */
  contentSelector?: string;
}

export default function ReadingProgressBar({ contentSelector }: ReadingProgressBarProps) {
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    let rafId: number | null = null;
    let ticking = false;

    const updateProgress = () => {
      const element = contentSelector
        ? document.querySelector(contentSelector)
        : document.documentElement;

      if (!element) {
        setProgress(0);
        return;
      }

      // Calculate scroll progress
      const scrollTop = contentSelector
        ? window.scrollY - (element as HTMLElement).offsetTop
        : window.scrollY;

      const scrollHeight = contentSelector
        ? (element as HTMLElement).scrollHeight
        : document.documentElement.scrollHeight;

      const clientHeight = contentSelector
        ? window.innerHeight
        : document.documentElement.clientHeight;

      // Calculate progress percentage (0 to 1)
      const totalScrollable = scrollHeight - clientHeight;
      const currentProgress = totalScrollable > 0
        ? Math.min(Math.max(scrollTop / totalScrollable, 0), 1)
        : 0;

      setProgress(currentProgress);
      ticking = false;
    };

    const requestTick = () => {
      if (!ticking) {
        rafId = requestAnimationFrame(updateProgress);
        ticking = true;
      }
    };

    const handleScroll = () => {
      requestTick();
    };

    // Initial calculation
    updateProgress();

    // Add scroll listener
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });

    return () => {
      window.removeEventListener('scroll', handleScroll);
      window.removeEventListener('resize', updateProgress);
      if (rafId) {
        cancelAnimationFrame(rafId);
      }
    };
  }, [contentSelector]);

  return (
    <div
      className="reading-progress-container"
      role="progressbar"
      aria-label="Reading progress"
      aria-valuenow={Math.round(progress * 100)}
      aria-valuemin={0}
      aria-valuemax={100}
    >
      <div
        className="reading-progress-bar"
        style={{
          transform: `scaleX(${progress})`,
        }}
      />

      <style jsx>{`
        .reading-progress-container {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          width: 100%;
          height: 4px;
          background: transparent;
          z-index: 9999;
          pointer-events: none;
        }

        .reading-progress-bar {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: linear-gradient(
            90deg,
            rgb(240, 94, 69) 0%,
            rgb(233, 38, 40) 100%
          );
          transform-origin: left center;
          transition: transform 0.1s cubic-bezier(0.33, 1, 0.68, 1);
          will-change: transform;
          box-shadow: 0 2px 8px rgba(233, 38, 40, 0.3);
        }

        /* Respect user's motion preferences */
        @media (prefers-reduced-motion: reduce) {
          .reading-progress-bar {
            transition: none;
          }
        }

        /* Add subtle glow effect on progress */
        .reading-progress-bar::after {
          content: '';
          position: absolute;
          top: 0;
          right: 0;
          width: 100px;
          height: 100%;
          background: linear-gradient(
            90deg,
            transparent 0%,
            rgba(255, 255, 255, 0.4) 100%
          );
          opacity: 0.8;
        }

        @media (prefers-reduced-motion: reduce) {
          .reading-progress-bar::after {
            display: none;
          }
        }

        /* Ensure it appears above sticky headers */
        @media (min-width: 1024px) {
          .reading-progress-container {
            z-index: 10000;
          }
        }
      `}</style>
    </div>
  );
}
