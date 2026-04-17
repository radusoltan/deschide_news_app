'use client';

import { useMemo, useState, useTransition } from 'react';
import {
  runFactCheck,
  type FactCheckResult,
  type FactCheckStatus,
} from '@/app/actions/factcheck';

interface FactCheckPanelProps {
  articleId: number;
}

interface HistoryItem {
  question: string;
  answer: string;
  status: FactCheckStatus;
  timestamp: number;
}

const QUESTION_MIN_LENGTH = 10;
const QUESTION_MAX_LENGTH = 500;
const HISTORY_LIMIT = 5;

const ERROR_MESSAGES: Partial<Record<FactCheckStatus, string>> = {
  disabled:
    'Funcționalitatea de fact-check este dezactivată din configurare.',
  unavailable:
    'Serviciul NotebookLM este temporar indisponibil. Încearcă din nou mai târziu.',
  no_notebook:
    'Topicul nu are încă notebook sincronizat. Încearcă după următoarea rulare a sync-ului (~6h).',
  no_topics:
    'Articolul nu are topicuri atribuite. Atribuie un topic cu notebook NotebookLM și reîncearcă.',
  failed:
    'NotebookLM nu a putut genera un răspuns. Încearcă să reformulezi întrebarea.',
  rate_limited:
    'Prea multe cereri. Așteaptă un minut și încearcă din nou.',
  timeout:
    'Cererea a durat prea mult (>60s). NotebookLM poate fi suprasolicitat.',
  validation:
    'Verifică întrebarea — trebuie să aibă între 10 și 500 de caractere.',
  not_found: 'Articolul nu a fost găsit.',
  network: 'Eroare de rețea. Verifică conexiunea.',
};

