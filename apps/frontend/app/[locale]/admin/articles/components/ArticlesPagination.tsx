'use client';

import { useRouter } from 'next/navigation';
import { Pagination } from 'flowbite-react';

interface ArticlesPaginationProps {
  currentPage: number;
  totalPages: number;
  locale: string;
}

export function ArticlesPagination({ currentPage, totalPages, locale }: ArticlesPaginationProps) {
  const router = useRouter();

  const handlePageChange = (page: number) => {
    router.push(`/${locale}/admin/articles?page=${page}`);
  };

  return (
    <Pagination
      currentPage={currentPage}
      totalPages={totalPages}
      onPageChange={handlePageChange}
      showIcons
      previousLabel="Previous"
      nextLabel="Next"
    />
  );
}
