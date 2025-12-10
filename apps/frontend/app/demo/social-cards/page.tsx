/**
 * Social Media Cards Demo Page
 *
 * Showcases all social media card components in various configurations.
 * This page is for design review and testing purposes.
 */

import React from 'react';
import { BreakingCard, OpinionCard, QuoteCard } from '@/components/social';
import { Heading } from '@/components/typography';

export default function SocialCardsDemo() {
  return (
    <main className="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto">
        {/* Page Header */}
        <div className="text-center mb-12">
          <Heading level={1} variant="oxford" className="mb-4">
            Social Media Cards Showcase
          </Heading>
          <p className="text-gray-600 text-lg max-w-3xl mx-auto">
            Premium social media card components designed according to the Deschide brandbook.
            All cards are square (1:1 aspect ratio) and optimized for Instagram, Facebook, Twitter, and LinkedIn.
          </p>
        </div>

        {/* Breaking Card Section */}
        <section className="mb-16">
          <Heading level={2} variant="oxford" className="mb-6">
            Breaking News Cards
          </Heading>
          <p className="text-gray-600 mb-8">
            Breaking news cards with two layout variants: with-border (5% Oxford Blue margin) and no-border.
          </p>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* With Border */}
            <div>
              <BreakingCard
                title="Breaking: Moldova Signs Historic EU Agreement"
                image="https://images.unsplash.com/photo-1523995462485-3d171b5c8fa9?w=800&h=600&fit=crop"
                layout="with-border"
                category="Politică"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">With Border Layout</p>
            </div>

            {/* No Border */}
            <div>
              <BreakingCard
                title="Major Economic Reform Announced by Government"
                image="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=800&h=600&fit=crop"
                layout="no-border"
                category="Economie"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">No Border Layout</p>
            </div>

            {/* Without Category Badge */}
            <div>
              <BreakingCard
                title="Urgent: Emergency Session Called for Parliament"
                image="https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=800&h=600&fit=crop"
                layout="with-border"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">Without Category</p>
            </div>
          </div>
        </section>

        {/* Opinion Card Section */}
        <section className="mb-16">
          <Heading level={2} variant="tomato" className="mb-6">
            Opinion / Editorial Cards
          </Heading>
          <p className="text-gray-600 mb-8">
            Opinion cards with Tomato background, decorative shapes, and author attribution.
          </p>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Standard Opinion */}
            <div>
              <OpinionCard
                title="Why Moldova Needs Political Reform Now"
                author={{
                  name: "Ion Popescu",
                  photo: "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop",
                  title: "Political Analyst"
                }}
                badge="Opinie"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">Standard Badge</p>
            </div>

            {/* Custom Badge */}
            <div>
              <OpinionCard
                title="The Future of Energy Independence in Eastern Europe"
                author={{
                  name: "Maria Ionescu",
                  photo: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop",
                  title: "Energy Policy Expert"
                }}
                badge="Opinia Este"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">Custom Badge</p>
            </div>

            {/* Short Title */}
            <div>
              <OpinionCard
                title="Time for Change"
                author={{
                  name: "Alexandru Popa",
                  photo: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop",
                  title: "Former Minister"
                }}
                badge="Editorial"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">Short Title</p>
            </div>
          </div>
        </section>

        {/* Quote Card Section */}
        <section className="mb-16">
          <Heading level={2} variant="oxford" className="mb-6">
            Quote / Interview Cards
          </Heading>
          <p className="text-gray-600 mb-8">
            Quote cards with Oxford Blue background and decorative quote marks.
          </p>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* With Author Title */}
            <div>
              <QuoteCard
                quote="Moldova's future depends on transparency, accountability, and the rule of law in all government institutions."
                author="Maria Ionescu"
                authorTitle="Former Minister of Justice"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">With Author Title</p>
            </div>

            {/* Without Author Title */}
            <div>
              <QuoteCard
                quote="The greatest threat to democracy is not external pressure, but internal corruption and complacency."
                author="Ion Popescu"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">Without Author Title</p>
            </div>

            {/* Short Quote */}
            <div>
              <QuoteCard
                quote="Change begins with transparency."
                author="Alexandru Popa"
                authorTitle="Political Analyst"
              />
              <p className="text-sm text-gray-500 mt-2 text-center">Short Quote</p>
            </div>
          </div>
        </section>

        {/* Mixed Layout Section */}
        <section className="mb-16">
          <Heading level={2} variant="oxford" className="mb-6">
            Mixed Layout Example
          </Heading>
          <p className="text-gray-600 mb-8">
            All three card types displayed together in a grid layout.
          </p>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <BreakingCard
              title="Breaking: New Elections Scheduled for Spring"
              image="https://images.unsplash.com/photo-1495364141860-b0d03eccd065?w=800&h=600&fit=crop"
              layout="with-border"
              category="Politică"
            />

            <QuoteCard
              quote="Democracy is not a spectator sport. It requires active participation from every citizen."
              author="Maria Ionescu"
              authorTitle="Civic Leader"
            />

            <OpinionCard
              title="Building a Better Future Through Education"
              author={{
                name: "Ion Popescu",
                photo: "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop",
                title: "Education Minister"
              }}
            />
          </div>
        </section>

        {/* Design Notes */}
        <section className="bg-white rounded-lg shadow-lg p-8">
          <Heading level={2} variant="oxford" className="mb-4">
            Design Notes
          </Heading>
          <div className="prose prose-lg max-w-none">
            <h3 className="font-heading text-brand-oxford">Brand Compliance</h3>
            <ul className="text-gray-700">
              <li><strong>Typography:</strong> League Spartan Bold (headings - always UPPERCASE), Poppins (body text)</li>
              <li><strong>Colors:</strong> Oxford Blue (#112240), Tomato (#F05E45), Mindaro (#D4FB8C - max 10%)</li>
              <li><strong>Logo:</strong> White variant, size sm, bottom right placement</li>
              <li><strong>Aspect Ratio:</strong> Square (1:1) for optimal social media sharing</li>
            </ul>

            <h3 className="font-heading text-brand-oxford">Technical Features</h3>
            <ul className="text-gray-700">
              <li>Responsive design (mobile-first)</li>
              <li>Smooth hover animations (scale 1.02x)</li>
              <li>Next.js Image optimization</li>
              <li>Proper ARIA labels for accessibility</li>
              <li>GPU-accelerated CSS transforms</li>
              <li>Semantic HTML markup</li>
            </ul>

            <h3 className="font-heading text-brand-oxford">Export Sizes</h3>
            <ul className="text-gray-700">
              <li><strong>Instagram:</strong> 1080x1080px</li>
              <li><strong>Facebook:</strong> 1200x1200px</li>
              <li><strong>Twitter:</strong> 1200x1200px</li>
              <li><strong>LinkedIn:</strong> 1200x1200px</li>
            </ul>
          </div>
        </section>
      </div>
    </main>
  );
}
