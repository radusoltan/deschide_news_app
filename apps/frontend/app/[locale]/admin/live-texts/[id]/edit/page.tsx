import { notFound } from 'next/navigation';
import { getLiveTextById } from '@/lib/api';
import { LiveTextForm } from '../../components/LiveTextForm';
import type { LiveText } from '@/lib/types/livetext';
import type { Category } from '@/lib/types/article';

interface EditLiveTextPageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function EditLiveTextPage({ params }: EditLiveTextPageProps) {
  const { locale, id } = await params;

  // Fetch LiveText data
  let liveText: LiveText | null = null;
  try {
    liveText = await getLiveTextById(parseInt(id, 10), { locale, cache: 'no-store' });
  } catch (err) {
    console.error('Failed to fetch live text:', err);
    notFound();
  }

  if (!liveText) {
    notFound();
  }

  // Fetch categories for selection
  let categories: Category[] = [];
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
      title: 'Editare Live Text',
      subtitle: 'Editați evenimentul live',
    },
    en: {
      title: 'Edit Live Text',
      subtitle: 'Edit live event',
    },
    ru: {
      title: 'Редактировать Live Текст',
      subtitle: 'Редактирование события в прямом эфире',
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
      <LiveTextForm locale={locale} initialData={liveText} isEdit={true} categories={categories} />
    </div>
  );
}

export async function generateMetadata({ params }: EditLiveTextPageProps) {
  const { id } = await params;

  return {
    title: `Edit Live Text #${id}`,
    description: 'Edit live text event',
  };
}
