/**
 * Article Image Component
 * Displays article images with CDN integration, lazy loading, and lightbox
 */

'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import { buildImageUrl } from '@/lib/api/important-articles';

interface ArticleImageProps {
  image: {
    path: string;
    width?: number;
    height?: number;
    alt?: string;
    title?: string;
    credit?: string;
  };
  priority?: boolean;
  className?: string;
  enableLightbox?: boolean;
}

/**
 * Image Lightbox Modal
 */
function ImageLightbox({
  src,
  alt,
  onClose,
}: {
  src: string;
  alt: string;
  onClose: () => void;
}) {
  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90"
      onClick={onClose}
    >
      <button
        onClick={onClose}
        className="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors"
        aria-label="Close"
      >
        <svg
          className="w-8 h-8"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M6 18L18 6M6 6l12 12"
          />
        </svg>
      </button>

      <div className="max-w-7xl max-h-screen p-4" onClick={(e) => e.stopPropagation()}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={src}
          alt={alt}
          className="max-w-full max-h-screen object-contain"
        />
      </div>
    </div>
  );
}

export default function ArticleImage({
  image,
  priority = false,
  className = '',
  enableLightbox = true,
}: ArticleImageProps) {
  const [showLightbox, setShowLightbox] = useState(false);
  const [imageError, setImageError] = useState(false);

  const imageUrl = buildImageUrl(image.path);
  const alt = image.alt || image.title || 'Article image';

  if (imageError) {
    return (
      <div className="w-full h-64 bg-gray-200 flex items-center justify-center rounded-lg">
        <div className="text-center text-gray-500">
          <svg
            className="w-12 h-12 mx-auto mb-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <p className="text-sm">Image not available</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <figure className={`mb-6 ${className}`}>
        <div
          className={`relative overflow-hidden rounded-lg ${
            enableLightbox ? 'cursor-pointer hover:opacity-95 transition-opacity' : ''
          }`}
          onClick={() => enableLightbox && setShowLightbox(true)}
        >
          <Image
            src={imageUrl}
            alt={alt}
            width={image.width || 1600}
            height={image.height || 900}
            priority={priority}
            className="max-w-full h-auto mx-auto"
            placeholder="blur"
            blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYwMCIgaGVpZ2h0PSI5MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YzZjRmNiIvPjwvc3ZnPg=="
            onError={() => setImageError(true)}
          />

          {/* Zoom indicator overlay */}
          {enableLightbox && (
            <div className="absolute inset-0 flex items-center justify-center bg-black/0 hover:bg-black/20 transition-all">
              <svg
                className="w-12 h-12 text-white opacity-0 hover:opacity-100 transition-opacity"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"
                />
              </svg>
            </div>
          )}
        </div>

        {/* Image caption and credit */}
        {(alt || image.credit) && (
          <figcaption className="text-sm text-gray-600 mt-2 px-2">
            {alt && <span className="block">{alt}</span>}
            {image.credit && (
              <span className="block text-xs text-gray-500 mt-1">
                Photo credit: {image.credit}
              </span>
            )}
          </figcaption>
        )}
      </figure>

      {/* Lightbox Modal */}
      {showLightbox && (
        <ImageLightbox
          src={imageUrl}
          alt={alt}
          onClose={() => setShowLightbox(false)}
        />
      )}
    </>
  );
}
