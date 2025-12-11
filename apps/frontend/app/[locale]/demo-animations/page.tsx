/**
 * Animation & UI Components Demo Page
 *
 * Visual demonstration of all premium animations and UI components.
 * This page showcases the brand-aligned design system with interactive examples.
 *
 * Access: /[locale]/demo-animations
 */

'use client';

import React, { useState } from 'react';
import {
  LoadingSpinner,
  LoadingOverlay,
  InlineLoader,
  PulseLoader,
  SkeletonCard,
  SkeletonGrid,
  ArticleCardSkeleton,
  HeroCardSkeleton,
  CompactCardSkeleton,
  HorizontalCardSkeleton,
  ListItemSkeleton,
} from '@/components/ui';

export default function AnimationsDemoPage() {
  const [showOverlay, setShowOverlay] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleLoadingDemo = () => {
    setLoading(true);
    setTimeout(() => setLoading(false), 3000);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-brand-oxford-900 text-white py-8">
        <div className="container-deschide">
          <h1 className="text-4xl font-bold animate-fade-in-up">
            Premium Animations & UI Components
          </h1>
          <p className="mt-2 text-gray-300 animate-fade-in-up stagger-1">
            Brand-aligned design system with sophisticated interactions
          </p>
        </div>
      </header>

      <main className="container-deschide py-12 space-y-16">

        {/* Loading Spinners Section */}
        <section className="animate-fade-in-up">
          <h2 className="text-3xl font-bold text-brand-oxford-900 mb-6">
            Loading Spinners
          </h2>

          <div className="bg-white rounded-xl shadow-lg p-8 space-y-8">
            {/* Size Variants */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Size Variants</h3>
              <div className="flex items-end gap-8">
                <div className="text-center">
                  <LoadingSpinner size="sm" />
                  <p className="mt-2 text-sm text-gray-600">Small</p>
                </div>
                <div className="text-center">
                  <LoadingSpinner size="md" />
                  <p className="mt-2 text-sm text-gray-600">Medium</p>
                </div>
                <div className="text-center">
                  <LoadingSpinner size="lg" />
                  <p className="mt-2 text-sm text-gray-600">Large</p>
                </div>
                <div className="text-center">
                  <LoadingSpinner size="xl" />
                  <p className="mt-2 text-sm text-gray-600">Extra Large</p>
                </div>
              </div>
            </div>

            {/* Color Variants */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Color Variants</h3>
              <div className="flex items-center gap-8">
                <div className="text-center">
                  <LoadingSpinner variant="tomato" />
                  <p className="mt-2 text-sm text-gray-600">Tomato</p>
                </div>
                <div className="text-center">
                  <LoadingSpinner variant="oxford" />
                  <p className="mt-2 text-sm text-gray-600">Oxford Blue</p>
                </div>
                <div className="text-center">
                  <LoadingSpinner variant="red" />
                  <p className="mt-2 text-sm text-gray-600">Red</p>
                </div>
              </div>
            </div>

            {/* With Text */}
            <div>
              <h3 className="text-xl font-semibold mb-4">With Text</h3>
              <LoadingSpinner size="lg" text="Loading articles..." />
            </div>

            {/* Inline Loader */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Inline Loaders</h3>
              <div className="flex gap-4">
                <button
                  disabled
                  className="btn-brand-primary opacity-70 cursor-not-allowed flex items-center gap-2"
                >
                  <InlineLoader variant="white" />
                  Saving...
                </button>
                <button
                  onClick={handleLoadingDemo}
                  disabled={loading}
                  className="btn-brand-secondary flex items-center gap-2"
                >
                  {loading && <InlineLoader variant="white" />}
                  {loading ? 'Loading...' : 'Test Loading'}
                </button>
              </div>
            </div>

            {/* Pulse Loader */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Pulse Loaders</h3>
              <div className="flex gap-8">
                <PulseLoader variant="tomato" />
                <PulseLoader variant="oxford" />
                <PulseLoader variant="red" />
              </div>
            </div>

            {/* Loading Overlay */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Loading Overlay</h3>
              <button
                onClick={() => setShowOverlay(true)}
                className="btn-brand-accent"
              >
                Show Loading Overlay
              </button>
            </div>
          </div>
        </section>

        {/* Skeleton States Section */}
        <section className="animate-fade-in-up stagger-2">
          <h2 className="text-3xl font-bold text-brand-oxford-900 mb-6">
            Skeleton Loading States
          </h2>

          <div className="space-y-8">
            {/* Article Card Skeleton */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Article Card Skeleton</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <ArticleCardSkeleton />
                <ArticleCardSkeleton showCategory={false} />
                <ArticleCardSkeleton showImage={false} />
              </div>
            </div>

            {/* Hero Card Skeleton */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Hero Card Skeleton</h3>
              <div className="max-w-4xl">
                <HeroCardSkeleton />
              </div>
            </div>

            {/* Compact Card Skeleton */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Compact Card Skeleton</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <CompactCardSkeleton />
                <CompactCardSkeleton showImage={false} />
              </div>
            </div>

            {/* Horizontal Card Skeleton */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Horizontal Card Skeleton</h3>
              <HorizontalCardSkeleton />
            </div>

            {/* List Item Skeleton */}
            <div>
              <h3 className="text-xl font-semibold mb-4">List Item Skeleton</h3>
              <div className="bg-white rounded-lg p-6">
                <ListItemSkeleton />
                <ListItemSkeleton />
                <ListItemSkeleton />
              </div>
            </div>

            {/* Skeleton Grid */}
            <div>
              <h3 className="text-xl font-semibold mb-4">Skeleton Grid (6 cards with stagger)</h3>
              <SkeletonGrid variant="article" count={6} columns={{ sm: 1, md: 2, lg: 3 }} />
            </div>
          </div>
        </section>

        {/* Animation Utilities Section */}
        <section className="animate-fade-in-up stagger-3">
          <h2 className="text-3xl font-bold text-brand-oxford-900 mb-6">
            Animation Utilities
          </h2>

          <div className="space-y-8">
            {/* Fade Animations */}
            <div className="bg-white rounded-xl shadow-lg p-8">
              <h3 className="text-xl font-semibold mb-4">Fade Animations</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="border-2 border-gray-200 rounded-lg p-6 animate-fade-in">
                  <div className="text-center">
                    <div className="w-16 h-16 bg-brand-tomato-500 rounded-full mx-auto" />
                    <p className="mt-3 font-semibold">Fade In</p>
                    <code className="text-xs text-gray-600">animate-fade-in</code>
                  </div>
                </div>
                <div className="border-2 border-gray-200 rounded-lg p-6 animate-fade-in-up">
                  <div className="text-center">
                    <div className="w-16 h-16 bg-brand-oxford-900 rounded-full mx-auto" />
                    <p className="mt-3 font-semibold">Fade In Up</p>
                    <code className="text-xs text-gray-600">animate-fade-in-up</code>
                  </div>
                </div>
                <div className="border-2 border-gray-200 rounded-lg p-6 animate-fade-in-down">
                  <div className="text-center">
                    <div className="w-16 h-16 bg-brand-red-600 rounded-full mx-auto" />
                    <p className="mt-3 font-semibold">Fade In Down</p>
                    <code className="text-xs text-gray-600">animate-fade-in-down</code>
                  </div>
                </div>
              </div>
            </div>

            {/* Hover Effects */}
            <div className="bg-white rounded-xl shadow-lg p-8">
              <h3 className="text-xl font-semibold mb-4">Hover Effects</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="border-2 border-gray-200 rounded-lg p-6 hover-lift cursor-pointer">
                  <div className="text-center">
                    <div className="w-16 h-16 bg-brand-tomato-500 rounded-full mx-auto" />
                    <p className="mt-3 font-semibold">Hover Lift</p>
                    <code className="text-xs text-gray-600">hover-lift</code>
                  </div>
                </div>
                <div className="border-2 border-gray-200 rounded-lg p-6 hover-scale cursor-pointer">
                  <div className="text-center">
                    <div className="w-16 h-16 bg-brand-oxford-900 rounded-full mx-auto" />
                    <p className="mt-3 font-semibold">Hover Scale</p>
                    <code className="text-xs text-gray-600">hover-scale</code>
                  </div>
                </div>
                <div className="border-2 border-gray-200 rounded-lg p-6 opacity-transition cursor-pointer">
                  <div className="text-center">
                    <div className="w-16 h-16 bg-brand-red-600 rounded-full mx-auto" />
                    <p className="mt-3 font-semibold">Opacity</p>
                    <code className="text-xs text-gray-600">opacity-transition</code>
                  </div>
                </div>
              </div>
            </div>

            {/* Breaking News Badge */}
            <div className="bg-white rounded-xl shadow-lg p-8">
              <h3 className="text-xl font-semibold mb-4">Breaking News Badge</h3>
              <div className="breaking-news-badge">
                Breaking News
              </div>
            </div>

            {/* Underline Animation */}
            <div className="bg-white rounded-xl shadow-lg p-8">
              <h3 className="text-xl font-semibold mb-4">Underline Animation</h3>
              <a href="#" className="underline-animate text-2xl font-bold text-brand-oxford-900">
                Hover me to see animated underline
              </a>
            </div>

            {/* Staggered Animation */}
            <div className="bg-white rounded-xl shadow-lg p-8">
              <h3 className="text-xl font-semibold mb-4">Staggered Animation (List)</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {[1, 2, 3, 4, 5, 6].map((i) => (
                  <div
                    key={i}
                    className={`border-2 border-gray-200 rounded-lg p-4 animate-fade-in-up stagger-${i}`}
                  >
                    <p className="font-semibold">Item {i} - Delay: {i * 0.1}s</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* Card Examples Section */}
        <section className="animate-fade-in-up stagger-4">
          <h2 className="text-3xl font-bold text-brand-oxford-900 mb-6">
            Interactive Card Examples
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Card with hover lift and image zoom */}
            <article className="bg-white rounded-lg overflow-hidden shadow-sm hover-lift">
              <div className="hover-image-zoom">
                <div className="w-full h-48 bg-gradient-oxford-tomato" />
              </div>
              <div className="p-4">
                <span className="inline-block px-3 py-1 bg-brand-tomato-100 text-brand-tomato-600 text-sm font-semibold rounded">
                  Category
                </span>
                <h3 className="mt-2 text-xl font-bold text-brand-oxford-900">
                  Card with Hover Lift
                </h3>
                <p className="mt-2 text-gray-600">
                  Hover over this card to see the lift animation with brand shadow.
                </p>
              </div>
            </article>

            {/* Card with gradient overlay */}
            <article className="bg-white rounded-lg overflow-hidden shadow-sm hover-lift">
              <div className="relative">
                <div className="w-full h-48 bg-gradient-tomato-red" />
                <div className="absolute inset-0 card-overlay-gradient" />
                <div className="absolute bottom-0 left-0 right-0 p-4">
                  <h3 className="text-xl font-bold text-white text-on-photo">
                    Gradient Overlay
                  </h3>
                </div>
              </div>
              <div className="p-4">
                <p className="text-gray-600">
                  Card with Oxford Blue gradient overlay for better text readability.
                </p>
              </div>
            </article>

            {/* Card with all animations */}
            <article className="bg-white rounded-lg overflow-hidden shadow-sm hover-lift">
              <div className="hover-image-zoom">
                <div className="w-full h-48 gradient-oxford-soft" />
              </div>
              <div className="p-4">
                <div className="breaking-news-badge mb-3 inline-flex">
                  Breaking
                </div>
                <h3 className="text-xl font-bold text-brand-oxford-900 underline-animate">
                  <a href="#">Full Featured Card</a>
                </h3>
                <p className="mt-2 text-gray-600">
                  Combined hover lift, image zoom, breaking badge, and underline animation.
                </p>
              </div>
            </article>
          </div>
        </section>

      </main>

      {/* Loading Overlay Demo */}
      {showOverlay && (
        <LoadingOverlay
          fullScreen
          text="Loading demonstration..."
        />
      )}

      {/* Auto-close overlay after 3 seconds */}
      {showOverlay && setTimeout(() => setShowOverlay(false), 3000)}
    </div>
  );
}
