'use client';

import { useState, useRef, useEffect, useCallback } from 'react';
import type { Tag } from '@/lib/types/tag';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

interface TagSelectorProps {
  selectedTags: Tag[];
  onChange: (tags: Tag[]) => void;
  locale: string;
  maxTags?: number;
  disabled?: boolean;
}

export default function TagSelector({
  selectedTags,
  onChange,
  locale,
  maxTags = 10,
  disabled = false,
}: TagSelectorProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Tag[]>([]);
  const [popularTags, setPopularTags] = useState<Tag[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [highlightIndex, setHighlightIndex] = useState(-1);
  const inputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Fetch popular tags on mount
  useEffect(() => {
    async function loadPopular() {
      try {
        const headers: HeadersInit = { 'Content-Type': 'application/json' };
        if (locale) headers['Accept-Language'] = locale;
        const res = await fetch(`${API_BASE_URL}/api/tags/popular?limit=15`, { headers });
        if (res.ok) {
          const data = await res.json();
          setPopularTags(data['hydra:member'] || []);
        }
      } catch {
        // silent fail
      }
    }
    loadPopular();
  }, [locale]);

  // Close dropdown on outside click
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(e.target as Node) &&
        inputRef.current &&
        !inputRef.current.contains(e.target as Node)
      ) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const searchTags = useCallback(
    async (q: string) => {
      if (q.trim().length < 2) {
        setResults([]);
        return;
      }
      setIsLoading(true);
      try {
        const headers: HeadersInit = { 'Content-Type': 'application/json' };
        if (locale) headers['Accept-Language'] = locale;
        const res = await fetch(
          `${API_BASE_URL}/api/tags/search?q=${encodeURIComponent(q.trim())}&limit=15`,
          { headers }
        );
        if (res.ok) {
          const data = await res.json();
          setResults(data['hydra:member'] || []);
        }
      } catch {
        setResults([]);
      } finally {
        setIsLoading(false);
      }
    },
    [locale]
  );

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setQuery(value);
    setHighlightIndex(-1);

    if (debounceRef.current) clearTimeout(debounceRef.current);

    if (value.trim().length >= 2) {
      debounceRef.current = setTimeout(() => searchTags(value), 300);
    } else {
      setResults([]);
    }
  };

  const handleSelect = (tag: Tag) => {
    if (selectedTags.length >= maxTags) return;
    if (selectedTags.some((t) => t.id === tag.id)) return;
    onChange([...selectedTags, tag]);
    setQuery('');
    setResults([]);
    setHighlightIndex(-1);
    inputRef.current?.focus();
  };

  const handleRemove = (tagId: number) => {
    onChange(selectedTags.filter((t) => t.id !== tagId));
  };

  // Dropdown items: search results or popular (filtered out already-selected)
  const selectedIds = new Set(selectedTags.map((t) => t.id));
  const displayItems =
    query.trim().length >= 2
      ? results.filter((t) => !selectedIds.has(t.id))
      : popularTags.filter((t) => !selectedIds.has(t.id));

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      setIsOpen(false);
      return;
    }

    if (e.key === 'Backspace' && query === '' && selectedTags.length > 0) {
      handleRemove(selectedTags[selectedTags.length - 1].id);
      return;
    }

    if (!isOpen || displayItems.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlightIndex((prev) =>
        prev < displayItems.length - 1 ? prev + 1 : 0
      );
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlightIndex((prev) =>
        prev > 0 ? prev - 1 : displayItems.length - 1
      );
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (highlightIndex >= 0 && highlightIndex < displayItems.length) {
        handleSelect(displayItems[highlightIndex]);
      }
    }
  };

  const atMax = selectedTags.length >= maxTags;

  return (
    <div className="relative">
      {/* Selected tags + input */}
      <div
        className={`flex flex-wrap gap-2 items-center px-3 py-2 border rounded-lg bg-surface dark:bg-gray-700 transition-colors ${
          disabled
            ? 'opacity-50 cursor-not-allowed border-gray-300 dark:border-gray-600'
            : 'border-gray-300 dark:border-gray-600 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-transparent'
        }`}
      >
        {selectedTags.map((tag) => (
          <span
            key={tag.id}
            className="inline-flex items-center gap-1 px-2.5 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"
          >
            #{tag.name}
            {!disabled && (
              <button
                type="button"
                onClick={() => handleRemove(tag.id)}
                className="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors"
                aria-label={`Remove tag ${tag.name}`}
              >
                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            )}
          </span>
        ))}

        {!atMax && !disabled && (
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={handleInputChange}
            onFocus={() => setIsOpen(true)}
            onKeyDown={handleKeyDown}
            placeholder={selectedTags.length === 0 ? 'Cauta tag-uri...' : 'Adauga tag...'}
            className="flex-1 min-w-[120px] bg-transparent border-none outline-none text-sm text-primary dark:text-primary-dark placeholder:text-gray-400 dark:placeholder:text-gray-500 p-0"
            role="combobox"
            aria-expanded={isOpen}
            aria-haspopup="listbox"
            aria-label="Search tags"
            disabled={disabled}
          />
        )}

        {atMax && (
          <span className="text-xs text-secondary dark:text-gray-400 py-1">
            Maxim {maxTags} tag-uri
          </span>
        )}
      </div>

      {/* Dropdown */}
      {isOpen && !disabled && !atMax && (
        <div
          ref={dropdownRef}
          className="absolute z-50 mt-1 w-full bg-surface dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto"
          role="listbox"
        >
          {isLoading && (
            <div className="px-4 py-3 text-sm text-secondary dark:text-gray-400 text-center">
              Se cauta...
            </div>
          )}

          {!isLoading && displayItems.length === 0 && query.trim().length >= 2 && (
            <div className="px-4 py-3 text-sm text-secondary dark:text-gray-400 text-center">
              Niciun tag gasit pentru &quot;{query}&quot;
            </div>
          )}

          {!isLoading && displayItems.length === 0 && query.trim().length < 2 && popularTags.length === 0 && (
            <div className="px-4 py-3 text-sm text-secondary dark:text-gray-400 text-center">
              Scrie minim 2 caractere pentru a cauta
            </div>
          )}

          {!isLoading && query.trim().length < 2 && displayItems.length > 0 && (
            <div className="px-4 py-2 text-xs font-medium text-secondary dark:text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-gray-600">
              Tag-uri populare
            </div>
          )}

          {!isLoading &&
            displayItems.map((tag, index) => (
              <button
                key={tag.id}
                type="button"
                onClick={() => handleSelect(tag)}
                onMouseEnter={() => setHighlightIndex(index)}
                className={`w-full text-left px-4 py-2.5 text-sm flex items-center justify-between transition-colors ${
                  index === highlightIndex
                    ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                    : 'text-primary dark:text-primary-dark hover:bg-gray-50 dark:hover:bg-gray-600'
                }`}
                role="option"
                aria-selected={index === highlightIndex}
              >
                <span>
                  <span className="text-secondary dark:text-gray-400">#</span>
                  {tag.name}
                </span>
                {tag.usageCount > 0 && (
                  <span className="text-xs text-secondary dark:text-gray-400">
                    {tag.usageCount} articole
                  </span>
                )}
              </button>
            ))}
        </div>
      )}
    </div>
  );
}
