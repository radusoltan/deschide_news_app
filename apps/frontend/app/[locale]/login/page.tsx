import { redirect } from 'next/navigation';
import { isAuthenticated } from '@/app/actions/auth';
import LoginForm from './LoginForm';

export const metadata = {
  title: 'Sign In | Deschide News',
  description: 'Sign in to your account',
};

export default async function LoginPage() {
  // Redirect if already authenticated
  const authenticated = await isAuthenticated();
  if (authenticated) {
    redirect('/');
  }

  return (
    <section className="bg-gray-50 dark:bg-gray-900">
      <div className="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0">
        {/* Logo */}
        <a
          href="/"
          className="flex items-center mb-6 text-2xl font-semibold text-gray-900 dark:text-white"
        >
          <svg
            className="w-8 h-8 mr-2"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" />
          </svg>
          Deschide News
        </a>

        {/* Login Card */}
        <div className="w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
          <div className="p-6 space-y-4 md:space-y-6 sm:p-8">
            <h1 className="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
              Sign in to your account
            </h1>

            <LoginForm />
          </div>
        </div>
      </div>
    </section>
  );
}
