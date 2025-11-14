'use client';

import { useRouter } from 'next/navigation';
import { Pagination } from 'flowbite-react';

interface CategoriesPaginationProps {
  currentPage: number;
  totalPages: number;
  locale: string;
}

export function CategoriesPagination({ currentPage, totalPages, locale }: CategoriesPaginationProps) {
  const router = useRouter();

  const handlePageChange = (page: number) => {
    router.push(`/${locale}/admin/categories?page=${page}`);
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
