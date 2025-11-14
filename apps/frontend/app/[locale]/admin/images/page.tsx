import type { Metadata } from 'next';
import { FiUpload } from 'react-icons/fi';
import Link from 'next/link';
import { ImageGallery } from './components/ImageGallery';
import { ImagesPagination } from './components/ImagesPagination';
import { getImages } from '@/lib/api/images';

export const metadata: Metadata = {
  title: 'Image Library | Admin',
  description: 'Manage images',
};

// Disable static optimization for this page to always fetch fresh data
export const dynamic = 'force-dynamic';
export const revalidate = 0;

interface PageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export default async function ImagesPage({ params, searchParams }: PageProps) {
  const { locale } = await params;
  const { page: pageParam } = await searchParams;

  const currentPage = pageParam ? parseInt(pageParam, 10) : 1;
  const itemsPerPage = 24; // 24 images per page for gallery grid

  // Fetch images from API
  let images: any[] = [];
  let totalItems = 0;
  let error: string | null = null;

  try {
    const response = await getImages({ page: currentPage, itemsPerPage });
    // Handle both 'hydra:member' and 'member' formats
    images = response['hydra:member'] || (response as any).member || [];
    totalItems = response['hydra:totalItems'] || (response as any).totalItems || 0;
    console.log('Fetched images count:', images.length, 'Total:', totalItems);
  } catch (err) {
    console.error('Failed to fetch images:', err);
    error = err instanceof Error ? err.message : 'Failed to load images';
    images = [];
  }

  // Calculate stats for current page
  const currentPageImages = images.length;
  const totalSize = images.reduce((sum, img) => sum + (img.size || 0), 0);
  const totalSizeMB = (totalSize / (1024 * 1024)).toFixed(2);

  // Count by mime type (current page)
  const jpegCount = images.filter((img) => img.mimeType === 'image/jpeg').length;
  const pngCount = images.filter((img) => img.mimeType === 'image/png').length;
  const webpCount = images.filter((img) => img.mimeType === 'image/webp').length;
  const gifCount = images.filter((img) => img.mimeType === 'image/gif').length;

  const totalPages = Math.ceil(totalItems / itemsPerPage);

  return (
    <div className="p-4">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
            Image Library
          </h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Manage your images
          </p>
        </div>
        <Link
          href={`/${locale}/admin/images/upload`}
          className="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition-colors dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
        >
          <FiUpload className="w-5 h-5 mr-2" />
          Upload Images
        </Link>
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>Error:</strong> {error}
          </p>
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        {/* Total Images */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Images
              </p>
              <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {totalItems}
              </p>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                JPEG: {jpegCount} · PNG: {pngCount} · WebP: {webpCount} · GIF: {gifCount}
              </p>
            </div>
            <div className="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
              <svg
                className="w-6 h-6 text-blue-600 dark:text-blue-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
            </div>
          </div>
        </div>

        {/* Storage */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Storage
              </p>
              <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {totalSizeMB}
                <span className="text-lg font-normal text-gray-500 dark:text-gray-400 ml-1">
                  MB
                </span>
              </p>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Original images
              </p>
            </div>
            <div className="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
              <svg
                className="w-6 h-6 text-purple-600 dark:text-purple-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"
                />
              </svg>
            </div>
          </div>
        </div>

        {/* Average Size */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                Average Size
              </p>
              <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {totalItems > 0 ? (totalSize / currentPageImages / (1024 * 1024)).toFixed(2) : '0'}
                <span className="text-lg font-normal text-gray-500 dark:text-gray-400 ml-1">
                  MB
                </span>
              </p>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Per image
              </p>
            </div>
            <div className="p-3 bg-green-100 dark:bg-green-900 rounded-full">
              <svg
                className="w-6 h-6 text-green-600 dark:text-green-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Image Gallery Grid */}
      <ImageGallery images={images} locale={locale} />

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-8 flex justify-center">
          <ImagesPagination
            currentPage={currentPage}
            totalPages={totalPages}
            locale={locale}
          />
        </div>
      )}
    </div>
  );
}
