'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button, Label, TextInput, Spinner, Alert } from 'flowbite-react';
import { createShortLink, type CreateShortLinkData } from '@/lib/api/short-links';

interface CreateShortLinkFormProps {
  locale: string;
  accessToken: string | null;
}

export default function CreateShortLinkForm({ locale, accessToken }: CreateShortLinkFormProps) {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [createdLink, setCreatedLink] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const [formData, setFormData] = useState<CreateShortLinkData>({
    originalUrl: '',
    code: '',
    title: '',
  });

  const handleChange = (field: keyof CreateShortLinkData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setError(null);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);
    setCreatedLink(null);

    try {
      const token = accessToken;
      if (!token) {
        throw new Error('Nu sunteți autentificat');
      }

      const result = await createShortLink(formData, token);

      if (result) {
        // Success - show the created link
        const fullUrl = `${window.location.origin}/s/${result.code}`;
        setCreatedLink(fullUrl);

        // Reset form
        setFormData({
          originalUrl: '',
          code: '',
          title: '',
        });
      }
    } catch (err) {
      console.error('Failed to create short link:', err);
      setError(
        err instanceof Error ? err.message : 'Eroare la crearea linkului scurt'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCopy = async () => {
    if (createdLink) {
      try {
        await navigator.clipboard.writeText(createdLink);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
      } catch (err) {
        console.error('Failed to copy:', err);
      }
    }
  };

  const handleCreateAnother = () => {
    setCreatedLink(null);
    setCopied(false);
  };

  const handleGoToList = () => {
    router.push(`/${locale}/admin/short-links`);
  };

  // Show success screen if link was created
  if (createdLink) {
    return (
      <div className="max-w-2xl">
        <Alert color="success" className="mb-6">
          <span className="font-medium">Link scurt creat cu succes!</span>
        </Alert>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 space-y-4">
          <div>
            <Label htmlFor="created-url">Link scurt generat:</Label>
            <div className="flex gap-2 mt-2">
              <TextInput
                id="created-url"
                type="text"
                value={createdLink}
                readOnly
                className="flex-1"
              />
              <Button color="blue" onClick={handleCopy}>
                {copied ? (
                  <>
                    <svg
                      className="w-4 h-4 mr-2"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                    Copiat!
                  </>
                ) : (
                  <>
                    <svg
                      className="w-4 h-4 mr-2"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                      />
                    </svg>
                    Copiază
                  </>
                )}
              </Button>
            </div>
          </div>

          <div className="flex gap-4 pt-4">
            <Button color="blue" onClick={handleCreateAnother}>
              Creează alt link
            </Button>
            <Button color="gray" onClick={handleGoToList}>
              Înapoi la listă
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
      {error && (
        <Alert color="failure">
          <span className="font-medium">Eroare!</span> {error}
        </Alert>
      )}

      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 space-y-4">
        {/* Original URL */}
        <div>
          <Label htmlFor="originalUrl">URL Original *</Label>
          <TextInput
            id="originalUrl"
            type="url"
            placeholder="https://deschide.md/ro/articol-complet"
            value={formData.originalUrl}
            onChange={(e) => handleChange('originalUrl', e.target.value)}
            required
            disabled={isSubmitting}
          />
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            URL-ul complet către care va redirecționa linkul scurt
          </p>
        </div>

        {/* Custom Code */}
        <div>
          <Label htmlFor="code">Cod personalizat (opțional)</Label>
          <TextInput
            id="code"
            type="text"
            placeholder="cod-personalizat"
            value={formData.code}
            onChange={(e) => handleChange('code', e.target.value)}
            disabled={isSubmitting}
            pattern="[a-zA-Z0-9\-_]+"
            title="Folosiți doar litere, cifre, cratimă și underscore"
          />
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Lăsați gol pentru generare automată. Folosiți doar litere, cifre, - și _
          </p>
        </div>

        {/* Title */}
        <div>
          <Label htmlFor="title">Titlu (opțional)</Label>
          <TextInput
            id="title"
            type="text"
            placeholder="Descriere sau titlu pentru referință"
            value={formData.title}
            onChange={(e) => handleChange('title', e.target.value)}
            disabled={isSubmitting}
            maxLength={255}
          />
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Ajută la identificarea linkului în listă
          </p>
        </div>

        {/* Submit Button */}
        <div className="flex gap-4 pt-4">
          <Button type="submit" color="blue" disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <Spinner size="sm" light className="mr-2" />
                Se creează...
              </>
            ) : (
              'Creează link scurt'
            )}
          </Button>
          <Button
            type="button"
            color="gray"
            onClick={handleGoToList}
            disabled={isSubmitting}
          >
            Anulează
          </Button>
        </div>
      </div>
    </form>
  );
}
