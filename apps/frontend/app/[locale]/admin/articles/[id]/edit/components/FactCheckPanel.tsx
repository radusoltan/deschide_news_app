'use client';

import { useState, useTransition } from 'react';
import { runFactCheck, type FactCheckResult } from '@/app/actions/factcheck';

interface FactCheckPanelProps {
  articleId: number;
}

export default function FactCheckPanel({ articleId }: FactCheckPanelProps) {
  const [result, setResult] = useState<FactCheckResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();
  const [customQuestion, setCustomQuestion] = useState('');

  const handleFactCheck = () => {
    startTransition(async () => {
      setError(null);
      setResult(null);

      const question = customQuestion.trim() || undefined;
      const response = await runFactCheck(articleId, question);

      if (response.error) {
        setError(response.error);
      } else if (response.data) {
        setResult(response.data);
      }
    });
  };

  return (
    <div className="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg">
      <div className="px-4 py-3 bg-gray-50 dark:bg-gray-800 rounded-t-lg border-b border-gray-200 dark:border-gray-700">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
          Fact-Check (NotebookLM)
        </h3>
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
          Verifică afirmațiile articolului folosind sursele din NotebookLM
        </p>
      </div>

      <div className="p-4 space-y-3">
        {/* Custom question input */}
        <div>
          <label
            htmlFor="factcheck-question"
            className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1"
          >
            Întrebare personalizată (opțional)
          </label>
          <textarea
            id="factcheck-question"
            value={customQuestion}
            onChange={(e) => setCustomQuestion(e.target.value)}
            placeholder="Lasă gol pentru verificare automată a afirmațiilor principale..."
            className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
            rows={2}
            disabled={isPending}
          />
        </div>

        {/* Action button */}
        <button
          onClick={handleFactCheck}
          disabled={isPending}
          className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed rounded-md transition-colors"
        >
          {isPending ? (
            <>
              <svg
                className="animate-spin h-4 w-4"
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
              Asking NotebookLM... (5-30s)
            </>
          ) : (
            'Run Fact-Check'
          )}
        </button>

        {/* Error */}
        {error && (
          <div className="p-3 text-sm bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800 rounded-md">
            {error}
          </div>
        )}

        {/* Result */}
        {result && (
          <div className="space-y-2">
            <div className="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md">
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-medium text-green-700 dark:text-green-300">
                  Rezultat fact-check
                </span>
                {result.cached && (
                  <span className="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded">
                    cached
                  </span>
                )}
              </div>
              <div className="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">
                {result.answer}
              </div>
            </div>
            <div className="flex gap-4 text-xs text-gray-500 dark:text-gray-400">
              <span>Topic ID: {result.topicId}</span>
              <span>
                Verificat: {new Date(result.checkedAt).toLocaleString('ro-RO')}
              </span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
