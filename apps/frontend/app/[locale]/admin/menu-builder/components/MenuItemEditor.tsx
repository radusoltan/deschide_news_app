'use client';

import {
  Button,
  Modal,
  Spinner,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from 'flowbite-react';
import { HiOutlineExclamationCircle } from 'react-icons/hi';
import type { MenuItem } from '@/lib/types/menu';
import type { Category } from '@/lib/types/article';

// ============================================================================
// Add Category Modal
// ============================================================================

interface AddCategoryModalProps {
  show: boolean;
  onClose: () => void;
  saving: boolean;
  activeTab: 'main' | 'footer';
  availableCategories: Category[];
  dropdownItems: MenuItem[];
  selectedCategoryId: string;
  setSelectedCategoryId: (v: string) => void;
  addCategoryParent: string;
  setAddCategoryParent: (v: string) => void;
  onSubmit: () => Promise<void>;
}

export function AddCategoryModal({
  show,
  onClose,
  saving,
  activeTab,
  availableCategories,
  dropdownItems,
  selectedCategoryId,
  setSelectedCategoryId,
  addCategoryParent,
  setAddCategoryParent,
  onSubmit,
}: AddCategoryModalProps) {
  return (
    <Modal
      show={show}
      onClose={() => {
        onClose();
        setSelectedCategoryId('');
        setAddCategoryParent('');
      }}
    >
      <ModalHeader>Add Category to Menu</ModalHeader>
      <ModalBody>
        <div className="space-y-4">
          <div>
            <label
              htmlFor="category-select"
              className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
            >
              Select Category
            </label>
            <select
              id="category-select"
              value={selectedCategoryId}
              onChange={(e) => setSelectedCategoryId(e.target.value)}
              className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-primary-dark"
            >
              <option value="">-- Select a category --</option>
              {availableCategories.map((cat) => (
                <option key={cat.id} value={String(cat.id)}>
                  {cat.title} {cat.slug ? `(/${cat.slug})` : ''}
                </option>
              ))}
            </select>
            {availableCategories.length === 0 && (
              <p className="mt-2 text-sm text-secondary">
                All categories are already in this menu.
              </p>
            )}
          </div>

          {/* Parent dropdown selector (only for main menu) */}
          {activeTab === 'main' && dropdownItems.length > 0 && (
            <div>
              <label
                htmlFor="category-parent-select"
                className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
              >
                Parent (optional)
              </label>
              <select
                id="category-parent-select"
                value={addCategoryParent}
                onChange={(e) => setAddCategoryParent(e.target.value)}
                className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-primary-dark"
              >
                <option value="">-- Top level (no parent) --</option>
                {dropdownItems.map((dd) => (
                  <option key={dd.id} value={dd['@id']}>
                    {dd.label}
                  </option>
                ))}
              </select>
              <p className="mt-1 text-xs text-secondary dark:text-gray-400">
                Nest this category under a dropdown menu item.
              </p>
            </div>
          )}
        </div>
      </ModalBody>
      <ModalFooter>
        <Button onClick={onSubmit} disabled={!selectedCategoryId || saving}>
          {saving ? <Spinner size="sm" className="mr-2" /> : null}
          Add Category
        </Button>
        <Button
          color="gray"
          onClick={() => {
            onClose();
            setSelectedCategoryId('');
            setAddCategoryParent('');
          }}
        >
          Cancel
        </Button>
      </ModalFooter>
    </Modal>
  );
}

// ============================================================================
// Add External Link Modal
// ============================================================================

interface AddLinkModalProps {
  show: boolean;
  onClose: () => void;
  saving: boolean;
  activeTab: 'main' | 'footer';
  dropdownItems: MenuItem[];
  linkLabel: string;
  setLinkLabel: (v: string) => void;
  linkUrl: string;
  setLinkUrl: (v: string) => void;
  linkNewTab: boolean;
  setLinkNewTab: (v: boolean) => void;
  addLinkParent: string;
  setAddLinkParent: (v: string) => void;
  onSubmit: () => Promise<void>;
}

export function AddLinkModal({
  show,
  onClose,
  saving,
  activeTab,
  dropdownItems,
  linkLabel,
  setLinkLabel,
  linkUrl,
  setLinkUrl,
  linkNewTab,
  setLinkNewTab,
  addLinkParent,
  setAddLinkParent,
  onSubmit,
}: AddLinkModalProps) {
  return (
    <Modal
      show={show}
      onClose={() => {
        onClose();
        setLinkLabel('');
        setLinkUrl('');
        setLinkNewTab(false);
        setAddLinkParent('');
      }}
    >
      <ModalHeader>Add External Link</ModalHeader>
      <ModalBody>
        <div className="space-y-4">
          <div>
            <label
              htmlFor="link-label"
              className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
            >
              Label
            </label>
            <input
              type="text"
              id="link-label"
              value={linkLabel}
              onChange={(e) => setLinkLabel(e.target.value)}
              placeholder="e.g. Partner Site"
              className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-primary-dark"
            />
          </div>
          <div>
            <label
              htmlFor="link-url"
              className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
            >
              URL
            </label>
            <input
              type="url"
              id="link-url"
              value={linkUrl}
              onChange={(e) => setLinkUrl(e.target.value)}
              placeholder="https://example.com"
              className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-primary-dark"
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
              className="text-sm font-medium text-primary dark:text-primary-dark"
            >
              Open in new tab
            </label>
          </div>

          {/* Parent dropdown selector (only for main menu) */}
          {activeTab === 'main' && dropdownItems.length > 0 && (
            <div>
              <label
                htmlFor="link-parent-select"
                className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
              >
                Parent (optional)
              </label>
              <select
                id="link-parent-select"
                value={addLinkParent}
                onChange={(e) => setAddLinkParent(e.target.value)}
                className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-primary-dark"
              >
                <option value="">-- Top level (no parent) --</option>
                {dropdownItems.map((dd) => (
                  <option key={dd.id} value={dd['@id']}>
                    {dd.label}
                  </option>
                ))}
              </select>
            </div>
          )}
        </div>
      </ModalBody>
      <ModalFooter>
        <Button
          onClick={onSubmit}
          disabled={!linkLabel.trim() || !linkUrl.trim() || saving}
        >
          {saving ? <Spinner size="sm" className="mr-2" /> : null}
          Add Link
        </Button>
        <Button
          color="gray"
          onClick={() => {
            onClose();
            setLinkLabel('');
            setLinkUrl('');
            setLinkNewTab(false);
            setAddLinkParent('');
          }}
        >
          Cancel
        </Button>
      </ModalFooter>
    </Modal>
  );
}

// ============================================================================
// Add Dropdown Modal
// ============================================================================

interface AddDropdownModalProps {
  show: boolean;
  onClose: () => void;
  saving: boolean;
  dropdownLabel: string;
  setDropdownLabel: (v: string) => void;
  dropdownLabelEn: string;
  setDropdownLabelEn: (v: string) => void;
  dropdownLabelRu: string;
  setDropdownLabelRu: (v: string) => void;
  onSubmit: () => Promise<void>;
}

export function AddDropdownModal({
  show,
  onClose,
  saving,
  dropdownLabel,
  setDropdownLabel,
  dropdownLabelEn,
  setDropdownLabelEn,
  dropdownLabelRu,
  setDropdownLabelRu,
  onSubmit,
}: AddDropdownModalProps) {
  const resetAndClose = () => {
    onClose();
    setDropdownLabel('');
    setDropdownLabelEn('');
    setDropdownLabelRu('');
  };

  return (
    <Modal show={show} onClose={resetAndClose}>
      <ModalHeader>Add Dropdown Menu</ModalHeader>
      <ModalBody>
        <div className="space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            A dropdown acts as a container for sub-items. It does not link anywhere itself.
            After creating it, add categories or external links as children.
          </p>
          <div>
            <label
              htmlFor="dropdown-label"
              className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
            >
              Label (RO)
            </label>
            <input
              type="text"
              id="dropdown-label"
              value={dropdownLabel}
              onChange={(e) => setDropdownLabel(e.target.value)}
              placeholder='e.g. "Știri" or "Mai mult"'
              className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-primary-dark"
            />
          </div>
          <div>
            <label
              htmlFor="dropdown-label-en"
              className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
            >
              Label (EN)
            </label>
            <input
              type="text"
              id="dropdown-label-en"
              value={dropdownLabelEn}
              onChange={(e) => setDropdownLabelEn(e.target.value)}
              placeholder='e.g. "News" or "More"'
              className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-primary-dark"
            />
          </div>
          <div>
            <label
              htmlFor="dropdown-label-ru"
              className="block mb-2 text-sm font-medium text-primary dark:text-primary-dark"
            >
              Label (RU)
            </label>
            <input
              type="text"
              id="dropdown-label-ru"
              value={dropdownLabelRu}
              onChange={(e) => setDropdownLabelRu(e.target.value)}
              placeholder='e.g. "Новости" or "Ещё"'
              className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-primary-dark"
            />
          </div>
        </div>
      </ModalBody>
      <ModalFooter>
        <button
          onClick={onSubmit}
          disabled={!dropdownLabel.trim() || saving}
          className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-amber-500 text-white hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-700 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed dark:disabled:bg-gray-700 dark:disabled:text-gray-500"
        >
          {saving ? <Spinner size="sm" className="mr-2" /> : null}
          Add Dropdown
        </button>
        <button
          onClick={resetAndClose}
          className="px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
        >
          Cancel
        </button>
      </ModalFooter>
    </Modal>
  );
}

// ============================================================================
// Delete Confirmation Modal
// ============================================================================

interface DeleteMenuItemModalProps {
  show: boolean;
  onClose: () => void;
  saving: boolean;
  deletingItem: MenuItem | null;
  onConfirm: () => Promise<void>;
}

export function DeleteMenuItemModal({
  show,
  onClose,
  saving,
  deletingItem,
  onConfirm,
}: DeleteMenuItemModalProps) {
  return (
    <Modal
      show={show}
      size="md"
      onClose={onClose}
      popup
    >
      <ModalHeader />
      <ModalBody>
        <div className="text-center">
          <HiOutlineExclamationCircle className="mx-auto mb-4 h-14 w-14 text-gray-400 dark:text-gray-200" />
          <h3 className="mb-2 text-lg font-normal text-secondary dark:text-gray-400">
            Are you sure you want to delete{' '}
            <span className="font-semibold text-primary dark:text-primary-dark">
              {deletingItem?.label}
            </span>
            ?
          </h3>
          {deletingItem?.type === 'dropdown' &&
            deletingItem.children &&
            deletingItem.children.length > 0 && (
              <p className="mb-4 text-sm text-red-600 dark:text-red-400">
                This dropdown has {deletingItem.children.length} sub-item(s).
                They will also be removed.
              </p>
            )}
          <div className="flex justify-center gap-4">
            <Button color="failure" onClick={onConfirm} disabled={saving}>
              {saving ? <Spinner size="sm" className="mr-2" /> : null}
              Yes, delete
            </Button>
            <Button color="gray" onClick={onClose}>
              No, cancel
            </Button>
          </div>
        </div>
      </ModalBody>
    </Modal>
  );
}
