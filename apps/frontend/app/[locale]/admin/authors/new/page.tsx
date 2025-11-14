import AuthorForm from '../components/AuthorForm';

interface NewAuthorPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function NewAuthorPage({ params }: NewAuthorPageProps) {
  const { locale } = await params;

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Create New Author
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Add a new author to the system
        </p>
      </div>

      {/* Author Form */}
      <AuthorForm locale={locale} />
    </div>
  );
}
