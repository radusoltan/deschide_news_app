'use client'

import { useState, useCallback, useRef, useEffect } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useIntl } from 'react-intl'
import LanguageSwitcher from '@/app/components/LanguageSwitcher'
import { Logo } from '@/components/brand/Logo'
import { buildLocalizedUrl, buildCategoryUrl } from '@/lib/utils/url-builder'
import { getMenuItemHref } from '@/lib/api/public-menu'
import type { Locale } from '@/lib/types'
import type { Category } from '@/lib/types/article'
import type { MenuItem } from '@/lib/types/menu'

interface HeaderProps {
  locale: string;
  categories?: Category[];
  menuItems?: MenuItem[];
}

interface DarkModeToggleProps {
  compact?: boolean;
}

function DarkModeToggle({ compact = false }: DarkModeToggleProps) {
  // Lazy useState init reads localStorage on the client first render,
  // so React 19 strict-mode does not flag a synchronous setState inside
  // useEffect (react-hooks/set-state-in-effect). The icon path will
  // legitimately differ between SSR ('system') and the client first
  // render (whatever localStorage holds); suppressHydrationWarning on
  // the button is the documented escape hatch for this exact pattern,
  // already used on <html> in app/[locale]/layout.tsx alongside the
  // anti-flash inline script that pre-applies data-theme.
  const [theme, setTheme] = useState<'light' | 'dark' | 'system'>(() => {
    if (typeof window === 'undefined') {
      return 'system'
    }
    try {
      const stored = window.localStorage.getItem('theme-preference')
      if (stored === 'light' || stored === 'dark' || stored === 'system') {
        return stored
      }
    } catch { /* ignore */ }
    return 'system'
  })

  const applyTheme = useCallback((nextTheme: 'light' | 'dark' | 'system') => {
    if (typeof document === 'undefined') {
      return
    }

    if (nextTheme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark')
      return
    }

    if (nextTheme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light')
      return
    }

    document.documentElement.removeAttribute('data-theme')

    if (
      typeof window !== 'undefined' &&
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-color-scheme: dark)').matches
    ) {
      document.documentElement.setAttribute('data-theme', 'dark')
    }
  }, [])

  useEffect(() => {
    applyTheme(theme)
  }, [applyTheme, theme])

  const cycleTheme = () => {
    const nextTheme = theme === 'light' ? 'dark' : theme === 'dark' ? 'system' : 'light'
    setTheme(nextTheme)

    try {
      if (typeof window !== 'undefined') {
        window.localStorage.setItem('theme-preference', nextTheme)
      }
    } catch {
      // Ignore storage errors and keep in-memory theme state.
    }

    applyTheme(nextTheme)
  }

  const iconClass = compact ? 'w-4 h-4' : 'w-5 h-5'

  return (
    <button
      onClick={cycleTheme}
      className={`flex items-center justify-center min-h-[44px] min-w-[44px] text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface)] dark:focus:ring-offset-[var(--color-surface-dark)] rounded-sm ${compact ? 'px-2' : 'px-3'}`}
      aria-label={`Current theme: ${theme}. Click to cycle between light, dark, and system themes.`}
      title={`Theme: ${theme}`}
      suppressHydrationWarning
    >
      {theme === 'light' && (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      )}
      {theme === 'dark' && (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
      )}
      {theme === 'system' && (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      )}
    </button>
  )
}