export default function FactCheckPanel({ articleId }: FactCheckPanelProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [customQuestion, setCustomQuestion] = useState('');
  const [result, setResult] = useState<FactCheckResult | null>(null);
  const [resultStatus, setResultStatus] = useState<FactCheckStatus | null>(null);
  const [errorStatus, setErrorStatus] = useState<FactCheckStatus | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [history, setHistory] = useState<HistoryItem[]>([]);
  const [isPending, startTransition] = useTransition();

  const charCount = customQuestion.length;
  const trimmedLength = customQuestion.trim().length;
  const isQuestionProvided = trimmedLength > 0;
  const isQuestionValid =
    !isQuestionProvided ||
    (trimmedLength >= QUESTION_MIN_LENGTH && trimmedLength <= QUESTION_MAX_LENGTH);
  const canSubmit = !isPending && isQuestionValid;

  const charCountClass = useMemo(() => {
    if (!isQuestionProvided) return 'text-gray-500 dark:text-gray-400';
    if (trimmedLength < QUESTION_MIN_LENGTH || trimmedLength > QUESTION_MAX_LENGTH) {
      return 'text-red-600 dark:text-red-400';
    }
    return 'text-gray-500 dark:text-gray-400';
  }, [isQuestionProvided, trimmedLength]);

  const handleFactCheck = () => {
    if (!canSubmit) return;
    startTransition(async () => {
      setErrorStatus(null);
      setErrorMessage(null);
      setResult(null);
      setResultStatus(null);

      const question = customQuestion.trim() || undefined;
      const response = await runFactCheck(articleId, question);

      if (response.data) {
        setResult(response.data);
        setResultStatus(response.status);
        setHistory((prev) => {
          const next: HistoryItem[] = [
            {
              question: response.data?.question ?? question ?? '',
              answer: response.data?.answer ?? '',
              status: response.status,
              timestamp: Date.now(),
            },
            ...prev,
          ];
          return next.slice(0, HISTORY_LIMIT);
        });
        return;
      }

      const message =
        response.violations?.[0]?.message ??
        response.error ??
        ERROR_MESSAGES[response.status] ??
        'Eroare necunoscută.';
      setErrorStatus(response.status);
      setErrorMessage(message);
    });
  };

  const replayHistoryItem = (item: HistoryItem) => {
    setCustomQuestion(item.question);
    setResult({
      answer: item.answer,
      question: item.question,
      topicId: 0,
      notebookId: null,
      cached: item.status === 'cached',
      checkedAt: new Date(item.timestamp).toISOString(),
    });
    setResultStatus(item.status);
    setErrorStatus(null);
    setErrorMessage(null);
  };

  const errorBannerClass =
    errorStatus === 'no_notebook' || errorStatus === 'no_topics'
      ? 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 border-yellow-200 dark:border-yellow-800'
      : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border-red-200 dark:border-red-800';

  return (
    <div className="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg">
      <button
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        aria-expanded={isOpen}
        aria-controls="factcheck-body"
        className="flex w-full items-center justify-between px-4 py-3 text-left rounded-t-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
      >
        <div>
          <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
            Fact-Check (NotebookLM)
          </h3>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Verifică afirmațiile articolului folosind sursele din NotebookLM
          </p>
        </div>
        <span
          aria-hidden="true"
          className="text-gray-500 dark:text-gray-400 text-xs ml-4"
        >
          {isOpen ? '▼' : '▶'}
        </span>
      </button>

      {isOpen && (
        <div
          id="factcheck-body"
          className="p-4 space-y-3 border-t border-gray-200 dark:border-gray-700"
        >
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
            <div className={`text-xs mt-1 ${charCountClass}`}>
              <span data-testid="factcheck-char-counter">
                {charCount} / {QUESTION_MAX_LENGTH}
              </span>
              {isQuestionProvided && trimmedLength < QUESTION_MIN_LENGTH && (
                <span className="ml-2">(min {QUESTION_MIN_LENGTH})</span>
              )}
            </div>
          </div>

          <button
            onClick={handleFactCheck}
            disabled={!canSubmit}
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed rounded-md transition-colors"
          >
            {isPending ? (
              <>
                <svg
                  className="animate-spin h-4 w-4"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
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

          <div aria-live="polite" aria-atomic="true" className="space-y-2">
            {errorStatus && errorMessage && (
              <div
                data-testid="factcheck-error"
                data-status={errorStatus}
                className={`p-3 text-sm border rounded-md ${errorBannerClass}`}
              >
                {errorMessage}
              </div>
            )}

            {result && (
              <div className="space-y-2">
                <div className="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-medium text-green-700 dark:text-green-300">
                      Rezultat fact-check
                    </span>
                    {(resultStatus === 'cached' || result.cached) && (
                      <span
                        data-testid="factcheck-cached-badge"
                        className="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded"
                      >
                        🕒 cached
                      </span>
                    )}
                  </div>
                  <div className="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">
                    {result.answer}
                  </div>
                </div>
                <div className="flex gap-4 text-xs text-gray-500 dark:text-gray-400">
                  {result.topicId > 0 && <span>Topic ID: {result.topicId}</span>}
                  <span>
                    Verificat:{' '}
                    {new Date(result.checkedAt).toLocaleString('ro-RO')}
                  </span>
                </div>
              </div>
            )}
          </div>

          {history.length > 0 && (
            <details className="mt-4" data-testid="factcheck-history">
              <summary className="cursor-pointer text-xs text-gray-600 dark:text-gray-400">
                Întrebări recente ({history.length})
              </summary>
              <ul className="mt-2 space-y-1">
                {history.map((item) => (
                  <li key={item.timestamp}>
                    <button
                      type="button"
                      onClick={() => replayHistoryItem(item)}
                      className="text-left text-xs text-blue-600 hover:underline dark:text-blue-400"
                    >
                      {item.question.length > 60
                        ? `${item.question.substring(0, 60)}…`
                        : item.question}
                    </button>
                  </li>
                ))}
              </ul>
            </details>
          )}
        </div>
      )}
    </div>
  );
}
