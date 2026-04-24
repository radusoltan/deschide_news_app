'use client';

import { useEffect, useState, useCallback, useMemo } from 'react';
import {
  useSensor,
  useSensors,
  KeyboardSensor,
  PointerSensor,
  DragEndEvent,
  DragStartEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import type { MenuItem } from '@/lib/types/menu';
import type { Category } from '@/lib/types/article';

// ============================================================================
// Constants
// ============================================================================

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

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
export interface FlatItem {
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
  const byId = new Map<number, MenuItem>();
  for (const item of items) {
    byId.set(item.id, item);
  }

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

  const topLevel = items.filter((i) => !i.parent);
  const childIds = new Set<number>();

  const result = topLevel
    .sort((a, b) => a.position - b.position)
    .map((item) => {
      const fromMap = childMap.get(item.id) || [];
      const embedded = item.children && item.children.length > 0 ? item.children : [];
      const children = fromMap.length > 0 ? fromMap : embedded;

      for (const child of children) childIds.add(child.id);

      return {
        ...item,
        children: children.sort((a, b) => a.position - b.position),
      };
    });

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
    if (typeof iri['@id'] === 'string') return extractIdFromIri(iri['@id']);
    if (typeof iri.id === 'number') return iri.id as number;
    return null;
  }
  if (typeof iri !== 'string') return null;
  const match = iri.match(/\/(\d+)$/);
  return match ? parseInt(match[1], 10) : null;
}

/** Normalize parent field to a string IRI or null */
function normalizeParentIri(parent: string | number | Record<string, unknown> | null | undefined): string | null {
  if (parent == null) return null;
  if (typeof parent === 'string') return parent;
  if (typeof parent === 'object' && typeof parent['@id'] === 'string') return parent['@id'];
  if (typeof parent === 'number') return `/api/menu-items/${parent}`;
  return null;
}

/** PATCH operations needed after reorder */
interface PatchOp {
  id: number;
  data: { position?: number; parent?: string | null };
}

