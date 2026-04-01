'use client';

import { useState, useCallback } from 'react';
import { Button, Spinner } from 'flowbite-react';
import { translateEntityAction } from '@/app/actions/translations';

interface TranslateButtonProps {
  entityType: 'article' | 'category' | 'author';
  entityId: number;
  currentStatus?: string | null;
}

type ButtonState = 'idle' | 'loading' | 'success' | 'error';

export default function TranslateButton({
  entityType,
  entityId,
  currentStatus,
}: TranslateButtonProps) {
  const [state, setState] = useState<ButtonState>('idle');
  const [errorMessage, setErrorMessage] = useState<string>('');

  const handleClick = useCallback(async () => {
    setState('loading');
    setErrorMessage('');

    const result = await translateEntityAction(entityType, entityId);

    if (result.success) {
      setState('success');
      setTimeout(() => setState('idle'), 3000);
    } else {
      setErrorMessage(result.error || 'Translation failed');
      setState('error');
      setTimeout(() => setState('idle'), 5000);
    }
  }, [entityType, entityId]);

  const isDisabled = state === 'loading' || currentStatus === 'in_progress';
  const hasBeenTranslated = currentStatus === 'completed' || currentStatus === 'needs_review';

  return (
    <div className="inline-flex items-center gap-2">
      <Button
        size="sm"
        color={state === 'success' ? 'success' : state === 'error' ? 'failure' : 'light'}
        onClick={handleClick}
        disabled={isDisabled}
      >
        {state === 'loading' && (
          <>
            <Spinner size="sm" className="mr-2" />
            Se traduce...
          </>
        )}

        {state === 'success' && (
          <>
            <svg className="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Tradus cu succes!
          </>
        )}

        {state === 'error' && (
          <>
            <svg className="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Eroare
          </>
        )}

        {state === 'idle' && (
          <>
            <svg className="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
            </svg>
            {hasBeenTranslated ? 'Retraduce' : 'Traducere automată'}
          </>
        )}
      </Button>

      {currentStatus === 'in_progress' && state === 'idle' && (
        <span className="text-xs text-amber-600 dark:text-amber-400">
          Traducere în curs...
        </span>
      )}

      {state === 'error' && errorMessage && (
        <span className="text-xs text-red-600 dark:text-red-400">
          {errorMessage}
        </span>
      )}

      {currentStatus && state === 'idle' && currentStatus !== 'in_progress' && (
        <span className={`text-xs ${
          currentStatus === 'completed'
            ? 'text-green-600 dark:text-green-400'
            : currentStatus === 'needs_review'
              ? 'text-amber-600 dark:text-amber-400'
              : currentStatus === 'failed'
                ? 'text-red-600 dark:text-red-400'
                : 'text-gray-500'
        }`}>
          {currentStatus === 'completed' && 'Tradus'}
          {currentStatus === 'needs_review' && 'Necesită revizie'}
          {currentStatus === 'failed' && 'Eșuat'}
        </span>
      )}
    </div>
  );
}
