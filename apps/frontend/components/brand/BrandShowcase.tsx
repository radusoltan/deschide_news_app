/**
 * Brand Showcase Component
 *
 * This component demonstrates the Deschide brand design system
 * Use it as a reference or testing ground for brand components
 *
 * NOT FOR PRODUCTION - Development/Testing only
 */

import React from 'react';
import { Logo, LogoWithSpacing, LogoIcon, LogoWithTagline } from './Logo';
import { Heading, HeroHeading } from '../typography/Heading';
import { Text, Meta, Quote } from '../typography/Text';

export const BrandShowcase: React.FC = () => {
  return (
    <div className="container-deschide py-12 space-y-16">
      {/* Logo Variants */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Logo Variants</h2>

        <div className="space-y-8">
          {/* Blue Logos */}
          <div className="bg-white p-8 rounded-lg">
            <p className="text-sm font-medium text-gray-600 mb-4">Blue (Oxford) - Primary</p>
            <div className="flex items-center gap-8 flex-wrap">
              <Logo variant="blue" size="sm" />
              <Logo variant="blue" size="md" />
              <Logo variant="blue" size="lg" />
              <LogoIcon variant="blue" size="md" />
            </div>
          </div>

          {/* Red Logos */}
          <div className="bg-white p-8 rounded-lg">
            <p className="text-sm font-medium text-gray-600 mb-4">Red (Tomato) - Secondary</p>
            <div className="flex items-center gap-8 flex-wrap">
              <Logo variant="red" size="sm" />
              <Logo variant="red" size="md" />
              <Logo variant="red" size="lg" />
              <LogoIcon variant="red" size="md" />
            </div>
          </div>

          {/* White Logos */}
          <div className="bg-brand-oxford p-8 rounded-lg">
            <p className="text-sm font-medium text-white mb-4">White - On Dark Backgrounds</p>
            <div className="flex items-center gap-8 flex-wrap">
              <Logo variant="white" size="sm" />
              <Logo variant="white" size="md" />
              <Logo variant="white" size="lg" />
              <LogoIcon variant="white" size="md" />
            </div>
          </div>

          {/* Logo with Spacing */}
          <div className="bg-gray-100 p-8 rounded-lg">
            <p className="text-sm font-medium text-gray-600 mb-4">With Required Clear Space</p>
            <LogoWithSpacing variant="blue" size="md" />
          </div>

          {/* Logo with Tagline */}
          <div className="bg-white p-8 rounded-lg">
            <p className="text-sm font-medium text-gray-600 mb-4">With Tagline</p>
            <LogoWithTagline variant="blue" size="md" tagline="Știri din Moldova" />
          </div>
        </div>
      </section>

      {/* Typography - Headings */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Typography - Headings</h2>

        <div className="space-y-8 bg-white p-8 rounded-lg">
          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">Hero Heading</p>
            <HeroHeading variant="oxford">Breaking News from Moldova</HeroHeading>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">H1 - Oxford Blue</p>
            <Heading level={1} variant="oxford">Latest Political Updates</Heading>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">H2 - Tomato</p>
            <Heading level={2} variant="tomato">Economic News Section</Heading>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">H3 - Default</p>
            <Heading level={3}>Featured Stories</Heading>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">H4 - Centered</p>
            <Heading level={4} align="center">Opinion & Analysis</Heading>
          </div>
        </div>
      </section>

      {/* Typography - Text */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Typography - Text</h2>

        <div className="space-y-6 bg-white p-8 rounded-lg">
          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">Body Large</p>
            <Text variant="body-lg">
              This is body large text (19px) used for introduction paragraphs and lead content.
              It provides better readability for important text sections.
            </Text>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">Body Regular</p>
            <Text variant="body">
              This is standard body text (17px) used for article content. It's optimized for
              long-form reading with comfortable line height and spacing.
            </Text>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">Body Small</p>
            <Text variant="body-sm">
              This is small body text (15px) used for secondary content, captions, and footnotes.
            </Text>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">Accent Text (Meta)</p>
            <Meta color="tomato">PUBLISHED 2 HOURS AGO</Meta>
            <span className="mx-2 text-gray-400">•</span>
            <Meta color="oxford">POLITICĂ</Meta>
          </div>

          <div>
            <p className="text-sm font-medium text-gray-600 mb-2">Quote</p>
            <Quote author="Prime Minister">
              Moldova is committed to European integration and democratic reforms.
              We believe in transparency and accountability.
            </Quote>
          </div>
        </div>
      </section>

      {/* Brand Colors */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Brand Colors</h2>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="space-y-2">
            <div className="bg-brand-oxford h-24 rounded-lg"></div>
            <p className="text-sm font-medium">Oxford Blue</p>
            <p className="text-xs text-gray-600">#112240</p>
            <p className="text-xs text-gray-500">40% usage</p>
          </div>

          <div className="space-y-2">
            <div className="bg-brand-tomato h-24 rounded-lg"></div>
            <p className="text-sm font-medium">Tomato</p>
            <p className="text-xs text-gray-600">#F05E45</p>
            <p className="text-xs text-gray-500">40-50% usage</p>
          </div>

          <div className="space-y-2">
            <div className="bg-brand-red h-24 rounded-lg"></div>
            <p className="text-sm font-medium">Red CMYK</p>
            <p className="text-xs text-gray-600">#E92628</p>
            <p className="text-xs text-gray-500">10-30% accent</p>
          </div>

          <div className="space-y-2">
            <div className="bg-brand-mindaro h-24 rounded-lg"></div>
            <p className="text-sm font-medium">Mindaro</p>
            <p className="text-xs text-gray-600">#D4FB8C</p>
            <p className="text-xs text-gray-500">Max 10%</p>
          </div>
        </div>
      </section>

      {/* Gradients */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Brand Gradients</h2>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="gradient-oxford-tomato h-32 rounded-lg flex items-center justify-center">
            <span className="text-white font-heading text-2xl">Oxford → Tomato</span>
          </div>

          <div className="gradient-tomato-red h-32 rounded-lg flex items-center justify-center">
            <span className="text-white font-heading text-2xl">Tomato → Red</span>
          </div>
        </div>
      </section>

      {/* Buttons */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Brand Buttons</h2>

        <div className="flex flex-wrap gap-4 bg-white p-8 rounded-lg">
          <button className="btn-brand-primary">Primary Button</button>
          <button className="btn-brand-secondary">Secondary Button</button>
          <button className="btn-brand-accent">Accent Button</button>
          <button className="btn-brand-outline">Outline Button</button>
        </div>
      </section>

      {/* Text on Photo */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Text on Photos</h2>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="relative h-64 bg-gradient-to-br from-gray-600 to-gray-800 rounded-lg flex items-center justify-center">
            <Heading level={2} className="text-on-photo text-white" variant="oxford">
              Standard Shadow
            </Heading>
          </div>

          <div className="relative h-64 bg-gradient-to-br from-gray-700 to-gray-900 rounded-lg flex items-center justify-center">
            <Heading level={2} className="text-on-photo-strong text-white" variant="oxford">
              Strong Shadow
            </Heading>
          </div>
        </div>
      </section>

      {/* Accent Bars */}
      <section>
        <h2 className="text-2xl font-bold mb-8 text-gray-800">Accent Bars</h2>

        <div className="space-y-4">
          <div className="accent-bar-oxford bg-white p-6 rounded-lg">
            <Heading level={3} variant="oxford">Oxford Accent Bar</Heading>
            <Text variant="body" className="mt-2">Used for primary content sections and highlights.</Text>
          </div>

          <div className="accent-bar-tomato bg-white p-6 rounded-lg">
            <Heading level={3} variant="tomato">Tomato Accent Bar</Heading>
            <Text variant="body" className="mt-2">Used for secondary content and featured items.</Text>
          </div>

          <div className="accent-bar-red bg-white p-6 rounded-lg">
            <Heading level={3} variant="default">Red Accent Bar</Heading>
            <Text variant="body" className="mt-2">Used for breaking news and urgent alerts.</Text>
          </div>
        </div>
      </section>
    </div>
  );
};

export default BrandShowcase;
