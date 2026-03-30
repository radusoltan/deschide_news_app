'use client';

import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Badge, Button } from 'flowbite-react';
import {
  HiOutlineTrash,
  HiPencil,
  HiChevronDown,
  HiChevronRight,
  HiFolder,
  HiExternalLink,
  HiTag,
} from 'react-icons/hi';
import type { MenuItem } from '@/lib/types/menu';

// ============================================================================
// Drag Handle icon (6-dot grip)
// ============================================================================
function GripIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      width="16"
      height="16"
      viewBox="0 0 16 16"
      fill="currentColor"
      aria-hidden="true"
    >
      <circle cx="5" cy="3" r="1.5" />
      <circle cx="11" cy="3" r="1.5" />
      <circle cx="5" cy="8" r="1.5" />
      <circle cx="11" cy="8" r="1.5" />
      <circle cx="5" cy="13" r="1.5" />
      <circle cx="11" cy="13" r="1.5" />
    </svg>
  );
}

// ============================================================================
// Props
// ============================================================================

interface SortableMenuItemProps {
  item: MenuItem;
  depth: number;
  isExpanded?: boolean;
  hasChildren?: boolean;
  onToggleExpand?: () => void;
  onToggleActive: (item: MenuItem) => void;
  onEdit: (item: MenuItem) => void;
  onDelete: (item: MenuItem) => void;
  saving: boolean;
}

export default function SortableMenuItem({
  item,
  depth,
  isExpanded,
  hasChildren,
  onToggleExpand,
  onToggleActive,
  onEdit,
  onDelete,
  saving,
}: SortableMenuItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: item.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  // Type icon
  const TypeIcon =
    item.type === 'dropdown'
      ? HiFolder
      : item.type === 'external_link'
        ? HiExternalLink
        : HiTag;

  // Type badge
  const typeBadge =
    item.type === 'dropdown' ? (
      <Badge color="warning" size="sm">
        Dropdown
      </Badge>
    ) : item.type === 'category' ? (
      <Badge color="info" size="sm">
        Category
      </Badge>
    ) : (
      <Badge color="purple" size="sm">
        External Link
      </Badge>
    );

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`
        flex items-center gap-3 px-4 py-3 border-b
        dark:border-gray-700 transition-colors
        ${isDragging ? 'bg-blue-50 dark:bg-blue-900/30 shadow-lg z-50 rounded-lg opacity-90' : ''}
        ${item.isActive ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-800/50'}
        ${depth === 1 ? 'ml-10 border-l-2 border-l-blue-200 dark:border-l-blue-700' : ''}
      `}
    >
      {/* Drag handle */}
      <button
        className="cursor-grab active:cursor-grabbing p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 touch-none"
        {...attributes}
        {...listeners}
        aria-label={`Drag ${item.label}`}
      >
        <GripIcon className="w-4 h-4" />
      </button>

      {/* Expand/Collapse for dropdowns */}
      {item.type === 'dropdown' ? (
        <button
          onClick={onToggleExpand}
          className="p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          aria-label={isExpanded ? 'Collapse' : 'Expand'}
        >
          {isExpanded ? (
            <HiChevronDown className="w-4 h-4" />
          ) : (
            <HiChevronRight className="w-4 h-4" />
          )}
        </button>
      ) : (
        /* Spacer to align non-dropdown items */
        <div className="w-6" />
      )}

      {/* Type icon */}
      <TypeIcon
        className={`w-4 h-4 flex-shrink-0 ${
          item.type === 'dropdown'
            ? 'text-amber-500'
            : item.type === 'category'
              ? 'text-blue-500'
              : 'text-purple-500'
        }`}
      />

      {/* Label + URL */}
      <div className="flex-1 min-w-0">
        <span
          className={`font-medium text-sm ${
            item.isActive
              ? 'text-gray-900 dark:text-white'
              : 'text-gray-400 dark:text-gray-500 line-through'
          }`}
        >
          {item.label}
        </span>
        {item.url && (
          <div className="text-xs text-gray-400 dark:text-gray-500 truncate max-w-xs">
            {item.url}
          </div>
        )}
        {item.type === 'dropdown' && hasChildren && (
          <span className="text-xs text-gray-400 dark:text-gray-500 ml-2">
            ({(item.children || []).length} sub-items)
          </span>
        )}
      </div>

      {/* Type badge */}
      <div className="flex-shrink-0 hidden sm:block">{typeBadge}</div>

      {/* Active toggle */}
      <button
        onClick={() => onToggleActive(item)}
        disabled={saving}
        className="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 flex-shrink-0"
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

      {/* Edit */}
      <Button
        size="xs"
        color="light"
        onClick={() => onEdit(item)}
        disabled={saving}
        title="Edit translations"
      >
        <HiPencil className="h-4 w-4" />
      </Button>

      {/* Delete */}
      <Button
        size="xs"
        color="failure"
        onClick={() => onDelete(item)}
        disabled={saving}
        title="Delete"
      >
        <HiOutlineTrash className="h-4 w-4" />
      </Button>
    </div>
  );
}
