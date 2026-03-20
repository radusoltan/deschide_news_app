'use client';

import { useEffect, useState } from 'react';
import {
    Button,
    Modal,
    Spinner,
    Alert,
    ModalHeader,
    ModalBody,
    ModalFooter
} from 'flowbite-react';
import { HiOutlineTrash, HiPlus, HiOutlineExclamationCircle } from 'react-icons/hi';

// Helper function to get auth token from cookies
async function getAuthToken(): Promise<string | null> {
  try {
    const response = await fetch('/api/auth/token');
    if (!response.ok) return null;
    const data = await response.json();
    return data.token;
  } catch {
    return null;
  }
}

interface Article {
  id: number;
  title: string;
  slug: string;
  category?: {
    id: number;
    name: string;
  };
}

interface ImportantArticle {
  id: number;
  article: Article;
  position: number;
}

interface ImportantArticlesManagerProps {
  locale: string;
}

export default function ImportantArticlesManager({ locale }: ImportantArticlesManagerProps) {
  const [importantArticles, setImportantArticles] = useState<ImportantArticle[]>([]);
  const [allArticles, setAllArticles] = useState<Article[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddModal, setShowAddModal] = useState(false);
  const [selectedArticle, setSelectedArticle] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    fetchImportantArticles();
    fetchAllArticles();
  }, []);

  const fetchImportantArticles = async () => {
    try {
      const token = await getAuthToken();
      if (!token) {
        setError('Not authenticated');
        setLoading(false);
        return;
      }

      const response = await fetch('http://127.0.0.1:8081/api/important_articles', {
        headers: {
          'Accept-Language': locale,
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) throw new Error('Failed to fetch important articles');

      const data = await response.json();
      setImportantArticles(data['hydra:member'] || data.member || []);
    } catch (err) {
      setError('Failed to load important articles');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const fetchAllArticles = async () => {
    try {
      const token = await getAuthToken();
      if (!token) return;

      const response = await fetch('http://127.0.0.1:8081/api/articles?itemsPerPage=100', {
        headers: {
          'Accept-Language': locale,
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) throw new Error('Failed to fetch articles');

      const data = await response.json();
      setAllArticles(data['hydra:member'] || data.member || []);
    } catch (err) {
      console.error('Failed to load articles:', err);
    }
  };

  const handleAddArticle = async () => {
    if (!selectedArticle) return;

    try {
      const token = await getAuthToken();
      if (!token) {
        setError('Not authenticated');
        return;
      }

      const nextPosition = importantArticles.length + 1;

      const response = await fetch('http://127.0.0.1:8081/api/important_articles', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          'Accept-Language': locale,
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
          article: `/api/articles/${selectedArticle}`,
          position: nextPosition,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData['hydra:description'] || 'Failed to add article');
      }

      setSuccess('Article added successfully!');
      setShowAddModal(false);
      setSelectedArticle(null);
      fetchImportantArticles();

      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to add article');
      setTimeout(() => setError(null), 5000);
    }
  };

  const handleRemoveArticle = async (id: number) => {
    if (importantArticles.length <= 5) {
      setError('Cannot remove article. The list must have at least 5 articles.');
      setTimeout(() => setError(null), 5000);
      return;
    }

    if (!confirm('Are you sure you want to remove this article from the important list?')) {
      return;
    }

    try {
      const token = await getAuthToken();
      if (!token) {
        setError('Not authenticated');
        return;
      }

      const response = await fetch(`http://127.0.0.1:8081/api/important_articles/${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to remove article');
      }

      setSuccess('Article removed successfully!');
      fetchImportantArticles();

      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to remove article');
      setTimeout(() => setError(null), 5000);
    }
  };

  const handleMoveUp = (index: number) => {
    if (index === 0) return;

    const items = Array.from(importantArticles);
    const [item] = items.splice(index, 1);
    items.splice(index - 1, 0, item);

    const updatedItems = items.map((item, idx) => ({
      ...item,
      position: idx + 1,
    }));

    setImportantArticles(updatedItems);
  };

  const handleMoveDown = (index: number) => {
    if (index === importantArticles.length - 1) return;

    const items = Array.from(importantArticles);
    const [item] = items.splice(index, 1);
    items.splice(index + 1, 0, item);

    const updatedItems = items.map((item, idx) => ({
      ...item,
      position: idx + 1,
    }));

    setImportantArticles(updatedItems);
  };

  const availableArticles = allArticles.filter(
    article => !importantArticles.some(ia => ia.article.id === article.id)
  ).filter(article =>
    (article.title || '').toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (loading) {
    return (
      <div className="flex justify-center items-center py-12">
        <Spinner size="xl" />
      </div>
    );
  }

  return (
    <div className="p-6">
      {/* Alerts */}
      {error && (
        <Alert color="failure" className="mb-4" icon={HiOutlineExclamationCircle}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert color="success" className="mb-4">
          {success}
        </Alert>
      )}

      {/* Stats */}
      <div className="mb-6 flex items-center justify-between">
        <div className="text-sm text-gray-600 dark:text-gray-400">
          <span className="font-semibold text-gray-900 dark:text-white">{importantArticles.length}</span> / 25 articles
          {importantArticles.length < 5 && (
            <span className="ml-2 text-red-600 font-semibold">
              (Minimum 5 required)
            </span>
          )}
        </div>

        <Button
          onClick={() => setShowAddModal(true)}
          disabled={importantArticles.length >= 25}
          size="sm"
        >
          <HiPlus className="mr-2 h-5 w-5" />
          Add Article
        </Button>
      </div>

      {/* Important Articles List */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm text-left text-gray-500 dark:text-gray-400">
          <thead className="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
              <th scope="col" className="px-6 py-3 w-20">Position</th>
              <th scope="col" className="px-6 py-3">Article Title</th>
              <th scope="col" className="px-6 py-3 w-40">Actions</th>
            </tr>
          </thead>
          <tbody>
            {importantArticles.map((item, index) => (
              <tr
                key={item.id}
                className="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                  {item.position}
                </td>
                <td className="px-6 py-4">
                  {item.article.title}
                </td>
                <td className="px-6 py-4">
                  <div className="flex gap-1 items-center">
                    <button
                      onClick={() => handleMoveUp(index)}
                      disabled={index === 0}
                      className="px-2 py-1 text-sm font-bold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded disabled:text-gray-300 disabled:cursor-not-allowed dark:text-blue-400 dark:hover:bg-blue-900"
                      title="Move up"
                    >
                      ↑
                    </button>
                    <button
                      onClick={() => handleMoveDown(index)}
                      disabled={index === importantArticles.length - 1}
                      className="px-2 py-1 text-sm font-bold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded disabled:text-gray-300 disabled:cursor-not-allowed dark:text-blue-400 dark:hover:bg-blue-900"
                      title="Move down"
                    >
                      ↓
                    </button>
                    <Button
                      size="xs"
                      color="failure"
                      onClick={() => handleRemoveArticle(item.id)}
                      disabled={importantArticles.length <= 5}
                      title={importantArticles.length <= 5 ? "Cannot remove - minimum 5 articles required" : "Remove article"}
                    >
                      <HiOutlineTrash className="h-4 w-4" />
                    </Button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Add Article Modal */}
      <Modal show={showAddModal} onClose={() => setShowAddModal(false)}>
        <ModalHeader>Add Article to Important List</ModalHeader>
        <ModalBody>
          <div className="space-y-4">
            <div>
              <label htmlFor="search" className="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                Search Articles
              </label>
              <input
                type="text"
                id="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search by title..."
                className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
              />
            </div>

            <div className="max-h-96 overflow-y-auto">
              <div className="space-y-2">
                {availableArticles.map((article) => (
                  <div
                    key={article.id}
                    onClick={() => setSelectedArticle(article.id)}
                    className={`p-3 border rounded-lg cursor-pointer transition-colors ${
                      selectedArticle === article.id
                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900'
                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-600'
                    }`}
                  >
                    <div className="font-medium text-gray-900 dark:text-white">
                      {article.title}
                    </div>
                    {article.category && (
                      <div className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {article.category.name}
                      </div>
                    )}
                  </div>
                ))}
                {availableArticles.length === 0 && (
                  <div className="text-center text-gray-500 py-8">
                    No available articles found
                  </div>
                )}
              </div>
            </div>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button onClick={handleAddArticle} disabled={!selectedArticle}>
            Add Article
          </Button>
          <Button color="gray" onClick={() => setShowAddModal(false)}>
            Cancel
          </Button>
        </ModalFooter>
      </Modal>
    </div>
  );
}
