'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import type { LiveText, LiveTextStatus } from '@/lib/types/livetext';

interface LiveTextFormProps {
  locale: string;
  initialData?: LiveText;
  isEdit?: boolean;
  categories?: any[];
}

export function LiveTextForm({ locale, initialData, isEdit = false, categories = [] }: LiveTextFormProps) {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [formData, setFormData] = useState({
    title: initialData?.title || '',
    description: initialData?.description || '',
    status: initialData?.status || 'draft' as LiveTextStatus,
    startTime: initialData?.startTime ? initialData.startTime.slice(0, 16) : '',
    endTime: initialData?.endTime ? initialData.endTime.slice(0, 16) : '',
    categoryId: initialData?.category?.id || '',
  });

  const texts = {
    ro: {
      title: 'Titlu',
      titlePlaceholder: 'Introduceți titlul Live Text-ului',
      description: 'Descriere',
      descriptionPlaceholder: 'Descriere opțională...',
      status: 'Status',
      category: 'Categorie',
      selectCategory: 'Selectați categoria',
      startTime: 'Data/Ora început',
      endTime: 'Data/Ora sfârșit',
      cancel: 'Anulare',
      save: 'Salvare',
      saving: 'Se salvează...',
      create: 'Creare Live Text',
      edit: 'Editare Live Text',
      required: 'Acest câmp este obligatoriu',
      statusDraft: 'Draft',
      statusLive: 'Live',
      statusPaused: 'Pauzat',
      statusEnded: 'Încheiat',
    },
    en: {
      title: 'Title',
      titlePlaceholder: 'Enter Live Text title',
      description: 'Description',
      descriptionPlaceholder: 'Optional description...',
      status: 'Status',
      category: 'Category',
      selectCategory: 'Select category',
      startTime: 'Start Date/Time',
      endTime: 'End Date/Time',
      cancel: 'Cancel',
      save: 'Save',
      saving: 'Saving...',
      create: 'Create Live Text',
      edit: 'Edit Live Text',
      required: 'This field is required',
      statusDraft: 'Draft',
      statusLive: 'Live',
      statusPaused: 'Paused',
      statusEnded: 'Ended',
    },
    ru: {
      title: 'Название',
      titlePlaceholder: 'Введите название Live Текста',
      description: 'Описание',
      descriptionPlaceholder: 'Необязательное описание...',
      status: 'Статус',
      category: 'Категория',
      selectCategory: 'Выберите категорию',
      startTime: 'Дата/Время начала',
      endTime: 'Дата/Время окончания',
      cancel: 'Отмена',
      save: 'Сохранить',
      saving: 'Сохранение...',
      create: 'Создать Live Текст',
      edit: 'Редактировать Live Текст',
      required: 'Это поле обязательно',
      statusDraft: 'Черновик',
      statusLive: 'В эфире',
      statusPaused: 'Приостановлено',
      statusEnded: 'Завершено',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
      const url = isEdit
        ? `${apiUrl}/api/live_texts/${initialData?.id}`
        : `${apiUrl}/api/live_texts`;

      const method = isEdit ? 'PUT' : 'POST';

      const payload: any = {
        title: formData.title,
        description: formData.description || null,
        status: formData.status,
        startTime: formData.startTime ? new Date(formData.startTime).toISOString() : null,
        endTime: formData.endTime ? new Date(formData.endTime).toISOString() : null,
      };

      // Add category if selected
      if (formData.categoryId) {
        payload.category = `/api/categories/${formData.categoryId}`;
      }

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/ld+json',
          'Accept': 'application/ld+json',
          'Accept-Language': locale,
        },
        credentials: 'include',
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.detail || errorData.message || 'Failed to save');
      }

      const data = await response.json();

      // Redirect to list page
      router.push(`/${locale}/admin/live-texts`);
      router.refresh();
    } catch (err) {
      console.error('Error saving live text:', err);
      setError(err instanceof Error ? err.message : 'Failed to save live text');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCancel = () => {
    router.back();
  };

  return (
    <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
      <form onSubmit={handleSubmit}>
        {/* Error Message */}
        {error && (
          <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <p className="text-sm text-red-600 dark:text-red-400">
              <strong>Error:</strong> {error}
            </p>
          </div>
        )}

        <div className="space-y-6">
          {/* Title */}
          <div>
            <label
              htmlFor="title"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              {t.title} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="title"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              placeholder={t.titlePlaceholder}
              required
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-surface dark:bg-surface-dark text-primary dark:text-primary-dark"
            />
          </div>

          {/* Description */}
          <div>
            <label
              htmlFor="description"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              {t.description}
            </label>
            <textarea
              id="description"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder={t.descriptionPlaceholder}
              rows={3}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-surface dark:bg-surface-dark text-primary dark:text-primary-dark"
            />
          </div>

          {/* Category */}
          <div>
            <label
              htmlFor="category"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              {t.category}
            </label>
            <select
              id="category"
              value={formData.categoryId}
              onChange={(e) => setFormData({ ...formData, categoryId: e.target.value })}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-surface dark:bg-surface-dark text-primary dark:text-primary-dark"
            >
              <option value="">{t.selectCategory}</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.title}
                </option>
              ))}
            </select>
          </div>

          {/* Status */}
          <div>
            <label
              htmlFor="status"
              className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
            >
              {t.status} <span className="text-red-500">*</span>
            </label>
            <select
              id="status"
              value={formData.status}
              onChange={(e) => setFormData({ ...formData, status: e.target.value as LiveTextStatus })}
              required
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-surface dark:bg-surface-dark text-primary dark:text-primary-dark"
            >
              <option value="draft">{t.statusDraft}</option>
              <option value="live">{t.statusLive}</option>
              <option value="paused">{t.statusPaused}</option>
              <option value="ended">{t.statusEnded}</option>
            </select>
          </div>

          {/* Date/Time Fields */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Start Time */}
            <div>
              <label
                htmlFor="startTime"
                className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
              >
                {t.startTime}
              </label>
              <input
                type="datetime-local"
                id="startTime"
                value={formData.startTime}
                onChange={(e) => setFormData({ ...formData, startTime: e.target.value })}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-surface dark:bg-surface-dark text-primary dark:text-primary-dark"
              />
            </div>

            {/* End Time */}
            <div>
              <label
                htmlFor="endTime"
                className="block text-sm font-medium text-primary dark:text-primary-dark mb-2"
              >
                {t.endTime}
              </label>
              <input
                type="datetime-local"
                id="endTime"
                value={formData.endTime}
                onChange={(e) => setFormData({ ...formData, endTime: e.target.value })}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-surface dark:bg-surface-dark text-primary dark:text-primary-dark"
              />
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="mt-8 flex items-center justify-end gap-4">
          <button
            type="button"
            onClick={handleCancel}
            disabled={isSubmitting}
            className="px-6 py-2 border border-gray-300 dark:border-gray-600 text-primary dark:text-primary-dark rounded-lg hover:bg-surface-sunken dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
          >
            {t.cancel}
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors disabled:opacity-50 flex items-center gap-2"
          >
            {isSubmitting && (
              <svg
                className="w-4 h-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  className="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  strokeWidth="4"
                />
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
            )}
            {isSubmitting ? t.saving : t.save}
          </button>
        </div>
      </form>
    </div>
  );
}
