'use client';

import { useEffect, useState, useCallback, useMemo } from 'react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
  DragOverlay,
  DragStartEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
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
  HiExternalLink,
  HiFolder,
} from 'react-icons/hi';
import type { MenuItem } from '@/lib/types/menu';
import type { Category } from '@/lib/types/article';
import SortableMenuItem from './SortableMenuItem';
import DragOverlayItem from './DragOverlayItem';

// ============================================================================
// Constants
// ============================================================================

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

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
// Tree helpers
// ============================================================================

/** Flat item used for rendering the sortable list */
interface FlatItem {
  item: MenuItem;
  depth: number; // 0 = top-level, 1 = child of a dropdown
  parentId: number | null;
}

/**
 * Build an ordered tree from the API response.
 * The API may return items with `children` already populated (if using
 * ?exists[parent]=false), or it may return a flat list with `parent` IRIs.
 * We handle both cases and deduplicate to avoid rendering the same item twice.
 */
function buildTree(items: MenuItem[]): MenuItem[] {
  // Index every item by ID for fast lookup
  const byId = new Map<number, MenuItem>();
  for (const item of items) {
    byId.set(item.id, item);
  }

  // Collect children by parent ID from the `parent` field
  const childMap = new Map<number, MenuItem[]>();
  for (const item of items) {
    if (item.parent) {
      const parentId = extractIdFromIri(item.parent);
      if (parentId !== null) {
        const siblings = childMap.get(parentId) || [];
        siblings.push(item);
        childMap.set(parentId, siblings);
      }
    }
  }

  // Top-level = items without a parent
  const topLevel = items.filter((i) => !i.parent);

  // IDs that are assigned as children — used to prevent duplicates
  const childIds = new Set<number>();

  const result = topLevel
    .sort((a, b) => a.position - b.position)
    .map((item) => {
      // Prefer children declared via `parent` field (reflects optimistic updates);
      // fall back to embedded `children` array from the API.
      const fromMap = childMap.get(item.id) || [];
      const embedded = item.children && item.children.length > 0 ? item.children : [];
      const children = fromMap.length > 0 ? fromMap : embedded;

      for (const child of children) childIds.add(child.id);

      return {
        ...item,
        children: children.sort((a, b) => a.position - b.position),
      };
    });

  // Filter out top-level items that are actually children (deduplicate)
  return result.filter((item) => !childIds.has(item.id));
}

/** Flatten tree into a render-order list with depth info */
function flattenTree(tree: MenuItem[], expandedIds: Set<number>): FlatItem[] {
  const result: FlatItem[] = [];
  for (const item of tree) {
    result.push({ item, depth: 0, parentId: null });
    if (item.type === 'dropdown' && expandedIds.has(item.id)) {
      for (const child of item.children || []) {
        result.push({ item: child, depth: 1, parentId: item.id });
      }
    }
  }
  return result;
}

/** Extract numeric ID from an IRI string, nested object, or number */
function extractIdFromIri(iri: string | number | Record<string, unknown> | null | undefined): number | null {
  if (iri == null) return null;
  if (typeof iri === 'number') return iri;
  if (typeof iri === 'object') {
    // API may return parent as a nested object: {"@id": "/api/menu-items/5", "id": 5, ...}
    if (typeof iri['@id'] === 'string') return extractIdFromIri(iri['@id']);
    if (typeof iri.id === 'number') return iri.id as number;
    return null;
  }
  if (typeof iri !== 'string') return null;
  const match = iri.match(/\/(\d+)$/);
  return match ? parseInt(match[1], 10) : null;
}

/** Normalize parent field to a string IRI or null (API may return nested object) */
function normalizeParentIri(parent: string | number | Record<string, unknown> | null | undefined): string | null {
  if (parent == null) return null;
  if (typeof parent === 'string') return parent;
  if (typeof parent === 'object' && typeof parent['@id'] === 'string') return parent['@id'];
  if (typeof parent === 'number') return `/api/menu-items/${parent}`;
  return null;
}

/**
 * After a drag-end on the flat list, rebuild the tree structure and
 * return a list of PATCH operations needed.
 */
interface PatchOp {
  id: number;
  data: { position?: number; parent?: string | null };
}

