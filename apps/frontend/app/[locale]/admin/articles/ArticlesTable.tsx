import Link from 'next/link';
import { Checkbox, Badge } from 'flowbite-react';
import { getArticles, type Article } from '@/lib/dal';

interface ArticlesTableProps {
  locale: string;
}

export default async function ArticlesTable({ locale }: ArticlesTableProps) {
  let data;
  let error;

  try {
    data = await getArticles({ locale, page: 1, itemsPerPage: 30 });
  } catch (err) {
    error = err instanceof Error ? err.message : 'Failed to fetch articles';
  }

  if (error) {
    return (
      <div className="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400">
        <span className="font-medium">Error:</span> {error}
      </div>
    );
  }

  if (!data || data.member.length === 0) {
    return (
      <div className="p-8 text-center text-gray-500 dark:text-gray-400">
        <p className="text-lg mb-2">No articles found</p>
        <p className="text-sm">Create your first article to get started.</p>
      </div>
    );
  }

  const articles = data.member;
  const totalItems = data.totalItems;

  const getStatusBadge = (status?: string) => {
    switch (status) {
      case 'published':
        return <Badge color="success">Published</Badge>;
      case 'submitted':
        return <Badge color="warning">Submitted</Badge>;
      case 'new':
        return <Badge color="gray">New</Badge>;
      default:
        return <Badge color="gray">{status || 'Unknown'}</Badge>;
    }
  };

  const formatDate = (dateString?: string) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  return (
    <div className="overflow-x-auto">
      <div className="relative overflow-x-auto shadow-md sm:rounded-lg">
        <table className="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead className="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" className="p-4">
                <Checkbox />
              </th>
              <th scope="col" className="px-6 py-3">
                Title
              </th>
              <th scope="col" className="px-6 py-3">
                Status
              </th>
              <th scope="col" className="px-6 py-3">
                Category
              </th>
              <th scope="col" className="px-6 py-3">
                Author
              </th>
              <th scope="col" className="px-6 py-3">
                Published
              </th>
              <th scope="col" className="px-6 py-3">
                <span className="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {articles.map((article) => (
              <tr
                key={article.id}
                className="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                <td className="w-4 p-4">
                  <Checkbox />
                </td>
                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                  {article.title}
                </td>
                <td className="px-6 py-4">{getStatusBadge(article.status)}</td>
                <td className="px-6 py-4">
                  {typeof article.category === 'object' && article.category !== null
                    ? (article.category as any).name || '-'
                    : article.category || '-'}
                </td>
                <td className="px-6 py-4">
                  {typeof article.author === 'object' && article.author !== null
                    ? (article.author as any).name || '-'
                    : article.author || '-'}
                </td>
                <td className="px-6 py-4">{formatDate(article.publishedAt)}</td>
                <td className="px-6 py-4 text-right">
                  <Link
                    href={`/${locale}/admin/articles/${article.id}/edit`}
                    className="font-medium text-blue-600 hover:underline dark:text-blue-500"
                  >
                    Edit
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Total count */}
      <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
        Showing {articles.length} of {totalItems} articles
      </div>
    </div>
  );
}
