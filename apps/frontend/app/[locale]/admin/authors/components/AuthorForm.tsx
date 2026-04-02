'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Label, TextInput, Textarea, Select, Button, Spinner, Checkbox } from 'flowbite-react';
import { createAuthorAction, updateAuthorAction } from '@/app/actions/authors';
import TranslateButton from '@/components/admin/TranslateButton';

interface AuthorFormProps {
  locale: string;
  author?: {
    id?: number;
    firstName?: string;
    lastName?: string;
    email?: string;
    slug?: string;
    bio?: string;
    status?: string;
    isActive?: boolean;
    twitter?: string;
    facebook?: string;
    linkedin?: string;
    website?: string;
    translationStatus?: string | null;
  };
}

export default function AuthorForm({ locale, author }: AuthorFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [formData, setFormData] = useState({
    firstName: author?.firstName || '',
    lastName: author?.lastName || '',
    email: author?.email || '',
    slug: author?.slug || '',
    bio: author?.bio || '',
    status: author?.status || 'active',
    isActive: author?.isActive ?? true,
    twitter: author?.twitter || '',
    facebook: author?.facebook || '',
    linkedin: author?.linkedin || '',
    website: author?.website || '',
  });

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const formDataObj = new FormData(e.currentTarget);

      let result;
      if (author?.id) {
        result = await updateAuthorAction(author.id, locale, formDataObj);
      } else {
        result = await createAuthorAction(locale, formDataObj);
      }

      if (result.errors?._form) {
        setError(result.errors._form[0]);
        setLoading(false);
        return;
      }

      if (result.message) {
        router.push(`/${locale}/admin/authors`);
        router.refresh();
      }
    } catch (err) {
      console.error('Form submission error:', err);
      setError('An unexpected error occurred');
      setLoading(false);
    }
  };

  const handleCancel = () => {
    router.push(`/${locale}/admin/authors`);
  };

  const generateSlug = () => {
    const slug = `${formData.firstName}-${formData.lastName}`
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/--+/g, '-')
      .trim();
    setFormData({ ...formData, slug });
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Error Message */}
      {error && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
        </div>
      )}

      {/* Basic Information */}
      <div className="bg-surface dark:bg-surface-dark p-6 rounded-lg shadow">
        <h3 className="text-lg font-medium text-primary dark:text-primary-dark mb-4">
          Basic Information
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* First Name */}
          <div>
            <Label htmlFor="firstName">First Name *</Label>
            <TextInput
              id="firstName"
              name="firstName"
              type="text"
              value={formData.firstName}
              onChange={(e) => setFormData({ ...formData, firstName: e.target.value })}
              placeholder="John"
              required
              disabled={loading}
            />
          </div>

          {/* Last Name */}
          <div>
            <Label htmlFor="lastName">Last Name *</Label>
            <TextInput
              id="lastName"
              name="lastName"
              type="text"
              value={formData.lastName}
              onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
              placeholder="Doe"
              required
              disabled={loading}
            />
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
              placeholder="john.doe@example.com"
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
                onClick={generateSlug}
                className="text-xs text-blue-600 hover:underline dark:text-blue-500"
                disabled={loading || (!formData.firstName && !formData.lastName)}
              >
                Generate from name
              </button>
            </div>
            <TextInput
              id="slug"
              name="slug"
              type="text"
              value={formData.slug}
              onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
              placeholder="john-doe"
              required
              disabled={loading}
            />
          </div>

          {/* Status */}
          <div>
            <Label htmlFor="status">Status</Label>
            <Select
              id="status"
              name="status"
              value={formData.status}
              onChange={(e) => setFormData({ ...formData, status: e.target.value })}
              disabled={loading}
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
            </Select>
          </div>

          {/* Is Active Checkbox */}
          <div className="flex items-center pt-6">
            <Checkbox
              id="isActive"
              name="isActive"
              checked={formData.isActive}
              onChange={(e) => setFormData({ ...formData, isActive: e.target.checked })}
              disabled={loading}
            />
            <Label htmlFor="isActive" className="ml-2">
              Active Author (can publish articles)
            </Label>
          </div>
        </div>

        {/* Bio */}
        <div className="mt-4">
          <Label htmlFor="bio">Bio</Label>
          <Textarea
            id="bio"
            name="bio"
            value={formData.bio}
            onChange={(e) => setFormData({ ...formData, bio: e.target.value })}
            placeholder="Brief description about the author..."
            rows={4}
            disabled={loading}
          />
          {author?.id && formData.bio && (
            <div className="mt-2">
              <TranslateButton
                entityType="author"
                entityId={author.id}
                currentStatus={author.translationStatus}
              />
            </div>
          )}
        </div>
      </div>

      {/* Social Media Links */}
      <div className="bg-surface dark:bg-surface-dark p-6 rounded-lg shadow">
        <h3 className="text-lg font-medium text-primary dark:text-primary-dark mb-4">
          Social Media & Website
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Twitter */}
          <div>
            <Label htmlFor="twitter">Twitter</Label>
            <TextInput
              id="twitter"
              name="twitter"
              type="text"
              value={formData.twitter}
              onChange={(e) => setFormData({ ...formData, twitter: e.target.value })}
              placeholder="@username or full URL"
              disabled={loading}
            />
          </div>

          {/* Facebook */}
          <div>
            <Label htmlFor="facebook">Facebook</Label>
            <TextInput
              id="facebook"
              name="facebook"
              type="text"
              value={formData.facebook}
              onChange={(e) => setFormData({ ...formData, facebook: e.target.value })}
              placeholder="Facebook profile URL"
              disabled={loading}
            />
          </div>

          {/* LinkedIn */}
          <div>
            <Label htmlFor="linkedin">LinkedIn</Label>
            <TextInput
              id="linkedin"
              name="linkedin"
              type="text"
              value={formData.linkedin}
              onChange={(e) => setFormData({ ...formData, linkedin: e.target.value })}
              placeholder="LinkedIn profile URL"
              disabled={loading}
            />
          </div>

          {/* Website */}
          <div>
            <Label htmlFor="website">Website</Label>
            <TextInput
              id="website"
              name="website"
              type="url"
              value={formData.website}
              onChange={(e) => setFormData({ ...formData, website: e.target.value })}
              placeholder="https://example.com"
              disabled={loading}
            />
          </div>
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center justify-end gap-4">
        <Button type="button" color="gray" onClick={handleCancel} disabled={loading}>
          Cancel
        </Button>
        <Button type="submit" disabled={loading}>
          {loading ? (
            <>
              <Spinner size="sm" className="mr-2" />
              {author?.id ? 'Updating...' : 'Creating...'}
            </>
          ) : (
            <>{author?.id ? 'Update Author' : 'Create Author'}</>
          )}
        </Button>
      </div>
    </form>
  );
}
