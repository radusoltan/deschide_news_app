import Link from 'next/link';

export default function NotFound() {
  return (
    <div data-testid="not-found" className="flex min-h-[50vh] flex-col items-center justify-center px-4">
      <h1 className="mb-4 text-4xl font-display font-bold">404</h1>
      <p className="mb-6 max-w-md text-center text-xl">
        Pagina căutată nu a fost găsită.
      </p>
      <Link
        href="/"
        className="rounded-lg border border-current px-6 py-3 transition-opacity hover:opacity-80"
      >
        Înapoi la pagina principală
      </Link>
    </div>
  );
}