function computePatchOps(
  flatItems: FlatItem[],
  originalTree: MenuItem[],
): PatchOp[] {
  const ops: PatchOp[] = [];

  // Build a map of original state for comparison
  const originalMap = new Map<number, { position: number; parentIri: string | null }>();
  for (const topItem of originalTree) {
    originalMap.set(topItem.id, { position: topItem.position, parentIri: normalizeParentIri(topItem.parent) });
    for (const child of topItem.children || []) {
      originalMap.set(child.id, { position: child.position, parentIri: normalizeParentIri(child.parent) });
    }
  }

  // Walk the flat list and assign new positions + parents
  let topPosition = 1;
  let currentDropdownId: number | null = null;
  let childPosition = 1;

  for (const { item, depth } of flatItems) {
    let newPosition: number;
    let newParentIri: string | null;

    if (depth === 0) {
      newPosition = topPosition++;
      newParentIri = null;
      if (item.type === 'dropdown') {
        currentDropdownId = item.id;
        childPosition = 1;
      } else {
        currentDropdownId = null;
      }
    } else {
      // depth === 1: child of currentDropdownId
      newPosition = childPosition++;
      newParentIri = currentDropdownId
        ? `/api/menu-items/${currentDropdownId}`
        : null;
    }

    const orig = originalMap.get(item.id);
    if (!orig) {
      // New item (shouldn't happen in normal flow)
      ops.push({ id: item.id, data: { position: newPosition, parent: newParentIri } });
      continue;
    }

    const posChanged = orig.position !== newPosition;
    const parentChanged = (orig.parentIri || null) !== (newParentIri || null);

    if (posChanged || parentChanged) {
      const patch: PatchOp['data'] = {};
      if (posChanged) patch.position = newPosition;
      if (parentChanged) patch.parent = newParentIri;
      ops.push({ id: item.id, data: patch });
    }
  }

  return ops;
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
  const [rawMenuItems, setRawMenuItems] = useState<MenuItem[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // DnD
  const [activeId, setActiveId] = useState<number | null>(null);
  const [expandedDropdowns, setExpandedDropdowns] = useState<Set<number>>(
    new Set()
  );

  // Modals
  const [showAddCategoryModal, setShowAddCategoryModal] = useState(false);
  const [showAddLinkModal, setShowAddLinkModal] = useState(false);
  const [showAddDropdownModal, setShowAddDropdownModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  // Form state - Add Category
  const [selectedCategoryId, setSelectedCategoryId] = useState<string>('');
  const [addCategoryParent, setAddCategoryParent] = useState<string>('');

  // Form state - Add External Link
  const [linkLabel, setLinkLabel] = useState('');
  const [linkUrl, setLinkUrl] = useState('');
  const [linkNewTab, setLinkNewTab] = useState(false);
  const [addLinkParent, setAddLinkParent] = useState<string>('');

  // Form state - Add Dropdown
  const [dropdownLabel, setDropdownLabel] = useState('');

  // Form state - Delete
  const [deletingItem, setDeletingItem] = useState<MenuItem | null>(null);

  // DnD sensors
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  // ==========================================================================
  // Derived tree state
  // ==========================================================================

  const tree = useMemo(() => buildTree(rawMenuItems), [rawMenuItems]);

  const flatItems = useMemo(
    () => flattenTree(tree, expandedDropdowns),
    [tree, expandedDropdowns]
  );

  const sortableIds = useMemo(
    () => flatItems.map((fi) => fi.item.id),
    [flatItems]
  );

  // The item currently being dragged (for overlay)
  const activeItem = useMemo(
    () => (activeId !== null ? flatItems.find((fi) => fi.item.id === activeId)?.item ?? null : null),
    [activeId, flatItems]
  );

  // Dropdown items available as parent targets
  const dropdownItems = useMemo(
    () => tree.filter((i) => i.type === 'dropdown'),
    [tree]
  );

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
      const items: MenuItem[] = data['hydra:member'] || data.member || [];
      setRawMenuItems(items);
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

    // Invalidate public menu cache so changes appear immediately
    fetch('/api/revalidate-menu', { method: 'POST' }).catch(() => {});
  }, []);

  const showErrorMessage = useCallback((msg: string) => {
    setError(msg);
    setTimeout(() => setError(null), 5000);
  }, []);

  /** Categories already present in the current menu tab */
  const usedCategoryIris = rawMenuItems
    .filter((item) => item.type === 'category' && item.category)
    .map((item) => item.category);

  const availableCategories = categories.filter(
    (cat) => !usedCategoryIris.includes(cat['@id'])
  );

  /** Compute the next position for a new top-level item */
  const getNextTopPosition = (): number => {
    if (tree.length === 0) return 1;
    return Math.max(...tree.map((i) => i.position)) + 1;
  };

  /** Compute the next position for a child inside a dropdown */
  const getNextChildPosition = (parentId: number): number => {
    const parent = tree.find((i) => i.id === parentId);
    if (!parent || !parent.children || parent.children.length === 0) return 1;
    return Math.max(...parent.children.map((c) => c.position)) + 1;
  };

  // ==========================================================================
  // Batch PATCH helper
  // ==========================================================================

  const batchPatch = useCallback(
    async (ops: PatchOp[]) => {
      if (ops.length === 0) return;

      const token = await getAuthToken();
      if (!token) throw new Error('Not authenticated');

      // Execute all patches in parallel
      const results = await Promise.allSettled(
        ops.map((op) =>
          fetch(`${API_BASE_URL}/api/menu-items/${op.id}`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/merge-patch+json',
              Authorization: `Bearer ${token}`,
              'Accept-Language': locale,
            },
            body: JSON.stringify(op.data),
          })
        )
      );

      const failures = results.filter((r) => r.status === 'rejected');
      if (failures.length > 0) {
        console.error('Some PATCH operations failed:', failures);
        throw new Error(`${failures.length} of ${ops.length} updates failed`);
      }
    },
    [locale]
  );

  // ==========================================================================
  // DnD handlers (main menu)
  // ==========================================================================

  const handleDragStart = useCallback((event: DragStartEvent) => {
    setActiveId(event.active.id as number);
  }, []);

  const handleDragEnd = useCallback(
    async (event: DragEndEvent) => {
      setActiveId(null);
      const { active, over } = event;
      if (!over || active.id === over.id) return;

      const oldIndex = flatItems.findIndex((fi) => fi.item.id === active.id);
      const newIndex = flatItems.findIndex((fi) => fi.item.id === over.id);
      if (oldIndex === -1 || newIndex === -1) return;

      const movedItem = flatItems[oldIndex];

      // Prevent dropping a dropdown inside another dropdown
      if (movedItem.item.type === 'dropdown' && movedItem.depth === 0) {
        // Check if the target position is inside a dropdown (depth > 0)
        const targetDepth = flatItems[newIndex].depth;
        if (targetDepth === 1) {
          // Don't allow nesting a dropdown inside another dropdown
          return;
        }
      }

      // Reorder the flat list
      const newFlatItems = arrayMove(flatItems, oldIndex, newIndex);

      // Recalculate depths based on new positions and the moved item
      const recalculated = recalculateDepths(newFlatItems, active.id as number);

      // Optimistic update: rebuild raw items with correct parent and children
      // First, build items with updated parent field and clear stale children
      const updatedItems = recalculated.map((fi) => ({
        ...fi.item,
        parent: fi.parentId ? `/api/menu-items/${fi.parentId}` : null,
        children: [] as MenuItem[],
      }));

      // Rebuild children arrays for dropdowns
      const itemMap = new Map<number, MenuItem>();
      for (const item of updatedItems) {
        itemMap.set(item.id, item);
      }
      for (const item of updatedItems) {
        if (item.parent) {
          const parentId = extractIdFromIri(item.parent);
          if (parentId !== null) {
            const parent = itemMap.get(parentId);
            if (parent) {
              parent.children.push(item);
            }
          }
        }
      }

      setRawMenuItems(updatedItems);

      // Compute patches
      const ops = computePatchOps(recalculated, tree);

      if (ops.length > 0) {
        setSaving(true);
        try {
          await batchPatch(ops);
          // Re-fetch to confirm server state
          await fetchMenuItems();
          showSuccessMessage('Menu order updated');
        } catch (err) {
          showErrorMessage(
            err instanceof Error ? err.message : 'Failed to update order'
          );
          // Re-fetch to restore correct state
          await fetchMenuItems();
        } finally {
          setSaving(false);
        }
      }
    },
    [flatItems, tree, batchPatch, fetchMenuItems, showSuccessMessage, showErrorMessage]
  );

  /**
   * After reordering, recalculate which items are children of which dropdowns.
   * Rules:
   * - Dropdowns always stay at depth 0.
   * - The moved item's depth is determined by context: if placed after a
   *   dropdown (among its children), it becomes a child; otherwise top-level.
   * - Non-moved items keep their original depth, but orphaned children
   *   (whose dropdown moved away) become top-level.
   */
  function recalculateDepths(items: FlatItem[], movedItemId: number): FlatItem[] {
    const result: FlatItem[] = [];
    let currentDropdownId: number | null = null;

    for (const fi of items) {
      if (fi.item.type === 'dropdown') {
        // Dropdowns always at top level
        result.push({ ...fi, depth: 0, parentId: null });
        currentDropdownId = fi.item.id;
      } else if (fi.item.id === movedItemId) {
        // Moved item: determine depth from context
        if (currentDropdownId !== null) {
          result.push({ ...fi, depth: 1, parentId: currentDropdownId });
        } else {
          result.push({ ...fi, depth: 0, parentId: null });
        }
      } else if (fi.depth === 1 && currentDropdownId !== null) {
        // Non-moved child that still has a valid dropdown parent above
        result.push({ ...fi, depth: 1, parentId: currentDropdownId });
      } else {
        // Top-level item (or orphaned child whose dropdown moved away)
        result.push({ ...fi, depth: 0, parentId: null });
        currentDropdownId = null;
      }
    }

    return result;
  }

  // ==========================================================================
  // Footer reorder (simple ▲/▼)
  // ==========================================================================

  const handleMoveUp = useCallback(
    async (index: number) => {
      if (index === 0) return;

      const items = [...tree];
      const current = items[index];
      const above = items[index - 1];

      setSaving(true);
      try {
        await batchPatch([
          { id: current.id, data: { position: above.position } },
          { id: above.id, data: { position: current.position } },
        ]);
        await fetchMenuItems();
      } catch (err) {
        showErrorMessage(err instanceof Error ? err.message : 'Failed to reorder');
      } finally {
        setSaving(false);
      }
    },
    [tree, batchPatch, fetchMenuItems, showErrorMessage]
  );

  const handleMoveDown = useCallback(
    async (index: number) => {
      if (index >= tree.length - 1) return;

      const items = [...tree];
      const current = items[index];
      const below = items[index + 1];

      setSaving(true);
      try {
        await batchPatch([
          { id: current.id, data: { position: below.position } },
          { id: below.id, data: { position: current.position } },
        ]);
        await fetchMenuItems();
      } catch (err) {
        showErrorMessage(err instanceof Error ? err.message : 'Failed to reorder');
      } finally {
        setSaving(false);
      }
    },
    [tree, batchPatch, fetchMenuItems, showErrorMessage]
  );

  // ==========================================================================
  // Toggle expand
  // ==========================================================================

  const toggleExpand = useCallback((id: number) => {
    setExpandedDropdowns((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  // Auto-expand dropdowns that have children
  useEffect(() => {
    const withChildren = tree
      .filter((i) => i.type === 'dropdown' && i.children && i.children.length > 0)
      .map((i) => i.id);
    if (withChildren.length > 0) {
      setExpandedDropdowns((prev) => {
        const next = new Set(prev);
        for (const id of withChildren) next.add(id);
        return next;
      });
    }
  }, [tree]);

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

      const parentIri = addCategoryParent || null;
      const parentId = parentIri ? extractIdFromIri(parentIri) : null;
      const nextPosition = parentId
        ? getNextChildPosition(parentId)
        : getNextTopPosition();

      const body: Record<string, unknown> = {
        menu: activeTab,
        type: 'category',
        label: category.title,
        category: category['@id'],
        position: nextPosition,
        isActive: true,
        openInNewTab: false,
      };

      if (parentIri) {
        body.parent = parentIri;
      }

      const response = await fetch(`${API_BASE_URL}/api/menu-items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          Authorization: `Bearer ${token}`,
          'Accept-Language': locale,
        },
        body: JSON.stringify(body),
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
      setAddCategoryParent('');
      await fetchMenuItems();

      // Auto-expand parent if added as child
      if (parentId) {
        setExpandedDropdowns((prev) => new Set([...prev, parentId]));
      }
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to add category');
    } finally {
      setSaving(false);
    }
  }, [
    selectedCategoryId,
    categories,
    addCategoryParent,
    activeTab,
    locale,
    tree,
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

      const parentIri = addLinkParent || null;
      const parentId = parentIri ? extractIdFromIri(parentIri) : null;
      const nextPosition = parentId
        ? getNextChildPosition(parentId)
        : getNextTopPosition();

      const body: Record<string, unknown> = {
        menu: activeTab,
        type: 'external_link',
        label: linkLabel.trim(),
        url: linkUrl.trim(),
        position: nextPosition,
        isActive: true,
        openInNewTab: linkNewTab,
      };

      if (parentIri) {
        body.parent = parentIri;
      }

      const response = await fetch(`${API_BASE_URL}/api/menu-items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          Authorization: `Bearer ${token}`,
          'Accept-Language': locale,
        },
        body: JSON.stringify(body),
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
      setAddLinkParent('');
      await fetchMenuItems();

      // Auto-expand parent if added as child
      if (parentId) {
        setExpandedDropdowns((prev) => new Set([...prev, parentId]));
      }
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to add link');
    } finally {
      setSaving(false);
    }
  }, [
    linkLabel,
    linkUrl,
    linkNewTab,
    addLinkParent,
    activeTab,
    locale,
    tree,
    fetchMenuItems,
    showSuccessMessage,
    showErrorMessage,
  ]);

  // ==========================================================================
  // Add Dropdown
  // ==========================================================================

  const handleAddDropdown = useCallback(async () => {
    if (!dropdownLabel.trim()) return;

    setSaving(true);
    try {
      const token = await getAuthToken();
      if (!token) throw new Error('Not authenticated');

      const nextPosition = getNextTopPosition();

      const response = await fetch(`${API_BASE_URL}/api/menu-items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          Authorization: `Bearer ${token}`,
          'Accept-Language': locale,
        },
        body: JSON.stringify({
          menu: activeTab,
          type: 'dropdown',
          label: dropdownLabel.trim(),
          position: nextPosition,
          isActive: true,
          openInNewTab: false,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(
          errorData['hydra:description'] || 'Failed to add dropdown'
        );
      }

      showSuccessMessage('Dropdown added to menu');
      setShowAddDropdownModal(false);
      setDropdownLabel('');
      await fetchMenuItems();
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to add dropdown');
    } finally {
      setSaving(false);
    }
  }, [dropdownLabel, activeTab, locale, tree, fetchMenuItems, showSuccessMessage, showErrorMessage]);

  // ==========================================================================
  // Delete
  // ==========================================================================

  const openDeleteModal = useCallback((item: MenuItem) => {
    setDeletingItem(item);
    setShowDeleteModal(true);
  }, []);

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
  // Total item count (including children)
  // ==========================================================================

  const totalItemCount = useMemo(() => {
    let count = 0;
    for (const item of tree) {
      count++;
      if (item.children) count += item.children.length;
    }
    return count;
  }, [tree]);

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

      {/* Tab Switcher + Actions */}
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

        <div className="flex gap-2 flex-wrap">
          {activeTab === 'main' && (
            <Button
              size="sm"
              color="warning"
              onClick={() => setShowAddDropdownModal(true)}
            >
              <HiFolder className="mr-2 h-4 w-4" />
              Add Dropdown
            </Button>
          )}
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
          {totalItemCount}
        </span>{' '}
        items in{' '}
        <span className="font-semibold text-gray-900 dark:text-white">
          {activeTab === 'main' ? 'Main' : 'Footer'}
        </span>{' '}
        menu
        {saving && (
          <span className="ml-3 inline-flex items-center gap-1 text-blue-600 dark:text-blue-400">
            <Spinner size="sm" /> Saving...
          </span>
        )}
      </div>

      {/* Empty state */}
      {tree.length === 0 ? (
        <div className="text-center py-12 text-gray-500 dark:text-gray-400">
          No items in this menu yet. Add a category, external link, or dropdown to get started.
        </div>
      ) : activeTab === 'main' ? (
        /* ================================================================ */
        /* Main Menu: DnD Tree View                                        */
        /* ================================================================ */
        <div className="border rounded-lg dark:border-gray-700 overflow-hidden">
          {/* Legend */}
          <div className="px-4 py-2 bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span>Drag items to reorder. Drop under a dropdown to nest.</span>
          </div>

          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
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
                  onToggleExpand={() => toggleExpand(fi.item.id)}
                  onDelete={openDeleteModal}
                  saving={saving}
                />
              ))}
            </SortableContext>

            <DragOverlay>
              {activeItem ? <DragOverlayItem item={activeItem} /> : null}
            </DragOverlay>
          </DndContext>
        </div>
      ) : (
        /* ================================================================ */
        /* Footer Menu: Simple Table with ▲/▼ buttons (no nesting)         */
        /* ================================================================ */
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
                <th scope="col" className="px-4 py-3 w-28">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {tree.map((item, index) => (
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
                          &#9650;
                        </button>
                        <button
                          onClick={() => handleMoveDown(index)}
                          disabled={index === tree.length - 1 || saving}
                          className="px-1 py-0.5 text-xs font-bold text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded disabled:text-gray-300 disabled:cursor-not-allowed dark:text-blue-400 dark:hover:bg-blue-900 dark:disabled:text-gray-600"
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
                        &#8599;
                      </span>
                    )}
                  </td>

                  {/* Actions */}
                  <td className="px-4 py-3">
                    <Button
                      size="xs"
                      color="failure"
                      onClick={() => openDeleteModal(item)}
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
      )}

      {/* ================================================================== */}
      {/* Add Category Modal                                                 */}
      {/* ================================================================== */}
      <Modal
        show={showAddCategoryModal}
        onClose={() => {
          setShowAddCategoryModal(false);
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

            {/* Parent dropdown selector (only for main menu) */}
            {activeTab === 'main' && dropdownItems.length > 0 && (
              <div>
                <label
                  htmlFor="category-parent-select"
                  className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
                >
                  Parent (optional)
                </label>
                <select
                  id="category-parent-select"
                  value={addCategoryParent}
                  onChange={(e) => setAddCategoryParent(e.target.value)}
                  className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">-- Top level (no parent) --</option>
                  {dropdownItems.map((dd) => (
                    <option key={dd.id} value={dd['@id']}>
                      {dd.label}
                    </option>
                  ))}
                </select>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Nest this category under a dropdown menu item.
                </p>
              </div>
            )}
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
              setAddCategoryParent('');
            }}
          >
            Cancel
          </Button>
        </ModalFooter>
      </Modal>

      {/* ================================================================== */}
      {/* Add External Link Modal                                            */}
      {/* ================================================================== */}
      <Modal
        show={showAddLinkModal}
        onClose={() => {
          setShowAddLinkModal(false);
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

            {/* Parent dropdown selector (only for main menu) */}
            {activeTab === 'main' && dropdownItems.length > 0 && (
              <div>
                <label
                  htmlFor="link-parent-select"
                  className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
                >
                  Parent (optional)
                </label>
                <select
                  id="link-parent-select"
                  value={addLinkParent}
                  onChange={(e) => setAddLinkParent(e.target.value)}
                  className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
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
              setAddLinkParent('');
            }}
          >
            Cancel
          </Button>
        </ModalFooter>
      </Modal>

      {/* ================================================================== */}
      {/* Add Dropdown Modal                                                 */}
      {/* ================================================================== */}
      <Modal
        show={showAddDropdownModal}
        onClose={() => {
          setShowAddDropdownModal(false);
          setDropdownLabel('');
        }}
      >
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
                className="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
              >
                Label
              </label>
              <input
                type="text"
                id="dropdown-label"
                value={dropdownLabel}
                onChange={(e) => setDropdownLabel(e.target.value)}
                placeholder='e.g. "More" or "Topics"'
                className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
              />
            </div>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button
            onClick={handleAddDropdown}
            disabled={!dropdownLabel.trim() || saving}
            color="warning"
          >
            {saving ? <Spinner size="sm" className="mr-2" /> : null}
            Add Dropdown
          </Button>
          <Button
            color="gray"
            onClick={() => {
              setShowAddDropdownModal(false);
              setDropdownLabel('');
            }}
          >
            Cancel
          </Button>
        </ModalFooter>
      </Modal>

      {/* ================================================================== */}
      {/* Delete Confirmation Modal                                          */}
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
            <h3 className="mb-2 text-lg font-normal text-gray-500 dark:text-gray-400">
              Are you sure you want to delete{' '}
              <span className="font-semibold text-gray-900 dark:text-white">
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
