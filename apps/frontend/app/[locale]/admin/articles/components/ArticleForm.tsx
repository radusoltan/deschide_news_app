'use client';

import TranslateButton from '@/components/admin/TranslateButton';
import type { Author } from '@/lib/api/authors';
import type { Tag } from '@/lib/types/tag';
import { useArticleForm, type ArticleFormArticle, type Category } from './form/useArticleForm';
import { ErrorBanner } from './form/ArticleMetadataSection';
import ArticleContentSection from './form/ArticleContentSection';
import ArticleMetadataSection from './form/ArticleMetadataSection';
import ArticleMediaSection from './form/ArticleMediaSection';
import ArticleRelationsSection from './form/ArticleRelationsSection';
import ArticleSeoSection from './form/ArticleSeoSection';

// ============================================================================
// Props
// ============================================================================

interface ArticleFormProps {
  locale: string;
  categories: Category[];
  authors: Author[];
  article?: ArticleFormArticle;
}

// ============================================================================
// Orchestrator
// ============================================================================

export default function ArticleForm({ locale, categories, authors, article }: ArticleFormProps) {
  const form = useArticleForm(locale, article);

  return (
    <form onSubmit={form.handleSubmit} className="space-y-8">
      {/* Validation Error Banner */}
      <ErrorBanner formErrors={form.formErrors} />

      {/* Basic Information (title, slug, lead, content) */}
      <ArticleContentSection
        formData={form.formData}
        setFormData={form.setFormData}
        formErrors={form.formErrors}
        isSubmitting={form.isSubmitting}
        attachedImages={form.attachedImages}
        onTitleChange={form.handleTitleChange}
      />

      {/* Category & Publishing (category, status, badge, featured, authors) */}
      <ArticleMetadataSection
        formData={form.formData}
        setFormData={form.setFormData}
        categories={categories}
        authors={authors}
        isSubmitting={form.isSubmitting}
      />

      {/* Attached Images */}
      <ArticleMediaSection
        articleId={article?.id}
        attachedImages={form.attachedImages}
        setAttachedImages={form.setAttachedImages}
        isImagePickerOpen={form.isImagePickerOpen}
        setIsImagePickerOpen={form.setIsImagePickerOpen}
        isImageUploadOpen={form.isImageUploadOpen}
        setIsImageUploadOpen={form.setIsImageUploadOpen}
        onSelectImages={form.handleSelectImages}
        onUploadComplete={form.handleUploadComplete}
        onDetachImage={form.handleDetachImage}
        onSetFeatured={form.handleSetFeatured}
        onReorderImages={form.handleReorderImages}
      />

      {/* Tags & Topics */}
      <ArticleRelationsSection
        locale={locale}
        formData={form.formData}
        isSubmitting={form.isSubmitting}
        tagsOpen={form.tagsOpen}
        setTagsOpen={form.setTagsOpen}
        selectedTags={form.selectedTags}
        setSelectedTags={form.setSelectedTags}
        topicsOpen={form.topicsOpen}
        setTopicsOpen={form.setTopicsOpen}
        selectedTopics={form.selectedTopics}
        setSelectedTopics={form.setSelectedTopics}
      />

      {/* SEO */}
      <ArticleSeoSection
        formData={form.formData}
        setFormData={form.setFormData}
        isSubmitting={form.isSubmitting}
        articleId={article?.id}
        seoOpen={form.seoOpen}
        setSeoOpen={form.setSeoOpen}
        isOptimizing={form.isOptimizing}
        seoHighlight={form.seoHighlight}
        onOptimizeSeo={form.handleOptimizeSeo}
      />

      {/* Form Actions */}
      <div className="flex justify-between items-center gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
        {/* Close Button (Left) */}
        <button
          type="button"
          onClick={form.handleClose}
          disabled={form.isSubmitting}
          className="px-6 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-surface-sunken dark:hover:bg-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Close
        </button>

        {/* Translate Button (Center) */}
        {article?.id && (
          <TranslateButton
            entityType="article"
            entityId={article.id}
            currentStatus={article.translationStatus}
          />
        )}

        {/* Save Buttons (Right) */}
        <div className="flex gap-3">
          <button
            type="button"
            onClick={form.handleSaveAndClose}
            disabled={form.isSubmitting}
            className="px-6 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {form.isSubmitting && form.saveAction === 'saveAndClose' ? (
              <span className="flex items-center">
                <svg
                  className="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                Saving...
              </span>
            ) : (
              'Save & Close'
            )}
          </button>

          <button
            type="button"
            onClick={form.handleSave}
            disabled={form.isSubmitting}
            className="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {form.isSubmitting && form.saveAction === 'save' ? (
              <span className="flex items-center">
                <svg
                  className="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                Saving...
              </span>
            ) : (
              'Save'
            )}
          </button>
        </div>
      </div>
    </form>
  );
}
