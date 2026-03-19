import Link from 'next/link';
import CreateShortLinkForm from '../components/CreateShortLinkForm';
import { getAccessToken } from '@/lib/auth/session';

interface NewShortLinkPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function NewShortLinkPage({
  params,
}: NewShortLinkPageProps) {
  const { locale } = await params;
  const accessToken = await getAccessToken();

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-6">
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
          <Link
            href={`/${locale}/admin/short-links`}
            className="hover:text-blue-600 dark:hover:text-blue-400"
          >
            Linkuri Scurte
          </Link>
          <span>/</span>
          <span className="text-gray-900 dark:text-white">Creează Nou</span>
        </div>

        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Creează Link Scurt
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Generați un link scurt pentru orice URL. Puteți specifica un cod
          personalizat sau lăsa sistemul să genereze unul automat.
        </p>
      </div>

      {/* Info Card */}
      <div className="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div className="flex gap-3">
          <div className="flex-shrink-0">
            <svg
              className="w-5 h-5 text-blue-600 dark:text-blue-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div className="flex-1">
            <h3 className="text-sm font-medium text-blue-900 dark:text-blue-300">
              Beneficiile linkurilor scurte
            </h3>
            <ul className="mt-2 text-sm text-blue-800 dark:text-blue-400 space-y-1">
              <li>✓ Mai ușor de partajat pe rețelele sociale</li>
              <li>✓ Statistici detaliate despre clicuri și vizitatori</li>
              <li>✓ URL-uri memorabile și profesionale</li>
              <li>✓ Urmărire complete a referrers și dispozitive</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Form */}
      <CreateShortLinkForm locale={locale} accessToken={accessToken} />
    </div>
  );
}
