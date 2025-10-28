'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Label, TextInput, Select, Button, Spinner, Checkbox } from 'flowbite-react';
import { createCategoryAction, updateCategoryAction } from '@/app/actions/categories';

interface CategoryFormProps {
  locale: string;
  category?: {
    id?: number;
    title?: string;
    slug?: string;
    status?: string;
    onFrontPage?: boolean;
  };
}

export default function CategoryForm({ locale, category }: CategoryFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);

  const [formData, setFormData] = useState({
    title: category?.title || '',
    slug: category?.slug || '',
    status: category?.status || 'active',
    onFrontPage: category?.onFrontPage || false,
  });

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);

    try {
      const formDataObj = new FormData(e.currentTarget);

      let result;
      if (category?.id) {
        result = await updateCategoryAction(category.id, locale, formDataObj);
      } else {
        result = await createCategoryAction(locale, formDataObj);
      }

      if (result.errors?._form) {
        setLoading(false);
        return;
      }

      if (result.message) {
        router.push(`/${locale}/admin/categories`);
        router.refresh();
      }
    } catch (err) {
      console.error('Form submission error:', err);
      setLoading(false);
    }
  };

  const handleCancel = () => {
    router.push(`/${locale}/admin/categories`);
  };

  const generateSlug = () => {
    const slug = formData.title
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/--+/g, '-')
      .trim();
    setFormData({ ...formData, slug });
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Title */}
      <div>
        <Label htmlFor="title" value="Title *" />
        <TextInput
          id="title"
          name="title"
          type="text"
          value={formData.title}
          onChange={(e) => setFormData({ ...formData, title: e.target.value })}
          placeholder="Enter category title"
          required
          disabled={loading}
        />
      </div>

      {/* Slug */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <Label htmlFor="slug" value="Slug *" />
          <button
            type="button"
            onClick={generateSlug}
            className="text-xs text-blue-600 hover:underline dark:text-blue-500"
            disabled={loading || !formData.title}
          >
            Generate from title
          </button>
        </div>
        <TextInput
          id="slug"
          name="slug"
          type="text"
          value={formData.slug}
          onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
          placeholder="category-slug"
          required
          disabled={loading}
        />
        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
          URL-friendly version of the title
        </p>
      </div>

      {/* Status */}
      <div>
        <Label htmlFor="status" value="Status *" />
        <Select
          id="status"
          name="status"
          value={formData.status}
          onChange={(e) => setFormData({ ...formData, status: e.target.value })}
          required
          disabled={loading}
        >
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </Select>
      </div>

      {/* On Front Page */}
      <div className="flex items-center gap-2">
        <Checkbox
          id="onFrontPage"
          name="onFrontPage"
          checked={formData.onFrontPage}
          onChange={(e) => setFormData({ ...formData, onFrontPage: e.target.checked })}
          disabled={loading}
        />
        <Label htmlFor="onFrontPage" value="Display on front page" />
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
            <>{category?.id ? 'Update Category' : 'Create Category'}</>
          )}
        </Button>
        <Button type="button" color="gray" onClick={handleCancel} disabled={loading}>
          Cancel
        </Button>
      </div>
    </form>
  );
}
