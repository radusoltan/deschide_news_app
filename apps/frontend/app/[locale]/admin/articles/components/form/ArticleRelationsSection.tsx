'use client';

import dynamic from 'next/dynamic';
import type { Tag } from '@/lib/types/tag';
import type { ArticleFormData } from './useArticleForm';

const TagSelector = dynamic(() => import('@/components/admin/tags/TagSelector'), {
  ssr: false,
});
const TopicSelector = dynamic(() => import('@/components/admin/topics/TopicSelector'), {
  ssr: false,
});

// ============================================================================
// Props
// ============================================================================

interface ArticleRelationsSectionProps {
  locale: string;
  formData: ArticleFormData;
  isSubmitting: boolean;

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
}

// ============================================================================
// Component
// ============================================================================

export default function ArticleRelationsSection({
  locale,
  formData,
  isSubmitting,
  tagsOpen,
  setTagsOpen,
  selectedTags,
  setSelectedTags,
  topicsOpen,
  setTopicsOpen,
  selectedTopics,
  setSelectedTopics,
}: ArticleRelationsSectionProps) {
  return (
    <>
      {/* Tags Section */}
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow">
        <button
          type="button"
          onClick={() => setTagsOpen(!tagsOpen)}
          className="w-full flex items-center justify-between p-6 text-left"
        >
          <div className="flex items-center gap-3">
            <svg className="w-5 h-5 text-secondary dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
            </svg>
            <h2 className="text-xl font-semibold text-primary dark:text-primary-dark">Tag-uri</h2>
            {selectedTags.length > 0 && (
              <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                {selectedTags.length} tag-uri
              </span>
            )}
          </div>
          <svg className={`w-5 h-5 text-secondary dark:text-gray-400 transition-transform ${tagsOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        {tagsOpen && (
          <div className="px-6 pb-6 space-y-4">
            <TagSelector
              selectedTags={selectedTags}
              onChange={setSelectedTags}
              locale={locale}
              maxTags={10}
              disabled={isSubmitting}
            />
            <p className="text-xs text-secondary dark:text-gray-400">
              Adauga pana la 10 tag-uri relevante pentru articol. Scrie minim 2 caractere pentru a cauta.
            </p>
          </div>
        )}
      </div>

      {/* Topics Section */}
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow">
        <button
          type="button"
          onClick={() => setTopicsOpen(!topicsOpen)}
          className="w-full flex items-center justify-between p-6 text-left"
        >
          <div className="flex items-center gap-3">
            <svg className="w-5 h-5 text-secondary dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 3.5a1.5 1.5 0 013 0V4a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-.5a1.5 1.5 0 000 3h.5a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-.5a1.5 1.5 0 00-3 0v.5a1 1 0 01-1 1H6a1 1 0 01-1-1v-3a1 1 0 00-1-1h-.5a1.5 1.5 0 010-3H4a1 1 0 001-1V6a1 1 0 011-1h3a1 1 0 001-1v-.5z" />
            </svg>
            <h2 className="text-xl font-semibold text-primary dark:text-primary-dark">Topics</h2>
            {selectedTopics.length > 0 && (
              <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300">
                {selectedTopics.length} topics
              </span>
            )}
          </div>
          <svg className={`w-5 h-5 text-secondary dark:text-gray-400 transition-transform ${topicsOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        {topicsOpen && (
          <div className="px-6 pb-6 space-y-4">
            <TopicSelector
              selectedTopics={selectedTopics}
              onChange={setSelectedTopics}
              locale={locale}
              disabled={isSubmitting}
              articleTitle={formData.title}
              articleLead={formData.lead}
              articleContent={formData.content}
            />
            <p className="text-xs text-secondary dark:text-gray-400">
              Selecteaza topics tematice din arborele ierarhic. Foloseste butonul &quot;Sugereaza&quot; pentru detectie automata AI.
            </p>
          </div>
        )}
      </div>
    </>
  );
}