function computePatchOps(
  flatItems: FlatItem[],
  originalTree: MenuItem[],
): PatchOp[] {
  const ops: PatchOp[] = [];

  const originalMap = new Map<number, { position: number; parentIri: string | null }>();
  for (const topItem of originalTree) {
    originalMap.set(topItem.id, { position: topItem.position, parentIri: normalizeParentIri(topItem.parent) });
    for (const child of topItem.children || []) {
      originalMap.set(child.id, { position: child.position, parentIri: normalizeParentIri(child.parent) });
    }
  }

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
      newPosition = childPosition++;
      newParentIri = currentDropdownId
        ? `/api/menu-items/${currentDropdownId}`
        : null;
    }

    const orig = originalMap.get(item.id);
    if (!orig) {
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

/**
 * After reordering, recalculate which items are children of which dropdowns.
 */
function recalculateDepths(items: FlatItem[], movedItemId: number): FlatItem[] {
  const result: FlatItem[] = [];
  let currentDropdownId: number | null = null;

  for (const fi of items) {
    if (fi.item.type === 'dropdown') {
      result.push({ ...fi, depth: 0, parentId: null });
      currentDropdownId = fi.item.id;
    } else if (fi.item.id === movedItemId) {
      if (currentDropdownId !== null) {
        result.push({ ...fi, depth: 1, parentId: currentDropdownId });
      } else {
        result.push({ ...fi, depth: 0, parentId: null });
      }
    } else if (fi.depth === 1 && currentDropdownId !== null) {
      result.push({ ...fi, depth: 1, parentId: currentDropdownId });
    } else {
      result.push({ ...fi, depth: 0, parentId: null });
      currentDropdownId = null;
    }
  }

  return result;
}

// ============================================================================
// Hook
// ============================================================================

export type MenuTab = 'main' | 'footer';

export interface UseMenuBuilderReturn {
  // State
  activeTab: MenuTab;
  setActiveTab: (tab: MenuTab) => void;
  loading: boolean;
  saving: boolean;
  error: string | null;
  success: string | null;

  // Tree data
  tree: MenuItem[];
  flatItems: FlatItem[];
  sortableIds: number[];
  activeItem: MenuItem | null;
  dropdownItems: MenuItem[];
  totalItemCount: number;
  categories: Category[];
  availableCategories: Category[];

  // DnD
  expandedDropdowns: Set<number>;
  sensors: ReturnType<typeof useSensors>;
  handleDragStart: (event: DragStartEvent) => void;
  handleDragEnd: (event: DragEndEvent) => Promise<void>;
  toggleExpand: (id: number) => void;

  // Footer reorder
  handleMoveUp: (index: number) => Promise<void>;
  handleMoveDown: (index: number) => Promise<void>;

  // Modals
  showAddCategoryModal: boolean;
  setShowAddCategoryModal: (v: boolean) => void;
  showAddLinkModal: boolean;
  setShowAddLinkModal: (v: boolean) => void;
  showAddDropdownModal: boolean;
  setShowAddDropdownModal: (v: boolean) => void;
  showDeleteModal: boolean;
  setShowDeleteModal: (v: boolean) => void;
  deletingItem: MenuItem | null;

  // Add category form
  selectedCategoryId: string;
  setSelectedCategoryId: (v: string) => void;
  addCategoryParent: string;
  setAddCategoryParent: (v: string) => void;
  handleAddCategory: () => Promise<void>;

  // Add link form
  linkLabel: string;
  setLinkLabel: (v: string) => void;
  linkUrl: string;
  setLinkUrl: (v: string) => void;
  linkNewTab: boolean;
  setLinkNewTab: (v: boolean) => void;
  addLinkParent: string;
  setAddLinkParent: (v: string) => void;
  handleAddExternalLink: () => Promise<void>;

  // Add dropdown form
  dropdownLabel: string;
  setDropdownLabel: (v: string) => void;
  dropdownLabelEn: string;
  setDropdownLabelEn: (v: string) => void;
  dropdownLabelRu: string;
  setDropdownLabelRu: (v: string) => void;
  handleAddDropdown: () => Promise<void>;

  // Delete
  openDeleteModal: (item: MenuItem) => void;
  handleDelete: () => Promise<void>;
}

export function useMenuBuilder(locale: string): UseMenuBuilderReturn {
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
  const [expandedDropdowns, setExpandedDropdowns] = useState<Set<number>>(new Set());

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
  const [dropdownLabelEn, setDropdownLabelEn] = useState('');
  const [dropdownLabelRu, setDropdownLabelRu] = useState('');

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

  const activeItem = useMemo(
    () => (activeId !== null ? flatItems.find((fi) => fi.item.id === activeId)?.item ?? null : null),
    [activeId, flatItems]
  );

  const dropdownItems = useMemo(
    () => tree.filter((i) => i.type === 'dropdown'),
    [tree]
  );

  const usedCategoryIris = rawMenuItems
    .filter((item) => item.type === 'category' && item.category)
    .map((item) => item.category);

  const availableCategories = categories.filter(
    (cat) => !usedCategoryIris.includes(cat['@id'])
  );

  const totalItemCount = useMemo(() => {
    let count = 0;
    for (const item of tree) {
      count++;
      if (item.children) count += item.children.length;
    }
    return count;
  }, [tree]);

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

  const getNextTopPosition = (): number => {
    if (tree.length === 0) return 1;
    return Math.max(...tree.map((i) => i.position)) + 1;
  };

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
  // DnD handlers
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
      const targetItem = flatItems[newIndex];

      // Prevent dropping a dropdown inside another dropdown
      if (movedItem.item.type === 'dropdown' && movedItem.depth === 0) {
        const targetDepth = flatItems[newIndex].depth;
        if (targetDepth === 1) {
          return;
        }
      }

      // Special case: dropping a non-dropdown item directly onto a dropdown
      const droppedOntoDropdown =
        targetItem.item.type === 'dropdown' &&
        movedItem.item.type !== 'dropdown' &&
        targetItem.depth === 0;

      let newFlatItems: FlatItem[];
      if (droppedOntoDropdown) {
        const withoutMoved = flatItems.filter((_, i) => i !== oldIndex);
        const dropdownIdx = withoutMoved.findIndex((fi) => fi.item.id === targetItem.item.id);
        let insertIdx = dropdownIdx + 1;
        while (insertIdx < withoutMoved.length && withoutMoved[insertIdx].depth === 1 && withoutMoved[insertIdx].parentId === targetItem.item.id) {
          insertIdx++;
        }
        const nested: FlatItem = { ...movedItem, depth: 1, parentId: targetItem.item.id };
        withoutMoved.splice(insertIdx, 0, nested);
        newFlatItems = withoutMoved;
        setExpandedDropdowns((prev) => new Set([...prev, targetItem.item.id]));
      } else {
        newFlatItems = arrayMove(flatItems, oldIndex, newIndex);
      }

      const recalculated = recalculateDepths(newFlatItems, active.id as number);

      // Optimistic update
      const updatedItems = recalculated.map((fi) => ({
        ...fi.item,
        parent: fi.parentId ? `/api/menu-items/${fi.parentId}` : null,
        children: [] as MenuItem[],
      }));

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

      const ops = computePatchOps(recalculated, tree);

      if (ops.length > 0) {
        setSaving(true);
        try {
          await batchPatch(ops);
          await fetchMenuItems();
          showSuccessMessage('Menu order updated');
        } catch (err) {
          showErrorMessage(
            err instanceof Error ? err.message : 'Failed to update order'
          );
          await fetchMenuItems();
        } finally {
          setSaving(false);
        }
      }
    },
    [flatItems, tree, expandedDropdowns, batchPatch, fetchMenuItems, showSuccessMessage, showErrorMessage]
  );

  // ==========================================================================
  // Footer reorder
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

  // Auto-expand all dropdowns
  useEffect(() => {
    const allDropdowns = tree
      .filter((i) => i.type === 'dropdown')
      .map((i) => i.id);
    if (allDropdowns.length > 0) {
      setExpandedDropdowns((prev) => {
        const next = new Set(prev);
        for (const id of allDropdowns) next.add(id);
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

      // Create dropdown in default locale (ro)
      const response = await fetch(`${API_BASE_URL}/api/menu-items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          Authorization: `Bearer ${token}`,
          'Accept-Language': 'ro',
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

      const created = await response.json();
      const createdId = created.id;

      // Add EN translation if provided
      if (dropdownLabelEn.trim() && createdId) {
        await fetch(`${API_BASE_URL}/api/menu-items/${createdId}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/merge-patch+json',
            Authorization: `Bearer ${token}`,
            'Accept-Language': 'en',
          },
          body: JSON.stringify({ label: dropdownLabelEn.trim() }),
        });
      }

      // Add RU translation if provided
      if (dropdownLabelRu.trim() && createdId) {
        await fetch(`${API_BASE_URL}/api/menu-items/${createdId}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/merge-patch+json',
            Authorization: `Bearer ${token}`,
            'Accept-Language': 'ru',
          },
          body: JSON.stringify({ label: dropdownLabelRu.trim() }),
        });
      }

      showSuccessMessage('Dropdown added to menu');
      setShowAddDropdownModal(false);
      setDropdownLabel('');
      setDropdownLabelEn('');
      setDropdownLabelRu('');
      await fetchMenuItems();
    } catch (err) {
      showErrorMessage(err instanceof Error ? err.message : 'Failed to add dropdown');
    } finally {
      setSaving(false);
    }
  }, [dropdownLabel, dropdownLabelEn, dropdownLabelRu, activeTab, tree, fetchMenuItems, showSuccessMessage, showErrorMessage]);

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

  return {
    activeTab,
    setActiveTab,
    loading,
    saving,
    error,
    success,
    tree,
    flatItems,
    sortableIds,
    activeItem,
    dropdownItems,
    totalItemCount,
    categories,
    availableCategories,
    expandedDropdowns,
    sensors,
    handleDragStart,
    handleDragEnd,
    toggleExpand,
    handleMoveUp,
    handleMoveDown,
    showAddCategoryModal,
    setShowAddCategoryModal,
    showAddLinkModal,
    setShowAddLinkModal,
    showAddDropdownModal,
    setShowAddDropdownModal,
    showDeleteModal,
    setShowDeleteModal,
    deletingItem,
    selectedCategoryId,
    setSelectedCategoryId,
    addCategoryParent,
    setAddCategoryParent,
    handleAddCategory,
    linkLabel,
    setLinkLabel,
    linkUrl,
    setLinkUrl,
    linkNewTab,
    setLinkNewTab,
    addLinkParent,
    setAddLinkParent,
    handleAddExternalLink,
    dropdownLabel,
    setDropdownLabel,
    dropdownLabelEn,
    setDropdownLabelEn,
    dropdownLabelRu,
    setDropdownLabelRu,
    handleAddDropdown,
    openDeleteModal,
    handleDelete,
  };
}
