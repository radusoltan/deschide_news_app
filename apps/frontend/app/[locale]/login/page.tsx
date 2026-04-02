import { redirect } from 'next/navigation';
import type { Metadata } from 'next';
import Link from 'next/link';
import { isAuthenticated } from '@/app/actions/auth';
import LoginForm from './LoginForm';

const translations = {
  ro: { title: 'Autentificare | Deschide News', description: 'Autentifică-te în contul tău', heading: 'Autentifică-te în cont' },
  en: { title: 'Sign In | Deschide News', description: 'Sign in to your account', heading: 'Sign in to your account' },
  ru: { title: 'Вход | Deschide News', description: 'Войдите в свой аккаунт', heading: 'Войдите в свой аккаунт' },
} as const;

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = translations[locale as keyof typeof translations] || translations.ro;
  return { title: t.title, description: t.description };
}

export default async function LoginPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = translations[locale as keyof typeof translations] || translations.ro;

  // Redirect if already authenticated
  const authenticated = await isAuthenticated();
  if (authenticated) {
    redirect(`/${locale}/admin`);
  }

  return (
    <section className="bg-surface-sunken dark:bg-surface-dark min-h-screen">
      <div className="flex flex-col items-center justify-center px-6 py-8 mx-auto min-h-screen lg:py-0">
        {/* Logo */}
        <Link
          href="/"
          className="flex items-center mb-6 text-2xl font-semibold text-primary dark:text-primary-dark"
        >
          <svg
            className="w-8 h-8 mr-2"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" />
          </svg>
          Deschide News
        </Link>

        {/* Login Card */}
        <div className="w-full bg-surface rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-surface-dark dark:border-gray-700">
          <div className="p-6 space-y-4 md:space-y-6 sm:p-8">
            <h1 className="text-xl font-bold leading-tight tracking-tight text-primary md:text-2xl dark:text-primary-dark">
              {t.heading}
            </h1>

            <LoginForm />
          </div>
        </div>
      </div>
    </section>
  );
}
