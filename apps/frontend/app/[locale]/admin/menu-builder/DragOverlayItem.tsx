'use client';

import { Badge } from 'flowbite-react';
import { HiFolder, HiExternalLink, HiTag } from 'react-icons/hi';
import type { MenuItem } from '@/lib/types/menu';

interface DragOverlayItemProps {
  item: MenuItem;
}

export default function DragOverlayItem({ item }: DragOverlayItemProps) {
  const TypeIcon =
    item.type === 'dropdown'
      ? HiFolder
      : item.type === 'external_link'
        ? HiExternalLink
        : HiTag;

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
    <div className="flex items-center gap-3 px-4 py-3 bg-white dark:bg-gray-800 border border-blue-300 dark:border-blue-600 rounded-lg shadow-xl">
      <TypeIcon
        className={`w-4 h-4 flex-shrink-0 ${
          item.type === 'dropdown'
            ? 'text-amber-500'
            : item.type === 'category'
              ? 'text-blue-500'
              : 'text-purple-500'
        }`}
      />
      <span className="font-medium text-sm text-gray-900 dark:text-white">
        {item.label}
      </span>
      <div className="flex-shrink-0">{typeBadge}</div>
    </div>
  );
}
