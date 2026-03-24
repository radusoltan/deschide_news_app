'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import NextImage from 'next/image';
import dynamic from 'next/dynamic';
import { DndContext, closestCenter, DragEndEvent, PointerSensor, KeyboardSensor, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, rectSortingStrategy, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { FiUpload, FiImage, FiStar, FiTrash2, FiPlus, FiCrop } from 'react-icons/fi';
import type { AttachedImage, ImageWithThumbnails, ThumbnailProfile, CropCoordinates } from '@/lib/types/image';

// Dynamically import CropModal (only loaded when modal opens)
const CropModal = dynamic(() => import('@/components/admin/images/CropModal').then(mod => ({ default: mod.CropModal })), {
  ssr: false,
  loading: () => <div>Loading crop tool...</div>,
});

interface AttachedImagesSectionProps {
  articleId?: number;
  attachedImages: AttachedImage[];
  onImagesChange: (images: AttachedImage[]) => void;
  onOpenUpload: () => void;
  onOpenPicker: () => void;
  onDetach: (articleImageId: number) => Promise<void>;
  onSetFeatured: (articleImageId: number) => Promise<void>;
  onReorder: (updates: Array<{ id: number; position: number }>) => Promise<void>;
  maxImages?: number;
}

function SortableImageCard({
  attachedImage,
  isFeatured,
  onDetach,
  onSetFeatured,
  onOpenCrop,
}: {
  attachedImage: AttachedImage;
  isFeatured: boolean;
  onDetach: (id: number) => void;
  onSetFeatured: (id: number) => void;
  onOpenCrop: (imageId: number) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: attachedImage.articleImageId,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  // Construct image URL from CDN — use path (includes subdirectory) with filename fallback
  const imagePath = attachedImage.image.path || `images/${attachedImage.image.filename}`;
  const imageUrl = (attachedImage.image.path || attachedImage.image.filename)
    ? `${process.env.NEXT_PUBLIC_CDN_URL || 'http://127.0.0.1:8082'}/uploads/${imagePath}`
    : '';

  return (
    <div
      ref={setNodeRef}
      style={style}
      className="relative group bg-white dark:bg-gray-800 rounded-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden hover:border-blue-500 dark:hover:border-blue-400 transition-colors"
    >
      {/* Drag Handle (invisible, covers whole card) */}
      <div
        {...attributes}
        {...listeners}
        className="absolute inset-0 z-10 cursor-move"
        style={{ touchAction: 'none' }}
      />

      {/* Featured Badge */}
      {isFeatured && (
        <div className="absolute top-2 left-2 z-20 bg-yellow-500 text-white px-2 py-1 rounded-md text-xs font-semibold flex items-center gap-1">
          <FiStar className="w-3 h-3 fill-current" />
          Featured
        </div>
      )}

      {/* Image Preview */}
      <div className="aspect-video bg-gray-100 dark:bg-gray-700 relative flex items-center justify-center overflow-hidden">
        {imageUrl ? (
          <NextImage
            src={imageUrl}
            alt={attachedImage.image.alt || 'Attached image'}
            fill
            loading="eager"
            className="object-cover"
            unoptimized
          />
        ) : (
          <FiImage className="w-12 h-12 text-gray-400" />
        )}
      </div>

      {/* Image Info */}
      <div className="p-3 space-y-2">
        <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
          {attachedImage.image.originalFilename || 'Untitled'}
        </p>
        {attachedImage.image.alt && (
          <p className="text-xs text-gray-600 dark:text-gray-400 truncate">
            Alt: {attachedImage.image.alt}
          </p>
        )}
        <div className="text-xs text-gray-500 dark:text-gray-500">
          {attachedImage.image.width} × {attachedImage.image.height} •{' '}
          {attachedImage.image.formattedSize}
        </div>
      </div>

      {/* Action Buttons (shown on hover) */}
      <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 z-20 pointer-events-none group-hover:pointer-events-auto">
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            onOpenCrop(attachedImage.image.id);
          }}
          className="p-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors"
          title="Crop thumbnail"
        >
          <FiCrop className="w-5 h-5" />
        </button>
        {!isFeatured && (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onSetFeatured(attachedImage.articleImageId);
            }}
            className="p-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition-colors"
            title="Set as featured"
          >
            <FiStar className="w-5 h-5" />
          </button>
        )}
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            if (confirm('Are you sure you want to detach this image?')) {
              onDetach(attachedImage.articleImageId);
            }
          }}
          className="p-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors"
          title="Detach image"
        >
          <FiTrash2 className="w-5 h-5" />
        </button>
      </div>
    </div>
  );
}

