/**
 * Public Menu Items API Service
 * Server-side fetch for public frontend navigation (no auth required)
 */

import type { MenuItem } from '../types/menu';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

/**
 * Fetch active menu items for a given menu type, sorted by position.
 * Uses Next.js ISR with 5-minute revalidation.
 * Returns a tree: top-level items with children nested inside.
 * Returns empty array on error (graceful fallback).
 */
export async function fetchPublicMenuItems(
  menu: 'main' | 'footer',
  locale: string = 'ro'
): Promise<MenuItem[]> {
  try {
    const url = new URL(`${API_BASE_URL}/api/menu-items`);
    url.searchParams.set('menu', menu);
    url.searchParams.set('isActive', 'true');
    url.searchParams.set('order[position]', 'asc');
    url.searchParams.set('itemsPerPage', '50');

    const response = await fetch(url.toString(), {
      headers: {
        'Accept-Language': locale,
        Accept: 'application/ld+json',
      },
      next: { tags: ['menu'] },
    });

    if (!response.ok) {
      console.error(`[public-menu] Failed to fetch ${menu} menu: ${response.status}`);
      return [];
    }

    const data = await response.json();
    const flat: MenuItem[] = data.member ?? data['hydra:member'] ?? [];

    return buildMenuTree(flat);
  } catch (error) {
    console.error(`[public-menu] Error fetching ${menu} menu:`, error);
    return [];
  }
}

/**
 * Build a tree from flat menu items.
 * Returns only top-level items (parent === null) with children nested.
 */
function buildMenuTree(items: MenuItem[]): MenuItem[] {
  const byIri = new Map<string, MenuItem>();
  for (const item of items) {
    // Ensure children array exists
    if (!item.children) item.children = [];
    byIri.set(item['@id'], item);
  }

  const topLevel: MenuItem[] = [];

  for (const item of items) {
    if (item.parent) {
      // This is a child item - attach to parent
      const parentIri = typeof item.parent === 'string' ? item.parent : (item.parent as { '@id': string })['@id'];
      const parentItem = byIri.get(parentIri);
      if (parentItem) {
        // Avoid duplicates (API may already include children in parent's children array)
        if (!parentItem.children.some(c => c.id === item.id)) {
          parentItem.children.push(item);
        }
      }
    } else {
      topLevel.push(item);
    }
  }

  // Sort children by position
  for (const item of topLevel) {
    item.children.sort((a, b) => a.position - b.position);
  }

  return topLevel;
}

/**
 * Build the URL for a menu item based on its type.
 * - category items: /{locale}/{categorySlug}
 * - external_link items: the raw URL
 */
export function getMenuItemHref(item: MenuItem, locale: string): string {
  if (item.type === 'external_link' && item.url) {
    return item.url;
  }

  if (item.type === 'category' && item.categorySlug) {
    const localePrefix = locale === 'ro' ? '' : `${locale}/`;
    return `/${localePrefix}${item.categorySlug}`;
  }

  // Dropdown items are containers -- no direct link
  if (item.type === 'dropdown') {
    return '#';
  }

  return '#';
}
