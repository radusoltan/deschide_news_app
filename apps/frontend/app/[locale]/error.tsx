'use client';

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center px-4">
      <h1 className="mb-4 text-2xl font-display font-bold">A apărut o eroare</h1>
      <p className="mb-6 max-w-md text-center text-lg">
        Ne cerem scuze pentru inconveniență. Vă rugăm să încercați din nou.
      </p>
      {error.digest ? (
        <p className="mb-6 text-sm text-gray-500">
          Cod eroare: {error.digest}
        </p>
      ) : null}
      <button
        onClick={reset}
        className="rounded-lg border border-current px-6 py-3 transition-opacity hover:opacity-80"
      >
        Încearcă din nou
      </button>
    </div>
  );
}
