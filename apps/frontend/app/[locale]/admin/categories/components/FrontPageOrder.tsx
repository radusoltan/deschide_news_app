'use client';

import { useState, useTransition } from 'react';
import { reorderFrontPageCategoriesAction } from '@/app/actions/categories';

interface FrontPageCategory {
  id: number;
  title: string;
  slug: string;
  frontPagePosition: number;
}

interface FrontPageOrderProps {
  categories: FrontPageCategory[];
  locale: string;
}

export default function FrontPageOrder({ categories: initial, locale }: FrontPageOrderProps) {
  const [items, setItems] = useState<FrontPageCategory[]>(
    [...initial].sort((a, b) => a.frontPagePosition - b.frontPagePosition)
  );
  const [dragIndex, setDragIndex] = useState<number | null>(null);
  const [overIndex, setOverIndex] = useState<number | null>(null);
  const [isPending, startTransition] = useTransition();
  const [status, setStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const hasChanges = items.some((item, idx) => {
    const original = initial.find((c) => c.id === item.id);
    return original ? original.frontPagePosition !== idx + 1 : true;
  });

  function handleDragStart(index: number) {
    setDragIndex(index);
    setStatus(null);
  }

  function handleDragOver(e: React.DragEvent, index: number) {
    e.preventDefault();
    setOverIndex(index);
  }

  function handleDrop(index: number) {
    if (dragIndex === null || dragIndex === index) {
      setDragIndex(null);
      setOverIndex(null);
      return;
    }

    const updated = [...items];
    const [moved] = updated.splice(dragIndex, 1);
    updated.splice(index, 0, moved);
    setItems(updated);
    setDragIndex(null);
    setOverIndex(null);
  }

  function moveItem(from: number, direction: -1 | 1) {
    const to = from + direction;
    if (to < 0 || to >= items.length) return;
    const updated = [...items];
    [updated[from], updated[to]] = [updated[to], updated[from]];
    setItems(updated);
    setStatus(null);
  }

  function handleSave() {
    const positions = items.map((item, idx) => ({
      id: item.id,
      frontPagePosition: idx + 1,
    }));

    startTransition(async () => {
      const result = await reorderFrontPageCategoriesAction(locale, positions);
      if (result.success) {
        setStatus({ type: 'success', message: result.message || 'Saved' });
      } else {
        setStatus({ type: 'error', message: result.errors?._form?.[0] || 'Error' });
      }
    });
  }

  if (items.length === 0) {
    return (
      <div className="p-4 text-sm text-secondary dark:text-gray-400">
        No categories are marked for the front page.
      </div>
    );
  }

  return (
    <div>
      {/* Header */}
      <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold text-primary dark:text-primary-dark">
            Ordine pe Pagina Principala
          </h2>
          <p className="text-sm text-secondary dark:text-gray-400">
            Trage pentru a reordona categoriile afisate pe homepage
          </p>
        </div>
        <div className="flex items-center gap-3">
          {status && (
            <span
              className={`text-sm font-medium ${
                status.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
              }`}
            >
              {status.message}
            </span>
          )}
          <button
            onClick={handleSave}
            disabled={isPending || !hasChanges}
            className="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isPending ? 'Se salveaza...' : 'Salveaza ordinea'}
          </button>
        </div>
      </div>

      {/* Sortable list */}
      <ul className="divide-y divide-gray-200 dark:divide-gray-700">
        {items.map((item, index) => (
          <li
            key={item.id}
            draggable
            onDragStart={() => handleDragStart(index)}
            onDragOver={(e) => handleDragOver(e, index)}
            onDragEnd={() => { setDragIndex(null); setOverIndex(null); }}
            onDrop={() => handleDrop(index)}
            className={`flex items-center gap-3 px-4 py-3 transition-colors cursor-grab active:cursor-grabbing ${
              dragIndex === index
                ? 'opacity-50 bg-blue-50 dark:bg-blue-900/20'
                : overIndex === index
                ? 'bg-blue-50 dark:bg-blue-900/20 border-t-2 border-blue-500'
                : 'hover:bg-surface-sunken dark:hover:bg-gray-700/50'
            }`}
          >
            {/* Drag handle */}
            <span className="text-gray-400 dark:text-secondary flex-shrink-0 select-none" title="Drag to reorder">
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z" />
              </svg>
            </span>

            {/* Position number */}
            <span className="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-sm font-bold text-primary dark:text-primary-dark flex-shrink-0">
              {index + 1}
            </span>

            {/* Title */}
            <span className="flex-1 font-medium text-primary dark:text-primary-dark">
              {item.title}
            </span>

            {/* Slug */}
            <span className="text-xs text-gray-400 dark:text-secondary font-mono">
              /{item.slug}
            </span>

            {/* Up/Down arrows */}
            <div className="flex flex-col gap-0.5 flex-shrink-0">
              <button
                onClick={() => moveItem(index, -1)}
                disabled={index === 0}
                className="p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-primary-dark disabled:opacity-30 disabled:cursor-not-allowed"
                title="Move up"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                </svg>
              </button>
              <button
                onClick={() => moveItem(index, 1)}
                disabled={index === items.length - 1}
                className="p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-primary-dark disabled:opacity-30 disabled:cursor-not-allowed"
                title="Move down"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
