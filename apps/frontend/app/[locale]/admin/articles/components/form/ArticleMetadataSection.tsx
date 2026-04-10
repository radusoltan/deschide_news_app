'use client';

import type { Author } from '@/lib/api/authors';
import type { ArticleFormData, Category } from './useArticleForm';

// ============================================================================
// Validation Error Banner
// ============================================================================

interface ErrorBannerProps {
  formErrors: Record<string, string[] | undefined>;
}

export function ErrorBanner({ formErrors }: ErrorBannerProps) {
  if (!formErrors || Object.keys(formErrors).length === 0) return null;

  return (
    <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
      <div className="flex items-start">
        <svg className="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <h3 className="text-sm font-medium text-red-800 dark:text-red-200">
            Please fix the following errors:
          </h3>
          <ul className="mt-2 text-sm text-red-700 dark:text-red-300 list-disc list-inside space-y-1">
            {formErrors.title?.map((err, i) => <li key={`title-${i}`}>{err}</li>)}
            {formErrors.slug?.map((err, i) => <li key={`slug-${i}`}>{err}</li>)}
            {formErrors.content?.map((err, i) => <li key={`content-${i}`}>{err}</li>)}
            {formErrors._form?.map((err, i) => <li key={`form-${i}`}>{err}</li>)}
          </ul>
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Category & Publishing Section
// ============================================================================

interface ArticleMetadataSectionProps {
  formData: ArticleFormData;
  setFormData: React.Dispatch<React.SetStateAction<ArticleFormData>>;
  categories: Category[];
  authors: Author[];
  isSubmitting: boolean;
}

export default function ArticleMetadataSection({
  formData,
  setFormData,
  categories,
  authors,
  isSubmitting,
}: ArticleMetadataSectionProps) {
  return (
    <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6 space-y-6">
      <h2 className="text-xl font-semibold text-primary dark:text-primary-dark">
        Category & Publishing
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Category */}
        <div>
          <label
            htmlFor="category"
            className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
          >
            Category
          </label>
          <select
            id="category"
            name="category"
            value={formData.category}
            onChange={(e) => setFormData((prev) => ({ ...prev, category: e.target.value }))}
            disabled={isSubmitting}
            className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">-- Select Category --</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.title}
              </option>
            ))}
          </select>
          <p className="mt-1 text-xs text-secondary dark:text-gray-400">
            Choose a category for this article
          </p>
        </div>

        {/* Status */}
        <div>
          <label
            htmlFor="status"
            className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
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
            className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="new">New</option>
            <option value="submitted">Submitted</option>
            <option value="published">Published</option>
          </select>
        </div>

        {/* Publish At - Only show when status is 'submitted' */}
        {formData.status === 'submitted' && (
          <div>
            <label
              htmlFor="publishAt"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              Scheduled Publish Date & Time
            </label>
            <input
              type="datetime-local"
              id="publishAt"
              name="publishAt"
              value={formData.publishAt}
              onChange={(e) => setFormData((prev) => ({ ...prev, publishAt: e.target.value }))}
              disabled={isSubmitting}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <p className="mt-1 text-sm text-secondary dark:text-gray-400">
              Leave empty to publish immediately. Set a future date/time to schedule publication.
            </p>
          </div>
        )}

        {/* Badge */}
        <div>
          <label
            htmlFor="badge"
            className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
          >
            Badge
          </label>
          <select
            id="badge"
            name="badge"
            value={formData.badge}
            onChange={(e) => setFormData((prev) => ({ ...prev, badge: e.target.value }))}
            disabled={isSubmitting}
            className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">-- No Badge --</option>
            <option value="breaking">Breaking News</option>
            <option value="alert">Alert</option>
            <option value="flash">Flash</option>
          </select>
          <p className="mt-1 text-xs text-secondary dark:text-gray-400">
            Special badge displayed on the article card
          </p>
        </div>

        {/* Featured Toggle */}
        <div className="flex items-center gap-3 pt-6">
          <button
            type="button"
            role="switch"
            aria-checked={formData.isFeatured}
            onClick={() => setFormData((prev) => ({ ...prev, isFeatured: !prev.isFeatured }))}
            disabled={isSubmitting}
            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 ${
              formData.isFeatured ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'
            }`}
          >
            <span
              className={`inline-block h-4 w-4 transform rounded-full bg-surface transition-transform ${
                formData.isFeatured ? 'translate-x-6' : 'translate-x-1'
              }`}
            />
          </button>
          <label className="text-sm font-medium text-primary dark:text-primary-dark">
            Featured Article
          </label>
        </div>
      </div>

      {/* Authors Section */}
      <div>
        <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-2">
          Authors <span className="text-red-500">*</span> (max 5)
        </label>

        <div className="space-y-2">
          {formData.authors.map((authorIri, index) => {
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
                  className="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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

        {formData.authors.length === 0 && (
          <p className="mt-2 text-xs text-red-600 dark:text-red-400">
            At least one author is required
          </p>
        )}
        {formData.authors.length >= 5 && (
          <p className="mt-2 text-xs text-secondary dark:text-gray-400">
            Maximum of 5 authors reached
          </p>
        )}
        <p className="mt-1 text-xs text-secondary dark:text-gray-400">
          Select authors for this article (minimum 1, maximum 5)
        </p>
      </div>
    </div>
  );
}