export default function Header({ locale, categories = [], menuItems = [] }: HeaderProps) {
  const intl = useIntl()
  const router = useRouter()
  const [isSearchOpen, setIsSearchOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)
  const [openDropdownId, setOpenDropdownId] = useState<number | null>(null)
  const [mobileOpenDropdownId, setMobileOpenDropdownId] = useState<number | null>(null)
  const [isCompact, setIsCompact] = useState(false)
  const dropdownRef = useRef<HTMLLIElement>(null)

  // menuItems is now a tree: top-level items with children nested
  // Fallback to categories if no menu items from API
  const hasMenuItems = menuItems.length > 0
  const menuCategories = hasMenuItems ? [] : categories.filter((cat) => cat.inMenu === true)

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setOpenDropdownId(null)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  // Compact header on scroll
  useEffect(() => {
    let ticking = false
    function onScroll() {
      if (!ticking) {
        requestAnimationFrame(() => {
          setIsCompact(window.scrollY > 60)
          ticking = false
        })
        ticking = true
      }
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const handleSearch = useCallback((e: React.FormEvent) => {
    e.preventDefault()
    if (searchQuery.trim()) {
      router.push(`${buildLocalizedUrl('/search', locale as Locale)}?q=${encodeURIComponent(searchQuery.trim())}`)
      setIsSearchOpen(false)
      setSearchQuery('')
    }
  }, [searchQuery, locale, router])

  return (
    <>
      {/* Header */}
      <header className="fixed top-0 left-0 right-0 z-50">
        <nav className={`backdrop-blur-md bg-[var(--color-surface-elevated)]/95 dark:bg-[var(--color-surface-elevated-dark)]/95 border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)] transition-all duration-300 ${isCompact ? 'shadow-lg' : ''}`}>
          <div className="max-w-[1440px] mx-auto px-3 sm:px-4 xl:px-6">
            <div className="flex items-center justify-between">
              {/* Desktop Layout */}
              <div className="hidden lg:flex items-center flex-1">
                {/* Logo - Left */}
                <div className="flex items-center">
                  <Logo
                    variant="blue"
                    size={isCompact ? 'sm' : 'md'}
                    href={buildLocalizedUrl('/', locale as Locale)}
                  />
                </div>

                {/* Center Navigation */}
                <nav className="flex-1 flex justify-center">
                  <ul className="flex items-center font-sans text-[var(--font-size-sm)] font-medium">
                    {/* Dynamic menu items from API (tree structure with dropdowns) */}
                    {hasMenuItems ? menuItems.map((item) => {
                      const isDropdown = item.type === 'dropdown' || (item.children && item.children.length > 0)
                      const isOpen = openDropdownId === item.id

                      if (isDropdown) {
                        return (
                          <li key={item.id} className="relative" ref={isOpen ? dropdownRef : undefined}>
                            <button
                              onClick={() => setOpenDropdownId(isOpen ? null : item.id)}
                              onMouseEnter={() => setOpenDropdownId(item.id)}
                              className={`flex items-center gap-1 px-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors border-b-2 border-transparent hover:border-[var(--color-accent)] ${isCompact ? 'py-2' : 'py-3'} min-h-[44px]`}
                              aria-expanded={isOpen}
                              aria-haspopup="true"
                            >
                              {item.label}
                              <svg
                                className={`w-4 h-4 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                              </svg>
                            </button>

                            {isOpen && item.children && item.children.length > 0 && (
                              <div
                                className="absolute top-full left-0 mt-0 w-48 bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] border border-[var(--color-border)] dark:border-[var(--color-border-dark)] rounded-b-lg shadow-lg z-50 overflow-hidden"
                                onMouseLeave={() => setOpenDropdownId(null)}
                              >
                                {item.children.map((child) => (
                                  <Link
                                    key={child.id}
                                    href={getMenuItemHref(child, locale)}
                                    className="block px-4 py-3 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-sunken-dark)] hover:text-[var(--color-accent)] transition-colors font-sans text-[var(--font-size-sm)]"
                                    onClick={() => setOpenDropdownId(null)}
                                    {...(child.type === 'external_link' && child.openInNewTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                                  >
                                    {child.label}
                                  </Link>
                                ))}
                              </div>
                            )}
                          </li>
                        )
                      }

                      return (
                        <li key={item.id} className="relative">
                          <Link
                            className={`block px-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors border-b-2 border-transparent hover:border-[var(--color-accent)] ${isCompact ? 'py-2' : 'py-3'} min-h-[44px] flex items-center`}
                            href={getMenuItemHref(item, locale)}
                            {...(item.type === 'external_link' && item.openInNewTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                          >
                            {item.label}
                            {item.type === 'external_link' && item.openInNewTab && (
                              <svg className="w-3 h-3 ml-1 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            )}
                          </Link>
                        </li>
                      )
                    }) : menuCategories.map((category) => (
                      <li key={category.id} className="relative">
                        <Link
                          className={`block px-4 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors border-b-2 border-transparent hover:border-[var(--color-accent)] ${isCompact ? 'py-2' : 'py-3'} min-h-[44px] flex items-center`}
                          href={buildCategoryUrl(category, locale as Locale)}
                        >
                          {category.title}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </nav>

                {/* Right Side - Search, Language, Dark Mode */}
                <div className="flex items-center gap-1">
                  {/* Search Button */}
                  <div className="search-dropdown relative">
                    <button
                      className={`flex items-center justify-center min-h-[44px] min-w-[44px] text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface)] dark:focus:ring-offset-[var(--color-surface-dark)] rounded-sm ${isCompact ? 'px-2' : 'px-3'}`}
                      onClick={() => setIsSearchOpen(!isSearchOpen)}
                      aria-label={intl.formatMessage({ id: 'common.search' })}
                    >
                      {!isSearchOpen ? (
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                          <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                        </svg>
                      ) : (
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                          <path fillRule="evenodd" d="M13.854 2.146a.5.5 0 0 1 0 .708l-11 11a.5.5 0 0 1-.708-.708l11-11a.5.5 0 0 1 .708 0Z"/>
                          <path fillRule="evenodd" d="M2.146 2.146a.5.5 0 0 0 0 .708l11 11a.5.5 0 0 0 .708-.708l-11-11a.5.5 0 0 0-.708 0Z"/>
                        </svg>
                      )}
                    </button>
                    {isSearchOpen && (
                      <div className="absolute left-auto right-0 top-full z-50 mt-2 w-80 bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] border border-[var(--color-border)] dark:border-[var(--color-border-dark)] rounded-lg shadow-lg p-4">
                        <form onSubmit={handleSearch} className="flex gap-2">
                          <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="flex-1 py-2 px-4 bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)] border border-[var(--color-border)] dark:border-[var(--color-border-dark)] rounded-md text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] placeholder-[var(--color-text-tertiary)] dark:placeholder-[var(--color-text-tertiary-dark)] focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:border-[var(--color-focus)] font-sans text-[var(--font-size-sm)]"
                            placeholder={intl.formatMessage({ id: 'common.search' })}
                            aria-label={intl.formatMessage({ id: 'common.search' })}
                            autoFocus
                          />
                          <button
                            className="px-4 py-2 bg-[var(--color-accent)] hover:bg-[var(--color-accent-hover)] text-white rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface)] dark:focus:ring-offset-[var(--color-surface-dark)] font-sans text-[var(--font-size-sm)] font-medium"
                            type="submit"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                              <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                            </svg>
                          </button>
                        </form>
                      </div>
                    )}
                  </div>

                  {/* Language Switcher */}
                  <div className="relative">
                    <LanguageSwitcher />
                  </div>

                  {/* Dark Mode Toggle */}
                  <DarkModeToggle compact={isCompact} />
                </div>
              </div>

              {/* Mobile Layout */}
              <div className="flex lg:hidden items-center justify-between w-full">
                {/* Mobile Menu Button - Left */}
                <button
                  type="button"
                  className="flex items-center justify-center min-h-[44px] min-w-[44px] text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface)] dark:focus:ring-offset-[var(--color-surface-dark)] rounded-sm"
                  onClick={() => setIsMobileMenuOpen(true)}
                  aria-label={intl.formatMessage({ id: 'common.menu' })}
                >
                  <svg className="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
                </button>

                {/* Logo - Center */}
                <div className="flex items-center">
                  <Logo
                    variant="blue"
                    size={isCompact ? 'sm' : 'md'}
                    href={buildLocalizedUrl('/', locale as Locale)}
                  />
                </div>

                {/* Search Button - Right */}
                <button
                  className="flex items-center justify-center min-h-[44px] min-w-[44px] text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:text-[var(--color-accent)] transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface)] dark:focus:ring-offset-[var(--color-surface-dark)] rounded-sm"
                  onClick={() => setIsSearchOpen(!isSearchOpen)}
                  aria-label={intl.formatMessage({ id: 'common.search' })}
                >
                  {!isSearchOpen ? (
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                    </svg>
                  ) : (
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                      <path fillRule="evenodd" d="M13.854 2.146a.5.5 0 0 1 0 .708l-11 11a.5.5 0 0 1-.708-.708l11-11a.5.5 0 0 1 .708 0Z"/>
                      <path fillRule="evenodd" d="M2.146 2.146a.5.5 0 0 0 0 .708l11 11a.5.5 0 0 0 .708-.708l-11-11a.5.5 0 0 0-.708 0Z"/>
                    </svg>
                  )}
                </button>
              </div>
            </div>

            {/* Mobile Search Dropdown */}
            {isSearchOpen && (
              <div className="lg:hidden mt-2 pb-3">
                <form onSubmit={handleSearch} className="flex gap-2">
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="flex-1 py-2 px-4 bg-[var(--color-surface)] dark:bg-[var(--color-surface-dark)] border border-[var(--color-border)] dark:border-[var(--color-border-dark)] rounded-md text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] placeholder-[var(--color-text-tertiary)] dark:placeholder-[var(--color-text-tertiary-dark)] focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:border-[var(--color-focus)] font-sans text-[var(--font-size-sm)]"
                    placeholder={intl.formatMessage({ id: 'common.search' })}
                    aria-label={intl.formatMessage({ id: 'common.search' })}
                    autoFocus
                  />
                  <button
                    className="px-4 py-2 bg-[var(--color-accent)] hover:bg-[var(--color-accent-hover)] text-white rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface)] dark:focus:ring-offset-[var(--color-surface-dark)] font-sans text-[var(--font-size-sm)] font-medium"
                    type="submit"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                    </svg>
                  </button>
                </form>
              </div>
            )}
          </div>
        </nav>
      </header>

      {/* Mobile Menu */}
      {isMobileMenuOpen && (
        <div className="fixed w-full h-full inset-0 z-50 lg:hidden">
          {/* Background Overlay */}
          <div
            className="fixed bg-black/60 backdrop-blur-sm w-full h-full inset-0"
            onClick={() => setIsMobileMenuOpen(false)}
          />

          {/* Mobile Sidebar */}
          <nav className="fixed right-0 w-80 max-w-[85vw] h-full bg-[var(--color-surface-elevated)] dark:bg-[var(--color-surface-elevated-dark)] border-l border-[var(--color-border)] dark:border-[var(--color-border-dark)] shadow-xl overflow-auto animate-slide-in-right">
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-[var(--color-border)] dark:border-[var(--color-border-dark)]">
              <Logo variant="blue" size="sm" />
              <button
                onClick={() => setIsMobileMenuOpen(false)}
                className="flex items-center justify-center min-h-[44px] min-w-[44px] text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-text-primary)] dark:hover:text-[var(--color-text-primary-dark)] transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-focus)] focus:ring-offset-2 focus:ring-offset-[var(--color-surface-elevated)] dark:focus:ring-offset-[var(--color-surface-elevated-dark)] rounded-sm"
                aria-label="Close menu"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Navigation Links */}
            <div className="py-4">
              <ul className="space-y-1 font-sans">
                {/* Home */}
                <li>
                  <Link
                    href={buildLocalizedUrl('/', locale as Locale)}
                    className="flex items-center px-4 py-3 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-sunken-dark)] hover:text-[var(--color-accent)] transition-colors font-medium text-[var(--font-size-base)] min-h-[44px]"
                    onClick={() => setIsMobileMenuOpen(false)}
                  >
                    <svg className="w-5 h-5 mr-3 text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {intl.formatMessage({ id: 'common.home' })}
                  </Link>
                </li>

                {/* Dynamic menu items from API (tree with dropdowns) */}
                {hasMenuItems ? menuItems.map((item) => {
                  const isDropdown = item.type === 'dropdown' || (item.children && item.children.length > 0)
                  const isMobileOpen = mobileOpenDropdownId === item.id

                  if (isDropdown) {
                    return (
                      <li key={item.id}>
                        <button
                          onClick={() => setMobileOpenDropdownId(isMobileOpen ? null : item.id)}
                          className="w-full flex items-center justify-between px-4 py-3 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-sunken-dark)] hover:text-[var(--color-accent)] transition-colors font-medium text-[var(--font-size-base)] min-h-[44px]"
                        >
                          <div className="flex items-center">
                            <svg className="w-5 h-5 mr-3 text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                            </svg>
                            <span>{item.label}</span>
                          </div>
                          <svg
                            className={`w-4 h-4 transition-transform ${isMobileOpen ? 'rotate-180' : ''}`}
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                          </svg>
                        </button>

                        {isMobileOpen && item.children && item.children.length > 0 && (
                          <ul className="bg-[var(--color-surface-sunken)] dark:bg-[var(--color-surface-sunken-dark)] space-y-1">
                            {item.children.map((child) => (
                              <li key={child.id}>
                                <Link
                                  href={getMenuItemHref(child, locale)}
                                  className="flex items-center pl-12 pr-4 py-3 text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] hover:text-[var(--color-accent)] transition-colors text-[var(--font-size-sm)] min-h-[44px]"
                                  onClick={() => setIsMobileMenuOpen(false)}
                                  {...(child.type === 'external_link' && child.openInNewTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                                >
                                  {child.label}
                                </Link>
                              </li>
                            ))}
                          </ul>
                        )}
                      </li>
                    )
                  }

                  return (
                    <li key={item.id}>
                      <Link
                        href={getMenuItemHref(item, locale)}
                        className="flex items-center px-4 py-3 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-sunken-dark)] hover:text-[var(--color-accent)] transition-colors font-medium text-[var(--font-size-base)] min-h-[44px]"
                        onClick={() => setIsMobileMenuOpen(false)}
                        {...(item.type === 'external_link' && item.openInNewTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                      >
                        <span className="w-5 h-5 mr-3 flex items-center justify-center">
                          <div className="w-2 h-2 rounded-full bg-[var(--color-section-politics)]" />
                        </span>
                        {item.label}
                      </Link>
                    </li>
                  )
                }) : menuCategories.map((category) => (
                  <li key={category.id}>
                    <Link
                      href={buildCategoryUrl(category, locale as Locale)}
                      className="flex items-center px-4 py-3 text-[var(--color-text-primary)] dark:text-[var(--color-text-primary-dark)] hover:bg-[var(--color-surface-sunken)] dark:hover:bg-[var(--color-surface-sunken-dark)] hover:text-[var(--color-accent)] transition-colors font-medium text-[var(--font-size-base)] min-h-[44px]"
                      onClick={() => setIsMobileMenuOpen(false)}
                    >
                      <span className="w-5 h-5 mr-3 flex items-center justify-center">
                        <div className="w-2 h-2 rounded-full bg-[var(--color-section-politics)]" />
                      </span>
                      {category.title}
                    </Link>
                  </li>
                ))}
              </ul>

              {/* Settings Section */}
              <div className="mt-6 pt-4 border-t border-[var(--color-border)] dark:border-[var(--color-border-dark)]">
                <div className="px-4 py-2">
                  <h3 className="text-[var(--color-text-secondary)] dark:text-[var(--color-text-secondary-dark)] font-medium text-[var(--font-size-sm)] uppercase tracking-wider">
                    {intl.formatMessage({ id: 'common.settings', defaultMessage: 'Setări' })}
                  </h3>
                </div>

                <div className="space-y-1">
                  {/* Language Switcher */}
                  <div className="px-4 py-2">
                    <div className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-[var(--font-size-xs)] mb-2 uppercase tracking-wider">
                      {intl.formatMessage({ id: 'common.language', defaultMessage: 'Limba' })}
                    </div>
                    <LanguageSwitcher />
                  </div>

                  {/* Theme Toggle */}
                  <div className="px-4 py-2">
                    <div className="text-[var(--color-text-tertiary)] dark:text-[var(--color-text-tertiary-dark)] text-[var(--font-size-xs)] mb-2 uppercase tracking-wider">
                      {intl.formatMessage({ id: 'common.theme', defaultMessage: 'Temă' })}
                    </div>
                    <DarkModeToggle />
                  </div>
                </div>
              </div>
            </div>
          </nav>
        </div>
      )}
    </>
  )
}
