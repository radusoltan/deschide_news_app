'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Label, TextInput, Select, Button, Spinner } from 'flowbite-react';
import { createCategoryAction, updateCategoryAction } from '@/app/actions/categories';
import { generateSlug } from '@/lib/utils/slug';

interface ParentCategory {
  id: number;
  title: string;
}

/** Custom toggle — works correctly with Tailwind CSS 4 */
function Toggle({
  checked,
  label,
  onChange,
  disabled,
}: {
  checked: boolean;
  label: string;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => !disabled && onChange(!checked)}
      className={`group flex items-center gap-3 ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
    >
      <span
        className={`relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors duration-200 ${
          checked ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'
        }`}
      >
        <span
          className={`inline-block h-5 w-5 rounded-full bg-white shadow-sm ring-1 ring-gray-200 dark:ring-gray-500 transition-transform duration-200 translate-y-0.5 ${
            checked ? 'translate-x-[1.375rem]' : 'translate-x-0.5'
          }`}
        />
      </span>
      <span className="text-sm font-medium text-gray-900 dark:text-gray-300">{label}</span>
    </button>
  );
}

type FrontPageLayout = 'featured-grid' | 'grid-3col' | 'compact-list' | 'grid-4col';

interface CategoryFormProps {
  locale: string;
  categories?: ParentCategory[];
  category?: {
    id?: number;
    title?: string;
    slug?: string;
    status?: string;
    onFrontPage?: boolean;
    frontPageLayout?: FrontPageLayout | null;
    inMenu?: boolean;
    inFooterMenu?: boolean;
    parentId?: number | null;
  };
}

const LAYOUT_OPTIONS: { value: FrontPageLayout; label: string; description: string }[] = [
  {
    value: 'featured-grid',
    label: 'Featured Grid',
    description: '1 featured + 3 compact + 4 small cards',
  },
  {
    value: 'grid-3col',
    label: 'Grid 3 Columns',
    description: '3-column card grid (6 articles)',
  },
  {
    value: 'compact-list',
    label: 'Compact List',
    description: 'Compact rows with thumbnails (6 articles)',
  },
  {
    value: 'grid-4col',
    label: 'Grid 4 Columns',
    description: '4-column card grid (8 articles)',
  },
];

/* ================================================================== */
/*  Layout Preview SVG Mini-diagrams                                   */
/* ================================================================== */

function LayoutPreviewFeaturedGrid() {
  return (
    <svg viewBox="0 0 120 80" className="w-full h-auto" fill="none">
      {/* Featured large card */}
      <rect x="2" y="2" width="56" height="44" rx="2" className="fill-current opacity-30" />
      <rect x="4" y="30" width="36" height="3" rx="1" className="fill-current opacity-60" />
      <rect x="4" y="35" width="28" height="2" rx="1" className="fill-current opacity-40" />
      {/* 3 compact rows on the right */}
      <rect x="62" y="2" width="56" height="12" rx="2" className="fill-current opacity-20" />
      <rect x="62" y="17" width="56" height="12" rx="2" className="fill-current opacity-20" />
      <rect x="62" y="32" width="56" height="12" rx="2" className="fill-current opacity-20" />
      {/* 4 small cards bottom */}
      <rect x="2" y="50" width="26" height="28" rx="2" className="fill-current opacity-15" />
      <rect x="32" y="50" width="26" height="28" rx="2" className="fill-current opacity-15" />
      <rect x="62" y="50" width="26" height="28" rx="2" className="fill-current opacity-15" />
      <rect x="92" y="50" width="26" height="28" rx="2" className="fill-current opacity-15" />
    </svg>
  );
}

function LayoutPreviewGrid3col() {
  return (
    <svg viewBox="0 0 120 80" className="w-full h-auto" fill="none">
      {/* Row 1 */}
      <rect x="2" y="2" width="36" height="35" rx="2" className="fill-current opacity-20" />
      <rect x="4" y="22" width="24" height="3" rx="1" className="fill-current opacity-50" />
      <rect x="4" y="27" width="18" height="2" rx="1" className="fill-current opacity-30" />
      <rect x="42" y="2" width="36" height="35" rx="2" className="fill-current opacity-20" />
      <rect x="44" y="22" width="24" height="3" rx="1" className="fill-current opacity-50" />
      <rect x="44" y="27" width="18" height="2" rx="1" className="fill-current opacity-30" />
      <rect x="82" y="2" width="36" height="35" rx="2" className="fill-current opacity-20" />
      <rect x="84" y="22" width="24" height="3" rx="1" className="fill-current opacity-50" />
      <rect x="84" y="27" width="18" height="2" rx="1" className="fill-current opacity-30" />
      {/* Row 2 */}
      <rect x="2" y="42" width="36" height="35" rx="2" className="fill-current opacity-15" />
      <rect x="4" y="62" width="24" height="3" rx="1" className="fill-current opacity-40" />
      <rect x="42" y="42" width="36" height="35" rx="2" className="fill-current opacity-15" />
      <rect x="44" y="62" width="24" height="3" rx="1" className="fill-current opacity-40" />
      <rect x="82" y="42" width="36" height="35" rx="2" className="fill-current opacity-15" />
      <rect x="84" y="62" width="24" height="3" rx="1" className="fill-current opacity-40" />
    </svg>
  );
}

function LayoutPreviewCompactList() {
  return (
    <svg viewBox="0 0 120 80" className="w-full h-auto" fill="none">
      {/* 3 columns × 2 rows of compact items (thumbnail + text) */}
      {[0, 40, 80].map((x) =>
        [0, 1].map((row) => {
          const y = 2 + row * 40;
          return (
            <g key={`${x}-${row}`}>
              <rect x={x + 2} y={y} width="14" height="14" rx="2" className="fill-current opacity-25" />
              <rect x={x + 19} y={y + 2} width="17" height="3" rx="1" className="fill-current opacity-50" />
              <rect x={x + 19} y={y + 7} width="12" height="2" rx="1" className="fill-current opacity-30" />
              <line x1={x + 2} y1={y + 18} x2={x + 36} y2={y + 18} className="stroke-current opacity-10" strokeWidth="0.5" />
              <rect x={x + 2} y={y + 20} width="14" height="14" rx="2" className="fill-current opacity-25" />
              <rect x={x + 19} y={y + 22} width="17" height="3" rx="1" className="fill-current opacity-50" />
              <rect x={x + 19} y={y + 27} width="12" height="2" rx="1" className="fill-current opacity-30" />
            </g>
          );
        })
      )}
    </svg>
  );
}

function LayoutPreviewGrid4col() {
  return (
    <svg viewBox="0 0 120 80" className="w-full h-auto" fill="none">
      {/* Row 1: 4 cards */}
      {[2, 32, 62, 92].map((x) => (
        <g key={`r1-${x}`}>
          <rect x={x} y="2" width="26" height="35" rx="2" className="fill-current opacity-20" />
          <rect x={x + 2} y="22" width="16" height="3" rx="1" className="fill-current opacity-50" />
          <rect x={x + 2} y="27" width="12" height="2" rx="1" className="fill-current opacity-30" />
        </g>
      ))}
      {/* Row 2: 4 cards */}
      {[2, 32, 62, 92].map((x) => (
        <g key={`r2-${x}`}>
          <rect x={x} y="42" width="26" height="35" rx="2" className="fill-current opacity-15" />
          <rect x={x + 2} y="62" width="16" height="3" rx="1" className="fill-current opacity-40" />
          <rect x={x + 2} y="27" width="12" height="2" rx="1" className="fill-current opacity-25" />
        </g>
      ))}
    </svg>
  );
}

const LAYOUT_PREVIEWS: Record<FrontPageLayout, () => React.ReactElement> = {
  'featured-grid': LayoutPreviewFeaturedGrid,
  'grid-3col': LayoutPreviewGrid3col,
  'compact-list': LayoutPreviewCompactList,
  'grid-4col': LayoutPreviewGrid4col,
};

export default function CategoryForm({ locale, categories = [], category }: CategoryFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);

  const [formData, setFormData] = useState<{
    title: string;
    slug: string;
    status: string;
    onFrontPage: boolean;
    frontPageLayout: FrontPageLayout | null;
    inMenu: boolean;
    inFooterMenu: boolean;
    parent: string;
  }>({
    title: category?.title || '',
    slug: category?.slug || '',
    status: category?.status || 'active',
    onFrontPage: category?.onFrontPage || false,
    frontPageLayout: (category?.frontPageLayout as FrontPageLayout) || null,
    inMenu: category?.inMenu || false,
    inFooterMenu: category?.inFooterMenu || false,
    parent: category?.parentId?.toString() || '',
  });

  // Exclude current category from parent options (prevent self-reference)
  const parentOptions = categories.filter(c => c.id !== category?.id);

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);

    try {
      const formDataObj = new FormData(e.currentTarget);
      // Explicitly set all fields from React state (hidden inputs may not be in DOM)
      formDataObj.set('parent', formData.parent);
      formDataObj.set('onFrontPage', formData.onFrontPage ? 'on' : '');
      formDataObj.set('inMenu', formData.inMenu ? 'on' : '');
      formDataObj.set('inFooterMenu', formData.inFooterMenu ? 'on' : '');
      formDataObj.set('frontPageLayout', formData.frontPageLayout || '');

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

  const handleGenerateSlug = () => {
    const slug = generateSlug(formData.title);
    setFormData({ ...formData, slug });
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Title */}
      <div>
        <Label htmlFor="title">Title *</Label>
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
          <Label htmlFor="slug">Slug *</Label>
          <button
            type="button"
            onClick={handleGenerateSlug}
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

      {/* Parent Category */}
      <div>
        <Label htmlFor="parent">Parent Category</Label>
        <Select
          id="parent"
          name="parent"
          value={formData.parent}
          onChange={(e) => setFormData({ ...formData, parent: e.target.value })}
          disabled={loading}
        >
          <option value="">-- No parent (top-level) --</option>
          {parentOptions.map((cat) => (
            <option key={cat.id} value={cat.id}>
              {cat.title}
            </option>
          ))}
        </Select>
        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
          Select a parent to create a subcategory, or leave empty for a top-level category
        </p>
      </div>

      {/* Status */}
      <div>
        <Label htmlFor="status">Status *</Label>
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

      {/* Visibility Options — Toggles */}
      <div className="space-y-4">
        <Label>Visibility</Label>

        <div className="space-y-3">
          <Toggle
            checked={formData.onFrontPage}
            label="Display on front page"
            onChange={(checked) =>
              setFormData({
                ...formData,
                onFrontPage: checked,
                frontPageLayout: checked ? (formData.frontPageLayout || 'featured-grid') : null,
              })
            }
            disabled={loading}
          />
          {/* Hidden input so FormData picks up the value */}
          {formData.onFrontPage && <input type="hidden" name="onFrontPage" value="on" />}

          <Toggle
            checked={formData.inMenu}
            label="Show in main menu"
            onChange={(checked) => setFormData({ ...formData, inMenu: checked })}
            disabled={loading}
          />
          {formData.inMenu && <input type="hidden" name="inMenu" value="on" />}

          <Toggle
            checked={formData.inFooterMenu}
            label="Show in footer menu"
            onChange={(checked) => setFormData({ ...formData, inFooterMenu: checked })}
            disabled={loading}
          />
          {formData.inFooterMenu && <input type="hidden" name="inFooterMenu" value="on" />}
        </div>
      </div>

      {/* Front Page Layout Selector — visible only when onFrontPage is on */}
      {formData.onFrontPage && (
        <div className="space-y-3">
          <Label>Front Page Layout</Label>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Choose how this category section appears on the homepage
          </p>
          {/* Hidden input for form submission */}
          <input type="hidden" name="frontPageLayout" value={formData.frontPageLayout || ''} />

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
            {LAYOUT_OPTIONS.map((option) => {
              const isSelected = formData.frontPageLayout === option.value;
              const Preview = LAYOUT_PREVIEWS[option.value];
              return (
                <button
                  key={option.value}
                  type="button"
                  disabled={loading}
                  onClick={() => setFormData({ ...formData, frontPageLayout: option.value })}
                  className={`relative flex flex-col items-center p-3 rounded-lg border-2 transition-all duration-200 cursor-pointer
                    ${isSelected
                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-500/30'
                      : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-500'
                    }
                    ${loading ? 'opacity-50 cursor-not-allowed' : ''}
                  `}
                >
                  {/* Check mark */}
                  {isSelected && (
                    <div className="absolute top-1.5 right-1.5 w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center">
                      <svg className="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    </div>
                  )}

                  {/* Preview diagram */}
                  <div className={`w-full mb-2 ${isSelected ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 dark:text-gray-500'}`}>
                    <Preview />
                  </div>

                  {/* Label */}
                  <span className={`text-xs font-semibold ${isSelected ? 'text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300'}`}>
                    {option.label}
                  </span>
                  <span className={`text-[10px] mt-0.5 text-center leading-tight ${isSelected ? 'text-blue-600/70 dark:text-blue-400/70' : 'text-gray-400 dark:text-gray-500'}`}>
                    {option.description}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      )}

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
