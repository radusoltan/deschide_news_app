/**
 * Menu Item type definitions aligned with backend MenuItem entity
 */

export interface MenuItem {
  id: number;
  '@id': string;
  '@type': string;
  menu: 'main' | 'footer';
  type: 'category' | 'external_link' | 'dropdown';
  label: string;
  url: string | null;
  category: string | null; // IRI like /api/categories/32
  categorySlug: string | null;
  categoryTitle: string | null;
  position: number;
  isActive: boolean;
  openInNewTab: boolean;
  cssClass: string | null;
  parent: string | null;      // IRI of parent menu item or null
  children: MenuItem[];        // nested sub-items
  createdAt: string;
  updatedAt: string;
}

export interface MenuItemListResponse {
  '@context': string;
  '@id': string;
  '@type': string;
  'hydra:member'?: MenuItem[];
  member?: MenuItem[];
  'hydra:totalItems'?: number;
  totalItems?: number;
}

export interface CreateMenuItemData {
  menu: 'main' | 'footer';
  type: 'category' | 'external_link' | 'dropdown';
  label: string;
  url?: string | null;
  category?: string | null;
  position: number;
  isActive?: boolean;
  openInNewTab?: boolean;
  cssClass?: string | null;
  parent?: string | null;
}

export interface UpdateMenuItemData {
  label?: string;
  url?: string | null;
  position?: number;
  isActive?: boolean;
  openInNewTab?: boolean;
  cssClass?: string | null;
  parent?: string | null;
}
