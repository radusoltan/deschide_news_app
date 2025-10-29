'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import Image from 'next/image';
import { FiTrash, FiSearch, FiCrop, FiLoader } from 'react-icons/fi';
import type { Image as ImageType, ImageWithThumbnails, ThumbnailProfile, CropCoordinates } from '@/lib/types/image';
import { CropModal } from '@/components/admin/images/CropModal';

interface ImageGalleryProps {
  images: ImageType[];
  locale: string;
  onImageDeleted?: (id: number) => void;
}

export function ImageGallery({ images: initialImages, locale, onImageDeleted }: ImageGalleryProps) {
  const router = useRouter();
  const [images, setImages] = useState(initialImages);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearchQuery, setDebouncedSearchQuery] = useState('');
  const [filterType, setFilterType] = useState<string>('all');
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [isSearching, setIsSearching] = useState(false);

  // Crop modal state
  const [showCropModal, setShowCropModal] = useState(false);
  const [selectedImage, setSelectedImage] = useState<ImageWithThumbnails | null>(null);
  const [profiles, setProfiles] = useState<ThumbnailProfile[]>([]);
  const [loadingProfiles, setLoadingProfiles] = useState(false);

  // Debounce search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearchQuery(searchQuery);
    }, 500);

    return () => clearTimeout(timer);
  }, [searchQuery]);

  // Fetch images when search changes (server-side search)
  useEffect(() => {
    if (debouncedSearchQuery) {
      fetchImagesWithSearch();
    } else {
      // Reset to initial images when search is cleared
      setImages(initialImages);
    }
  }, [debouncedSearchQuery]);

  const fetchImagesWithSearch = async () => {
    setIsSearching(true);
    try {
      const params = new URLSearchParams({
        page: '1',
        itemsPerPage: '100',
        search: debouncedSearchQuery,
      });

      const response = await fetch(`/api/images?${params.toString()}`);
      if (!response.ok) throw new Error('Failed to search images');

      const data = await response.json();
      const members = data['hydra:member'] || data.member || [];
      setImages(members);
    } catch (error) {
      console.error('Search error:', error);
    } finally {
      setIsSearching(false);
    }
  };

  // Filter images based on type (client-side filter)
  const filteredImages = images.filter((item) => {
    const matchesType =
      filterType === 'all' ||
      (filterType === 'jpeg' && item.mimeType === 'image/jpeg') ||
      (filterType === 'png' && item.mimeType === 'image/png') ||
      (filterType === 'webp' && item.mimeType === 'image/webp') ||
      (filterType === 'gif' && item.mimeType === 'image/gif');

    return matchesType;
  });

  const formatFileSize = (bytes: number | null): string => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const handleDelete = async (id: number) => {
    const confirmed = window.confirm(
      'Are you sure you want to delete this image? This action cannot be undone.'
    );

    if (!confirmed) return;

    setDeletingId(id);
    try {
      const response = await fetch(`/api/images/${id}`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({ error: 'Delete failed' }));
        throw new Error(error.error || 'Delete failed');
      }

      setImages((prev) => prev.filter((img) => img.id !== id));
      onImageDeleted?.(id);
      router.refresh();
    } catch (error) {
      console.error('Delete error:', error);
      alert(`Failed to delete image: ${error instanceof Error ? error.message : 'Unknown error'}`);
    } finally {
      setDeletingId(null);
    }
  };

  const handleImageClick = (imageId: number) => {
    router.push(`/${locale}/admin/images/${imageId}/edit`);
  };

  // Load profiles when needed
  const loadProfilesIfNeeded = async () => {
    if (profiles.length === 0 && !loadingProfiles) {
      try {
        setLoadingProfiles(true);
        const response = await fetch('/api/thumbnail-profiles');
        if (!response.ok) throw new Error('Failed to load profiles');
        const profilesData = await response.json();
        setProfiles(profilesData);
      } catch (error) {
        console.error('Failed to load thumbnail profiles:', error);
        alert('Failed to load crop profiles');
      } finally {
        setLoadingProfiles(false);
      }
    }
  };

  const handleOpenCropModal = async (image: ImageType) => {
    await loadProfilesIfNeeded();

    try {
      // Load full image data with thumbnails
      const response = await fetch(`/api/images/${image.id}/with-thumbnails`);
      if (!response.ok) throw new Error('Failed to load image details');
      const fullImage: ImageWithThumbnails = await response.json();
      setSelectedImage(fullImage);
      setShowCropModal(true);
    } catch (error) {
      console.error('Failed to load image details:', error);
      alert('Failed to load image details');
    }
  };

  const handleCropComplete = async (
    profile: string,
    format: string,
    cropData: CropCoordinates
  ) => {
    if (!selectedImage) return;

    try {
      const response = await fetch('/api/images/crop', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          imageId: selectedImage.id,
          profile,
          format,
          cropData,
        }),
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to apply crop');
      }

      // Success - don't close modal, let user continue cropping other profiles
      // CropModal will show toast notification
    } catch (error) {
      console.error('Crop failed:', error);
      throw error; // Re-throw to let CropModal handle error and show toast
    }
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
      {/* Search and Filter */}
      <div className="p-6 border-b border-gray-200 dark:border-gray-700">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          {/* Search */}
          <div className="relative flex-1 max-w-md">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <FiSearch className="w-5 h-5 text-gray-400" />
            </div>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400"
              placeholder="Search by filename, description, alt..."
            />
            {isSearching && (
              <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <FiLoader className="w-5 h-5 text-gray-400 animate-spin" />
              </div>
            )}
          </div>

          {/* Type Filter */}
          <div className="flex gap-2 flex-wrap">
            <button
              onClick={() => setFilterType('all')}
              className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                filterType === 'all'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              All
            </button>
            <button
              onClick={() => setFilterType('jpeg')}
              className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                filterType === 'jpeg'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              JPEG
            </button>
            <button
              onClick={() => setFilterType('png')}
              className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                filterType === 'png'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              PNG
            </button>
            <button
              onClick={() => setFilterType('webp')}
              className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                filterType === 'webp'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              WebP
            </button>
            <button
              onClick={() => setFilterType('gif')}
              className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                filterType === 'gif'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              GIF
            </button>
          </div>
        </div>
      </div>

      {/* Gallery Grid */}
      {filteredImages.length === 0 ? (
        <div className="p-12 text-center">
          <p className="text-gray-500 dark:text-gray-400">
            {searchQuery || filterType !== 'all'
              ? 'No images found matching your filters.'
              : 'No images yet. Upload some to get started!'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-6">
          {filteredImages.map((image) => {
            // Build the image URL using CDN URL for better performance and CORS handling
            const cdnUrl = process.env.NEXT_PUBLIC_CDN_URL || process.env.NEXT_PUBLIC_API_URL;
            let imageUrl = '';

            if (image.contentUrl) {
              // contentUrl already contains the full path
              imageUrl = `${cdnUrl}${image.contentUrl}`;
            } else if (image.filename) {
              // Build URL from filename - images are stored in /uploads/images/
              imageUrl = `${cdnUrl}/uploads/images/${image.filename}`;
            }

            return (
              <div
                key={image.id}
                className="group relative bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-500 transition-colors"
              >
                {/* Image Preview - Clickable to edit */}
                <div
                  onClick={() => handleImageClick(image.id)}
                  className="cursor-pointer"
                >
                  <div className="aspect-[4/3] relative bg-gray-100 dark:bg-gray-800">
                    {imageUrl ? (
                      <img
                        src={imageUrl}
                        alt={image.alt || image.originalFilename || 'Image'}
                        className="w-full h-full object-cover"
                        onError={(e) => {
                          // Hide broken image and show placeholder
                          e.currentTarget.style.display = 'none';
                          const parent = e.currentTarget.parentElement;
                          if (parent) {
                            parent.innerHTML = `
                              <div class="w-full h-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                              </div>
                            `;
                          }
                        }}
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center">
                        <svg
                          className="w-12 h-12 text-gray-400"
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
                    )}
                  </div>
                </div>

                {/* Info */}
                <div className="p-3 space-y-2">
                  <p className="text-xs text-gray-900 dark:text-gray-100 truncate font-medium">
                    {image.originalFilename}
                  </p>
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                      {formatFileSize(image.size)}
                    </span>
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                      {image.width}×{image.height}
                    </span>
                  </div>
                </div>

                {/* Hover Actions - Crop & Delete */}
                <div className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                  {/* Crop Button */}
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      handleOpenCropModal(image);
                    }}
                    className="p-2 bg-blue-600 dark:bg-blue-700 rounded-lg shadow-lg hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors"
                    title="Crop Thumbnail"
                  >
                    <FiCrop className="w-5 h-5 text-white" />
                  </button>

                  {/* Delete Button */}
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      handleDelete(image.id);
                    }}
                    disabled={deletingId === image.id}
                    className="p-2 bg-red-600 dark:bg-red-700 rounded-lg shadow-lg hover:bg-red-700 dark:hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    title="Delete Image"
                  >
                    {deletingId === image.id ? (
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
                    ) : (
                      <FiTrash className="w-5 h-5 text-white" />
                    )}
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Results Count */}
      <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          Showing {filteredImages.length} of {images.length} images
        </p>
      </div>

      {/* Crop Modal */}
      {showCropModal && selectedImage && profiles.length > 0 && (
        <CropModal
          image={selectedImage}
          profiles={profiles}
          onCropComplete={handleCropComplete}
          onClose={() => setShowCropModal(false)}
        />
      )}
    </div>
  );
}
