'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Image from 'next/image';
import { FiSave, FiX } from 'react-icons/fi';
import type { Image as ImageType } from '@/lib/types/image';

interface ImageEditFormProps {
  locale: string;
  image: ImageType;
}

export default function ImageEditForm({ locale, image }: ImageEditFormProps) {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    alt: image.alt || '',
    caption: image.caption || '',
    description: image.description || '',
  });

  // Build image URL
  const cdnUrl = process.env.NEXT_PUBLIC_CDN_URL || process.env.NEXT_PUBLIC_API_URL;
  const imageUrl = `${cdnUrl}/uploads/images/originals/${image.filename}`;

  const formatFileSize = (bytes: number | null): string => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatDate = (dateString: string | null): string => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      const response = await fetch(`/api/images/${image.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({ error: 'Update failed' }));
        throw new Error(error.error || 'Failed to update image');
      }

      router.push(`/${locale}/admin/images`);
      router.refresh();
    } catch (error) {
      console.error('Update error:', error);
      alert(`Failed to update image: ${error instanceof Error ? error.message : 'Unknown error'}`);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCancel = () => {
    router.push(`/${locale}/admin/images`);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Image Preview and Info */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Image Preview */}
        <div>
          <h2 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
            Image Preview
          </h2>
          <div className="aspect-video relative bg-gray-100 dark:bg-surface-dark rounded-lg overflow-hidden">
            <Image
              src={imageUrl}
              alt={image.alt || image.originalFilename || 'Image'}
              fill
              className="object-contain"
              unoptimized
            />
          </div>
        </div>

        {/* Image Information */}
        <div>
          <h2 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
            Image Information
          </h2>
          <dl className="space-y-3 text-sm">
            <div>
              <dt className="font-medium text-primary dark:text-primary-dark">Filename</dt>
              <dd className="mt-1 text-primary dark:text-primary-dark break-all">
                {image.originalFilename}
              </dd>
            </div>
            <div>
              <dt className="font-medium text-primary dark:text-primary-dark">File Size</dt>
              <dd className="mt-1 text-primary dark:text-primary-dark">
                {formatFileSize(image.size)}
              </dd>
            </div>
            <div>
              <dt className="font-medium text-primary dark:text-primary-dark">Dimensions</dt>
              <dd className="mt-1 text-primary dark:text-primary-dark">
                {image.width} × {image.height} pixels
              </dd>
            </div>
            <div>
              <dt className="font-medium text-primary dark:text-primary-dark">MIME Type</dt>
              <dd className="mt-1 text-primary dark:text-primary-dark">
                {image.mimeType}
              </dd>
            </div>
            <div>
              <dt className="font-medium text-primary dark:text-primary-dark">Uploaded</dt>
              <dd className="mt-1 text-primary dark:text-primary-dark">
                {formatDate(image.uploadedAt)}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      {/* Metadata Fields */}
      <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h2 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
          Image Metadata
        </h2>

        <div className="space-y-4">
          {/* Alt Text */}
          <div>
            <label
              htmlFor="alt"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              Alt Text <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="alt"
              value={formData.alt}
              onChange={(e) => setFormData((prev) => ({ ...prev, alt: e.target.value }))}
              required
              disabled={isSubmitting}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Describe the image for accessibility"
            />
            <p className="mt-1 text-xs text-secondary dark:text-gray-400">
              Required for accessibility. Describe what&apos;s in the image.
            </p>
          </div>

          {/* Caption */}
          <div>
            <label
              htmlFor="caption"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              Caption
            </label>
            <input
              type="text"
              id="caption"
              value={formData.caption}
              onChange={(e) => setFormData((prev) => ({ ...prev, caption: e.target.value }))}
              disabled={isSubmitting}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Optional caption to display with the image"
            />
          </div>

          {/* Description */}
          <div>
            <label
              htmlFor="description"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              Description
            </label>
            <textarea
              id="description"
              value={formData.description}
              onChange={(e) => setFormData((prev) => ({ ...prev, description: e.target.value }))}
              disabled={isSubmitting}
              rows={4}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Detailed description for search and organization"
            />
          </div>
        </div>
      </div>

      {/* Action Buttons */}
      <div className="flex items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
        <button
          type="button"
          onClick={handleCancel}
          disabled={isSubmitting}
          className="px-4 py-2 text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-surface-sunken dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-2"
        >
          <FiX className="w-5 h-5" />
          Cancel
        </button>
        <button
          type="submit"
          disabled={isSubmitting}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-2"
        >
          {isSubmitting ? (
            <>
              <svg
                className="animate-spin h-5 w-5 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  className="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  strokeWidth="4"
                />
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
              Saving...
            </>
          ) : (
            <>
              <FiSave className="w-5 h-5" />
              Save Changes
            </>
          )}
        </button>
      </div>
    </form>
  );
}
