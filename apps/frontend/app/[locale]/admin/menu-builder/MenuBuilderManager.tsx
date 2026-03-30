'use client';

import { useEffect, useState, useCallback } from 'react';
import {
  Button,
  Modal,
  Spinner,
  Alert,
  Badge,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from 'flowbite-react';
import {
  HiOutlineTrash,
  HiPlus,
  HiOutlineExclamationCircle,
  HiPencil,
  HiExternalLink,
} from 'react-icons/hi';
import type { MenuItem } from '@/lib/types/menu';
import type { Category } from '@/lib/types/article';

// ============================================================================
// Constants
// ============================================================================

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';
const LOCALES = ['ro', 'en', 'ru'] as const;
const LOCALE_LABELS: Record<string, string> = { ro: 'RO', en: 'EN', ru: 'RU' };

// ============================================================================
// Auth helper
// ============================================================================

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

// ============================================================================
// Component
// ============================================================================

interface MenuBuilderManagerProps {
  locale: string;
}

type MenuTab = 'main' | 'footer';

export default function MenuBuilderManager({ locale }: MenuBuilderManagerProps) {
  // State
  const [activeTab, setActiveTab] = useState<MenuTab>('main');
  const [menuItems, setMenuItems] = useState<MenuItem[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // Modals
  const [showAddCategoryModal, setShowAddCategoryModal] = useState(false);
  const [showAddLinkModal, setShowAddLinkModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  // Form state - Add Category
  const [selectedCategoryId, setSelectedCategoryId] = useState<string>('');

  // Form state - Add External Link
  const [linkLabel, setLinkLabel] = useState('');
  const [linkUrl, setLinkUrl] = useState('');
  const [linkNewTab, setLinkNewTab] = useState(false);

  // Form state - Edit
  const [editingItem, setEditingItem] = useState<MenuItem | null>(null);
  const [editLabels, setEditLabels] = useState<Record<string, string>>({});
  const [editLocaleTab, setEditLocaleTab] = useState<string>('ro');

  // Form state - Delete
  const [deletingItem, setDeletingItem] = useState<MenuItem | null>(null);

  // ==========================================================================
  // Data fetching
  // ==========================================================================

  const fetchMenuItems = useCallback(async () => {
    try {
      const token = await getAuthToken();
      if (!token) {
        setError('Not authenticated');
        setLoading(false);
        return;
      }

      const url = new URL(`${API_BASE_URL}/api/menu-items`);
      url.searchParams.set('menu', activeTab);
      url.searchParams.set('order[position]', 'asc');
      url.searchParams.set('itemsPerPage', '100');

      const response = await fetch(url.toString(), {
        headers: {
          'Accept-Language': locale,
          Authorization: `Bearer ${token}`,
        },
        cache: 'no-store',
      });

      if (!response.ok) throw new Error('Failed to fetch menu items');

      const data = await response.json();
      setMenuItems(data['hydra:member'] || data.member || []);
    } catch (err) {
      setError('Failed to load menu items');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, [activeTab, locale]);

  const fetchCategories = useCallback(async () => {
    try {
      const token = await getAuthToken();
      if (!token) return;

      const url = new URL(`${API_BASE_URL}/api/categories`);
      url.searchParams.set('itemsPerPage', '100');
      url.searchParams.set('status', 'active');

      const response = await fetch(url.toString(), {
        headers: {
          'Accept-Language': locale,
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) throw new Error('Failed to fetch categories');

      const data = await response.json();
      setCategories(data['hydra:member'] || data.member || []);
    } catch (err) {
      console.error('Failed to load categories:', err);
    }
  }, [locale]);

  useEffect(() => {
    setLoading(true);
    fetchMenuItems();
  }, [fetchMenuItems]);

  useEffect(() => {
    fetchCategories();
  }, [fetchCategories]);

  // ==========================================================================
  // Helpers
  // ==========================================================================

  const showSuccessMessage = useCallback((msg: string) => {
    setSuccess(msg);
    setTimeout(() => setSuccess(null), 3000);
  }, []);

  const showErrorMessage = useCallback((msg: string) => {
    setError(msg);
    setTimeout(() => setError(null), 5000);
  }, []);

  /**
   * Categories already present in the current menu tab.
   * Filter them out of the "Add Category" dropdown.
   */
  const usedCategoryIris = menuItems
    .filter((item) => item.type === 'category' && item.category)
    .map((item) => item.category);

  const availableCategories = categories.filter(
    (cat) => !usedCategoryIris.includes(cat['@id'])
  );

  // ==========================================================================
  // Reorder
  // ==========================================================================

  const handleMoveUp = useCallback(
    async (index: number) => {
      if (index === 0) return;

      const current = menuItems[index];
      const above = menuItems[index - 1];

      setSaving(true);
      try {
        const token = await getAuthToken();
        if (!token) throw new Error('Not authenticated');

        // Swap positions via two PATCH calls
        await Promise.all([
          fetch(`${API_BASE_URL}/api/menu-items/${current.id}`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/merge-patch+json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ position: above.position }),
          }),
          fetch(`${API_BASE_URL}/api/menu-items/${above.id}`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/merge-patch+json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ position: current.position }),
          }),
        ]);

        await fetchMenuItems();
      } catch (err) {
        showErrorMessage(err instanceof Error ? err.message : 'Failed to reorder');
      } finally {
        setSaving(false);
      }
    },
    [menuItems, fetchMenuItems, showErrorMessage]
  );

  const handleMoveDown = useCallback(
    async (index: number) => {
      if (index >= menuItems.length - 1) return;

      const current = menuItems[index];
      const below = menuItems[index + 1];

      setSaving(true);
      try {
        const token = await getAuthToken();
        if (!token) throw new Error('Not authenticated');

        await Promise.all([
          fetch(`${API_BASE_URL}/api/menu-items/${current.id}`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/merge-patch+json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ position: below.position }),
          }),
          fetch(`${API_BASE_URL}/api/menu-items/${below.id}`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/merge-patch+json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ position: current.position }),
          }),
        ]);

        await fetchMenuItems();
      } catch (err) {
        showErrorMessage(err instanceof Error ? err.message : 'Failed to reorder');
      } finally {
        setSaving(false);
      }
    },
    [menuItems, fetchMenuItems, showErrorMessage]
  );

  // ==========================================================================
  // Toggle active
  // ==========================================================================

  const handleToggleActive = useCallback(
    async (item: MenuItem) => {
      setSaving(true);
      try {
        const token = await getAuthToken();
        if (!token) throw new Error('Not authenticated');

        const response = await fetch(`${API_BASE_URL}/api/menu-items/${item.id}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/merge-patch+json',
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({ isActive: !item.isActive }),
        });

        if (!response.ok) throw new Error('Failed to toggle status');

        await fetchMenuItems();
      } catch (err) {
        showErrorMessage(err instanceof Error ? err.message : 'Failed to update status');
      } finally {
        setSaving(false);
      }
    },
    [fetchMenuItems, showErrorMessage]
  );

  // ==========================================================================
  // Add Category
  // ==========================================================================

  const handleAddCategory = useCallback(async () => {
    if (!selectedCategoryId) return;

    const category = categories.find((c) => String(c.id) === selectedCategoryId);
    if (!category) return;

    setSaving(true);
    try {
      const token = await getAuthToken();
      if (!token) throw new Error('Not authenticated');

      const nextPosition = menuItems.length > 0
        ? Math.max(...menuItems.map((i) => i.position)) + 1
        : 1;

      const response = await fetch(`${API_BASE_URL}/api/menu-items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          Authorization: `Bearer ${token}`,
          'Accept-Language': locale,
        },
        body: JSON.stringify({
          menu: activeTab,
          type: 'category',
          label: category.title,
          category: category['@id'],
          position: nextPosition,
          isActive: true,
          openInNewTab: false,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(
          errorData['hydra:description'] || 'Failed to add category'
        );
      }

      showSuccessMessage('Category added to menu');
      setShowAddCategoryModal(false);
      setSelectedCategoryId('');
      await fetchMenuItems();
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to add category');
    } finally {
      setSaving(false);
    }
  }, [
    selectedCategoryId,
    categories,
    menuItems,
    activeTab,
    locale,
    fetchMenuItems,
    showSuccessMessage,
    showErrorMessage,
  ]);

  // ==========================================================================
  // Add External Link
  // ==========================================================================

  const handleAddExternalLink = useCallback(async () => {
    if (!linkLabel.trim() || !linkUrl.trim()) return;

    setSaving(true);
    try {
      const token = await getAuthToken();
      if (!token) throw new Error('Not authenticated');

      const nextPosition = menuItems.length > 0
        ? Math.max(...menuItems.map((i) => i.position)) + 1
        : 1;

      const response = await fetch(`${API_BASE_URL}/api/menu-items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          Authorization: `Bearer ${token}`,
          'Accept-Language': locale,
        },
        body: JSON.stringify({
          menu: activeTab,
          type: 'external_link',
          label: linkLabel.trim(),
          url: linkUrl.trim(),
          position: nextPosition,
          isActive: true,
          openInNewTab: linkNewTab,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(
          errorData['hydra:description'] || 'Failed to add external link'
        );
      }

      showSuccessMessage('External link added to menu');
      setShowAddLinkModal(false);
      setLinkLabel('');
      setLinkUrl('');
      setLinkNewTab(false);
      await fetchMenuItems();
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to add link');
    } finally {
      setSaving(false);
    }
  }, [
    linkLabel,
    linkUrl,
    linkNewTab,
    menuItems,
    activeTab,
    locale,
    fetchMenuItems,
    showSuccessMessage,
    showErrorMessage,
  ]);

  // ==========================================================================
  // Edit (with translation tabs)
  // ==========================================================================

  const openEditModal = useCallback(
    async (item: MenuItem) => {
      setEditingItem(item);
      setEditLocaleTab('ro');

      // Pre-load labels for each locale
      const labels: Record<string, string> = {};
      const token = await getAuthToken();
      if (!token) return;

      for (const loc of LOCALES) {
        try {
          const response = await fetch(
            `${API_BASE_URL}/api/menu-items/${item.id}`,
            {
              headers: {
                'Accept-Language': loc,
                Authorization: `Bearer ${token}`,
              },
            }
          );
          if (response.ok) {
            const data = await response.json();
            labels[loc] = data.label || '';
          } else {
            labels[loc] = item.label;
          }
        } catch {
          labels[loc] = item.label;
        }
      }

      setEditLabels(labels);
      setShowEditModal(true);
    },
    []
  );

  const handleSaveEdit = useCallback(async () => {
    if (!editingItem) return;

    setSaving(true);
    try {
      const token = await getAuthToken();
      if (!token) throw new Error('Not authenticated');

      // Save label for each locale that has a value
      for (const loc of LOCALES) {
        const label = editLabels[loc]?.trim();
        if (!label) continue;

        const response = await fetch(
          `${API_BASE_URL}/api/menu-items/${editingItem.id}`,
          {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/merge-patch+json',
              Authorization: `Bearer ${token}`,
              'Accept-Language': loc,
            },
            body: JSON.stringify({ label }),
          }
        );

        if (!response.ok) {
          throw new Error(`Failed to save ${loc.toUpperCase()} translation`);
        }
      }

      showSuccessMessage('Menu item updated');
      setShowEditModal(false);
      setEditingItem(null);
      await fetchMenuItems();
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to update');
    } finally {
      setSaving(false);
    }
  }, [editingItem, editLabels, fetchMenuItems, showSuccessMessage, showErrorMessage]);

  // ==========================================================================
  // Delete
  // ==========================================================================

  const handleDelete = useCallback(async () => {
    if (!deletingItem) return;

    setSaving(true);
    try {
      const token = await getAuthToken();
      if (!token) throw new Error('Not authenticated');

      const response = await fetch(
        `${API_BASE_URL}/api/menu-items/${deletingItem.id}`,
        {
          method: 'DELETE',
          headers: { Authorization: `Bearer ${token}` },
        }
      );

      if (!response.ok && response.status !== 204) {
        throw new Error('Failed to delete menu item');
      }

      showSuccessMessage('Menu item deleted');
      setShowDeleteModal(false);
      setDeletingItem(null);
      await fetchMenuItems();
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to delete');
    } finally {
      setSaving(false);
    }
  }, [deletingItem, fetchMenuItems, showSuccessMessage, showErrorMessage]);

  // ==========================================================================
  // Render
  // ==========================================================================

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

      {/* Tab Switcher */}
      <div className="mb-6 flex items-center justify-between flex-wrap gap-4">
        <div className="flex gap-2">
          <button
            onClick={() => setActiveTab('main')}
            className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
              activeTab === 'main'
                ? 'bg-blue-700 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
            }`}
          >
            Main Menu
          </button>
          <button
            onClick={() => setActiveTab('footer')}
            className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
              activeTab === 'footer'
                ? 'bg-blue-700 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
            }`}
          >
            Footer Menu
          </button>
        </div>

        <div className="flex gap-2">
          <Button
            size="sm"
            onClick={() => setShowAddCategoryModal(true)}
            disabled={availableCategories.length === 0}
          >
            <HiPlus className="mr-2 h-4 w-4" />
            Add Category
          </Button>
          <Button
            size="sm"
            color="purple"
            onClick={() => setShowAddLinkModal(true)}
          >
            <HiExternalLink className="mr-2 h-4 w-4" />
            Add External Link
          </Button>
        </div>
      </div>

      {/* Item count */}
      <div className="mb-4 text-sm text-gray-600 dark:text-gray-400">
        <span className="font-semibold text-gray-900 dark:text-white">
          {menuItems.length}
        </span>{' '}
        items in{' '}
        <span className="font-semibold text-gray-900 dark:text-white">
          {activeTab === 'main' ? 'Main' : 'Footer'}
        </span>{' '}
        menu
      </div>

      {/* Table */}
      {menuItems.length === 0 ? (
        <div className="text-center py-12 text-gray-500 dark:text-gray-400">
          No items in this menu yet. Add a category or external link to get started.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left text-gray-500 dark:text-gray-400">
            <thead className="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
              <tr>
                <th scope="col" className="px-4 py-3 w-28">
                  Position
                </th>
                <th scope="col" className="px-4 py-3">
                  Label
                </th>
                <th scope="col" className="px-4 py-3 w-36">
                  Type
                </th>
                <th scope="col" className="px-4 py-3 w-24">
                  Status
                </th>
                <th scope="col" className="px-4 py-3 w-44">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {menuItems.map((item, index) => (
                <tr
                  key={item.id}
                  className={`border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 ${
                    item.isActive
                      ? 'bg-white dark:bg-gray-800'
                      : 'bg-gray-50/50 dark:bg-gray-800/50'
                  }`}
                >
                  {/* Position with reorder buttons */}
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1">
                      <span className="font-medium text-gray-900 dark:text-white w-6 text-center">
                        {item.position}
                      </span>
                      <div className="flex flex-col">
                        <button
                          onClick={() => handleMoveUp(index)}
                          disabled={index === 0 || saving}
                          className="px-1 py-0.5 text-xs font-bold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded disabled:text-gray-300 disabled:cursor-not-allowed dark:text-blue-400 dark:hover:bg-blue-900 dark:disabled:text-gray-600"
                          title="Move up"
                        >
                          ▲
                        </button>
                        <button
                          onClick={() => handleMoveDown(index)}
                          disabled={index === menuItems.length - 1 || saving}
                          className="px-1 py-0.5 text-xs font-bold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded disabled:text-gray-300 disabled:cursor-not-allowed dark:text-blue-400 dark:hover:bg-blue-900 dark:disabled:text-gray-600"
                          title="Move down"
                        >
                          ▼
                        </button>
                      </div>
                    </div>
                  </td>

                  {/* Label */}
                  <td className="px-4 py-3">
                    <span
                      className={`font-medium ${
                        item.isActive
                          ? 'text-gray-900 dark:text-white'
                          : 'text-gray-400 dark:text-gray-500 line-through'
                      }`}
                    >
                      {item.label}
                    </span>
                    {item.url && (
                      <div className="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate max-w-xs">
                        {item.url}
                      </div>
                    )}
                  </td>

                  {/* Type badge */}
                  <td className="px-4 py-3">
                    {item.type === 'category' ? (
                      <Badge color="info" size="sm">
                        Category
                      </Badge>
                    ) : (
                      <Badge color="purple" size="sm">
                        External Link
                      </Badge>
                    )}
                    {item.openInNewTab && (
                      <span className="ml-1 text-xs text-gray-400" title="Opens in new tab">
                        ↗
                      </span>
                    )}
                  </td>

                  {/* Status toggle */}
                  <td className="px-4 py-3">
                    <button
                      onClick={() => handleToggleActive(item)}
                      disabled={saving}
                      className="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                      style={{
                        backgroundColor: item.isActive ? '#2563eb' : '#d1d5db',
                      }}
                      title={item.isActive ? 'Active - click to deactivate' : 'Inactive - click to activate'}
                    >
                      <span
                        className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                          item.isActive ? 'translate-x-6' : 'translate-x-1'
                        }`}
                      />
                    </button>
                  </td>

                  {/* Actions */}
                  <td className="px-4 py-3">
                    <div className="flex gap-1 items-center">
                      <Button
                        size="xs"
                        color="light"
                        onClick={() => openEditModal(item)}
                        disabled={saving}
                        title="Edit translations"
                      >
                        <HiPencil className="h-4 w-4" />
                      </Button>
                      <Button
                        size="xs"
                        color="failure"
                        onClick={() => {
                          setDeletingItem(item);
                          setShowDeleteModal(true);
                        }}
                        disabled={saving}
                        title="Delete"
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
      )}

      {/* ================================================================== */}
      {/* Add Category Modal */}
      {/* ================================================================== */}
      <Modal
        show={showAddCategoryModal}
        onClose={() => {
          setShowAddCategoryModal(false);
          setSelectedCategoryId('');
        }}
      >
        <ModalHeader>Add Category to Menu</ModalHeader>
        <ModalBody>
          <div className="space-y-4">
            <div>
              <label
                htmlFor="category-select"
                className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
              >
                Select Category
              </label>
              <select
                id="category-select"
                value={selectedCategoryId}
                onChange={(e) => setSelectedCategoryId(e.target.value)}
                className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
                <option value="">-- Select a category --</option>
                {availableCategories.map((cat) => (
                  <option key={cat.id} value={String(cat.id)}>
                    {cat.title} {cat.slug ? `(/${cat.slug})` : ''}
                  </option>
                ))}
              </select>
              {availableCategories.length === 0 && (
                <p className="mt-2 text-sm text-gray-500">
                  All categories are already in this menu.
                </p>
              )}
            </div>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button onClick={handleAddCategory} disabled={!selectedCategoryId || saving}>
            {saving ? <Spinner size="sm" className="mr-2" /> : null}
            Add Category
          </Button>
          <Button
            color="gray"
            onClick={() => {
              setShowAddCategoryModal(false);
              setSelectedCategoryId('');
            }}
          >
            Cancel
          </Button>
        </ModalFooter>
      </Modal>

      {/* ================================================================== */}
      {/* Add External Link Modal */}
      {/* ================================================================== */}
      <Modal
        show={showAddLinkModal}
        onClose={() => {
          setShowAddLinkModal(false);
          setLinkLabel('');
          setLinkUrl('');
          setLinkNewTab(false);
        }}
      >
        <ModalHeader>Add External Link</ModalHeader>
        <ModalBody>
          <div className="space-y-4">
            <div>
              <label
                htmlFor="link-label"
                className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
              >
                Label
              </label>
              <input
                type="text"
                id="link-label"
                value={linkLabel}
                onChange={(e) => setLinkLabel(e.target.value)}
                placeholder="e.g. Partner Site"
                className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
              />
            </div>
            <div>
              <label
                htmlFor="link-url"
                className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
              >
                URL
              </label>
              <input
                type="url"
                id="link-url"
                value={linkUrl}
                onChange={(e) => setLinkUrl(e.target.value)}
                placeholder="https://example.com"
                className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
              />
            </div>
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="link-new-tab"
                checked={linkNewTab}
                onChange={(e) => setLinkNewTab(e.target.checked)}
                className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600"
              />
              <label
                htmlFor="link-new-tab"
                className="text-sm font-medium text-gray-900 dark:text-white"
              >
                Open in new tab
              </label>
            </div>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button
            onClick={handleAddExternalLink}
            disabled={!linkLabel.trim() || !linkUrl.trim() || saving}
          >
            {saving ? <Spinner size="sm" className="mr-2" /> : null}
            Add Link
          </Button>
          <Button
            color="gray"
            onClick={() => {
              setShowAddLinkModal(false);
              setLinkLabel('');
              setLinkUrl('');
              setLinkNewTab(false);
            }}
          >
            Cancel
          </Button>
        </ModalFooter>
      </Modal>

      {/* ================================================================== */}
      {/* Edit Modal with Translation Tabs */}
      {/* ================================================================== */}
      <Modal
        show={showEditModal}
        onClose={() => {
          setShowEditModal(false);
          setEditingItem(null);
        }}
      >
        <ModalHeader>
          Edit Menu Item
        </ModalHeader>
        <ModalBody>
          {editingItem && (
            <div className="space-y-4">
              <div className="text-sm text-gray-500 dark:text-gray-400">
                Type:{' '}
                {editingItem.type === 'category' ? (
                  <Badge color="info" size="sm">Category</Badge>
                ) : (
                  <Badge color="purple" size="sm">External Link</Badge>
                )}
              </div>

              {/* Locale tabs */}
              <div className="border-b border-gray-200 dark:border-gray-600">
                <ul className="flex flex-wrap -mb-px text-sm font-medium text-center">
                  {LOCALES.map((loc) => (
                    <li key={loc} className="mr-2">
                      <button
                        onClick={() => setEditLocaleTab(loc)}
                        className={`inline-block p-3 border-b-2 rounded-t-lg ${
                          editLocaleTab === loc
                            ? 'text-blue-600 border-blue-600 dark:text-blue-400 dark:border-blue-400'
                            : 'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'
                        }`}
                      >
                        {LOCALE_LABELS[loc]}
                      </button>
                    </li>
                  ))}
                </ul>
              </div>

              {/* Label input for selected locale */}
              <div>
                <label
                  htmlFor="edit-label"
                  className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
                >
                  Label ({LOCALE_LABELS[editLocaleTab]})
                </label>
                <input
                  type="text"
                  id="edit-label"
                  value={editLabels[editLocaleTab] || ''}
                  onChange={(e) =>
                    setEditLabels((prev) => ({
                      ...prev,
                      [editLocaleTab]: e.target.value,
                    }))
                  }
                  className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
              </div>
            </div>
          )}
        </ModalBody>
        <ModalFooter>
          <Button onClick={handleSaveEdit} disabled={saving}>
            {saving ? <Spinner size="sm" className="mr-2" /> : null}
            Save All Translations
          </Button>
          <Button
            color="gray"
            onClick={() => {
              setShowEditModal(false);
              setEditingItem(null);
            }}
          >
            Cancel
          </Button>
        </ModalFooter>
      </Modal>

      {/* ================================================================== */}
      {/* Delete Confirmation Modal */}
      {/* ================================================================== */}
      <Modal
        show={showDeleteModal}
        size="md"
        onClose={() => {
          setShowDeleteModal(false);
          setDeletingItem(null);
        }}
        popup
      >
        <ModalHeader />
        <ModalBody>
          <div className="text-center">
            <HiOutlineExclamationCircle className="mx-auto mb-4 h-14 w-14 text-gray-400 dark:text-gray-200" />
            <h3 className="mb-5 text-lg font-normal text-gray-500 dark:text-gray-400">
              Are you sure you want to delete{' '}
              <span className="font-semibold text-gray-900 dark:text-white">
                {deletingItem?.label}
              </span>
              ?
            </h3>
            <div className="flex justify-center gap-4">
              <Button color="failure" onClick={handleDelete} disabled={saving}>
                {saving ? <Spinner size="sm" className="mr-2" /> : null}
                Yes, delete
              </Button>
              <Button
                color="gray"
                onClick={() => {
                  setShowDeleteModal(false);
                  setDeletingItem(null);
                }}
              >
                No, cancel
              </Button>
            </div>
          </div>
        </ModalBody>
      </Modal>
    </div>
  );
}
