'use client';

import { useState, useEffect } from 'react';
import type { TopicTreeNode, TopicFlatItem } from '@/lib/types/topic';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

interface TopicFormModalProps {
  topic?: TopicTreeNode | null;
  parentTopic?: TopicTreeNode | null;
  flatList: TopicFlatItem[];
  locale: string;
  onClose: () => void;
  onSaved: () => void;
}

export default function TopicFormModal({
  topic,
  parentTopic,
  flatList,
  locale,
  onClose,
  onSaved,
}: TopicFormModalProps) {
  const isEdit = !!topic;

  const [title, setTitle] = useState(topic?.title ?? '');
  const [description, setDescription] = useState(topic?.description ?? '');
  const [parentId, setParentId] = useState<number | ''>(
    parentTopic?.id ?? ''
  );
  const [position, setPosition] = useState(topic?.position ?? 0);
  const [isActive, setIsActive] = useState(topic?.isActive ?? true);
  const [error, setError] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (topic) {
      setTitle(topic.title);
      setDescription(topic.description ?? '');
      setPosition(topic.position);
      setIsActive(topic.isActive);
    }
  }, [topic]);

  const handleSave = async () => {
    if (!title.trim()) {
      setError('Titlul este obligatoriu');
      return;
    }

    setIsSaving(true);
    setError('');

    try {
      const body: Record<string, unknown> = {
        title: title.trim(),
        description: description.trim() || null,
        position,
        isActive,
      };

      if (parentId !== '') {
        body.parent = `/api/topics/${parentId}`;
      } else {
        body.parent = null;
      }

      const url = isEdit
        ? `${API_BASE_URL}/api/topics/${topic!.id}`
        : `${API_BASE_URL}/api/topics`;

      const res = await fetch(url, {
        method: isEdit ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          'Accept-Language': locale,
        },
        body: JSON.stringify(body),
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(
          err['hydra:description'] || err.detail || 'Failed to save topic'
        );
      }

      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save topic');
    } finally {
      setIsSaving(false);
    }
  };

  // Filter out the current topic and its children from parent options
  const parentOptions = flatList.filter((item) => {
    if (!isEdit) return true;
    return item.id !== topic!.id;
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-900/80">
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
        <h3 className="text-lg font-semibold text-primary dark:text-primary-dark mb-4">
          {isEdit ? 'Editeaza topic' : 'Topic nou'}
        </h3>

        {error && (
          <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400">
            {error}
          </div>
        )}

        <div className="space-y-4">
          {/* Title */}
          <div>
            <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-1">
              Titlu <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="ex: Alegeri Parlamentare"
              maxLength={255}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              autoFocus
            />
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-1">
              Descriere
            </label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Descriere optionala"
              rows={3}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
            />
          </div>

          {/* Parent */}
          <div>
            <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-1">
              Topic parinte
            </label>
            <select
              value={parentId}
              onChange={(e) =>
                setParentId(e.target.value ? parseInt(e.target.value, 10) : '')
              }
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">— Fara parinte (root) —</option>
              {parentOptions.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.indent}{item.title}
                </option>
              ))}
            </select>
          </div>

          {/* Position + Active */}
          <div className="flex gap-4">
            <div className="flex-1">
              <label className="block text-sm font-medium text-primary dark:text-primary-dark mb-1">
                Pozitie
              </label>
              <input
                type="number"
                value={position}
                onChange={(e) => setPosition(parseInt(e.target.value, 10) || 0)}
                min={0}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-surface dark:bg-gray-700 text-primary dark:text-primary-dark focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div className="flex items-end pb-2">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isActive}
                  onChange={(e) => setIsActive(e.target.checked)}
                  className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                />
                <span className="text-sm text-primary dark:text-primary-dark">
                  Activ
                </span>
              </label>
            </div>
          </div>
        </div>

        <div className="mt-6 flex justify-end gap-3">
          <button
            onClick={onClose}
            disabled={isSaving}
            className="px-4 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            Anuleaza
          </button>
          <button
            onClick={handleSave}
            disabled={isSaving}
            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {isSaving ? 'Se salveaza...' : isEdit ? 'Salveaza' : 'Creeaza'}
          </button>
        </div>
      </div>
    </div>
  );
}
