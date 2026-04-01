import { notFound } from 'next/navigation';
import AuthorForm from '../../components/AuthorForm';
import { getAuthor } from '@/lib/dal';

interface EditAuthorPageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function EditAuthorPage({ params }: EditAuthorPageProps) {
  const { locale, id } = await params;
  const authorId = parseInt(id, 10);

  if (isNaN(authorId)) {
    notFound();
  }

  // Fetch author
  let author;
  try {
    author = await getAuthor(authorId, locale);
  } catch (error) {
    console.error('Failed to fetch author:', error);
    notFound();
  }

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
          Edit Author: {author.firstName} {author.lastName}
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Update author information
        </p>
      </div>

      {/* Author Form */}
      <AuthorForm locale={locale} author={author} />
    </div>
  );
}
