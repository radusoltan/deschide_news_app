'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Badge } from 'flowbite-react';
import { type ShortLink } from '@/lib/api/short-links';
import DeleteShortLinkModal from './DeleteShortLinkModal';

interface ShortLinksTableProps {
  shortLinks: ShortLink[];
  totalItems: number;
  locale: string;
}

export default function ShortLinksTable({
  shortLinks,
  totalItems,
  locale,
}: ShortLinksTableProps) {
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [selectedLink, setSelectedLink] = useState<{
    id: number;
    code: string;
  } | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);

  if (!shortLinks || shortLinks.length === 0) {
    return (
      <div className="p-8 text-center text-gray-500 dark:text-gray-400">
        <p className="text-lg mb-2">Niciun link scurt găsit</p>
        <p className="text-sm">Creați primul link scurt pentru a începe.</p>
      </div>
    );
  }

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ro-RO', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const handleDeleteClick = (link: { id: number; code: string }) => {
    setSelectedLink(link);
    setDeleteModalOpen(true);
  };

  const handleCloseDeleteModal = () => {
    setDeleteModalOpen(false);
    setSelectedLink(null);
  };

  const handleCopyUrl = async (shortLink: ShortLink) => {
    const fullUrl = `${window.location.origin}${shortLink.shortUrl}`;
    try {
      await navigator.clipboard.writeText(fullUrl);
      setCopiedId(shortLink.id);
      setTimeout(() => setCopiedId(null), 2000);
    } catch (err) {
      console.error('Failed to copy:', err);
    }
  };

  return (
    <div className="overflow-x-auto">
      <div className="relative overflow-x-auto shadow-md sm:rounded-lg">
        <table className="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead className="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" className="px-6 py-3">
                Cod
              </th>
              <th scope="col" className="px-6 py-3">
                Titlu / URL Original
              </th>
              <th scope="col" className="px-6 py-3">
                Link Scurt
              </th>
              <th scope="col" className="px-6 py-3">
                Clicuri
              </th>
              <th scope="col" className="px-6 py-3">
                Creat
              </th>
              <th scope="col" className="px-6 py-3">
                <span className="sr-only">Acțiuni</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {shortLinks.map((link) => (
              <tr
                key={link.id}
                className="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                <td className="px-6 py-4 font-mono font-medium text-gray-900 dark:text-white">
                  {link.code}
                </td>
                <td className="px-6 py-4">
                  <div className="flex flex-col gap-1">
                    {link.title && (
                      <div className="font-medium text-gray-900 dark:text-white">
                        {link.title}
                      </div>
                    )}
                    <div className="text-xs text-gray-500 dark:text-gray-400 truncate max-w-md">
                      {link.originalUrl}
                    </div>
                    {link.article && (
                      <Badge color="info" className="w-fit">
                        Articol: {link.article.title}
                      </Badge>
                    )}
                  </div>
                </td>
                <td className="px-6 py-4">
                  <button
                    onClick={() => handleCopyUrl(link)}
                    className="flex items-center gap-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    title="Copiază URL"
                  >
                    <span className="font-mono text-sm">{link.shortUrl}</span>
                    {copiedId === link.id ? (
                      <svg
                        className="w-4 h-4 text-green-500"
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
                    ) : (
                      <svg
                        className="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                        />
                      </svg>
                    )}
                  </button>
                </td>
                <td className="px-6 py-4">
                  <Badge color="success" className="w-fit">
                    {link.clickCount.toLocaleString('ro-RO')}
                  </Badge>
                </td>
                <td className="px-6 py-4 text-xs">
                  {formatDate(link.createdAt)}
                </td>
                <td className="px-6 py-4 text-right">
                  <div className="flex items-center justify-end gap-3">
                    <Link
                      href={`/${locale}/admin/short-links/${link.id}/stats`}
                      className="font-medium text-green-600 hover:underline dark:text-green-500"
                    >
                      Statistici
                    </Link>
                    <button
                      type="button"
                      onClick={() =>
                        handleDeleteClick({ id: link.id, code: link.code })
                      }
                      className="font-medium text-red-600 hover:underline dark:text-red-500"
                    >
                      Șterge
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Total count */}
      <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
        Afișare {shortLinks.length} din {totalItems} linkuri scurte
      </div>

      {/* Delete Modal */}
      {selectedLink && (
        <DeleteShortLinkModal
          isOpen={deleteModalOpen}
          onClose={handleCloseDeleteModal}
          shortLink={selectedLink}
          locale={locale}
        />
      )}
    </div>
  );
}
