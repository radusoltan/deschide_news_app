'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { useDropzone } from 'react-dropzone';
import { FiUpload, FiCheck, FiAlertCircle, FiArrowLeft, FiLink } from 'react-icons/fi';
import Link from 'next/link';
import type { Image } from '@/lib/types/image';

interface UploadProgress {
  filename: string;
  status: 'uploading' | 'complete' | 'error';
  progress: number;
  error?: string;
  imageData?: Image;
}

type UploadMode = 'file' | 'url';

export default function UploadImagesPage() {
  const router = useRouter();
  const params = useParams();
  const locale = params.locale as string;

  const [uploads, setUploads] = useState<Map<string, UploadProgress>>(new Map());
  const [isProcessing, setIsProcessing] = useState(false);
  const [uploadMode, setUploadMode] = useState<UploadMode>('file');
  const [imageUrl, setImageUrl] = useState('');
  const [altText, setAltText] = useState('');

  const uploadFile = async (file: File) => {
    const fileKey = `${file.name}-${file.size}-${file.lastModified}`;

    // Initialize progress
    setUploads((prev) => {
      const newMap = new Map(prev);
      newMap.set(fileKey, {
        filename: file.name,
        status: 'uploading',
        progress: 0,
      });
      return newMap;
    });

    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('alt', altText || '');
      formData.append('caption', '');
      formData.append('description', '');

      const response = await fetch('/api/images', {
        method: 'POST',
        body: formData,
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({ error: 'Upload failed' }));
        throw new Error(error.error || 'Upload failed');
      }

      const imageData: Image = await response.json();

      // Update to complete
      setUploads((prev) => {
        const newMap = new Map(prev);
        newMap.set(fileKey, {
          filename: file.name,
          status: 'complete',
          progress: 100,
          imageData,
        });
        return newMap;
      });

      return imageData;
    } catch (error: any) {
      console.error('Upload error:', error);

      // Update to error
      setUploads((prev) => {
        const newMap = new Map(prev);
        newMap.set(fileKey, {
          filename: file.name,
          status: 'error',
          progress: 0,
          error: error.message || 'Upload failed',
        });
        return newMap;
      });

      return null;
    }
  };

  const uploadFromUrl = async () => {
    if (!imageUrl.trim()) {
      return;
    }

    setIsProcessing(true);

    const fileKey = `url-${imageUrl}`;

    // Initialize progress
    setUploads((prev) => {
      const newMap = new Map(prev);
      newMap.set(fileKey, {
        filename: imageUrl,
        status: 'uploading',
        progress: 0,
      });
      return newMap;
    });

    try {
      const response = await fetch('/api/images/upload-from-url', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ url: imageUrl }),
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({ error: 'Upload failed' }));
        throw new Error(error.error || 'Upload failed');
      }

      const imageData: Image = await response.json();

      // Update to complete
      setUploads((prev) => {
        const newMap = new Map(prev);
        newMap.set(fileKey, {
          filename: imageUrl,
          status: 'complete',
          progress: 100,
          imageData,
        });
        return newMap;
      });

      // Clear URL input
      setImageUrl('');
    } catch (error: any) {
      console.error('Upload from URL error:', error);

      // Update to error
      setUploads((prev) => {
        const newMap = new Map(prev);
        newMap.set(fileKey, {
          filename: imageUrl,
          status: 'error',
          progress: 0,
          error: error.message || 'Upload failed',
        });
        return newMap;
      });
    } finally {
      setIsProcessing(false);
    }
  };

  const onDrop = async (acceptedFiles: File[]) => {
    setIsProcessing(true);

    // Upload all files in parallel
    const uploadPromises = acceptedFiles.map((file) => uploadFile(file));
    await Promise.all(uploadPromises);

    setIsProcessing(false);
  };

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'image/jpeg': ['.jpg', '.jpeg'],
      'image/png': ['.png'],
      'image/gif': ['.gif'],
      'image/webp': ['.webp'],
    },
    maxSize: 10 * 1024 * 1024, // 10MB
    multiple: true,
  });

  const handleFinish = () => {
    router.push(`/${locale}/admin/images`);
  };

  const uploadsList = Array.from(uploads.values());
  const hasUploads = uploadsList.length > 0;
  const hasCompleted = uploadsList.some((u) => u.status === 'complete');
  const allDone = uploadsList.length > 0 && uploadsList.every((u) => u.status !== 'uploading');

  return (
    <div className="p-4">
      {/* Header */}
      <div className="mb-8">
        <Link
          href={`/${locale}/admin/images`}
          className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-primary dark:hover:text-gray-200 mb-4"
        >
          <FiArrowLeft className="w-4 h-4 mr-2" />
          Back to Images
        </Link>
        <h1 className="text-3xl font-bold text-primary dark:text-primary-dark">Upload Images</h1>
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Upload images from your computer or from a URL
        </p>
      </div>

      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-xl max-w-4xl mx-auto">
        {/* Content */}
        <div className="p-6 space-y-6">
          {/* Mode Tabs */}
          <div className="flex gap-2 border-b border-gray-200 dark:border-gray-700">
            <button
              type="button"
              onClick={() => setUploadMode('file')}
              className={`px-4 py-2 font-medium text-sm transition-colors border-b-2 ${
                uploadMode === 'file'
                  ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-primary dark:hover:text-gray-200'
              }`}
            >
              <FiUpload className="inline-block w-4 h-4 mr-2" />
              Upload Files
            </button>
            <button
              type="button"
              onClick={() => setUploadMode('url')}
              className={`px-4 py-2 font-medium text-sm transition-colors border-b-2 ${
                uploadMode === 'url'
                  ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-primary dark:hover:text-gray-200'
              }`}
            >
              <FiLink className="inline-block w-4 h-4 mr-2" />
              Upload from URL
            </button>
          </div>

          {/* Alt Text */}
          <div>
            <label
              htmlFor="altText"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              Alt Text (optional)
            </label>
            <input
              type="text"
              id="altText"
              value={altText}
              onChange={(e) => setAltText(e.target.value)}
              placeholder="Describe the image for accessibility"
              disabled={isProcessing}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark disabled:opacity-50"
            />
            <p className="mt-1 text-xs text-secondary dark:text-gray-400">
              This text will be applied to all images uploaded in this batch
            </p>
          </div>

          {/* Upload File Mode */}
          {uploadMode === 'file' && (
            <div
              {...getRootProps()}
              className={`
                border-2 border-dashed rounded-lg p-12 text-center cursor-pointer transition-all
                ${
                  isDragActive
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                    : 'border-gray-300 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500'
                }
              `}
            >
              <input {...getInputProps()} />
              <FiUpload className="w-16 h-16 mx-auto text-gray-400 mb-4" />
              {isDragActive ? (
                <p className="text-lg font-medium text-blue-600 dark:text-blue-400">
                  Drop files here...
                </p>
              ) : (
                <>
                  <p className="text-lg font-medium text-primary dark:text-primary-dark mb-2">
                    Drag & drop images here, or click to browse
                  </p>
                  <p className="text-sm text-gray-600 dark:text-gray-400">
                    Supports: JPEG, PNG, GIF, WebP • Max 10MB per file
                  </p>
                </>
              )}
            </div>
          )}

          {/* Upload from URL Mode */}
          {uploadMode === 'url' && (
            <div className="space-y-4">
              <div>
                <label
                  htmlFor="imageUrl"
                  className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
                >
                  Image URL
                </label>
                <div className="flex gap-2">
                  <input
                    type="url"
                    id="imageUrl"
                    value={imageUrl}
                    onChange={(e) => setImageUrl(e.target.value)}
                    placeholder="https://example.com/image.jpg"
                    disabled={isProcessing}
                    className="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark disabled:opacity-50"
                  />
                  <button
                    type="button"
                    onClick={uploadFromUrl}
                    disabled={!imageUrl.trim() || isProcessing}
                    className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    {isProcessing ? 'Uploading...' : 'Upload'}
                  </button>
                </div>
                <p className="mt-2 text-xs text-secondary dark:text-gray-400">
                  Enter a direct URL to an image (JPEG, PNG, GIF, WebP)
                </p>
              </div>
            </div>
          )}

          {/* Upload Progress List */}
          {hasUploads && (
            <div className="space-y-3">
              <h3 className="text-sm font-medium text-primary dark:text-primary-dark">
                Uploads ({uploadsList.length})
              </h3>
              {uploadsList.map((upload, index) => (
                <div
                  key={index}
                  className="flex items-center gap-3 p-3 bg-surface-sunken dark:bg-gray-700 rounded-lg"
                >
                  {/* Status Icon */}
                  <div className="flex-shrink-0">
                    {upload.status === 'complete' ? (
                      <div className="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                        <FiCheck className="w-5 h-5 text-green-600 dark:text-green-400" />
                      </div>
                    ) : upload.status === 'error' ? (
                      <div className="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                        <FiAlertCircle className="w-5 h-5 text-red-600 dark:text-red-400" />
                      </div>
                    ) : (
                      <div className="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                        <svg
                          className="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400"
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
                      </div>
                    )}
                  </div>

                  {/* File Info */}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-primary dark:text-primary-dark truncate">
                      {upload.filename}
                    </p>
                    {upload.status === 'error' && upload.error && (
                      <p className="text-xs text-red-600 dark:text-red-400 mt-1">{upload.error}</p>
                    )}
                    {upload.status === 'complete' && (
                      <p className="text-xs text-green-600 dark:text-green-400 mt-1">
                        Upload complete
                      </p>
                    )}
                    {upload.status === 'uploading' && (
                      <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">Uploading...</p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className="p-6 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            {uploadsList.filter((u) => u.status === 'complete').length} of {uploadsList.length}{' '}
            uploaded successfully
          </p>
          <div className="flex gap-3">
            <Link
              href={`/${locale}/admin/images`}
              className="px-6 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-surface-sunken dark:hover:bg-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-700"
            >
              Cancel
            </Link>
            {hasCompleted && allDone && (
              <button
                onClick={handleFinish}
                className="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
              >
                Done
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
