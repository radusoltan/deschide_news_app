'use client';

import dynamic from 'next/dynamic';
import type { AttachedImage } from '@/lib/types/image';

const AttachedImagesSection = dynamic(() => import('@/components/admin/articles/AttachedImagesSection'), {
  ssr: false,
});

const ImagePickerModal = dynamic(() => import('@/components/admin/articles/ImagePickerModal'), {
  ssr: false,
});

const ImageUploadModal = dynamic(() => import('@/components/admin/articles/ImageUploadModal'), {
  ssr: false,
});

// ============================================================================
// Props
// ============================================================================

interface ArticleMediaSectionProps {
  articleId: number | undefined;
  attachedImages: AttachedImage[];
  setAttachedImages: React.Dispatch<React.SetStateAction<AttachedImage[]>>;
  isImagePickerOpen: boolean;
  setIsImagePickerOpen: (v: boolean) => void;
  isImageUploadOpen: boolean;
  setIsImageUploadOpen: (v: boolean) => void;
  onSelectImages: (imageIds: number[]) => Promise<void>;
  onUploadComplete: (imageIds: number[]) => Promise<void>;
  onDetachImage: (articleImageId: number) => Promise<void>;
  onSetFeatured: (articleImageId: number) => Promise<void>;
  onReorderImages: (updates: Array<{ id: number; position: number }>) => Promise<void>;
}

// ============================================================================
// Component
// ============================================================================

export default function ArticleMediaSection({
  articleId,
  attachedImages,
  setAttachedImages,
  isImagePickerOpen,
  setIsImagePickerOpen,
  isImageUploadOpen,
  setIsImageUploadOpen,
  onSelectImages,
  onUploadComplete,
  onDetachImage,
  onSetFeatured,
  onReorderImages,
}: ArticleMediaSectionProps) {
  return (
    <>
      {/* Attached Images Section */}
      {articleId && (
        <AttachedImagesSection
          articleId={articleId}
          attachedImages={attachedImages}
          onImagesChange={setAttachedImages}
          onOpenUpload={() => setIsImageUploadOpen(true)}
          onOpenPicker={() => setIsImagePickerOpen(true)}
          onDetach={onDetachImage}
          onSetFeatured={onSetFeatured}
          onReorder={onReorderImages}
          maxImages={100}
        />
      )}

      {/* Image Picker Modal */}
      <ImagePickerModal
        isOpen={isImagePickerOpen}
        onClose={() => setIsImagePickerOpen(false)}
        onSelect={onSelectImages}
        attachedImageIds={attachedImages.map((img) => img.image.id)}
        multiSelect={true}
      />

      {/* Image Upload Modal */}
      <ImageUploadModal
        isOpen={isImageUploadOpen}
        onClose={() => setIsImageUploadOpen(false)}
        onUploadComplete={onUploadComplete}
        articleId={articleId}
      />
    </>
  );
}
