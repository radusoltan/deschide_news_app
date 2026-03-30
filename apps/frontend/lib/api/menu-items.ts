/**
 * Menu Items API Service
 * Admin endpoints for managing menu items (authentication required)
 */

import type {
  MenuItem,
  MenuItemListResponse,
  CreateMenuItemData,
  UpdateMenuItemData,
} from '../types/menu';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

/**
 * Fetch menu items filtered by menu type
 * GET /api/menu_items?menu=main|footer
 */
export async function fetchMenuItems(
  menu: 'main' | 'footer',
  token: string,
  locale?: string
): Promise<MenuItem[]> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = new URL(`${API_BASE_URL}/api/menu_items`);
  url.searchParams.set('menu', menu);
  url.searchParams.set('order[position]', 'asc');
  url.searchParams.set('itemsPerPage', '100');

  const response = await fetch(url.toString(), {
    method: 'GET',
    headers,
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(
      `Failed to fetch menu items: ${response.status} ${response.statusText}`
    );
  }

  const data: MenuItemListResponse = await response.json();
  return data['hydra:member'] || data.member || [];
}

/**
 * Create a new menu item
 * POST /api/menu_items
 */
export async function createMenuItem(
  data: CreateMenuItemData,
  token: string,
  locale?: string
): Promise<MenuItem> {
  const headers: HeadersInit = {
    'Content-Type': 'application/ld+json',
    Authorization: `Bearer ${token}`,
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const response = await fetch(`${API_BASE_URL}/api/menu_items`, {
    method: 'POST',
    headers,
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(
      errorData['hydra:description'] ||
        errorData.message ||
        `Failed to create menu item: ${response.status}`
    );
  }

  return response.json();
}

/**
 * Update an existing menu item
 * PATCH /api/menu_items/{id}
 */
export async function updateMenuItem(
  id: number,
  data: UpdateMenuItemData,
  token: string,
  locale?: string
): Promise<MenuItem> {
  const headers: HeadersInit = {
    'Content-Type': 'application/merge-patch+json',
    Authorization: `Bearer ${token}`,
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const response = await fetch(`${API_BASE_URL}/api/menu_items/${id}`, {
    method: 'PATCH',
    headers,
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(
      errorData['hydra:description'] ||
        errorData.message ||
        `Failed to update menu item: ${response.status}`
    );
  }

  return response.json();
}

/**
 * Delete a menu item
 * DELETE /api/menu_items/{id}
 */
export async function deleteMenuItem(
  id: number,
  token: string
): Promise<void> {
  const response = await fetch(`${API_BASE_URL}/api/menu_items/${id}`, {
    method: 'DELETE',
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  if (!response.ok && response.status !== 204) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(
      errorData['hydra:description'] ||
        errorData.message ||
        `Failed to delete menu item: ${response.status}`
    );
  }
}
