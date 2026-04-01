import { LiveTextForm } from '../components/LiveTextForm';

interface NewLiveTextPageProps {
  params: Promise<{
    locale: string;
  }>;
}

export default async function NewLiveTextPage({ params }: NewLiveTextPageProps) {
  const { locale } = await params;

  // Fetch categories for selection
  let categories: any[] = [];
  try {
    const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
    const response = await fetch(`${apiUrl}/api/categories?itemsPerPage=100&status=active`, {
      headers: {
        'Accept': 'application/ld+json',
        'Accept-Language': locale,
      },
      cache: 'no-store',
    });

    if (response.ok) {
      const data = await response.json();
      categories = data['hydra:member'] || [];
    }
  } catch (err) {
    console.error('Failed to fetch categories:', err);
  }

  const texts = {
    ro: {
      title: 'Creare Live Text',
      subtitle: 'Creați un nou eveniment live',
    },
    en: {
      title: 'Create Live Text',
      subtitle: 'Create a new live event',
    },
    ru: {
      title: 'Создать Live Текст',
      subtitle: 'Создайте новое событие в прямом эфире',
    },
  };

  const t = texts[locale as keyof typeof texts] || texts.ro;

  return (
    <div>
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-primary dark:text-primary-dark">
          {t.title}
        </h1>
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
          {t.subtitle}
        </p>
      </div>

      {/* Form */}
      <LiveTextForm locale={locale} categories={categories} />
    </div>
  );
}

export const metadata = {
  title: 'Create Live Text',
  description: 'Create a new live text event',
};
