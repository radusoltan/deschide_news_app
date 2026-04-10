'use client';

import {
  DndContext,
  closestCenter,
  DragEndEvent,
  DragStartEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { DragOverlay } from '@dnd-kit/core';
import { Badge, Spinner } from 'flowbite-react';
import { Button } from 'flowbite-react';
import { HiOutlineTrash } from 'react-icons/hi';
import type { MenuItem } from '@/lib/types/menu';
import type { FlatItem } from './useMenuBuilder';
import SortableMenuItem from '../SortableMenuItem';
import DragOverlayItem from '../DragOverlayItem';

// ============================================================================
// Main Menu Tree (DnD)
// ============================================================================

interface MenuTreeDndProps {
  flatItems: FlatItem[];
  sortableIds: number[];
  activeItem: MenuItem | null;
  expandedDropdowns: Set<number>;
  sensors: ReturnType<typeof import('@dnd-kit/core').useSensors>;
  saving: boolean;
  onDragStart: (event: DragStartEvent) => void;
  onDragEnd: (event: DragEndEvent) => Promise<void>;
  onToggleExpand: (id: number) => void;
  onDelete: (item: MenuItem) => void;
}

export function MenuTreeDnd({
  flatItems,
  sortableIds,
  activeItem,
  expandedDropdowns,
  sensors,
  saving,
  onDragStart,
  onDragEnd,
  onToggleExpand,
  onDelete,
}: MenuTreeDndProps) {
  return (
    <div className="border rounded-lg dark:border-gray-700 overflow-hidden">
      {/* Legend */}
      <div className="px-4 py-2 bg-surface-sunken dark:bg-gray-700 border-b dark:border-gray-600 flex items-center gap-4 text-xs text-secondary dark:text-gray-400">
        <span>Drag items to reorder. Drop under a dropdown to nest.</span>
      </div>

      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragStart={onDragStart}
        onDragEnd={onDragEnd}
      >
        <SortableContext
          items={sortableIds}
          strategy={verticalListSortingStrategy}
        >
          {flatItems.map((fi) => (
            <SortableMenuItem
              key={fi.item.id}
              item={fi.item}
              depth={fi.depth}
              isExpanded={expandedDropdowns.has(fi.item.id)}
              hasChildren={
                fi.item.type === 'dropdown' &&
                fi.item.children !== undefined &&
                fi.item.children.length > 0
              }
              onToggleExpand={() => onToggleExpand(fi.item.id)}
              onDelete={onDelete}
              saving={saving}
            />
          ))}
        </SortableContext>

        <DragOverlay>
          {activeItem ? <DragOverlayItem item={activeItem} /> : null}
        </DragOverlay>
      </DndContext>
    </div>
  );
}

// ============================================================================
// Footer Menu Table
// ============================================================================

interface FooterMenuTableProps {
  tree: MenuItem[];
  saving: boolean;
  onMoveUp: (index: number) => Promise<void>;
  onMoveDown: (index: number) => Promise<void>;
  onDelete: (item: MenuItem) => void;
}

export function FooterMenuTable({
  tree,
  saving,
  onMoveUp,
  onMoveDown,
  onDelete,
}: FooterMenuTableProps) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm text-left text-secondary dark:text-gray-400">
        <thead className="text-xs text-primary uppercase bg-surface-sunken dark:bg-gray-700 dark:text-gray-400">
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
            <th scope="col" className="px-4 py-3 w-28">
              Actions
            </th>
          </tr>
        </thead>
        <tbody>
          {tree.map((item, index) => (
            <tr
              key={item.id}
              className={`border-b dark:border-gray-700 hover:bg-surface-sunken dark:hover:bg-gray-600 ${
                item.isActive
                  ? 'bg-surface dark:bg-surface-dark'
                  : 'bg-surface-sunken/50 dark:bg-surface-dark/50'
              }`}
            >
              {/* Position with reorder buttons */}
              <td className="px-4 py-3">
                <div className="flex items-center gap-1">
                  <span className="font-medium text-primary dark:text-primary-dark w-6 text-center">
                    {item.position}
                  </span>
                  <div className="flex flex-col">
                    <button
                      onClick={() => onMoveUp(index)}
                      disabled={index === 0 || saving}
                      className="px-1 py-0.5 text-xs font-bold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded disabled:text-primary-dark disabled:cursor-not-allowed dark:text-blue-400 dark:hover:bg-blue-900 dark:disabled:text-gray-600"
                      title="Move up"
                    >
                      &#9650;
                    </button>
                    <button
                      onClick={() => onMoveDown(index)}
                      disabled={index === tree.length - 1 || saving}
                      className="px-1 py-0.5 text-xs font-bold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded disabled:text-primary-dark disabled:cursor-not-allowed dark:text-blue-400 dark:hover:bg-blue-900 dark:disabled:text-gray-600"
                      title="Move down"
                    >
                      &#9660;
                    </button>
                  </div>
                </div>
              </td>

              {/* Label */}
              <td className="px-4 py-3">
                <span
                  className={`font-medium ${
                    item.isActive
                      ? 'text-primary dark:text-primary-dark'
                      : 'text-gray-400 dark:text-secondary line-through'
                  }`}
                >
                  {item.label}
                </span>
                {item.url && (
                  <div className="text-xs text-gray-400 dark:text-secondary mt-0.5 truncate max-w-xs">
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
                    &#8599;
                  </span>
                )}
              </td>

              {/* Actions */}
              <td className="px-4 py-3">
                <Button
                  size="xs"
                  color="failure"
                  onClick={() => onDelete(item)}
                  disabled={saving}
                  title="Delete"
                >
                  <HiOutlineTrash className="h-4 w-4" />
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
