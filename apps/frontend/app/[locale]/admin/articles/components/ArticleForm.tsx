'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import dynamic from 'next/dynamic';
import { createArticleAction, updateArticleAction, type ArticleFormState } from '@/app/actions/articles';
import type { AttachedImage, Image } from '@/lib/types/image';
import type { Author } from '@/lib/api/authors';

// Import TinyMCE editor client-only (no SSR)
const TinyEditor = dynamic(() => import('@/components/editor/TinyEditor'), {
  ssr: false,
  loading: () => (
    <div className="w-full px-4 py-12 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-center text-gray-500 dark:text-gray-400">
      Loading editor...
    </div>
  ),
});

// Import image management components client-only
const AttachedImagesSection = dynamic(() => import('@/components/admin/articles/AttachedImagesSection'), {
  ssr: false,
});

const ImagePickerModal = dynamic(() => import('@/components/admin/articles/ImagePickerModal'), {
  ssr: false,
});

const ImageUploadModal = dynamic(() => import('@/components/admin/articles/ImageUploadModal'), {
  ssr: false,
});

// Helper function to generate slug from title
function generateSlug(text: string): string {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '') // Remove special chars
    .replace(/[\s_-]+/g, '-') // Replace spaces/underscores with hyphens
    .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
}

interface Category {
  id: number;
  title: string;
}

interface ArticleFormProps {
  locale: string;
  categories: Category[];
  authors: Author[];
  article?: {
    id?: number;
    title?: string;
    slug?: string;
    lead?: string;
    content?: string;
    status?: string;
    category?: string | number;
    authors?: string[]; // Array of author IRIs
  };
}

