import Link from 'next/link';
import { apiRequest } from '@/lib/api/client';
import { getAccessToken } from '@/lib/dal';

interface ThumbnailProfile {
  id: number;
  name: string;
  displayName: string;
  description: string;
  width: number;
  height: number;
  aspectRatio: string;
  mode: string;
  quality: number;
  category: string;
  dimensionsLabel: string;
}

interface ThumbnailProfilesPageProps {
  params: Promise<{ locale: string }>;
}

export default async function ThumbnailProfilesPage({ params }: ThumbnailProfilesPageProps) {
  const { locale } = await params;

  let profiles: ThumbnailProfile[] = [];
  let error: string | null = null;

  try {
    const token = await getAccessToken();
    if (token) {
      const data = await apiRequest<any>('/api/thumbnail_profiles', {
        token,
        next: { revalidate: 300 },
      });
      profiles = data?.['hydra:member'] || data?.member || (Array.isArray(data) ? data : []);
    }
  } catch (err) {
    console.error('Failed to fetch thumbnail profiles:', err);
    error = err instanceof Error ? err.message : 'Failed to load thumbnail profiles';
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
            <Link href={`/${locale}/admin`} className="hover:text-blue-600">
              Dashboard
            </Link>
            <span>/</span>
            <span>Thumbnail Profiles</span>
          </div>
          <h1 className="text-3xl font-bold text-primary dark:text-primary-dark">
            Thumbnail Profiles
          </h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Configure image thumbnail generation profiles
          </p>
        </div>
      </div>

      {/* Error */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">
            <strong>Error:</strong> {error}
          </p>
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Total Profiles</p>
          <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">{profiles.length}</p>
        </div>
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Categories</p>
          <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">
            {new Set(profiles.map(p => p.category)).size}
          </p>
        </div>
        <div className="bg-surface dark:bg-surface-dark rounded-lg shadow p-6">
          <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Quality</p>
          <p className="mt-2 text-3xl font-bold text-primary dark:text-primary-dark">
            {profiles.length > 0 ? Math.round(profiles.reduce((s, p) => s + p.quality, 0) / profiles.length) : 0}%
          </p>
        </div>
      </div>

      {/* Profiles Table */}
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left text-secondary dark:text-gray-400">
            <thead className="text-xs text-primary uppercase bg-surface-sunken dark:bg-gray-700 dark:text-gray-400">
              <tr>
                <th scope="col" className="px-6 py-3">Name</th>
                <th scope="col" className="px-6 py-3">Dimensions</th>
                <th scope="col" className="px-6 py-3">Aspect Ratio</th>
                <th scope="col" className="px-6 py-3">Mode</th>
                <th scope="col" className="px-6 py-3">Quality</th>
                <th scope="col" className="px-6 py-3">Category</th>
              </tr>
            </thead>
            <tbody>
              {profiles.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center text-secondary dark:text-gray-400">
                    No thumbnail profiles found
                  </td>
                </tr>
              ) : (
                profiles.map((profile) => (
                  <tr key={profile.id} className="bg-surface border-b dark:bg-surface-dark dark:border-gray-700 hover:bg-surface-sunken dark:hover:bg-gray-600">
                    <td className="px-6 py-4">
                      <div className="font-medium text-primary dark:text-primary-dark">
                        {profile.displayName}
                      </div>
                      <div className="text-xs text-secondary dark:text-gray-400 font-mono">
                        {profile.name}
                      </div>
                      {profile.description && (
                        <div className="text-xs text-gray-400 dark:text-secondary mt-1">
                          {profile.description}
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4 font-mono">
                      {profile.width} x {profile.height}
                    </td>
                    <td className="px-6 py-4">
                      <span className="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {profile.aspectRatio}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-primary-dark">
                        {profile.mode}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <div className="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                          <div
                            className="bg-green-500 h-2 rounded-full"
                            style={{ width: `${profile.quality}%` }}
                          />
                        </div>
                        <span className="text-xs">{profile.quality}%</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                        {profile.category}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {profiles.length > 0 && (
          <div className="px-6 py-4 bg-surface-sunken dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Showing <span className="font-medium text-primary dark:text-primary-dark">{profiles.length}</span> thumbnail profiles
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
