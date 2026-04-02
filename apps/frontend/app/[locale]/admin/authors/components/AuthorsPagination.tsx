'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';

interface AuthorsPaginationProps {
  currentPage: number;
  totalPages: number;
  locale: string;
}

export function AuthorsPagination({ currentPage, totalPages, locale }: AuthorsPaginationProps) {
  const searchParams = useSearchParams();

  const createPageURL = (pageNumber: number) => {
    const params = new URLSearchParams(searchParams);
    params.set('page', pageNumber.toString());
    return `/${locale}/admin/authors?${params.toString()}`;
  };

  // Show max 7 page numbers
  const getPageNumbers = () => {
    const delta = 2;
    const range = [];
    const rangeWithDots = [];
    let l;

    range.push(1);

    for (let i = currentPage - delta; i <= currentPage + delta; i++) {
      if (i >= 2 && i < totalPages) {
        range.push(i);
      }
    }

    if (totalPages > 1) {
      range.push(totalPages);
    }

    for (const i of range) {
      if (l) {
        if (i - l === 2) {
          rangeWithDots.push(l + 1);
        } else if (i - l !== 1) {
          rangeWithDots.push('...');
        }
      }
      rangeWithDots.push(i);
      l = i;
    }

    return rangeWithDots;
  };

  return (
    <nav className="flex items-center gap-x-1">
      {/* Previous button */}
      <Link
        href={createPageURL(currentPage - 1)}
        className={`min-w-[40px] flex justify-center items-center text-gray-800 hover:bg-gray-100 py-2 px-3 text-sm rounded-lg focus:outline-none focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-primary-dark dark:hover:bg-surface/10 dark:focus:bg-surface/10 ${
          currentPage <= 1 ? 'pointer-events-none opacity-50' : ''
        }`}
        aria-label="Previous page"
      >
        <svg
          className="flex-shrink-0 w-3.5 h-3.5"
          xmlns="http://www.w3.org/2000/svg"
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="m15 18-6-6 6-6" />
        </svg>
        <span className="sr-only">Previous</span>
      </Link>

      {/* Page numbers */}
      <div className="flex items-center gap-x-1">
        {getPageNumbers().map((pageNumber, index) => {
          if (pageNumber === '...') {
            return (
              <span
                key={`dots-${index}`}
                className="min-w-[40px] flex justify-center items-center text-gray-800 py-2 px-3 text-sm rounded-lg focus:outline-none dark:text-primary-dark"
              >
                ...
              </span>
            );
          }

          return (
            <Link
              key={pageNumber}
              href={createPageURL(pageNumber as number)}
              className={`min-w-[40px] flex justify-center items-center py-2 px-3 text-sm rounded-lg focus:outline-none ${
                currentPage === pageNumber
                  ? 'bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500'
                  : 'text-gray-800 hover:bg-gray-100 dark:text-primary-dark dark:hover:bg-surface/10'
              }`}
              aria-current={currentPage === pageNumber ? 'page' : undefined}
            >
              {pageNumber}
            </Link>
          );
        })}
      </div>

      {/* Next button */}
      <Link
        href={createPageURL(currentPage + 1)}
        className={`min-w-[40px] flex justify-center items-center text-gray-800 hover:bg-gray-100 py-2 px-3 text-sm rounded-lg focus:outline-none focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-primary-dark dark:hover:bg-surface/10 dark:focus:bg-surface/10 ${
          currentPage >= totalPages ? 'pointer-events-none opacity-50' : ''
        }`}
        aria-label="Next page"
      >
        <span className="sr-only">Next</span>
        <svg
          className="flex-shrink-0 w-3.5 h-3.5"
          xmlns="http://www.w3.org/2000/svg"
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="m9 18 6-6-6-6" />
        </svg>
      </Link>
    </nav>
  );
}
