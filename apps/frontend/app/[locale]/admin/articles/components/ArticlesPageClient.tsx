'use client';

import { useState } from 'react';
import { HiPlus } from 'react-icons/hi';
import CreateArticleModal from './CreateArticleModal';

interface Category {
  id: number;
  title: string;
}

interface ArticlesPageClientProps {
  locale: string;
  categories: Category[];
}

export function ArticlesPageClient({ locale, categories }: ArticlesPageClientProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);

  return (
    <>
      <button
        onClick={() => setIsModalOpen(true)}
        className="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
      >
        <HiPlus className="w-5 h-5 mr-2" />
        Create Article
      </button>

      <CreateArticleModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        locale={locale}
        categories={categories}
      />
    </>
  );
}
