/**
 * Logo Demo Page
 *
 * Visual demonstration of all logo variants and sizes.
 * This page showcases the SVG logo implementation with different colors.
 *
 * Access: /[locale]/demo-logo
 */

'use client';

import React from 'react';
import { Logo, LogoIcon, LogoWithSpacing, LogoWithTagline } from '@/components/brand/Logo';

export default function LogoDemoPage() {
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-brand-oxford-900 text-white py-8">
        <div className="container-deschide">
          <h1 className="text-4xl font-bold">Logo Component Demo</h1>
          <p className="mt-2 text-gray-300">
            SVG logo with color variants and size options
          </p>
        </div>
      </header>

      <main className="container-deschide py-12 space-y-16">
        {/* White Variant (on dark background) */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">White Variant (Dark Background)</h2>
          <div className="bg-brand-oxford-900 p-8 rounded-lg space-y-6">
            <div className="space-y-4">
              <div className="flex items-center gap-4">
                <span className="text-white w-20 text-sm">Small:</span>
                <Logo variant="white" size="sm" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-white w-20 text-sm">Medium:</span>
                <Logo variant="white" size="md" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-white w-20 text-sm">Large:</span>
                <Logo variant="white" size="lg" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-white w-20 text-sm">Extra Large:</span>
                <Logo variant="white" size="xl" />
              </div>
            </div>
          </div>
        </section>

        {/* Blue Variant (Oxford) */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">Blue Variant (Oxford)</h2>
          <div className="bg-white border border-gray-200 p-8 rounded-lg space-y-6">
            <div className="space-y-4">
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Small:</span>
                <Logo variant="blue" size="sm" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Medium:</span>
                <Logo variant="blue" size="md" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Large:</span>
                <Logo variant="blue" size="lg" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Extra Large:</span>
                <Logo variant="blue" size="xl" />
              </div>
            </div>
          </div>
        </section>

        {/* Red Variant (Tomato) */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">Red Variant (Tomato)</h2>
          <div className="bg-white border border-gray-200 p-8 rounded-lg space-y-6">
            <div className="space-y-4">
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Small:</span>
                <Logo variant="red" size="sm" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Medium:</span>
                <Logo variant="red" size="md" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Large:</span>
                <Logo variant="red" size="lg" />
              </div>
              <div className="flex items-center gap-4">
                <span className="text-gray-700 w-20 text-sm">Extra Large:</span>
                <Logo variant="red" size="xl" />
              </div>
            </div>
          </div>
        </section>

        {/* Logo with Link */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">With Link (Hover Effect)</h2>
          <div className="bg-white border border-gray-200 p-8 rounded-lg">
            <div className="flex flex-wrap gap-8">
              <Logo variant="blue" size="md" href="/" />
              <Logo variant="red" size="md" href="/" />
              <div className="bg-brand-oxford-900 p-4 rounded">
                <Logo variant="white" size="md" href="/" />
              </div>
            </div>
          </div>
        </section>

        {/* Logo Icon */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">Logo Icon (Square Format)</h2>
          <div className="bg-white border border-gray-200 p-8 rounded-lg">
            <div className="flex flex-wrap gap-8">
              <div>
                <p className="text-sm text-gray-600 mb-2">Small</p>
                <div className="flex gap-4">
                  <LogoIcon variant="blue" size="sm" />
                  <LogoIcon variant="red" size="sm" />
                  <LogoIcon variant="white" size="sm" />
                </div>
              </div>
              <div>
                <p className="text-sm text-gray-600 mb-2">Medium</p>
                <div className="flex gap-4">
                  <LogoIcon variant="blue" size="md" />
                  <LogoIcon variant="red" size="md" />
                  <LogoIcon variant="white" size="md" />
                </div>
              </div>
              <div>
                <p className="text-sm text-gray-600 mb-2">Large</p>
                <div className="flex gap-4">
                  <LogoIcon variant="blue" size="lg" />
                  <LogoIcon variant="red" size="lg" />
                  <LogoIcon variant="white" size="lg" />
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Logo with Spacing */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">Logo with Spacing (Brandbook Rules)</h2>
          <div className="bg-white border border-gray-200 p-8 rounded-lg">
            <div className="flex flex-wrap gap-8">
              <div className="border-2 border-dashed border-gray-300">
                <LogoWithSpacing variant="blue" size="md" />
              </div>
              <div className="border-2 border-dashed border-gray-300">
                <LogoWithSpacing variant="red" size="md" />
              </div>
              <div className="bg-brand-oxford-900 border-2 border-dashed border-gray-600">
                <LogoWithSpacing variant="white" size="md" />
              </div>
            </div>
          </div>
        </section>

        {/* Logo with Tagline */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">Logo with Tagline</h2>
          <div className="bg-white border border-gray-200 p-8 rounded-lg">
            <div className="flex flex-wrap gap-8">
              <LogoWithTagline variant="blue" size="md" tagline="Știri din Moldova" />
              <LogoWithTagline variant="red" size="lg" tagline="Știri din Moldova" />
            </div>
          </div>
        </section>

        {/* Header Preview */}
        <section>
          <h2 className="text-2xl font-bold mb-6 text-gray-900">Header Preview (Actual Usage)</h2>
          <div className="bg-brand-oxford-900 p-4 rounded-lg">
            <div className="flex justify-between items-center">
              <Logo variant="white" size="md" href="/" />
              <div className="flex gap-6 text-white text-sm font-heading uppercase">
                <a href="#" className="hover:text-brand-mindaro-400 transition-colors">Home</a>
                <a href="#" className="hover:text-brand-mindaro-400 transition-colors">Politică</a>
                <a href="#" className="hover:text-brand-mindaro-400 transition-colors">Economie</a>
                <a href="#" className="hover:text-brand-mindaro-400 transition-colors">Sport</a>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}
