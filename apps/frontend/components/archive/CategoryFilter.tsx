'use client';

/**
 * CategoryFilter Component
 * Displays categories with archived article counts
 * Vintage newspaper aesthetic with sepia tones
 */

import { useState, useEffect } from 'react';

interface CategoryData {
  id: number;
  name: string;
  slug: string;
  count: number;
}

interface CategoryFilterProps {
  selectedCategory: number | null;
  onCategoryChange: (categoryId: number | null) => void;
  locale?: string;
}

const translations = {
  ro: {
    allCategories: 'Toate categoriile',
    articles: 'articole',
    loading: 'Se încarcă...',
    noCategories: 'Nicio categorie găsită',
  },
  en: {
    allCategories: 'All categories',
    articles: 'articles',
    loading: 'Loading...',
    noCategories: 'No categories found',
  },
  ru: {
    allCategories: 'Все категории',
    articles: 'статей',
    loading: 'Загрузка...',
    noCategories: 'Категории не найдены',
  },
};

export default function CategoryFilter({
  selectedCategory,
  onCategoryChange,
  locale = 'ro',
}: CategoryFilterProps) {
  const [categories, setCategories] = useState<CategoryData[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const t = translations[locale as keyof typeof translations] || translations.ro;

  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
        const response = await fetch(`${apiUrl}/api/archive/categories?locale=${locale}`, {
          headers: {
            'Accept': 'application/json',
            'Accept-Language': locale,
          },
        });

        if (response.ok) {
          const data = await response.json();
          setCategories(data);
        }
      } catch (error) {
        console.error('Error fetching categories:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchCategories();
  }, [locale]);

  if (isLoading) {
    return (
      <div className="space-y-2">
        {[...Array(5)].map((_, i) => (
          <div
            key={i}
            className="animate-pulse h-10 bg-amber-100/50 rounded-lg"
            style={{ animationDelay: `${i * 80}ms` }}
          />
        ))}
      </div>
    );
  }

  const totalCount = categories.reduce((acc, cat) => acc + cat.count, 0);

  return (
    <nav className="space-y-1" aria-label="Category filter">
      {/* All Categories Option */}
      <button
        onClick={() => onCategoryChange(null)}
        className={`
          w-full text-left px-4 py-2.5 rounded-lg transition-all duration-300
          flex items-center justify-between group
          ${
            selectedCategory === null
              ? 'bg-gradient-to-r from-amber-600 to-amber-700 text-white shadow-lg shadow-amber-600/25'
              : 'bg-amber-50/80 hover:bg-amber-100 text-amber-900 hover:shadow-md'
          }
        `}
      >
        <span className="font-semibold">{t.allCategories}</span>
        <span
          className={`
            text-xs px-2 py-0.5 rounded-full
            ${selectedCategory === null ? 'bg-white/20' : 'bg-amber-200/50'}
          `}
        >
          {totalCount.toLocaleString()}
        </span>
      </button>

      {/* Category List */}
      <div className="pt-2 space-y-1 max-h-[300px] overflow-y-auto scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-amber-50">
        {categories.map((category, index) => (
          <button
            key={category.id}
            onClick={() => onCategoryChange(category.id)}
            className={`
              w-full text-left px-4 py-2.5 rounded-lg transition-all duration-300
              flex items-center justify-between group
              ${
                selectedCategory === category.id
                  ? 'bg-gradient-to-r from-amber-600 to-amber-700 text-white shadow-lg shadow-amber-600/25'
                  : 'bg-white/50 hover:bg-amber-50 text-amber-800 hover:shadow-sm border border-amber-100/50 hover:border-amber-200'
              }
            `}
          >
            <span className="font-medium truncate pr-2">{category.name}</span>
            <span
              className={`
                text-xs px-2 py-0.5 rounded-full flex-shrink-0
                ${
                  selectedCategory === category.id
                    ? 'bg-white/20 text-amber-100'
                    : 'bg-amber-100 text-amber-700 group-hover:bg-amber-200'
                }
              `}
            >
              {category.count.toLocaleString()}
            </span>
          </button>
        ))}
      </div>

      {categories.length === 0 && !isLoading && (
        <div className="text-center py-6 text-amber-600/70">
          <svg
            className="w-10 h-10 mx-auto mb-2 opacity-50"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
            />
          </svg>
          <p className="text-sm italic">{t.noCategories}</p>
        </div>
      )}
    </nav>
  );
}
