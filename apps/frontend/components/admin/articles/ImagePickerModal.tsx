'use client';

import { useState, useEffect, useCallback } from 'react';
import NextImage from 'next/image';
import { FiX, FiSearch, FiCheck, FiChevronLeft, FiChevronRight } from 'react-icons/fi';
import type { Image } from '@/lib/types/image';

interface ImagePickerModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (imageIds: number[]) => Promise<void>;
  attachedImageIds: number[]; // IDs of already attached images
  multiSelect?: boolean;
}

export default function ImagePickerModal({
  isOpen,
  onClose,
  onSelect,
  attachedImageIds,
  multiSelect = true,
}: ImagePickerModalProps) {
  const [images, setImages] = useState<Image[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearchQuery, setDebouncedSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const itemsPerPage = 12;
  const totalPages = Math.ceil(totalItems / itemsPerPage);

  // Debounce search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearchQuery(searchQuery);
      setCurrentPage(1); // Reset to first page when search changes
    }, 500); // 500ms debounce

    return () => clearTimeout(timer);
  }, [searchQuery]);

  const fetchImages = useCallback(async () => {
    setIsLoading(true);
    try {
      const params = new URLSearchParams({
        page: currentPage.toString(),
        itemsPerPage: itemsPerPage.toString(),
      });
      if (debouncedSearchQuery) {
        params.append('search', debouncedSearchQuery);
      }

      const response = await fetch(`/api/images?${params.toString()}`);
      if (!response.ok) {
        throw new Error('Failed to fetch images');
      }

      const data = await response.json();
      // Handle both 'hydra:member' and 'member' response formats
      const members = data['hydra:member'] || data.member || [];
      const total = data['hydra:totalItems'] || data.totalItems || 0;
      setImages(members);
      setTotalItems(total);
    } catch (error) {
      console.error('Failed to fetch images:', error);
      setImages([]);
      setTotalItems(0);
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, itemsPerPage, debouncedSearchQuery]);

  // Fetch images when modal opens or page/search changes
  useEffect(() => {
    if (isOpen) {
      fetchImages();
    }
  }, [isOpen, currentPage, debouncedSearchQuery, fetchImages]);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(e.target.value);
    // Page reset is handled in debounce effect
  };

  const handleImageClick = (imageId: number) => {
    if (!multiSelect) {
      setSelectedIds(new Set([imageId]));
      return;
    }

    setSelectedIds((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(imageId)) {
        newSet.delete(imageId);
      } else {
        newSet.add(imageId);
      }
      return newSet;
    });
  };

  const handleSubmit = async () => {
    if (selectedIds.size === 0) {
      return;
    }

    setIsSubmitting(true);
    try {
      // Return only image IDs to parent component
      const imageIds = Array.from(selectedIds);
      await onSelect(imageIds);

      // Reset and close
      setSelectedIds(new Set());
      setSearchQuery('');
      setCurrentPage(1);
      onClose();
    } catch (error) {
      console.error('Failed to process images:', error);
      alert('Failed to process images. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    if (!isSubmitting) {
      setSelectedIds(new Set());
      setSearchQuery('');
      setCurrentPage(1);
      onClose();
    }
  };

  if (!isOpen) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-6xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">
            Select Images from Library
          </h2>
          <button
            onClick={handleClose}
            disabled={isSubmitting}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors disabled:opacity-50"
          >
            <FiX className="w-6 h-6" />
          </button>
        </div>

        {/* Search Bar */}
        <div className="p-6 border-b border-gray-200 dark:border-gray-700">
          <div className="relative">
            <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
            <input
              type="text"
              value={searchQuery}
              onChange={handleSearchChange}
              placeholder="Search images by filename..."
              className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
            />
          </div>
        </div>

        {/* Image Grid */}
        <div className="flex-1 overflow-y-auto p-6">
          {isLoading ? (
            <div className="flex items-center justify-center h-64">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
          ) : images.length === 0 ? (
            <div className="text-center py-12">
              <p className="text-gray-600 dark:text-gray-400">
                {searchQuery ? 'No images found matching your search' : 'No images in library'}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {images.map((image) => {
                const isSelected = selectedIds.has(image.id);
                const isAttached = attachedImageIds.includes(image.id);
                // Use CDN for image URLs
                const imagePath = image.path || `images/${image.filename}`;
                const imageUrl = `${process.env.NEXT_PUBLIC_CDN_URL ?? ''}/uploads/${imagePath}`;

                return (
                  <div
                    key={image.id}
                    onClick={() => !isAttached && handleImageClick(image.id)}
                    className={`
                      relative cursor-pointer rounded-lg border-2 overflow-hidden transition-all
                      ${isSelected ? 'border-blue-500 ring-2 ring-blue-500' : 'border-gray-200 dark:border-gray-700'}
                      ${isAttached ? 'opacity-50 cursor-not-allowed' : 'hover:border-blue-400'}
                    `}
                  >
                    {/* Image Preview */}
                    <div className="aspect-video bg-gray-100 dark:bg-gray-700 relative flex items-center justify-center overflow-hidden">
                      <NextImage
                        src={imageUrl}
                        alt={image.alt || 'Image preview'}
                        fill
                        loading="eager"
                        className="object-cover"
                        unoptimized
                      />
                    </div>

                    {/* Image Info */}
                    <div className="p-3 bg-white dark:bg-gray-800">
                      <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {image.originalFilename || 'Untitled'}
                      </p>
                      <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {image.width} × {image.height} • {image.formattedSize}
                      </p>
                    </div>

                    {/* Already Attached Badge */}
                    {isAttached && (
                      <div className="absolute top-2 right-2 bg-gray-700 text-white px-2 py-1 rounded text-xs font-semibold">
                        Already Attached
                      </div>
                    )}

                    {/* Selection Indicator */}
                    {isSelected && !isAttached && (
                      <div className="absolute top-2 left-2 bg-blue-500 text-white p-1 rounded-full">
                        <FiCheck className="w-4 h-4" />
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Page {currentPage} of {totalPages} • {totalItems} total images
            </p>
            <div className="flex gap-2">
              <button
                onClick={() => setCurrentPage((prev) => Math.max(1, prev - 1))}
                disabled={currentPage === 1 || isLoading}
                className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <FiChevronLeft className="w-5 h-5" />
              </button>
              <button
                onClick={() => setCurrentPage((prev) => Math.min(totalPages, prev + 1))}
                disabled={currentPage === totalPages || isLoading}
                className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <FiChevronRight className="w-5 h-5" />
              </button>
            </div>
          </div>
        )}

        {/* Footer Actions */}
        <div className="p-6 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            {selectedIds.size} {selectedIds.size === 1 ? 'image' : 'images'} selected
          </p>
          <div className="flex gap-3">
            <button
              onClick={handleClose}
              disabled={isSubmitting}
              className="px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Cancel
            </button>
            <button
              onClick={handleSubmit}
              disabled={selectedIds.size === 0 || isSubmitting}
              className="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              {isSubmitting ? (
                <>
                  <svg
                    className="animate-spin h-4 w-4 text-white"
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
                  Attaching...
                </>
              ) : (
                `Attach Selected (${selectedIds.size})`
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
