import Link from 'next/link';
import { notFound } from 'next/navigation';
import UserForm from '../../components/UserForm';
import { getUser } from '@/lib/dal';

interface EditUserPageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function EditUserPage({ params }: EditUserPageProps) {
  const { locale, id } = await params;
  const userId = parseInt(id, 10);

  if (isNaN(userId)) {
    notFound();
  }

  let user;
  try {
    user = await getUser(userId);
  } catch (error) {
    console.error('Failed to fetch user:', error);
    notFound();
  }

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
          <span>Edit User</span>
        </div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
          Edit User
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Update user account details for <strong>{user.username}</strong>
        </p>
      </div>

      {/* User Form */}
      <div className="bg-white dark:bg-gray-800 shadow-md sm:rounded-lg p-6">
        <UserForm
          locale={locale}
          user={{
            id: user.id,
            username: user.username,
            email: user.email,
            firstName: user.firstName,
            lastName: user.lastName,
            roles: user.roles,
            active: user.active,
          }}
        />
      </div>
    </div>
  );
}
