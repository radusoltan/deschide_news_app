'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { createArticleAction, updateArticleAction, type ArticleFormState } from '@/app/actions/articles';
import type { AttachedImage } from '@/lib/types/image';
import type { Author } from '@/lib/api/authors';
import type { Tag } from '@/lib/types/tag';

// ============================================================================
// Slug helper
// ============================================================================

function generateSlug(text: string): string {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

// ============================================================================
// Types
// ============================================================================

export interface ArticleFormData {
  title: string;
  slug: string;
  lead: string;
  content: string;
  status: string;
  category: string | number;
  authors: string[];
  publishAt: string;
  badge: string;
  isFeatured: boolean;
  metaTitle: string;
  metaDescription: string;
}

export interface ArticleFormArticle {
  id?: number;
  title?: string;
  slug?: string;
  lead?: string;
  content?: string;
  status?: string;
  category?: string | number;
  authors?: string[];
  tags?: Tag[];
  topics?: Array<{ id: number; title: string; slug: string }>;
  publishAt?: string;
  badge?: string | null;
  isFeatured?: boolean;
  metaTitle?: string | null;
  metaDescription?: string | null;
  translationStatus?: string | null;
}

export interface Category {
  id: number;
  title: string;
}

export interface UseArticleFormReturn {
  // Form data
  formData: ArticleFormData;
  setFormData: React.Dispatch<React.SetStateAction<ArticleFormData>>;
  formErrors: NonNullable<ArticleFormState['errors']>;

  // Submit state
  isSubmitting: boolean;
  saveAction: 'save' | 'saveAndClose' | null;

  // Handlers
  handleTitleChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  handleSubmit: (e: React.FormEvent<HTMLFormElement>) => Promise<void>;
  handleSave: (e: React.MouseEvent<HTMLButtonElement>) => void;
  handleSaveAndClose: (e: React.MouseEvent<HTMLButtonElement>) => void;
  handleClose: () => void;

  // SEO
  seoOpen: boolean;
  setSeoOpen: (v: boolean) => void;
  isOptimizing: boolean;
  seoHighlight: boolean;
  handleOptimizeSeo: () => Promise<void>;

  // Tags
  tagsOpen: boolean;
  setTagsOpen: (v: boolean) => void;
  selectedTags: Tag[];
  setSelectedTags: React.Dispatch<React.SetStateAction<Tag[]>>;

  // Topics
  topicsOpen: boolean;
  setTopicsOpen: (v: boolean) => void;
  selectedTopics: Array<{ id: number; title: string; path: string }>;
  setSelectedTopics: React.Dispatch<React.SetStateAction<Array<{ id: number; title: string; path: string }>>>;

  // Images
  attachedImages: AttachedImage[];
  setAttachedImages: React.Dispatch<React.SetStateAction<AttachedImage[]>>;
  isImagePickerOpen: boolean;
  setIsImagePickerOpen: (v: boolean) => void;
  isImageUploadOpen: boolean;
  setIsImageUploadOpen: (v: boolean) => void;
  handleSelectImages: (imageIds: number[]) => Promise<void>;
  handleUploadComplete: (imageIds: number[]) => Promise<void>;
  handleDetachImage: (articleImageId: number) => Promise<void>;
  handleSetFeatured: (articleImageId: number) => Promise<void>;
  handleReorderImages: (updates: Array<{ id: number; position: number }>) => Promise<void>;
}

// ============================================================================
// Hook
// ============================================================================

export function useArticleForm(
  locale: string,
  article: ArticleFormArticle | undefined,
): UseArticleFormReturn {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [saveAction, setSaveAction] = useState<'save' | 'saveAndClose' | null>(null);
  const [formErrors, setFormErrors] = useState<NonNullable<ArticleFormState['errors']>>({});

  const [formData, setFormData] = useState<ArticleFormData>({
    title: article?.title || '',
    slug: article?.slug || '',
    lead: article?.lead || '',
    content: article?.content || '',
    status: article?.status || 'new',
    category: article?.category || '',
    authors: article?.authors || [],
    publishAt: article?.publishAt || '',
    badge: article?.badge || '',
    isFeatured: article?.isFeatured || false,
    metaTitle: article?.metaTitle || '',
    metaDescription: article?.metaDescription || '',
  });

  const [seoOpen, setSeoOpen] = useState(false);
  const [tagsOpen, setTagsOpen] = useState(false);
  const [topicsOpen, setTopicsOpen] = useState(false);
  const [selectedTags, setSelectedTags] = useState<Tag[]>(article?.tags || []);
  const [selectedTopics, setSelectedTopics] = useState<Array<{ id: number; title: string; path: string }>>(
    (article?.topics || []).map((t) => ({ id: t.id, title: t.title, path: t.title }))
  );
  const [isOptimizing, setIsOptimizing] = useState(false);
  const [seoHighlight, setSeoHighlight] = useState(false);

  // Image management state
  const [attachedImages, setAttachedImages] = useState<AttachedImage[]>([]);
  const [isImagePickerOpen, setIsImagePickerOpen] = useState(false);
  const [isImageUploadOpen, setIsImageUploadOpen] = useState(false);

  // ==========================================================================
  // Fetch attached images
  // ==========================================================================

  const fetchAttachedImages = useCallback(async () => {
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
  }, [article?.id]);

  useEffect(() => {
    if (article?.id) {
      fetchAttachedImages();
    }
  }, [article?.id, fetchAttachedImages]);

  // ==========================================================================
  // Title change (auto-slug in create mode)
  // ==========================================================================

  const handleTitleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setFormData((prev) => ({ ...prev, title: value }));
    if (!article?.id) {
      const slug = generateSlug(value);
      setFormData((prev) => ({ ...prev, slug }));
    }
  };

  // ==========================================================================
  // SEO optimization
  // ==========================================================================

  const handleOptimizeSeo = async () => {
    if (!article?.id || isOptimizing) return;
    setIsOptimizing(true);

    try {
      const response = await fetch(`/api/articles/${article.id}/optimize-seo`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ force: false, locale }),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || 'SEO optimization failed');
      }

      const data = await response.json();

      if (data.metaTitle) {
        setFormData((prev) => ({ ...prev, metaTitle: data.metaTitle }));
      }
      if (data.metaDescription) {
        setFormData((prev) => ({ ...prev, metaDescription: data.metaDescription }));
      }

      // Add suggested tags
      const allNewTagNames = [...(data.tagsAdded || []), ...(data.tagsExisting || [])];
      if (allNewTagNames.length > 0) {
        const apiBase = process.env.NEXT_PUBLIC_API_URL ?? '';
        const tagsResponse = await fetch(`${apiBase}/api/tags?itemsPerPage=100`, {
          headers: { 'Accept': 'application/ld+json' },
        });
        if (tagsResponse.ok) {
          const tagsData = await tagsResponse.json();
          const allTags: Tag[] = tagsData['hydra:member'] || tagsData.member || [];

          const newTags = allTags.filter((t: Tag) =>
            allNewTagNames.some((name: string) => t.name.toLowerCase() === name.toLowerCase())
          );

          setSelectedTags((prev) => {
            const existingIds = new Set(prev.map((t) => t.id));
            const toAdd = newTags.filter((t: Tag) => !existingIds.has(t.id));
            return [...prev, ...toAdd];
          });

          if (newTags.length > 0) {
            setTagsOpen(true);
          }
        }
      }

      setSeoHighlight(true);
      setTimeout(() => setSeoHighlight(false), 3000);

      const parts: string[] = [];
      if (data.metaTitle) parts.push(`metaTitle (${data.metaTitle.length} chars)`);
      if (data.metaDescription) parts.push(`metaDescription (${data.metaDescription.length} chars)`);
      const tagCount = (data.tagsAdded?.length || 0) + (data.tagsExisting?.length || 0);
      if (tagCount > 0) parts.push(`${tagCount} tag-uri`);
      alert(`SEO generat: ${parts.join(', ')}`);
    } catch (error) {
      alert(error instanceof Error ? error.message : 'Nu s-a putut genera SEO. Incearca din nou.');
    } finally {
      setIsOptimizing(false);
    }
  };

  // ==========================================================================
  // Form submission
  // ==========================================================================

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      const formDataObj = new FormData(e.currentTarget);

      formDataObj.set('title', formData.title);
      formDataObj.set('slug', formData.slug);
      formDataObj.set('category', formData.category.toString());
      formDataObj.set('status', formData.status);
      formDataObj.set('lead', formData.lead);
      formDataObj.set('content', formData.content);
      formDataObj.set('authors', JSON.stringify(formData.authors));
      formDataObj.set('badge', formData.badge);
      formDataObj.set('isFeatured', formData.isFeatured ? '1' : '0');
      formDataObj.set('metaTitle', formData.metaTitle);
      formDataObj.set('metaDescription', formData.metaDescription);

      const tagIris = selectedTags.map((t) => `/api/tags/${t.id}`);
      formDataObj.set('tags', JSON.stringify(tagIris));

      const topicIris = selectedTopics.map((t) => `/api/topics/${t.id}`);
      formDataObj.set('topics', JSON.stringify(topicIris));

      if (formData.publishAt) {
        const publishAtDate = new Date(formData.publishAt);
        formDataObj.set('publishAt', publishAtDate.toISOString());
      }

      let result: ArticleFormState;
      if (article?.id) {
        result = await updateArticleAction(article.id, locale, formDataObj);
      } else {
        result = await createArticleAction(locale, formDataObj);
      }

      if (result.errors && Object.keys(result.errors).length > 0) {
        setFormErrors(result.errors);
        setIsSubmitting(false);
        setSaveAction(null);
        return;
      }

      setFormErrors({});

      if (result.message) {
        if (saveAction === 'saveAndClose') {
          setIsSubmitting(false);
          setSaveAction(null);
          router.push(`/${locale}/admin/articles`);
          router.refresh();
        } else if (saveAction === 'save') {
          if (!article?.id && result.articleId) {
            setIsSubmitting(false);
            setSaveAction(null);
            router.push(`/${locale}/admin/articles/${result.articleId}/edit`);
            router.refresh();
          } else {
            router.refresh();
            setIsSubmitting(false);
            setSaveAction(null);
          }
        } else {
          setIsSubmitting(false);
          setSaveAction(null);
        }
      } else {
        setFormErrors({ _form: ['An unexpected error occurred. Please try again.'] });
        setIsSubmitting(false);
        setSaveAction(null);
      }
    } catch (err) {
      console.error('Form submission error:', err);
      setFormErrors({
        _form: [err instanceof Error ? err.message : 'Failed to save article. Please try again.']
      });
      setIsSubmitting(false);
      setSaveAction(null);
    }
  };

  const handleClose = () => {
    router.push(`/${locale}/admin/articles`);
  };

  const handleSaveAndClose = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    setSaveAction('saveAndClose');
    const form = e.currentTarget.closest('form');
    if (form) {
      form.requestSubmit();
    }
  };

  const handleSave = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    setSaveAction('save');
    const form = e.currentTarget.closest('form');
    if (form) {
      form.requestSubmit();
    }
  };

  // ==========================================================================
  // Image management
  // ==========================================================================

  const handleSelectImages = async (imageIds: number[]) => {
    if (article?.id) {
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
        await fetchAttachedImages();
      } catch (error) {
        console.error('Failed to attach images:', error);
        throw error;
      }
    } else {
      try {
        const imagePromises = imageIds.map((imageId) =>
          fetch(`/api/images/${imageId}`).then((res) => res.json())
        );
        const images = await Promise.all(imagePromises);
        const newAttachedImages: AttachedImage[] = images.map((image, index) => ({
          id: image.id,
          articleImageId: -(Date.now() + index),
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
        await fetchAttachedImages();
      } catch (error) {
        console.error('Failed to attach uploaded images:', error);
        throw error;
      }
    } else {
      try {
        const imagePromises = imageIds.map((imageId) =>
          fetch(`/api/images/${imageId}`).then((res) => res.json())
        );
        const images = await Promise.all(imagePromises);
        const newAttachedImages: AttachedImage[] = images.map((image, index) => ({
          id: image.id,
          articleImageId: -(Date.now() + index),
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
      const response = await fetch(`/api/articles/${article.id}/images/${articleImageId}`, {
        method: 'DELETE',
      });
      if (!response.ok) {
        throw new Error('Failed to detach image');
      }
    } else {
      setAttachedImages((prev) => prev.filter((img) => img.articleImageId !== articleImageId));
    }
  };

  const handleSetFeatured = async (articleImageId: number) => {
    if (article?.id) {
      const response = await fetch(`/api/articles/${article.id}/images/${articleImageId}/featured`, {
        method: 'PUT',
      });
      if (!response.ok) {
        throw new Error('Failed to set featured image');
      }
    } else {
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
      const response = await fetch(`/api/articles/${article.id}/images/reorder`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ updates }),
      });
      if (!response.ok) {
        throw new Error('Failed to reorder images');
      }
    } else {
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

  return {
    formData,
    setFormData,
    formErrors,
    isSubmitting,
    saveAction,
    handleTitleChange,
    handleSubmit,
    handleSave,
    handleSaveAndClose,
    handleClose,
    seoOpen,
    setSeoOpen,
    isOptimizing,
    seoHighlight,
    handleOptimizeSeo,
    tagsOpen,
    setTagsOpen,
    selectedTags,
    setSelectedTags,
    topicsOpen,
    setTopicsOpen,
    selectedTopics,
    setSelectedTopics,
    attachedImages,
    setAttachedImages,
    isImagePickerOpen,
    setIsImagePickerOpen,
    isImageUploadOpen,
    setIsImageUploadOpen,
    handleSelectImages,
    handleUploadComplete,
    handleDetachImage,
    handleSetFeatured,
    handleReorderImages,
  };
}
