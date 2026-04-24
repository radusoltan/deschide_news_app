'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Badge } from 'flowbite-react';
import DeleteUserModal from './DeleteUserModal';
import type { User } from '@/lib/dal';

interface UsersTableProps {
  users: User[];
  totalItems: number;
  locale: string;
}

function RoleBadge({ role }: { role: string }) {
  switch (role) {
    case 'ROLE_ADMIN':
      return <Badge color="failure">Admin</Badge>;
    case 'ROLE_EDITOR':
      return <Badge color="info">Editor</Badge>;
    case 'ROLE_USER':
      return <Badge color="gray">User</Badge>;
    default:
      return <Badge color="gray">{role}</Badge>;
  }
}

export default function UsersTable({ users, totalItems, locale }: UsersTableProps) {
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<{ id: number; username: string } | null>(null);

  if (!users || users.length === 0) {
    return (
      <div className="p-8 text-center text-secondary dark:text-gray-400">
        <p className="text-lg mb-2">No users found</p>
        <p className="text-sm">Create your first user to get started.</p>
      </div>
    );
  }

  const handleDeleteClick = (user: { id: number; username: string }) => {
    setSelectedUser(user);
    setDeleteModalOpen(true);
  };

  const handleCloseDeleteModal = () => {
    setDeleteModalOpen(false);
    setSelectedUser(null);
  };

  return (
    <div className="overflow-x-auto">
      <div className="relative overflow-x-auto shadow-md sm:rounded-lg">
        <table className="w-full text-sm text-left text-secondary dark:text-gray-400">
          <thead className="text-xs text-primary uppercase bg-surface-sunken dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" className="px-6 py-3">Username</th>
              <th scope="col" className="px-6 py-3">Email</th>
              <th scope="col" className="px-6 py-3">Name</th>
              <th scope="col" className="px-6 py-3">Roles</th>
              <th scope="col" className="px-6 py-3">Status</th>
              <th scope="col" className="px-6 py-3">
                <span className="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {users.map((user) => (
              <tr
                key={user.id}
                className="bg-surface border-b dark:bg-surface-dark dark:border-gray-700 hover:bg-surface-sunken dark:hover:bg-gray-600"
              >
                <td className="px-6 py-4 font-medium text-primary whitespace-nowrap dark:text-primary-dark">
                  {user.username}
                </td>
                <td className="px-6 py-4 text-secondary dark:text-gray-400">
                  {user.email}
                </td>
                <td className="px-6 py-4 text-secondary dark:text-gray-400">
                  {[user.firstName, user.lastName].filter(Boolean).join(' ') || (
                    <span className="text-primary-dark dark:text-gray-600">-</span>
                  )}
                </td>
                <td className="px-6 py-4">
                  <div className="flex flex-wrap gap-1">
                    {(user.roles || []).map((role: string) => (
                      <RoleBadge key={role} role={role} />
                    ))}
                  </div>
                </td>
                <td className="px-6 py-4">
                  {user.active !== false ? (
                    <Badge color="success">Active</Badge>
                  ) : (
                    <Badge color="gray">Inactive</Badge>
                  )}
                </td>
                <td className="px-6 py-4 text-right">
                  <div className="flex items-center justify-end gap-3">
                    <Link
                      href={`/${locale}/admin/users/${user.id}/edit`}
                      className="font-medium text-blue-600 hover:underline dark:text-blue-500"
                    >
                      Edit
                    </Link>
                    <button
                      type="button"
                      onClick={() => handleDeleteClick({ id: user.id, username: user.username })}
                      className="font-medium text-red-600 hover:underline dark:text-red-500"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Total count */}
      <div className="mt-4 p-4 text-sm text-gray-600 dark:text-gray-400">
        Showing {users.length} of {totalItems} users
      </div>

      {/* Delete Modal */}
      {selectedUser && (
        <DeleteUserModal
          isOpen={deleteModalOpen}
          onClose={handleCloseDeleteModal}
          user={selectedUser}
          locale={locale}
        />
      )}
    </div>
  );
}
