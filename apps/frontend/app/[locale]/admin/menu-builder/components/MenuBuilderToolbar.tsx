'use client';

import {
  Button,
  Spinner,
  Alert,
} from 'flowbite-react';
import {
  HiPlus,
  HiOutlineExclamationCircle,
  HiExternalLink,
  HiFolder,
} from 'react-icons/hi';
import type { MenuTab } from './useMenuBuilder';

// ============================================================================
// Alerts
// ============================================================================

interface MenuAlertsProps {
  error: string | null;
  success: string | null;
}

export function MenuAlerts({ error, success }: MenuAlertsProps) {
  return (
    <>
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
    </>
  );
}

// ============================================================================
// Tab Switcher + Action Buttons
// ============================================================================

interface MenuToolbarProps {
  activeTab: MenuTab;
  onTabChange: (tab: MenuTab) => void;
  onAddDropdown: () => void;
  onAddCategory: () => void;
  onAddLink: () => void;
  availableCategoriesCount: number;
}

export function MenuToolbar({
  activeTab,
  onTabChange,
  onAddDropdown,
  onAddCategory,
  onAddLink,
  availableCategoriesCount,
}: MenuToolbarProps) {
  return (
    <div className="mb-6 flex items-center justify-between flex-wrap gap-4">
      <div className="flex gap-2">
        <button
          onClick={() => onTabChange('main')}
          className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
            activeTab === 'main'
              ? 'bg-blue-700 text-white'
              : 'bg-gray-100 text-primary hover:bg-gray-200 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
          }`}
        >
          Main Menu
        </button>
        <button
          onClick={() => onTabChange('footer')}
          className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
            activeTab === 'footer'
              ? 'bg-blue-700 text-white'
              : 'bg-gray-100 text-primary hover:bg-gray-200 dark:bg-gray-700 dark:text-primary-dark dark:hover:bg-gray-600'
          }`}
        >
          Footer Menu
        </button>
      </div>

      <div className="flex gap-2 flex-wrap">
        {activeTab === 'main' && (
          <Button
            size="sm"
            color="warning"
            onClick={onAddDropdown}
          >
            <HiFolder className="mr-2 h-4 w-4" />
            Add Dropdown
          </Button>
        )}
        <Button
          size="sm"
          onClick={onAddCategory}
          disabled={availableCategoriesCount === 0}
        >
          <HiPlus className="mr-2 h-4 w-4" />
          Add Category
        </Button>
        <Button
          size="sm"
          color="purple"
          onClick={onAddLink}
        >
          <HiExternalLink className="mr-2 h-4 w-4" />
          Add External Link
        </Button>
      </div>
    </div>
  );
}

// ============================================================================
// Item Count Status
// ============================================================================

interface MenuStatusBarProps {
  totalItemCount: number;
  activeTab: MenuTab;
  saving: boolean;
}

export function MenuStatusBar({ totalItemCount, activeTab, saving }: MenuStatusBarProps) {
  return (
    <div className="mb-4 text-sm text-gray-600 dark:text-gray-400">
      <span className="font-semibold text-primary dark:text-primary-dark">
        {totalItemCount}
      </span>{' '}
      items in{' '}
      <span className="font-semibold text-primary dark:text-primary-dark">
        {activeTab === 'main' ? 'Main' : 'Footer'}
      </span>{' '}
      menu
      {saving && (
        <span className="ml-3 inline-flex items-center gap-1 text-blue-600 dark:text-blue-400">
          <Spinner size="sm" /> Saving...
        </span>
      )}
    </div>
  );
}