export default function ArticleForm({ locale, categories, authors, article }: ArticleFormProps) {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [saveAction, setSaveAction] = useState<'save' | 'saveAndClose' | null>(null);

  const [formData, setFormData] = useState({
    title: article?.title || '',
    slug: article?.slug || '',
    lead: article?.lead || '',
    content: article?.content || '',
    status: article?.status || 'new',
    category: article?.category || '',
    authors: article?.authors || [], // Array of author IRIs
  });

  // Image management state
  const [attachedImages, setAttachedImages] = useState<AttachedImage[]>([]);
  const [isImagePickerOpen, setIsImagePickerOpen] = useState(false);
  const [isImageUploadOpen, setIsImageUploadOpen] = useState(false);

  // Fetch attached images on mount (only for edit mode)
  useEffect(() => {
    if (article?.id) {
      fetchAttachedImages();
    }
  }, [article?.id]);

  const fetchAttachedImages = async () => {
    if (!article?.id) return;

    try {
      const response = await fetch(`/api/articles/${article.id}/images`);
      if (response.ok) {
        const data = await response.json();
        setAttachedImages(data);
      }
    } catch (error) {
      console.error('Failed to fetch attached images:', error);
    }
  };

  const handleTitleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setFormData((prev) => ({ ...prev, title: value }));

    // Auto-generate slug only in create mode
    if (!article?.id) {
      const slug = generateSlug(value);
      setFormData((prev) => ({ ...prev, slug }));
    }
  };

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      const formDataObj = new FormData(e.currentTarget);

      // Add editor content to FormData
      formDataObj.set('lead', formData.lead);
      formDataObj.set('content', formData.content);

      // Add authors to FormData (as JSON string of IRIs)
      formDataObj.set('authors', JSON.stringify(formData.authors));

      let result: ArticleFormState;
      if (article?.id) {
        result = await updateArticleAction(article.id, locale, formDataObj);
      } else {
        result = await createArticleAction(locale, formDataObj);
      }

      if (result.errors?._form) {
        setIsSubmitting(false);
        setSaveAction(null);
        return;
      }

      if (result.message) {
        // Handle redirect based on save action
        if (saveAction === 'saveAndClose') {
          router.push(`/${locale}/admin/articles`);
          router.refresh();
        } else if (saveAction === 'save') {
          // If creating a new article, redirect to edit page with the new ID
          if (!article?.id && result.articleId) {
            router.push(`/${locale}/admin/articles/${result.articleId}/edit`);
            router.refresh();
          } else {
            // If editing existing article, just refresh
            router.refresh();
            setIsSubmitting(false);
            setSaveAction(null);
          }
        }
      } else {
        // If no message returned, something went wrong but no errors were reported
        setIsSubmitting(false);
        setSaveAction(null);
      }
    } catch (err) {
      console.error('Form submission error:', err);
      setIsSubmitting(false);
      setSaveAction(null);
    }
  };

  const handleClose = () => {
    router.push(`/${locale}/admin/articles`);
  };

  const handleSaveAndClose = async (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    setSaveAction('saveAndClose');
    const form = e.currentTarget.closest('form');
    if (form) {
      form.requestSubmit();
    }
  };

  const handleSave = async (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    setSaveAction('save');
    const form = e.currentTarget.closest('form');
    if (form) {
      form.requestSubmit();
    }
  };

  // Image management handlers
  const handleSelectImages = async (imageIds: number[]) => {
    if (article?.id) {
      // Edit mode: attach images to existing article
      try {
        for (const imageId of imageIds) {
          const response = await fetch(`/api/articles/${article.id}/images`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ imageId }),
          });

          if (!response.ok) {
            throw new Error('Failed to attach image');
          }
        }

        // Refresh attached images list
        await fetchAttachedImages();
      } catch (error) {
        console.error('Failed to attach images:', error);
        throw error;
      }
    } else {
      // Create mode: fetch image data and add to local state
      try {
        const imagePromises = imageIds.map((imageId) =>
          fetch(`/api/images/${imageId}`).then((res) => res.json())
        );
        const images = await Promise.all(imagePromises);

        // Create temporary AttachedImage objects with dummy IDs
        const newAttachedImages: AttachedImage[] = images.map((image, index) => ({
          id: image.id,
          articleImageId: -(Date.now() + index), // Temporary negative ID
          image,
          position: attachedImages.length + index,
          isFeatured: false,
        }));

        setAttachedImages((prev) => [...prev, ...newAttachedImages]);
      } catch (error) {
        console.error('Failed to fetch image data:', error);
        throw error;
      }
    }
  };

  const handleUploadComplete = async (imageIds: number[]) => {
    if (article?.id) {
      // Edit mode: attach images to existing article
      try {
        for (const imageId of imageIds) {
          const response = await fetch(`/api/articles/${article.id}/images`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ imageId }),
          });

          if (!response.ok) {
            throw new Error('Failed to attach image');
          }
        }

        // Refresh attached images list
        await fetchAttachedImages();
      } catch (error) {
        console.error('Failed to attach uploaded images:', error);
        throw error;
      }
    } else {
      // Create mode: fetch image data and add to local state
      try {
        const imagePromises = imageIds.map((imageId) =>
          fetch(`/api/images/${imageId}`).then((res) => res.json())
        );
        const images = await Promise.all(imagePromises);

        // Create temporary AttachedImage objects with dummy IDs
        const newAttachedImages: AttachedImage[] = images.map((image, index) => ({
          id: image.id,
          articleImageId: -(Date.now() + index), // Temporary negative ID
          image,
          position: attachedImages.length + index,
          isFeatured: false,
        }));

        setAttachedImages((prev) => [...prev, ...newAttachedImages]);
      } catch (error) {
        console.error('Failed to fetch uploaded image data:', error);
        throw error;
      }
    }
  };

  const handleDetachImage = async (articleImageId: number) => {
    if (article?.id) {
      // Edit mode: detach from backend
      const response = await fetch(`/api/articles/${article.id}/images/${articleImageId}`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        throw new Error('Failed to detach image');
      }
    } else {
      // Create mode: remove from local state
      setAttachedImages((prev) => prev.filter((img) => img.articleImageId !== articleImageId));
    }
  };

  const handleSetFeatured = async (articleImageId: number) => {
    if (article?.id) {
      // Edit mode: update backend
      const response = await fetch(`/api/articles/${article.id}/images/${articleImageId}/featured`, {
        method: 'PUT',
      });

      if (!response.ok) {
        throw new Error('Failed to set featured image');
      }
    } else {
      // Create mode: update local state
      setAttachedImages((prev) =>
        prev.map((img) => ({
          ...img,
          isFeatured: img.articleImageId === articleImageId,
        }))
      );
    }
  };

  const handleReorderImages = async (updates: Array<{ id: number; position: number }>) => {
    if (article?.id) {
      // Edit mode: update backend
      const response = await fetch(`/api/articles/${article.id}/images/reorder`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ updates }),
      });

      if (!response.ok) {
        throw new Error('Failed to reorder images');
      }
    } else {
      // Create mode: update local state
      setAttachedImages((prev) => {
        const newImages = [...prev];
        updates.forEach((update) => {
          const index = newImages.findIndex((img) => img.articleImageId === update.id);
          if (index !== -1) {
            newImages[index] = { ...newImages[index], position: update.position };
          }
        });
        return newImages.sort((a, b) => a.position - b.position);
      });
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-8">
      {/* Basic Information */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
          Basic Information
        </h2>

        {/* Title */}
        <div>
          <label
            htmlFor="title"
            className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            Title <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            id="title"
            name="title"
            value={formData.title}
            onChange={handleTitleChange}
            placeholder="Enter article title"
            required
            disabled={isSubmitting}
            className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
          />
        </div>

        {/* Slug */}
        <div>
          <label
            htmlFor="slug"
            className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
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
            className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm"
          />
        </div>

        {/* Lead / Chapeau */}
        <div>
          <label
            htmlFor="lead"
            className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            Lead / Chapeau
          </label>
          <TinyEditor
            initialValue={formData.lead}
            onChange={(html) => setFormData((prev) => ({ ...prev, lead: html }))}
            height={400}
          />
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Introductory paragraph that appears at the beginning of the article (max 500 characters)
          </p>
        </div>

        {/* Content */}
        <div>
          <label
            htmlFor="content"
            className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            Content <span className="text-red-500">*</span>
          </label>
          <TinyEditor
            initialValue={formData.content}
            onChange={(html) => setFormData((prev) => ({ ...prev, content: html }))}
            height={800}
          />
        </div>
      </div>

      {/* Category & Publishing */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
          Category & Publishing
        </h2>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Category */}
          <div>
            <label
              htmlFor="category"
              className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
            >
              Category
            </label>
            <select
              id="category"
              name="category"
              value={formData.category}
              onChange={(e) => setFormData((prev) => ({ ...prev, category: e.target.value }))}
              disabled={isSubmitting}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">-- Select Category --</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.title}
                </option>
              ))}
            </select>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Choose a category for this article
            </p>
          </div>

          {/* Status */}
          <div>
            <label
              htmlFor="status"
              className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
            >
              Status <span className="text-red-500">*</span>
            </label>
            <select
              id="status"
              name="status"
              value={formData.status}
              onChange={(e) => setFormData((prev) => ({ ...prev, status: e.target.value }))}
              required
              disabled={isSubmitting}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="new">New</option>
              <option value="submitted">Submitted</option>
              <option value="published">Published</option>
            </select>
          </div>
        </div>

        {/* Authors Section */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Authors <span className="text-red-500">*</span> (max 5)
          </label>

          {/* Display selected authors */}
          <div className="space-y-2">
            {formData.authors.map((authorIri, index) => {
              const authorId = parseInt(authorIri.split('/').pop() || '0');
              const author = authors.find((a) => a.id === authorId);

              return (
                <div key={index} className="flex items-center gap-2">
                  <select
                    value={authorIri}
                    onChange={(e) => {
                      const newAuthors = [...formData.authors];
                      newAuthors[index] = e.target.value;
                      setFormData((prev) => ({ ...prev, authors: newAuthors }));
                    }}
                    disabled={isSubmitting}
                    className="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  >
                    <option value="">-- Select Author --</option>
                    {authors.map((a) => (
                      <option key={a.id} value={`/api/authors/${a.id}`}>
                        {a.fullName}
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    onClick={() => {
                      const newAuthors = formData.authors.filter((_, i) => i !== index);
                      setFormData((prev) => ({ ...prev, authors: newAuthors }));
                    }}
                    disabled={isSubmitting}
                    className="p-2 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors disabled:opacity-50"
                    title="Remove author"
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              );
            })}
          </div>

          {/* Add Author Button */}
          {formData.authors.length < 5 && (
            <button
              type="button"
              onClick={() => {
                setFormData((prev) => ({
                  ...prev,
                  authors: [...prev.authors, ''],
                }));
              }}
              disabled={isSubmitting}
              className="mt-3 flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Author
            </button>
          )}

          {/* Validation Messages */}
          {formData.authors.length === 0 && (
            <p className="mt-2 text-xs text-red-600 dark:text-red-400">
              At least one author is required
            </p>
          )}
          {formData.authors.length >= 5 && (
            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
              Maximum of 5 authors reached
            </p>
          )}
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Select authors for this article (minimum 1, maximum 5)
          </p>
        </div>
      </div>

      {/* Attached Images Section */}
      {article?.id && (
        <AttachedImagesSection
          articleId={article.id}
          attachedImages={attachedImages}
          onImagesChange={setAttachedImages}
          onOpenUpload={() => setIsImageUploadOpen(true)}
          onOpenPicker={() => setIsImagePickerOpen(true)}
          onDetach={handleDetachImage}
          onSetFeatured={handleSetFeatured}
          onReorder={handleReorderImages}
          maxImages={100}
        />
      )}

      {/* Form Actions */}
      <div className="flex justify-between items-center gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
        {/* Close Button (Left) */}
        <button
          type="button"
          onClick={handleClose}
          disabled={isSubmitting}
          className="px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Close
        </button>

        {/* Save Buttons (Right) */}
        <div className="flex gap-3">
          <button
            type="button"
            onClick={handleSaveAndClose}
            disabled={isSubmitting}
            className="px-6 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting && saveAction === 'saveAndClose' ? (
              <span className="flex items-center">
                <svg
                  className="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
              </span>
            ) : (
              'Save & Close'
            )}
          </button>

          <button
            type="button"
            onClick={handleSave}
            disabled={isSubmitting}
            className="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting && saveAction === 'save' ? (
              <span className="flex items-center">
                <svg
                  className="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
              </span>
            ) : (
              'Save'
            )}
          </button>
        </div>
      </div>

      {/* Image Picker Modal */}
      <ImagePickerModal
        isOpen={isImagePickerOpen}
        onClose={() => setIsImagePickerOpen(false)}
        onSelect={handleSelectImages}
        attachedImageIds={attachedImages.map((img) => img.image.id)}
        multiSelect={true}
      />

      {/* Image Upload Modal */}
      <ImageUploadModal
        isOpen={isImageUploadOpen}
        onClose={() => setIsImageUploadOpen(false)}
        onUploadComplete={handleUploadComplete}
        articleId={article?.id}
      />
    </form>
  );
}
