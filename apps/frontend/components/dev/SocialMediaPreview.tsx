/**
 * Social Media Preview Component
 *
 * Development tool to preview how articles will appear on social media
 * Facebook, Twitter, LinkedIn previews
 */

'use client';

import React, { useState } from 'react';
import type { OpenGraphMeta, TwitterCardMeta } from '@/lib/seo/social-media-meta';

interface SocialMediaPreviewProps {
  ogMeta: OpenGraphMeta;
  twitterMeta: TwitterCardMeta;
}

type PreviewType = 'facebook' | 'twitter' | 'linkedin';

export default function SocialMediaPreview({ ogMeta, twitterMeta }: SocialMediaPreviewProps) {
  const [activePreview, setActivePreview] = useState<PreviewType>('facebook');

  const ogImage = ogMeta.images[0];

  return (
    <div className="bg-gray-100 p-6 rounded-lg border-2 border-dashed border-gray-300">
      <h3 className="text-lg font-bold mb-4">Social Media Preview</h3>

      {/* Tabs */}
      <div className="flex gap-2 mb-4">
        <button
          onClick={() => setActivePreview('facebook')}
          className={`px-4 py-2 rounded ${
            activePreview === 'facebook'
              ? 'bg-blue-600 text-white'
              : 'bg-surface text-primary hover:bg-surface-sunken'
          }`}
        >
          Facebook
        </button>
        <button
          onClick={() => setActivePreview('twitter')}
          className={`px-4 py-2 rounded ${
            activePreview === 'twitter'
              ? 'bg-blue-400 text-white'
              : 'bg-surface text-primary hover:bg-surface-sunken'
          }`}
        >
          Twitter
        </button>
        <button
          onClick={() => setActivePreview('linkedin')}
          className={`px-4 py-2 rounded ${
            activePreview === 'linkedin'
              ? 'bg-blue-700 text-white'
              : 'bg-surface text-primary hover:bg-surface-sunken'
          }`}
        >
          LinkedIn
        </button>
      </div>

      {/* Preview Cards */}
      <div className="bg-surface rounded-lg shadow-md overflow-hidden max-w-xl">
        {activePreview === 'facebook' && (
          <FacebookPreview
            title={ogMeta.title}
            description={ogMeta.description}
            url={ogMeta.url}
            siteName={ogMeta.siteName}
            imageUrl={ogImage?.url}
          />
        )}

        {activePreview === 'twitter' && (
          <TwitterPreview
            title={twitterMeta.title}
            description={twitterMeta.description}
            imageUrl={twitterMeta.image}
            site={twitterMeta.site}
          />
        )}

        {activePreview === 'linkedin' && (
          <LinkedInPreview
            title={ogMeta.title}
            description={ogMeta.description}
            url={ogMeta.url}
            imageUrl={ogImage?.url}
          />
        )}
      </div>

      {/* Debug Info */}
      <details className="mt-4">
        <summary className="cursor-pointer text-sm text-gray-600 hover:text-primary">
          View Raw Meta Data
        </summary>
        <div className="mt-2 p-4 bg-gray-800 text-green-400 rounded text-xs overflow-auto max-h-64">
          <h4 className="font-bold mb-2">Open Graph:</h4>
          <pre>{JSON.stringify(ogMeta, null, 2)}</pre>
          <h4 className="font-bold mb-2 mt-4">Twitter Card:</h4>
          <pre>{JSON.stringify(twitterMeta, null, 2)}</pre>
        </div>
      </details>
    </div>
  );
}

function FacebookPreview({
  title,
  description,
  url,
  siteName,
  imageUrl,
}: {
  title: string;
  description: string;
  url: string;
  siteName: string;
  imageUrl?: string;
}) {
  const domain = url ? new URL(url).hostname : 'example.com';

  return (
    <div>
      {imageUrl && (
        <div className="relative aspect-[1.91/1] bg-gray-200">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={imageUrl}
            alt={title}
            className="w-full h-full object-cover"
            onError={(e) => {
              e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1200" height="630"%3E%3Crect fill="%23ddd" width="1200" height="630"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dominant-baseline="middle"%3ENo Image%3C/text%3E%3C/svg%3E';
            }}
          />
        </div>
      )}
      <div className="p-3 border-t border-gray-200 bg-surface-sunken">
        <div className="text-xs text-secondary uppercase mb-1">{domain}</div>
        <div className="font-semibold text-primary mb-1 line-clamp-2">{title}</div>
        <div className="text-sm text-gray-600 line-clamp-1">{description}</div>
      </div>
    </div>
  );
}

function TwitterPreview({
  title,
  description,
  imageUrl,
  site,
}: {
  title: string;
  description: string;
  imageUrl?: string;
  site?: string;
}) {
  return (
    <div className="border border-gray-300 rounded-2xl overflow-hidden">
      {imageUrl && (
        <div className="relative aspect-[2/1] bg-gray-200">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={imageUrl}
            alt={title}
            className="w-full h-full object-cover"
            onError={(e) => {
              e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1200" height="600"%3E%3Crect fill="%23ddd" width="1200" height="600"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dominant-baseline="middle"%3ENo Image%3C/text%3E%3C/svg%3E';
            }}
          />
        </div>
      )}
      <div className="p-3">
        <div className="font-semibold text-primary mb-1 line-clamp-1">{title}</div>
        <div className="text-sm text-gray-600 line-clamp-2 mb-1">{description}</div>
        {site && <div className="text-xs text-secondary">{site}</div>}
      </div>
    </div>
  );
}

function LinkedInPreview({
  title,
  description,
  url,
  imageUrl,
}: {
  title: string;
  description: string;
  url: string;
  imageUrl?: string;
}) {
  const domain = url ? new URL(url).hostname : 'example.com';

  return (
    <div className="border border-gray-300">
      {imageUrl && (
        <div className="relative aspect-[1.91/1] bg-gray-200">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={imageUrl}
            alt={title}
            className="w-full h-full object-cover"
            onError={(e) => {
              e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1200" height="627"%3E%3Crect fill="%23ddd" width="1200" height="627"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dominant-baseline="middle"%3ENo Image%3C/text%3E%3C/svg%3E';
            }}
          />
        </div>
      )}
      <div className="p-3 bg-surface">
        <div className="font-semibold text-primary mb-1 line-clamp-2">{title}</div>
        <div className="text-xs text-secondary mb-2">{domain}</div>
      </div>
    </div>
  );
}
