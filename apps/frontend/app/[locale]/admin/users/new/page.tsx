import Link from 'next/link';
import UserForm from '../components/UserForm';

interface NewUserPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function NewUserPage({ params }: NewUserPageProps) {
  const { locale } = await params;

  return (
    <div className="p-4">
      {/* Page Header */}
      <div className="mb-4">
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
          <Link href={`/${locale}/admin`} className="hover:text-blue-600">
            Dashboard
          </Link>
          <span>/</span>
          <Link href={`/${locale}/admin/users`} className="hover:text-blue-600">
            Users
          </Link>
          <span>/</span>
          <span>New User</span>
        </div>
        <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
          Create New User
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Add a new user account
        </p>
      </div>

      {/* User Form */}
      <div className="bg-surface dark:bg-surface-dark shadow-md sm:rounded-lg p-6">
        <UserForm locale={locale} />
      </div>
    </div>
  );
}