export default function AttachedImagesSection({
  articleId,
  attachedImages,
  onImagesChange,
  onOpenUpload,
  onOpenPicker,
  onDetach,
  onSetFeatured,
  onReorder,
  maxImages = 100,
}: AttachedImagesSectionProps) {
  const router = useRouter();
  const [isProcessing, setIsProcessing] = useState(false);

  // Crop modal state
  const [showCropModal, setShowCropModal] = useState(false);
  const [selectedImage, setSelectedImage] = useState<ImageWithThumbnails | null>(null);
  const [profiles, setProfiles] = useState<ThumbnailProfile[]>([]);
  const [loadingProfiles, setLoadingProfiles] = useState(false);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event;

    if (!over || active.id === over.id) {
      return;
    }

    const oldIndex = attachedImages.findIndex((img) => img.articleImageId === active.id);
    const newIndex = attachedImages.findIndex((img) => img.articleImageId === over.id);

    if (oldIndex === -1 || newIndex === -1) {
      return;
    }

    // Reorder locally (optimistic update)
    const reordered = [...attachedImages];
    const [movedItem] = reordered.splice(oldIndex, 1);
    reordered.splice(newIndex, 0, movedItem);

    // Update positions
    const updatedWithPositions = reordered.map((img, index) => ({
      ...img,
      position: index,
    }));

    onImagesChange(updatedWithPositions);

    // Update on server
    setIsProcessing(true);
    try {
      await onReorder(
        updatedWithPositions.map((img) => ({
          id: img.articleImageId,
          position: img.position,
        }))
      );
    } catch (error) {
      console.error('Failed to reorder images:', error);
      // Revert on error
      onImagesChange(attachedImages);
    } finally {
      setIsProcessing(false);
    }
  };

  const handleDetach = async (articleImageId: number) => {
    setIsProcessing(true);
    try {
      await onDetach(articleImageId);
      // Remove from local state
      onImagesChange(attachedImages.filter((img) => img.articleImageId !== articleImageId));
    } catch (error) {
      console.error('Failed to detach image:', error);
      alert('Failed to detach image. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleSetFeatured = async (articleImageId: number) => {
    setIsProcessing(true);
    try {
      await onSetFeatured(articleImageId);
      // Update local state
      onImagesChange(
        attachedImages.map((img) => ({
          ...img,
          isFeatured: img.articleImageId === articleImageId,
        }))
      );
    } catch (error) {
      console.error('Failed to set featured image:', error);
      alert('Failed to set featured image. Please try again.');
    } finally {
      setIsProcessing(false);
    }
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

  const handleOpenCropModal = async (imageId: number) => {
    await loadProfilesIfNeeded();

    try {
      // Load full image data with thumbnails
      const response = await fetch(`/api/images/${imageId}/with-thumbnails`);
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

  const remaining = maxImages - attachedImages.length;
  const featuredImage = attachedImages.find((img) => img.isFeatured);

  return (
    <div className="space-y-4">
      {/* Header with Info and Action Buttons */}
      <div className="flex items-center justify-between">
        <div className="text-sm space-y-1">
          <div className="text-gray-600 dark:text-gray-400">
            <span className="font-semibold text-gray-900 dark:text-white">
              {attachedImages.length}
            </span>{' '}
            / {maxImages} images attached
            {remaining > 0 && (
              <span className="ml-2 text-green-600 dark:text-green-400">
                ({remaining} remaining)
              </span>
            )}
          </div>
          {featuredImage && (
            <div className="flex items-center gap-1 text-yellow-600 dark:text-yellow-400">
              <FiStar className="w-3 h-3 fill-current" />
              <span className="text-xs font-medium">
                Featured: {featuredImage.image.alt || featuredImage.image.originalFilename || 'Untitled'}
              </span>
            </div>
          )}
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={onOpenUpload}
            disabled={attachedImages.length >= maxImages || isProcessing}
            className={`flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg
                     hover:bg-green-700 transition-colors ${
                       attachedImages.length >= maxImages
                         ? 'opacity-50 cursor-not-allowed'
                         : ''
                     }`}
          >
            <FiUpload className="w-4 h-4" />
            Upload New
          </button>
          <button
            type="button"
            onClick={onOpenPicker}
            disabled={attachedImages.length >= maxImages || isProcessing}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg
                     hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed
                     transition-colors"
          >
            <FiPlus className="w-4 h-4" />
            Select from List
          </button>
        </div>
      </div>

      {/* Images Grid with Drag & Drop */}
      {attachedImages.length === 0 ? (
        <div className="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-12">
          <div className="text-center">
            <FiImage className="mx-auto h-12 w-12 text-gray-400 mb-4" />
            <p className="text-gray-500 dark:text-gray-400 mb-6">
              No images attached to this article.
            </p>
            <div className="flex items-center justify-center gap-3">
              <button
                type="button"
                onClick={onOpenUpload}
                className="flex items-center gap-2 px-6 py-3 bg-green-600 text-white rounded-lg
                         hover:bg-green-700 transition-colors font-medium"
              >
                <FiUpload className="w-5 h-5" />
                Upload New
              </button>
              <button
                type="button"
                onClick={onOpenPicker}
                className="flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg
                         hover:bg-blue-700 transition-colors font-medium"
              >
                <FiPlus className="w-5 h-5" />
                Select from List
              </button>
            </div>
          </div>
        </div>
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragEnd={handleDragEnd}
        >
          <SortableContext
            items={attachedImages.map((img) => img.articleImageId)}
            strategy={rectSortingStrategy}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              {attachedImages.map((attachedImage) => (
                <SortableImageCard
                  key={attachedImage.articleImageId}
                  attachedImage={attachedImage}
                  isFeatured={attachedImage.isFeatured}
                  onDetach={handleDetach}
                  onSetFeatured={handleSetFeatured}
                  onOpenCrop={handleOpenCropModal}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      )}

      {/* Help Text */}
      {attachedImages.length > 0 && (
        <div className="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-4">
          <span>Drag to reorder</span>
          <span>•</span>
          <span>Hover to see actions</span>
        </div>
      )}

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
