'use client';

import dynamic from 'next/dynamic';
import type { AttachedImage } from '@/lib/types/image';
import type { ArticleFormData } from './useArticleForm';

const TinyEditor = dynamic(() => import('@/components/editor/TinyEditor'), {
  ssr: false,
  loading: () => (
    <div className="w-full px-4 py-12 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface-sunken dark:bg-gray-700 text-center text-secondary dark:text-gray-400">
      Loading editor...
    </div>
  ),
});

// ============================================================================
// Props
// ============================================================================

interface ArticleContentSectionProps {
  formData: ArticleFormData;
  setFormData: React.Dispatch<React.SetStateAction<ArticleFormData>>;
  formErrors: Record<string, string[] | undefined>;
  isSubmitting: boolean;
  attachedImages: AttachedImage[];
  onTitleChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
}

// ============================================================================
// Component
// ============================================================================

export default function ArticleContentSection({
  formData,
  setFormData,
  formErrors,
  isSubmitting,
  attachedImages,
  onTitleChange,
}: ArticleContentSectionProps) {
  const imageList = attachedImages.map((img) => ({
    title: img.image.originalFilename || `Image ${img.image.id}`,
    value: `${process.env.NEXT_PUBLIC_CDN_URL ?? ''}/uploads/images/${img.image.filename}`,
  }));

  return (
    <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6 space-y-6">
      <h2 className="text-xl font-semibold text-primary dark:text-primary-dark">
        Basic Information
      </h2>

      {/* Title */}
      <div>
        <label
          htmlFor="title"
          className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
        >
          Title <span className="text-red-500">*</span>
        </label>
        <input
          type="text"
          id="title"
          name="title"
          value={formData.title}
          onChange={onTitleChange}
          placeholder="Enter article title"
          required
          disabled={isSubmitting}
          className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark ${
            formErrors.title ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-gray-600'
          }`}
        />
        {formErrors.title && (
          <p className="mt-1 text-sm text-red-600 dark:text-red-400">{formErrors.title[0]}</p>
        )}
      </div>

      {/* Slug */}
      <div>
        <label
          htmlFor="slug"
          className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
        >
          Slug <span className="text-red-500">*</span>
        </label>
        <input
          type="text"
          id="slug"
          name="slug"
          value={formData.slug}
          onChange={(e) => setFormData((prev) => ({ ...prev, slug: e.target.value }))}
          placeholder="article-slug"
          required
          disabled={isSubmitting}
          className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark font-mono text-sm ${
            formErrors.slug ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-gray-600'
          }`}
        />
        {formErrors.slug && (
          <p className="mt-1 text-sm text-red-600 dark:text-red-400">{formErrors.slug[0]}</p>
        )}
      </div>

      {/* Lead / Chapeau */}
      <div>
        <label
          htmlFor="lead"
          className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
        >
          Lead / Chapeau
        </label>
        <TinyEditor
          initialValue={formData.lead}
          onChange={(html) => setFormData((prev) => ({ ...prev, lead: html }))}
          minHeight={100}
          maxHeight={250}
          imageList={imageList}
        />
        <p className="mt-1 text-xs text-secondary dark:text-gray-400">
          Introductory paragraph that appears at the beginning of the article (max 500 characters)
        </p>
      </div>

      {/* Content */}
      <div>
        <label
          htmlFor="content"
          className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
        >
          Content <span className="text-red-500">*</span>
        </label>
        <div className={formErrors.content ? 'ring-2 ring-red-500 rounded-lg' : ''}>
          <TinyEditor
            initialValue={formData.content}
            onChange={(html) => setFormData((prev) => ({ ...prev, content: html }))}
            minHeight={200}
            maxHeight={500}
            imageList={imageList}
          />
        </div>
        {formErrors.content && (
          <p className="mt-1 text-sm text-red-600 dark:text-red-400">{formErrors.content[0]}</p>
        )}
      </div>
    </div>
  );
}
