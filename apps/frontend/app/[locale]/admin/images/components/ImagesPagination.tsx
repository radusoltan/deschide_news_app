'use client';

import { useRouter } from 'next/navigation';
import { Pagination } from 'flowbite-react';

interface ImagesPaginationProps {
  currentPage: number;
  totalPages: number;
  locale: string;
}

export function ImagesPagination({ currentPage, totalPages, locale }: ImagesPaginationProps) {
  const router = useRouter();

  const handlePageChange = (page: number) => {
    router.push(`/${locale}/admin/images?page=${page}`);
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
