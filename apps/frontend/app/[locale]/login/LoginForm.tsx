'use client';

import { useActionState } from 'react';
import { useFormStatus } from 'react-dom';
import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { login, type LoginFormState } from '@/app/actions/auth';

function SubmitButton() {
  const { pending } = useFormStatus();

  return (
    <button
      type="submit"
      disabled={pending}
      className="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      {pending ? 'Signing in...' : 'Sign in'}
    </button>
  );
}

export default function LoginForm() {
  const router = useRouter();
  const [state, formAction] = useActionState<LoginFormState, FormData>(login, {});

  // Redirect on successful login
  useEffect(() => {
    if (state.message === 'Login successful') {
      // Check if there's a redirect URL in query params
      const params = new URLSearchParams(window.location.search);
      const from = params.get('from');

      // Extract locale from current path
      const pathParts = window.location.pathname.split('/');
      const locale = pathParts[1] || 'ro'; // Default to 'ro' if not found

      // Redirect to the original page or admin dashboard (with locale)
      const redirectTo = from || `/${locale}/admin`;
      router.push(redirectTo);
      router.refresh();
    }
  }, [state.message, router]);

  return (
    <form className="space-y-4 md:space-y-6" action={formAction}>
      {/* Form-level error — fixed min-height to prevent CLS */}
      <div className="min-h-[1rem]">
        {state.errors?._form && (
          <div className="p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-surface-dark dark:text-red-400">
            {state.errors._form.join(', ')}
          </div>
        )}
      </div>

      {/* Username field */}
      <div>
        <label
          htmlFor="username"
          className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
        >
          Username
        </label>
        <input
          type="text"
          name="username"
          id="username"
          defaultValue={state.username ?? ''}
          className="bg-surface-sunken border border-gray-300 text-primary rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-primary-dark dark:focus:ring-blue-500 dark:focus:border-blue-500"
          placeholder="admin"
          required
          autoComplete="username"
        />
        {state.errors?.username && (
          <p className="mt-2 text-sm text-red-600 dark:text-red-500">
            {state.errors.username.join(', ')}
          </p>
        )}
      </div>

      {/* Password field */}
      <div>
        <label
          htmlFor="password"
          className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
        >
          Password
        </label>
        <input
          type="password"
          name="password"
          id="password"
          placeholder="••••••••"
          className="bg-surface-sunken border border-gray-300 text-primary rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-primary-dark dark:focus:ring-blue-500 dark:focus:border-blue-500"
          required
          autoComplete="new-password"
        />
        {state.errors?.password && (
          <p className="mt-2 text-sm text-red-600 dark:text-red-500">
            {state.errors.password.join(', ')}
          </p>
        )}
      </div>

      {/* Remember me and Forgot password */}
      <div className="flex items-center justify-between">
        <div className="flex items-start">
          <div className="flex items-center h-5">
            <input
              id="remember"
              aria-describedby="remember"
              type="checkbox"
              className="w-4 h-4 border border-gray-300 rounded bg-surface-sunken focus:ring-3 focus:ring-blue-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-blue-600 dark:ring-offset-gray-800"
            />
          </div>
          <div className="ml-3 text-sm">
            <label htmlFor="remember" className="text-secondary dark:text-primary-dark">
              Remember me
            </label>
          </div>
        </div>
        <a
          href="#"
          className="text-sm font-medium text-blue-600 hover:underline dark:text-blue-500"
        >
          Forgot password?
        </a>
      </div>

      {/* Submit button */}
      <SubmitButton />

      {/* Sign up link */}
      <p className="text-sm font-light text-secondary dark:text-gray-400">
        Don&apos;t have an account yet?{' '}
        <a
          href="#"
          className="font-medium text-blue-600 hover:underline dark:text-blue-500"
        >
          Sign up
        </a>
      </p>
    </form>
  );
}
