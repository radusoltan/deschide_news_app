'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Label, TextInput, Button, Spinner } from 'flowbite-react';
import { createUserAction, updateUserAction } from '@/app/actions/users';

interface UserFormProps {
  locale: string;
  user?: {
    id?: number;
    username?: string;
    email?: string;
    firstName?: string;
    lastName?: string;
    roles?: string[];
    active?: boolean;
  };
}

export default function UserForm({ locale, user }: UserFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const isEdit = !!user?.id;

  const [formData, setFormData] = useState({
    username: user?.username || '',
    email: user?.email || '',
    plainPassword: '',
    confirmPassword: '',
    firstName: user?.firstName || '',
    lastName: user?.lastName || '',
    isActive: user?.active !== undefined ? user.active : true,
    roleAdmin: user?.roles?.includes('ROLE_ADMIN') ?? false,
    roleEditor: user?.roles?.includes('ROLE_EDITOR') ?? false,
  });

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    setErrors({});

    const fd = new FormData();
    fd.set('username', formData.username);
    fd.set('email', formData.email);
    fd.set('plainPassword', formData.plainPassword);
    fd.set('confirmPassword', formData.confirmPassword);
    fd.set('firstName', formData.firstName);
    fd.set('lastName', formData.lastName);
    fd.set('isActive', formData.isActive ? 'on' : 'off');
    if (formData.roleAdmin) fd.set('role_admin', 'on');
    if (formData.roleEditor) fd.set('role_editor', 'on');

    try {
      let result;
      if (isEdit && user?.id) {
        result = await updateUserAction(user.id, fd);
      } else {
        result = await createUserAction(fd);
      }

      if (result.errors) {
        setErrors(result.errors);
        setLoading(false);
        return;
      }

      if (result.message) {
        router.push(`/${locale}/admin/users`);
        router.refresh();
      }
    } catch (err) {
      console.error('Form submission error:', err);
      setErrors({ _form: [err instanceof Error ? err.message : 'Unexpected error'] });
      setLoading(false);
    }
  };

  const handleCancel = () => {
    router.push(`/${locale}/admin/users`);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Form-level error */}
      {errors._form && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">{errors._form[0]}</p>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Username */}
        <div>
          <Label htmlFor="username">Username *</Label>
          <TextInput
            id="username"
            name="username"
            type="text"
            value={formData.username}
            onChange={(e) => setFormData({ ...formData, username: e.target.value })}
            placeholder="Enter username"
            required
            disabled={loading}
            color={errors.username ? 'failure' : undefined}
          />
          {errors.username && (
            <p className="mt-1 text-xs text-red-600">{errors.username[0]}</p>
          )}
        </div>

        {/* Email */}
        <div>
          <Label htmlFor="email">Email *</Label>
          <TextInput
            id="email"
            name="email"
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
            placeholder="user@example.com"
            required
            disabled={loading}
            color={errors.email ? 'failure' : undefined}
          />
          {errors.email && (
            <p className="mt-1 text-xs text-red-600">{errors.email[0]}</p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* First Name */}
        <div>
          <Label htmlFor="firstName">First Name</Label>
          <TextInput
            id="firstName"
            name="firstName"
            type="text"
            value={formData.firstName}
            onChange={(e) => setFormData({ ...formData, firstName: e.target.value })}
            placeholder="First name"
            disabled={loading}
          />
        </div>

        {/* Last Name */}
        <div>
          <Label htmlFor="lastName">Last Name</Label>
          <TextInput
            id="lastName"
            name="lastName"
            type="text"
            value={formData.lastName}
            onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
            placeholder="Last name"
            disabled={loading}
          />
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Password */}
        <div>
          <Label htmlFor="plainPassword">
            Password {isEdit ? '(leave empty to keep current)' : '*'}
          </Label>
          <TextInput
            id="plainPassword"
            name="plainPassword"
            type="password"
            value={formData.plainPassword}
            onChange={(e) => setFormData({ ...formData, plainPassword: e.target.value })}
            placeholder={isEdit ? 'Leave empty to keep current password' : 'Minimum 8 characters'}
            required={!isEdit}
            disabled={loading}
            color={errors.plainPassword ? 'failure' : undefined}
          />
          {errors.plainPassword && (
            <p className="mt-1 text-xs text-red-600">{errors.plainPassword[0]}</p>
          )}
        </div>

        {/* Confirm Password */}
        <div>
          <Label htmlFor="confirmPassword">
            Confirm Password {isEdit ? '' : '*'}
          </Label>
          <TextInput
            id="confirmPassword"
            name="confirmPassword"
            type="password"
            value={formData.confirmPassword}
            onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
            placeholder="Repeat password"
            required={!isEdit && !!formData.plainPassword}
            disabled={loading}
            color={errors.confirmPassword ? 'failure' : undefined}
          />
          {errors.confirmPassword && (
            <p className="mt-1 text-xs text-red-600">{errors.confirmPassword[0]}</p>
          )}
        </div>
      </div>

      {/* Roles */}
      <div>
        <Label className="mb-2 block">Roles</Label>
        <div className="mt-2 flex flex-wrap gap-6">
          <label
            htmlFor="role_admin"
            className="relative flex items-center gap-3 cursor-pointer select-none"
          >
            <input
              type="checkbox"
              id="role_admin"
              checked={formData.roleAdmin}
              onChange={(e) => setFormData({ ...formData, roleAdmin: e.target.checked })}
              disabled={loading}
              className="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 dark:focus:ring-red-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
            />
            <span className="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 border border-red-200 dark:border-red-800">
              ADMIN
            </span>
          </label>

          <label
            htmlFor="role_editor"
            className="relative flex items-center gap-3 cursor-pointer select-none"
          >
            <input
              type="checkbox"
              id="role_editor"
              checked={formData.roleEditor}
              onChange={(e) => setFormData({ ...formData, roleEditor: e.target.checked })}
              disabled={loading}
              className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
            />
            <span className="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
              EDITOR
            </span>
          </label>

          <span className="flex items-center text-sm text-gray-400 dark:text-secondary italic">
            ROLE_USER is always assigned automatically
          </span>
        </div>
        {errors.roles && (
          <p className="mt-1 text-xs text-red-600">{errors.roles[0]}</p>
        )}
      </div>

      {/* Active Status — custom toggle */}
      <div>
        <label
          htmlFor="isActive"
          className="relative inline-flex items-center cursor-pointer select-none"
        >
          <input
            type="checkbox"
            id="isActive"
            checked={formData.isActive}
            onChange={(e) => setFormData({ ...formData, isActive: e.target.checked })}
            disabled={loading}
            className="sr-only peer"
          />
          <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-surface after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600" />
          <span className="ms-3 text-sm font-medium text-primary dark:text-primary-dark">
            Account active
          </span>
        </label>
        <p className="mt-1 text-xs text-secondary dark:text-gray-400">
          Inactive users cannot log in
        </p>
      </div>

      {/* Form Actions */}
      <div className="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
        <Button type="submit" color="blue" disabled={loading}>
          {loading ? (
            <>
              <Spinner size="sm" light className="mr-2" />
              Saving...
            </>
          ) : (
            <>{isEdit ? 'Update User' : 'Create User'}</>
          )}
        </Button>
        <Button type="button" color="gray" onClick={handleCancel} disabled={loading}>
          Cancel
        </Button>
      </div>
    </form>
  );
}
