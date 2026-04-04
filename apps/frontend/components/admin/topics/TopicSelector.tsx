'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import type { TopicTreeNode, TopicSuggestion } from '@/lib/types/topic';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

interface SelectedTopic {
  id: number;
  title: string;
  path: string;
}

interface TopicSelectorProps {
  selectedTopics: SelectedTopic[];
  onChange: (topics: SelectedTopic[]) => void;
  locale: string;
  disabled?: boolean;
  articleTitle?: string;
  articleLead?: string;
  articleContent?: string;
}

export default function TopicSelector({
  selectedTopics,
  onChange,
  locale,
  disabled = false,
  articleTitle = '',
  articleLead = '',
  articleContent = '',
}: TopicSelectorProps) {
  const [tree, setTree] = useState<TopicTreeNode[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [filter, setFilter] = useState('');
  const [suggestions, setSuggestions] = useState<TopicSuggestion[]>([]);
  const [isDetecting, setIsDetecting] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Fetch topic tree on mount
  useEffect(() => {
    async function loadTree() {
      try {
        const res = await fetch(`${API_BASE_URL}/api/topics/tree`, {
          headers: { 'Accept-Language': locale },
        });
        if (res.ok) {
          setTree(await res.json());
        }
      } catch {
        // silent fail
      }
    }
    loadTree();
  }, [locale]);

  // Close dropdown on outside click
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const isSelected = useCallback(
    (id: number) => selectedTopics.some((t) => t.id === id),
    [selectedTopics]
  );

  const toggleTopic = (id: number, title: string, path: string) => {
    if (isSelected(id)) {
      onChange(selectedTopics.filter((t) => t.id !== id));
    } else {
      onChange([...selectedTopics, { id, title, path }]);
    }
  };

  const removeTopic = (id: number) => {
    onChange(selectedTopics.filter((t) => t.id !== id));
  };

  const handleDetect = async () => {
    if (!articleTitle && !articleLead) return;

    setIsDetecting(true);
    setSuggestions([]);
    setShowSuggestions(true);

    try {
      const res = await fetch(`${API_BASE_URL}/api/topics/detect`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept-Language': locale,
        },
        body: JSON.stringify({
          title: articleTitle,
          lead: articleLead,
          content: articleContent,
        }),
      });

      if (res.ok) {
        const data = await res.json();
        setSuggestions(data.suggestions || []);
      }
    } catch {
      // silent fail
    } finally {
      setIsDetecting(false);
    }
  };

  const acceptSuggestion = (suggestion: TopicSuggestion) => {
    if (!isSelected(suggestion.topic.id)) {
      onChange([
        ...selectedTopics,
        {
          id: suggestion.topic.id,
          title: suggestion.topic.title,
          path: suggestion.topic.path,
        },
      ]);
    }
  };

  const acceptAllSuggestions = () => {
    const newTopics = [...selectedTopics];
    for (const s of suggestions) {
      if (!newTopics.some((t) => t.id === s.topic.id)) {
        newTopics.push({
          id: s.topic.id,
          title: s.topic.title,
          path: s.topic.path,
        });
      }
    }
    onChange(newTopics);
  };

  // Filter tree nodes
  const matchesFilter = (node: TopicTreeNode): boolean => {
    if (!filter) return true;
    const lowerFilter = filter.toLowerCase();
    if (node.title.toLowerCase().includes(lowerFilter)) return true;
    if (node.children?.some(matchesFilter)) return true;
    return false;
  };

  const confidenceBadge = (confidence: string) => {
    switch (confidence) {
      case 'high':
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
      case 'medium':
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300';
      default:
        return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
    }
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2">
        {/* Selector trigger */}
        <div className="relative flex-1" ref={dropdownRef}>
          {/* Selected pills */}
          <div
            className={`flex flex-wrap gap-2 items-center px-3 py-2 border rounded-lg bg-surface dark:bg-gray-700 transition-colors min-h-[42px] ${
              disabled
                ? 'opacity-50 cursor-not-allowed border-gray-300 dark:border-gray-600'
                : 'border-gray-300 dark:border-gray-600 cursor-pointer'
            }`}
            onClick={() => !disabled && setIsOpen(!isOpen)}
          >
            {selectedTopics.map((topic) => (
              <span
                key={topic.id}
                className="inline-flex items-center gap-1 px-2.5 py-1 text-sm font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300"
              >
                <span className="text-xs text-purple-600 dark:text-purple-400 max-w-[200px] truncate">
                  {topic.path}
                </span>
                {!disabled && (
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      removeTopic(topic.id);
                    }}
                    className="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full hover:bg-purple-200 dark:hover:bg-purple-800 transition-colors"
                  >
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                )}
              </span>
            ))}
            {selectedTopics.length === 0 && (
              <span className="text-sm text-gray-400 dark:text-gray-500">
                Selecteaza topics...
              </span>
            )}
          </div>

          {/* Dropdown */}
          {isOpen && !disabled && (
            <div className="absolute z-50 mt-1 w-full bg-surface dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-72 overflow-y-auto">
              {/* Filter input */}
              <div className="sticky top-0 bg-surface dark:bg-gray-700 p-2 border-b border-gray-100 dark:border-gray-600">
                <input
                  type="text"
                  value={filter}
                  onChange={(e) => setFilter(e.target.value)}
                  placeholder="Filtreaza topics..."
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded bg-surface dark:bg-gray-800 text-primary dark:text-primary-dark focus:ring-1 focus:ring-blue-500 focus:border-transparent"
                  autoFocus
                  onClick={(e) => e.stopPropagation()}
                />
              </div>

              {/* Tree */}
              <div className="p-1">
                {tree.filter(matchesFilter).map((node) => (
                  <TreeCheckNode
                    key={node.id}
                    node={node}
                    depth={0}
                    isSelected={isSelected}
                    onToggle={toggleTopic}
                    filter={filter}
                    matchesFilter={matchesFilter}
                  />
                ))}
                {tree.filter(matchesFilter).length === 0 && (
                  <div className="px-4 py-3 text-sm text-secondary dark:text-gray-400 text-center">
                    {filter ? `Niciun topic pentru "${filter}"` : 'Niciun topic disponibil'}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* AI Detect button */}
        <button
          type="button"
          onClick={handleDetect}
          disabled={disabled || isDetecting || (!articleTitle && !articleLead)}
          className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-800 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/50 disabled:opacity-50 transition-colors whitespace-nowrap"
          title="Sugereaza Topics (AI)"
        >
          {isDetecting ? (
            <svg className="animate-spin w-4 h-4" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          ) : (
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
            </svg>
          )}
          Sugereaza
        </button>
      </div>

      {/* AI Suggestions */}
      {showSuggestions && (
        <div className="border border-purple-200 dark:border-purple-800 rounded-lg bg-purple-50/50 dark:bg-purple-900/20 p-3">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-purple-700 dark:text-purple-300 uppercase tracking-wide">
              Sugestii AI
            </span>
            <div className="flex items-center gap-2">
              {suggestions.length > 0 && (
                <button
                  type="button"
                  onClick={acceptAllSuggestions}
                  className="text-xs text-purple-600 dark:text-purple-400 hover:underline"
                >
                  Accepta toate
                </button>
              )}
              <button
                type="button"
                onClick={() => {
                  setShowSuggestions(false);
                  setSuggestions([]);
                }}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          {isDetecting && (
            <div className="text-sm text-secondary dark:text-gray-400 text-center py-2">
              Se analizeaza articolul...
            </div>
          )}

          {!isDetecting && suggestions.length === 0 && (
            <div className="text-sm text-secondary dark:text-gray-400 text-center py-2">
              Nicio sugestie gasita.
            </div>
          )}

          {suggestions.map((s) => (
            <div
              key={s.topic.id}
              className="flex items-center gap-2 py-1.5"
            >
              <input
                type="checkbox"
                checked={isSelected(s.topic.id)}
                onChange={() => acceptSuggestion(s)}
                className="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
              />
              <span className="text-sm text-primary dark:text-primary-dark flex-1">
                {s.topic.path}
              </span>
              <span
                className={`text-xs px-1.5 py-0.5 rounded-full font-medium ${confidenceBadge(s.confidence)}`}
              >
                {s.confidence}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// Recursive tree node with checkbox
interface TreeCheckNodeProps {
  node: TopicTreeNode;
  depth: number;
  isSelected: (id: number) => boolean;
  onToggle: (id: number, title: string, path: string) => void;
  filter: string;
  matchesFilter: (node: TopicTreeNode) => boolean;
  parentPath?: string;
}

function TreeCheckNode({
  node,
  depth,
  isSelected,
  onToggle,
  filter,
  matchesFilter,
  parentPath = '',
}: TreeCheckNodeProps) {
  const [expanded, setExpanded] = useState(!!filter);
  const currentPath = parentPath ? `${parentPath} > ${node.title}` : node.title;
  const hasChildren = node.children && node.children.length > 0;
  const filteredChildren = hasChildren ? node.children!.filter(matchesFilter) : [];

  if (!node.isActive) return null;

  return (
    <div>
      <div
        className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-600/50"
        style={{ paddingLeft: `${depth * 16 + 8}px` }}
      >
        {hasChildren ? (
          <button
            type="button"
            onClick={() => setExpanded(!expanded)}
            className="w-4 h-4 flex items-center justify-center text-gray-400"
          >
            <svg
              className={`w-3 h-3 transition-transform ${expanded ? 'rotate-90' : ''}`}
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                clipRule="evenodd"
              />
            </svg>
          </button>
        ) : (
          <span className="w-4" />
        )}

        <label className="flex items-center gap-2 flex-1 cursor-pointer">
          <input
            type="checkbox"
            checked={isSelected(node.id)}
            onChange={() => onToggle(node.id, node.title, currentPath)}
            className="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
          />
          <span className="text-sm text-primary dark:text-primary-dark">
            {node.title}
          </span>
        </label>
      </div>

      {expanded && filteredChildren.map((child) => (
        <TreeCheckNode
          key={child.id}
          node={child}
          depth={depth + 1}
          isSelected={isSelected}
          onToggle={onToggle}
          filter={filter}
          matchesFilter={matchesFilter}
          parentPath={currentPath}
        />
      ))}
    </div>
  );
}
