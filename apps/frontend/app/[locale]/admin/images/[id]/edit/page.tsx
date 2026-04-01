import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getImageById } from '@/lib/api/images';
import ImageEditForm from './components/ImageEditForm';

interface EditImagePageProps {
  params: Promise<{
    locale: string;
    id: string;
  }>;
}

export default async function EditImagePage({ params }: EditImagePageProps) {
  const { locale, id } = await params;
  const imageId = parseInt(id, 10);

  if (isNaN(imageId)) {
    notFound();
  }

  // Fetch image data
  let image;
  try {
    image = await getImageById(imageId);
  } catch (error) {
    console.error('Failed to fetch image:', error);
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
          <Link href={`/${locale}/admin/images`} className="hover:text-blue-600">
            Images
          </Link>
          <span>/</span>
          <span>Edit Image</span>
        </div>
        <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
          Edit Image
        </h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Update image metadata and view thumbnails
        </p>
      </div>

      {/* Image Edit Form */}
      <div className="bg-surface dark:bg-surface-dark shadow-md sm:rounded-lg p-6">
        <ImageEditForm locale={locale} image={image} />
      </div>
    </div>
  );
}
