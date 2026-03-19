'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Modal, ModalHeader, ModalBody, ModalFooter, Button, Spinner } from 'flowbite-react';
import { deleteShortLink } from '@/lib/api/short-links';

interface DeleteShortLinkModalProps {
  isOpen: boolean;
  onClose: () => void;
  shortLink: {
    id: number;
    code: string;
  };
  locale: string;
  accessToken: string | null;
}

export default function DeleteShortLinkModal({
  isOpen,
  onClose,
  shortLink,
  locale,
  accessToken,
}: DeleteShortLinkModalProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  const handleDelete = async () => {
    setIsDeleting(true);
    setError(null);

    try {
      const token = accessToken;
      if (!token) {
        throw new Error('Nu sunteți autentificat');
      }

      if (!result.success) {
        throw new Error(result.error || 'Eroare la ștergerea linkului scurt');
      }

      // Success - close modal and refresh
      onClose();
      router.refresh();
    } catch (err) {
      console.error('Failed to delete short link:', err);
      setError(
        err instanceof Error
          ? err.message
          : 'Eroare la ștergerea linkului scurt'
      );
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <Modal show={isOpen} onClose={onClose} size="md">
      <ModalHeader>Confirmare ștergere</ModalHeader>
      <ModalBody>
        <div className="space-y-4">
          <p className="text-base leading-relaxed text-gray-500 dark:text-gray-400">
            Sigur doriți să ștergeți linkul scurt <strong>{shortLink.code}</strong>?
          </p>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Această acțiune este permanentă și nu poate fi anulată. Toate
            statisticile asociate vor fi pierdute.
          </p>
          {error && (
            <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
              <p className="text-sm text-red-600 dark:text-red-400">
                {error}
              </p>
            </div>
          )}
        </div>
      </ModalBody>
      <ModalFooter>
        <Button color="failure" onClick={handleDelete} disabled={isDeleting}>
          {isDeleting ? (
            <>
              <Spinner size="sm" light className="mr-2" />
              Se șterge...
            </>
          ) : (
            'Șterge'
          )}
        </Button>
        <Button color="gray" onClick={onClose} disabled={isDeleting}>
          Anulează
        </Button>
      </ModalFooter>
    </Modal>
  );
}
