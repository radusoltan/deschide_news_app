'use client';

import { useState, useMemo } from 'react';

interface BulkArchiveFormProps {
  token: string;
  onComplete: () => void;
  locale?: string;
}

interface ArchiveResponse {
  message: string;
  total_archived: number;
  cutoff_date: string;
}

const translations = {
  ro: {
    title: 'Arhivare în Masă',
    ageLabel: 'Vechime minimă (ani)',
    preview: 'Articolele publicate înainte de',
    willBeArchived: 'vor fi arhivate.',
    warning: '⚠️ Această operațiune nu poate fi anulată în mod automat.',
    submitButton: 'Arhivează Articolele Vechi',
    confirmTitle: 'Sunteți sigur?',
    confirmMessage: 'Doriți să arhivați toate articolele vechi? Această acțiune va marca articolele ca arhivate și le va muta din listele active.',
    cancel: 'Anulează',
    confirm: 'Confirmă',
    processing: 'Se procesează...',
    successMessage: 'articole arhivate cu succes',
    errorMessage: 'Eroare la arhivarea articolelor',
  },
  en: {
    title: 'Bulk Archive',
    ageLabel: 'Minimum age (years)',
    preview: 'Articles published before',
    willBeArchived: 'will be archived.',
    warning: '⚠️ This operation cannot be automatically undone.',
    submitButton: 'Archive Old Articles',
    confirmTitle: 'Are you sure?',
    confirmMessage: 'Do you want to archive all old articles? This action will mark articles as archived and remove them from active lists.',
    cancel: 'Cancel',
    confirm: 'Confirm',
    processing: 'Processing...',
    successMessage: 'articles archived successfully',
    errorMessage: 'Error archiving articles',
  },
  ru: {
    title: 'Массовая архивация',
    ageLabel: 'Минимальный возраст (лет)',
    preview: 'Статьи, опубликованные до',
    willBeArchived: 'будут архивированы.',
    warning: '⚠️ Эту операцию нельзя автоматически отменить.',
    submitButton: 'Архивировать старые статьи',
    confirmTitle: 'Вы уверены?',
    confirmMessage: 'Вы хотите архивировать все старые статьи? Это действие пометит статьи как архивные и удалит их из активных списков.',
    cancel: 'Отмена',
    confirm: 'Подтвердить',
    processing: 'Обработка...',
    successMessage: 'статей успешно архивировано',
    errorMessage: 'Ошибка при архивации статей',
  },
};

export default function BulkArchiveForm({ token, onComplete, locale = 'ro' }: BulkArchiveFormProps) {
  const [yearsOld, setYearsOld] = useState(4);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showSuccessToast, setShowSuccessToast] = useState(false);
  const [successCount, setSuccessCount] = useState(0);

  const t = translations[locale as keyof typeof translations] || translations.ro;

  // Calculate cutoff date
  const cutoffDate = useMemo(() => {
    const date = new Date();
    date.setFullYear(date.getFullYear() - yearsOld);
    return date.toLocaleDateString(locale, { year: 'numeric', month: 'long', day: 'numeric' });
  }, [yearsOld, locale]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setShowConfirmModal(true);
  };

  const handleConfirm = async () => {
    setShowConfirmModal(false);
    setIsLoading(true);
    setError(null);

    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
      const response = await fetch(`${apiUrl}/api/admin/articles/archive-bulk`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
          years_old: yearsOld,
          batch_size: 100,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      const data: ArchiveResponse = await response.json();

      setSuccessCount(data.total_archived);
      setShowSuccessToast(true);

      // Hide toast after 5 seconds
      setTimeout(() => setShowSuccessToast(false), 5000);

      // Call completion callback
      onComplete();
    } catch (err) {
      setError(err instanceof Error ? err.message : t.errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold mb-4 flex items-center gap-2 text-gray-900 dark:text-white">
          <svg
            className="w-5 h-5 text-amber-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"
            />
          </svg>
          {t.title}
        </h3>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Year threshold input */}
          <div>
            <label
              htmlFor="years-old"
              className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
            >
              {t.ageLabel}
            </label>
            <input
              id="years-old"
              type="number"
              min={1}
              max={10}
              value={yearsOld}
              onChange={(e) => setYearsOld(Number(e.target.value))}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
              disabled={isLoading}
            />
          </div>

          {/* Preview */}
          <div className="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
            <p className="text-amber-800 dark:text-amber-200 text-sm">
              {t.preview} <strong>{cutoffDate}</strong> {t.willBeArchived}
            </p>
          </div>

          {/* Warning */}
          <div className="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
            <p className="text-red-800 dark:text-red-200 text-sm">
              {t.warning}
            </p>
          </div>

          {/* Error message */}
          {error && (
            <div className="p-4 bg-red-100 dark:bg-red-900/30 rounded-lg border border-red-300 dark:border-red-700">
              <p className="text-red-800 dark:text-red-200 text-sm">
                {error}
              </p>
            </div>
          )}

          {/* Submit button */}
          <button
            type="submit"
            disabled={isLoading}
            className="w-full bg-amber-600 hover:bg-amber-700 disabled:bg-amber-400 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center gap-2"
          >
            {isLoading ? (
              <>
                <svg
                  className="animate-spin h-5 w-5 text-white"
                  xmlns="http://www.w3.org/2000/svg"
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
                {t.processing}
              </>
            ) : (
              t.submitButton
            )}
          </button>
        </form>
      </div>

      {/* Confirmation Modal */}
      {showConfirmModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div className="flex items-center gap-3 mb-4">
              <svg
                className="w-8 h-8 text-amber-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
              <h3 className="text-xl font-bold text-gray-900 dark:text-white">
                {t.confirmTitle}
              </h3>
            </div>

            <p className="text-gray-700 dark:text-gray-300 mb-6">
              {t.confirmMessage}
            </p>

            <div className="flex gap-3">
              <button
                onClick={() => setShowConfirmModal(false)}
                className="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-medium rounded-lg transition-colors"
              >
                {t.cancel}
              </button>
              <button
                onClick={handleConfirm}
                className="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition-colors"
              >
                {t.confirm}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Success Toast */}
      {showSuccessToast && (
        <div className="fixed top-4 right-4 z-50 animate-slide-in">
          <div className="bg-green-600 text-white px-6 py-4 rounded-lg shadow-lg flex items-center gap-3">
            <svg
              className="w-6 h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
            <div>
              <p className="font-semibold">
                {successCount} {t.successMessage}
              </p>
            </div>
            <button
              onClick={() => setShowSuccessToast(false)}
              className="ml-4 text-white hover:text-green-100"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      )}

      <style jsx>{`
        @keyframes slide-in {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        .animate-slide-in {
          animation: slide-in 0.3s ease-out;
        }
      `}</style>
    </>
  );
}
