'use client';

import type { ArticleFormData } from './useArticleForm';

// ============================================================================
// Props
// ============================================================================

interface ArticleSeoSectionProps {
  formData: ArticleFormData;
  setFormData: React.Dispatch<React.SetStateAction<ArticleFormData>>;
  isSubmitting: boolean;
  articleId: number | undefined;

  // SEO state from hook
  seoOpen: boolean;
  setSeoOpen: (v: boolean) => void;
  isOptimizing: boolean;
  seoHighlight: boolean;
  onOptimizeSeo: () => Promise<void>;
}

// ============================================================================
// Component
// ============================================================================

export default function ArticleSeoSection({
  formData,
  setFormData,
  isSubmitting,
  articleId,
  seoOpen,
  setSeoOpen,
  isOptimizing,
  seoHighlight,
  onOptimizeSeo,
}: ArticleSeoSectionProps) {
  return (
    <div className="bg-surface dark:bg-surface-dark rounded-lg shadow">
      <button
        type="button"
        onClick={() => setSeoOpen(!seoOpen)}
        className="w-full flex items-center justify-between p-6 text-left"
      >
        <div className="flex items-center gap-3">
          <svg className="w-5 h-5 text-secondary dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <h2 className="text-xl font-semibold text-primary dark:text-primary-dark">SEO</h2>
          <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-secondary dark:text-gray-400">
            {(formData.metaTitle ? 1 : 0) + (formData.metaDescription ? 1 : 0)}/2
          </span>
        </div>
        <svg className={`w-5 h-5 text-secondary dark:text-gray-400 transition-transform ${seoOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {seoOpen && (
        <div className="px-6 pb-6 space-y-6">
          {/* Optimize SEO Button */}
          {articleId && (
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={onOptimizeSeo}
                disabled={isOptimizing || isSubmitting || !formData.title || (!formData.lead && !formData.content)}
                className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                title={!formData.title || (!formData.lead && !formData.content) ? 'Articolul trebuie sa aiba titlu si continut' : 'Genereaza metaTitle, metaDescription si tag-uri cu AI'}
              >
                {isOptimizing ? (
                  <>
                    <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    Se genereaza...
                  </>
                ) : (
                  <>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Genereaza SEO
                  </>
                )}
              </button>
              <span className="text-xs text-secondary dark:text-gray-400">
                Gemini AI genereaza metaTitle, metaDescription si tag-uri
              </span>
            </div>
          )}

          {/* Meta Title */}
          <div>
            <label htmlFor="metaTitle" className="block text-sm font-medium text-primary dark:text-primary-dark mb-2">
              Meta Title
            </label>
            <input
              type="text"
              id="metaTitle"
              name="metaTitle"
              value={formData.metaTitle}
              onChange={(e) => setFormData((prev) => ({ ...prev, metaTitle: e.target.value }))}
              placeholder="Lasa gol pentru a folosi titlul articolului"
              disabled={isSubmitting}
              maxLength={60}
              className={`w-full px-4 py-2 border rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-500 ${
                seoHighlight ? 'border-green-500 dark:border-green-400 ring-2 ring-green-200 dark:ring-green-800' : 'border-gray-300 dark:border-gray-600'
              }`}
            />
            <div className="flex justify-between mt-1">
              <p className="text-xs text-secondary dark:text-gray-400">
                Titlu optimizat pentru motoarele de cautare
              </p>
              <span className={`text-xs font-mono ${
                formData.metaTitle.length > 60 ? 'text-red-500' : formData.metaTitle.length > 50 ? 'text-yellow-500' : 'text-secondary dark:text-gray-400'
              }`}>
                {formData.metaTitle.length}/60
              </span>
            </div>
          </div>

          {/* Meta Description */}
          <div>
            <label htmlFor="metaDescription" className="block text-sm font-medium text-primary dark:text-primary-dark mb-2">
              Meta Description
            </label>
            <textarea
              id="metaDescription"
              name="metaDescription"
              value={formData.metaDescription}
              onChange={(e) => setFormData((prev) => ({ ...prev, metaDescription: e.target.value }))}
              placeholder="Lasa gol pentru a folosi lead-ul articolului"
              disabled={isSubmitting}
              maxLength={160}
              rows={3}
              className={`w-full px-4 py-2 border rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none transition-colors duration-500 ${
                seoHighlight ? 'border-green-500 dark:border-green-400 ring-2 ring-green-200 dark:ring-green-800' : 'border-gray-300 dark:border-gray-600'
              }`}
            />
            <div className="flex justify-between mt-1">
              <p className="text-xs text-secondary dark:text-gray-400">
                Descriere care apare in rezultatele Google
              </p>
              <span className={`text-xs font-mono ${
                formData.metaDescription.length > 160 ? 'text-red-500' : formData.metaDescription.length > 140 ? 'text-yellow-500' : 'text-secondary dark:text-gray-400'
              }`}>
                {formData.metaDescription.length}/160
              </span>
            </div>
          </div>

          {/* Google Preview */}
          <div>
            <p className="text-xs font-medium text-secondary dark:text-gray-400 mb-2 uppercase tracking-wide">
              Google Preview
            </p>
            <div className="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-surface-sunken dark:bg-gray-800">
              <p className="text-lg text-blue-700 dark:text-blue-400 leading-snug truncate" style={{ fontFamily: 'arial, sans-serif' }}>
                {(formData.metaTitle || formData.title || 'Titlul articolului').substring(0, 60)}
              </p>
              <p className="text-sm text-green-700 dark:text-green-400 mt-1 truncate" style={{ fontFamily: 'arial, sans-serif' }}>
                deschide.md &rsaquo; {formData.slug || 'articol-slug'}
              </p>
              <p className="text-sm text-secondary dark:text-gray-400 mt-1 line-clamp-2" style={{ fontFamily: 'arial, sans-serif' }}>
                {(formData.metaDescription || formData.lead?.replace(/<[^>]*>/g, '') || 'Descrierea articolului va aparea aici...').substring(0, 160)}
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
