import Link from 'next/link';
import { Checkbox, Badge } from 'flowbite-react';
import { type Author } from '@/lib/dal';

interface AuthorsTableProps {
  authors: any[];
  totalItems: number;
  locale: string;
}

export default function AuthorsTable({ authors, totalItems, locale }: AuthorsTableProps) {
  if (!authors || authors.length === 0) {
    return (
      <div className="p-8 text-center text-secondary dark:text-gray-400">
        <p className="text-lg mb-2">No authors found</p>
        <p className="text-sm">Create your first author to get started.</p>
      </div>
    );
  }

  const getStatusBadge = (status?: string, isActive?: boolean) => {
    // Check explicit isActive=false (not undefined/null which means field wasn't returned)
    if (isActive === false) {
      return <Badge color="gray">Inactive</Badge>;
    }
    switch (status) {
      case 'active':
        return <Badge color="success">Active</Badge>;
      case 'pending':
        return <Badge color="warning">Pending</Badge>;
      case 'inactive':
        return <Badge color="gray">Inactive</Badge>;
      default:
        // If isActive is true (or not returned), default to Active
        if (isActive === true) {
          return <Badge color="success">Active</Badge>;
        }
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
        <table className="w-full text-sm text-left text-secondary dark:text-gray-400">
          <thead className="text-xs text-primary uppercase bg-surface-sunken dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" className="p-4">
                <Checkbox />
              </th>
              <th scope="col" className="px-6 py-3">
                Name
              </th>
              <th scope="col" className="px-6 py-3">
                Email
              </th>
              <th scope="col" className="px-6 py-3">
                Slug
              </th>
              <th scope="col" className="px-6 py-3">
                Status
              </th>
              <th scope="col" className="px-6 py-3">
                Created
              </th>
              <th scope="col" className="px-6 py-3">
                <span className="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {authors.map((author) => (
              <tr
                key={author.id}
                className="bg-surface border-b dark:bg-surface-dark dark:border-gray-700 hover:bg-surface-sunken dark:hover:bg-gray-600"
              >
                <td className="w-4 p-4">
                  <Checkbox />
                </td>
                <td className="px-6 py-4 font-medium text-primary whitespace-nowrap dark:text-primary-dark">
                  {author.firstName} {author.lastName}
                </td>
                <td className="px-6 py-4 text-secondary dark:text-gray-400">
                  {author.email}
                </td>
                <td className="px-6 py-4 text-secondary dark:text-gray-400">
                  {author.slug}
                </td>
                <td className="px-6 py-4">{getStatusBadge(author.status, author.isActive)}</td>
                <td className="px-6 py-4">{formatDate(author.createdAt)}</td>
                <td className="px-6 py-4 text-right">
                  <Link
                    href={`/${locale}/admin/authors/${author.id}/edit`}
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
        Showing {authors.length} of {totalItems} authors
      </div>
    </div>
  );
}
