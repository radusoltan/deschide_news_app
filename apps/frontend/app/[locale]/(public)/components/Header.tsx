'use client'

import { useState, useCallback } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useIntl } from 'react-intl'
import LanguageSwitcher from '@/app/components/LanguageSwitcher'
import { Logo } from '@/components/brand/Logo'

interface HeaderProps {
  locale: string;
}

export default function Header({ locale }: HeaderProps) {
  const intl = useIntl()
  const router = useRouter()
  const [isSearchOpen, setIsSearchOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)

  const handleSearch = useCallback((e: React.FormEvent) => {
    e.preventDefault()
    if (searchQuery.trim()) {
      router.push(`/${locale}/search?q=${encodeURIComponent(searchQuery.trim())}`)
      setIsSearchOpen(false)
      setSearchQuery('')
    }
  }, [searchQuery, locale, router])

  return (
    <>
      {/* Header */}
      <header className="fixed top-0 left-0 right-0 z-50">
        <nav className="bg-brand-oxford-900">
          <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
            <div className="flex justify-between">
              {/* Logo */}
              <div className="mx-w-10 flex items-center">
                <Logo variant="white" size="md" href={`/${locale}`} />
              </div>

              <div className="flex flex-row">
                {/* Desktop Navigation */}
                <ul className="navbar hidden lg:flex lg:flex-row text-white text-sm items-center font-heading uppercase">
                  <li className="active relative border-l border-white/10 hover:bg-brand-oxford-800">
                    <Link className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors" href={`/${locale}`}>
                      {intl.formatMessage({ id: 'common.home' })}
                    </Link>
                  </li>

                  {/* Politic */}
                  <li className="relative border-l border-white/10 hover:bg-brand-oxford-800">
                    <Link className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors" href={`/${locale}/politic`}>
                      {intl.formatMessage({ id: 'nav.politic', defaultMessage: 'Politic' })}
                    </Link>
                  </li>
                  {/* Externe */}
                  <li className="relative border-l border-white/10 hover:bg-brand-oxford-800">
                    <Link className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors" href={`/${locale}/externe`}>
                      {intl.formatMessage({ id: 'nav.externe', defaultMessage: 'Externe' })}
                    </Link>
                  </li>
                  {/* Social */}
                  <li className="relative border-l border-white/10 hover:bg-brand-oxford-800">
                    <Link className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors" href={`/${locale}/social`}>
                      {intl.formatMessage({ id: 'nav.social', defaultMessage: 'Social' })}
                    </Link>
                  </li>
                  {/* Editorial */}
                  <li className="relative border-l border-white/10 hover:bg-brand-oxford-800">
                    <Link className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors" href={`/${locale}/editorial`}>
                      {intl.formatMessage({ id: 'nav.editorial', defaultMessage: 'Editorial' })}
                    </Link>
                  </li>
                  {/* All Articles */}
                  <li className="relative border-l border-white/10 hover:bg-brand-oxford-800">
                    <Link className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors" href={`/${locale}/all`}>
                      {intl.formatMessage({ id: 'nav.all', defaultMessage: 'Toate' })}
                    </Link>
                  </li>
                  {/* Archive */}
                  <li className="relative border-l border-white/10 hover:bg-brand-oxford-800">
                    <Link className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors" href={`/${locale}/archive`}>
                      {intl.formatMessage({ id: 'nav.archive', defaultMessage: 'Arhivă' })}
                    </Link>
                  </li>
                </ul>

                {/* Language Switcher, Search & Mobile Menu */}
                <div className="flex flex-row items-center text-white">
                  {/* Language Switcher */}
                  <div className="relative border-r lg:border-l border-white/10 px-3 py-2">
                    <LanguageSwitcher />
                  </div>

                  {/* Search Button */}
                  <div className="search-dropdown relative border-r lg:border-l border-white/10 hover:bg-brand-oxford-800">
                    <button
                      className="block py-3 px-6 border-b-2 border-transparent hover:text-brand-mindaro-400 transition-colors"
                      onClick={() => setIsSearchOpen(!isSearchOpen)}
                    >
                      {!isSearchOpen ? (
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" className="bi bi-search" viewBox="0 0 16 16">
                          <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"></path>
                        </svg>
                      ) : (
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" className="bi bi-x-lg" viewBox="0 0 16 16">
                          <path fillRule="evenodd" d="M13.854 2.146a.5.5 0 0 1 0 .708l-11 11a.5.5 0 0 1-.708-.708l11-11a.5.5 0 0 1 .708 0Z"/>
                          <path fillRule="evenodd" d="M2.146 2.146a.5.5 0 0 0 0 .708l11 11a.5.5 0 0 0 .708-.708l-11-11a.5.5 0 0 0-.708 0Z"/>
                        </svg>
                      )}
                    </button>
                    {isSearchOpen && (
                      <div className="dropdown-menu absolute left-auto right-0 top-full z-50 text-left bg-white text-gray-700 border border-gray-100 mt-1 p-3" style={{ minWidth: '15rem' }}>
                        <form onSubmit={handleSearch} className="flex flex-wrap items-stretch w-full relative">
                          <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="flex-shrink flex-grow max-w-full leading-5 w-px flex-1 relative py-2 px-5 text-gray-800 bg-white border border-gray-300 overflow-x-auto focus:outline-none focus:border-brand-oxford-900 focus:ring-0"
                            placeholder={intl.formatMessage({ id: 'common.search' })}
                            aria-label={intl.formatMessage({ id: 'common.search' })}
                            autoFocus
                          />
                          <div className="flex -mr-px">
                            <button className="flex items-center py-2 px-5 -ml-1 leading-5 text-white bg-brand-oxford-900 hover:bg-brand-tomato-500 transition-colors focus:outline-none focus:ring-0" type="submit">
                              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" className="bi bi-search" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"></path>
                              </svg>
                            </button>
                          </div>
                        </form>
                      </div>
                    )}
                  </div>

                  {/* Mobile Menu Button */}
                  <div className="relative hover:bg-brand-oxford-800 block lg:hidden">
                    <button
                      type="button"
                      className="menu-mobile block py-3 px-6 border-b-2 border-transparent font-heading uppercase text-sm hover:text-brand-mindaro-400 transition-colors"
                      onClick={() => setIsMobileMenuOpen(true)}
                    >
                      <span className="sr-only">Mobile menu</span>
                      <svg className="inline-block h-6 w-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path>
                      </svg>
                      {intl.formatMessage({ id: 'common.menu' })}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </nav>
      </header>

      {/* Mobile Menu */}
      {isMobileMenuOpen && (
        <div className="side-area fixed w-full h-full inset-0 z-50">
          {/* Background Overlay */}
          <div
            className="back-menu fixed bg-brand-oxford-900 bg-opacity-90 w-full h-full inset-x-0 top-0"
            onClick={() => setIsMobileMenuOpen(false)}
          >
            <div className="cursor-pointer text-white absolute right-64 p-2 hover:text-brand-mindaro-400 transition-colors">
              <svg className="bi bi-x" width="2rem" height="2rem" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path fillRule="evenodd" d="M11.854 4.146a.5.5 0 010 .708l-7 7a.5.5 0 01-.708-.708l7-7a.5.5 0 01.708 0z" clipRule="evenodd"></path>
                <path fillRule="evenodd" d="M4.146 4.146a.5.5 0 000 .708l7 7a.5.5 0 00.708-.708l-7-7a.5.5 0 00-.708 0z" clipRule="evenodd"></path>
              </svg>
            </div>
          </div>

          {/* Mobile Navbar */}
          <nav className="side-menu flex flex-col right-0 w-64 fixed top-0 bg-white dark:bg-brand-oxford-900 h-full overflow-auto z-40">
            <div className="mb-auto">
              <nav className="relative flex flex-wrap">
                <div className="text-center py-4 w-full border-b border-gray-100 dark:border-white/10">
                  <Logo variant="blue" size="sm" className="dark:!text-white" />
                </div>
                <ul className="w-full float-none flex flex-col font-body">
                  <li className="relative">
                    <Link href={`/${locale}`} className="block py-2 px-5 border-b border-gray-100 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-brand-oxford-800 dark:text-white transition-colors">
                      {intl.formatMessage({ id: 'common.home' })}
                    </Link>
                  </li>
                  <li className="relative">
                    <Link href={`/${locale}/politic`} className="block py-2 px-5 border-b border-gray-100 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-brand-oxford-800 dark:text-white transition-colors">
                      {intl.formatMessage({ id: 'nav.politic', defaultMessage: 'Politic' })}
                    </Link>
                  </li>
                  <li className="relative">
                    <Link href={`/${locale}/externe`} className="block py-2 px-5 border-b border-gray-100 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-brand-oxford-800 dark:text-white transition-colors">
                      {intl.formatMessage({ id: 'nav.externe', defaultMessage: 'Externe' })}
                    </Link>
                  </li>
                  <li className="relative">
                    <Link href={`/${locale}/social`} className="block py-2 px-5 border-b border-gray-100 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-brand-oxford-800 dark:text-white transition-colors">
                      {intl.formatMessage({ id: 'nav.social', defaultMessage: 'Social' })}
                    </Link>
                  </li>
                  <li className="relative">
                    <Link href={`/${locale}/editorial`} className="block py-2 px-5 border-b border-gray-100 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-brand-oxford-800 dark:text-white transition-colors">
                      {intl.formatMessage({ id: 'nav.editorial', defaultMessage: 'Editorial' })}
                    </Link>
                  </li>
                  <li className="relative">
                    <Link href={`/${locale}/archive`} className="block py-2 px-5 border-b border-gray-100 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-brand-oxford-800 dark:text-white transition-colors">
                      {intl.formatMessage({ id: 'nav.archive', defaultMessage: 'Arhivă' })}
                    </Link>
                  </li>
                </ul>
              </nav>
            </div>
          </nav>
        </div>
      )}
    </>
  )
}
