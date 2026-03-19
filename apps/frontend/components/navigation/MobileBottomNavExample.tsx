'use client';

/**
 * MobileBottomNav Usage Example
 * Demonstrates complete integration with search, categories, and menu
 *
 * This is a reference implementation showing:
 * - State management for modals/sheets
 * - Callback handling
 * - Integration with existing components
 * - Proper TypeScript typing
 */

import { useState } from 'react';
import MobileBottomNav from './MobileBottomNav';
import type { Locale } from '@/lib/types';

// Example modal/sheet components (replace with your actual components)
function SearchModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm md:hidden">
      <div className="fixed inset-x-0 top-0 h-full bg-white animate-slide-in-down">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-xl font-bold text-brand-oxford-900">Search</h2>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            aria-label="Close search"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div className="p-4">
          <input
            type="search"
            placeholder="Search articles..."
            className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-tomato-500"
            autoFocus
          />
        </div>
      </div>
    </div>
  );
}

function CategoriesSheet({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  if (!isOpen) return null;

  const categories = [
    { id: 1, name: 'Politică', color: '#1d4ed8' },
    { id: 2, name: 'Economie', color: '#047857' },
    { id: 3, name: 'Societate', color: '#7c3aed' },
    { id: 4, name: 'Sport', color: '#dc2626' },
    { id: 5, name: 'Cultură', color: '#b45309' },
    { id: 6, name: 'Externe', color: '#0891b2' },
  ];

  return (
    <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm md:hidden" onClick={onClose}>
      <div
        className="fixed inset-x-0 bottom-0 bg-white rounded-t-3xl shadow-2xl max-h-[80vh] overflow-auto animate-slide-up"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Handle bar */}
        <div className="flex justify-center pt-3 pb-2">
          <div className="w-12 h-1 bg-gray-300 rounded-full" />
        </div>

        <div className="p-4 pb-20">
          <h2 className="text-xl font-bold text-brand-oxford-900 mb-4">Categorii</h2>

          <div className="grid grid-cols-2 gap-3">
            {categories.map((category) => (
              <button
                key={category.id}
                className="p-4 rounded-xl border-2 border-gray-200 hover:border-brand-tomato-500 transition-all text-left"
                onClick={onClose}
              >
                <div
                  className="w-8 h-8 rounded-lg mb-2"
                  style={{ backgroundColor: category.color }}
                />
                <span className="font-semibold text-brand-oxford-900">{category.name}</span>
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

function MobileMenu({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  if (!isOpen) return null;

  const menuItems = [
    { id: 1, label: 'Profil', icon: '👤' },
    { id: 2, label: 'Salvate', icon: '🔖' },
    { id: 3, label: 'Setări', icon: '⚙️' },
    { id: 4, label: 'Despre', icon: 'ℹ️' },
    { id: 5, label: 'Contact', icon: '📧' },
  ];

  return (
    <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm md:hidden" onClick={onClose}>
      <div
        className="fixed inset-y-0 right-0 w-80 max-w-[85vw] bg-white shadow-2xl animate-slide-in-right"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-xl font-bold text-brand-oxford-900">Meniu</h2>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            aria-label="Close menu"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div className="p-4 space-y-2">
          {menuItems.map((item) => (
            <button
              key={item.id}
              className="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-100 transition-colors text-left"
              onClick={onClose}
            >
              <span className="text-2xl">{item.icon}</span>
              <span className="font-medium text-brand-oxford-900">{item.label}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

/**
 * Main wrapper component that includes MobileBottomNav with full functionality
 * Use this as a template for your actual implementation
 */
export default function MobileBottomNavExample({ locale }: { locale: Locale }) {
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isCategoriesOpen, setIsCategoriesOpen] = useState(false);
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  return (
    <>
      {/* Mobile Bottom Navigation */}
      <MobileBottomNav
        locale={locale}
        onSearchClick={() => setIsSearchOpen(true)}
        onCategoriesClick={() => setIsCategoriesOpen(true)}
        onMenuClick={() => setIsMenuOpen(true)}
      />

      {/* Modals/Sheets */}
      <SearchModal isOpen={isSearchOpen} onClose={() => setIsSearchOpen(false)} />
      <CategoriesSheet isOpen={isCategoriesOpen} onClose={() => setIsCategoriesOpen(false)} />
      <MobileMenu isOpen={isMenuOpen} onClose={() => setIsMenuOpen(false)} />
    </>
  );
}

/**
 * Alternative: Server Component Wrapper
 * Use this pattern if you want to keep the wrapper as a server component
 */
export function MobileNavServerWrapper({ locale }: { locale: Locale }) {
  return <MobileBottomNavExample locale={locale} />;
}

/**
 * Integration Example 1: Root Layout
 *
 * app/[locale]/layout.tsx:
 *
 * import MobileBottomNavExample from '@/components/navigation/MobileBottomNavExample';
 *
 * export default function LocaleLayout({ children, params }) {
 *   return (
 *     <div className="min-h-screen">
 *       <Header />
 *       <main>{children}</main>
 *       <Footer />
 *       <MobileBottomNavExample locale={params.locale} />
 *     </div>
 *   );
 * }
 */

/**
 * Integration Example 2: Conditional Rendering
 *
 * Only show on public pages (not admin):
 *
 * export default function PublicLayout({ children, params }) {
 *   const isAdminRoute = usePathname()?.includes('/admin');
 *
 *   return (
 *     <>
 *       {children}
 *       {!isAdminRoute && <MobileBottomNavExample locale={params.locale} />}
 *     </>
 *   );
 * }
 */

/**
 * Integration Example 3: With Authentication
 *
 * Show different menu items for logged-in users:
 *
 * export default function AuthenticatedMobileNav({ locale, user }) {
 *   const [isMenuOpen, setIsMenuOpen] = useState(false);
 *
 *   return (
 *     <>
 *       <MobileBottomNav
 *         locale={locale}
 *         onMenuClick={() => setIsMenuOpen(true)}
 *       />
 *       <MobileMenu
 *         isOpen={isMenuOpen}
 *         onClose={() => setIsMenuOpen(false)}
 *         user={user} // Pass user data for personalized menu
 *       />
 *     </>
 *   );
 * }
 */

/**
 * Integration Example 4: With Analytics
 *
 * Track navigation interactions:
 *
 * export default function MobileNavWithAnalytics({ locale }) {
 *   const trackNavigation = (item: string) => {
 *     // Your analytics implementation
 *     gtag('event', 'mobile_nav_click', { item });
 *   };
 *
 *   return (
 *     <MobileBottomNav
 *       locale={locale}
 *       onSearchClick={() => {
 *         trackNavigation('search');
 *         setIsSearchOpen(true);
 *       }}
 *       onCategoriesClick={() => {
 *         trackNavigation('categories');
 *         setIsCategoriesOpen(true);
 *       }}
 *       onMenuClick={() => {
 *         trackNavigation('menu');
 *         setIsMenuOpen(true);
 *       }}
 *     />
 *   );
 * }
 */
