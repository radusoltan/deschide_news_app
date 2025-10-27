'use client'

import { useState } from 'react'
import Link from 'next/link'
import { useIntl } from 'react-intl'
import LanguageSwitcher from '@/app/components/LanguageSwitcher'

interface HeaderProps {
  locale: string;
}

export default function Header({ locale }: HeaderProps) {
  const intl = useIntl()
  const [isSearchOpen, setIsSearchOpen] = useState(false)
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)
  const [isPagesDropdownOpen, setIsPagesDropdownOpen] = useState(false)

  return (
    <>
      {/* Header */}
      <header className="fixed top-0 left-0 right-0 z-50">
        <nav className="bg-black">
          <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
            <div className="flex justify-between">
              {/* Logo */}
              <div className="mx-w-10 text-2xl font-bold capitalize text-white flex items-center">
                <Link href={`/${locale}`}>Deschide News</Link>
              </div>

              <div className="flex flex-row">
                {/* Desktop Navigation */}
                <ul className="navbar hidden lg:flex lg:flex-row text-gray-400 text-sm items-center font-bold">
                  <li className="active relative border-l border-gray-800 hover:bg-gray-900">
                    <Link className="block py-3 px-6 border-b-2 border-transparent" href={`/${locale}`}>
                      {intl.formatMessage({ id: 'common.home' })}
                    </Link>
                  </li>

                  {/* Pages Dropdown */}
                  <li
                    className="dropdown relative border-l border-gray-800 hover:bg-gray-900"
                    onMouseEnter={() => setIsPagesDropdownOpen(true)}
                    onMouseLeave={() => setIsPagesDropdownOpen(false)}
                  >
                    <a className="block py-3 px-6 border-b-2 border-transparent cursor-pointer" href="#">
                      {intl.formatMessage({ id: 'header.pages' })}
                    </a>

                    {isPagesDropdownOpen && (
                      <ul className="dropdown-menu font-normal absolute left-0 right-auto top-full z-50 border-b-0 text-left bg-white text-gray-700 border border-gray-100" style={{ minWidth: '12rem' }}>
                        <li className="relative hover:bg-gray-50">
                          <Link className="block py-2 px-6 border-b border-gray-100" href={`/${locale}/category/politics`}>
                            Politics
                          </Link>
                        </li>
                        <li className="relative hover:bg-gray-50">
                          <Link className="block py-2 px-6 border-b border-gray-100" href={`/${locale}/category/economy`}>
                            Economy
                          </Link>
                        </li>
                        <li className="relative hover:bg-gray-50">
                          <Link className="block py-2 px-6 border-b border-gray-100" href={`/${locale}/category/sports`}>
                            Sports
                          </Link>
                        </li>
                        <li className="relative hover:bg-gray-50">
                          <Link className="block py-2 px-6 border-b border-gray-100" href={`/${locale}/category/culture`}>
                            Culture
                          </Link>
                        </li>
                      </ul>
                    )}
                  </li>

                  <li className="relative border-l border-gray-800 hover:bg-gray-900">
                    <a className="block py-3 px-6 border-b-2 border-transparent" href="#">Sport</a>
                  </li>
                  <li className="relative border-l border-gray-800 hover:bg-gray-900">
                    <a className="block py-3 px-6 border-b-2 border-transparent" href="#">Travel</a>
                  </li>
                  <li className="relative border-l border-gray-800 hover:bg-gray-900">
                    <a className="block py-3 px-6 border-b-2 border-transparent" href="#">Techno</a>
                  </li>
                  <li className="relative border-l border-gray-800 hover:bg-gray-900">
                    <a className="block py-3 px-6 border-b-2 border-transparent" href="#">Worklife</a>
                  </li>
                  <li className="relative border-l border-gray-800 hover:bg-gray-900">
                    <a className="block py-3 px-6 border-b-2 border-transparent" href="#">Future</a>
                  </li>
                  <li className="relative border-l border-gray-800 hover:bg-gray-900">
                    <a className="block py-3 px-6 border-b-2 border-transparent" href="#">More</a>
                  </li>
                </ul>

                {/* Language Switcher, Search & Mobile Menu */}
                <div className="flex flex-row items-center text-gray-300">
                  {/* Language Switcher */}
                  <div className="relative border-r lg:border-l border-gray-800 px-3 py-2">
                    <LanguageSwitcher />
                  </div>

                  {/* Search Button */}
                  <div className="search-dropdown relative border-r lg:border-l border-gray-800 hover:bg-gray-900">
                    <button
                      className="block py-3 px-6 border-b-2 border-transparent"
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
                        <div className="flex flex-wrap items-stretch w-full relative">
                          <input
                            type="text"
                            className="flex-shrink flex-grow max-w-full leading-5 w-px flex-1 relative py-2 px-5 text-gray-800 bg-white border border-gray-300 overflow-x-auto focus:outline-none focus:border-gray-400 focus:ring-0"
                            placeholder={intl.formatMessage({ id: 'common.search' })}
                            aria-label={intl.formatMessage({ id: 'common.search' })}
                          />
                          <div className="flex -mr-px">
                            <button className="flex items-center py-2 px-5 -ml-1 leading-5 text-gray-100 bg-black hover:text-white hover:bg-gray-900 focus:outline-none focus:ring-0" type="submit">
                              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" className="bi bi-search" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"></path>
                              </svg>
                            </button>
                          </div>
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Mobile Menu Button */}
                  <div className="relative hover:bg-gray-800 block lg:hidden">
                    <button
                      type="button"
                      className="menu-mobile block py-3 px-6 border-b-2 border-transparent"
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
            className="back-menu fixed bg-gray-900 bg-opacity-70 w-full h-full inset-x-0 top-0"
            onClick={() => setIsMobileMenuOpen(false)}
          >
            <div className="cursor-pointer text-white absolute right-64 p-2">
              <svg className="bi bi-x" width="2rem" height="2rem" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path fillRule="evenodd" d="M11.854 4.146a.5.5 0 010 .708l-7 7a.5.5 0 01-.708-.708l7-7a.5.5 0 01.708 0z" clipRule="evenodd"></path>
                <path fillRule="evenodd" d="M4.146 4.146a.5.5 0 000 .708l7 7a.5.5 0 00.708-.708l-7-7a.5.5 0 00-.708 0z" clipRule="evenodd"></path>
              </svg>
            </div>
          </div>

          {/* Mobile Navbar */}
          <nav className="side-menu flex flex-col right-0 w-64 fixed top-0 bg-white dark:bg-gray-800 h-full overflow-auto z-40">
            <div className="mb-auto">
              <nav className="relative flex flex-wrap">
                <div className="text-center py-4 w-full font-bold border-b border-gray-100">DESCHIDE NEWS</div>
                <ul className="w-full float-none flex flex-col">
                  <li className="relative">
                    <Link href={`/${locale}`} className="block py-2 px-5 border-b border-gray-100 hover:bg-gray-50">
                      {intl.formatMessage({ id: 'common.home' })}
                    </Link>
                  </li>
                  <li className="relative">
                    <a href="#" className="block py-2 px-5 border-b border-gray-100 hover:bg-gray-50">
                      Sport
                    </a>
                  </li>
                  <li className="relative">
                    <a href="#" className="block py-2 px-5 border-b border-gray-100 hover:bg-gray-50">
                      Travel
                    </a>
                  </li>
                  <li className="relative">
                    <a href="#" className="block py-2 px-5 border-b border-gray-100 hover:bg-gray-50">
                      Techno
                    </a>
                  </li>
                  <li className="relative">
                    <a href="#" className="block py-2 px-5 border-b border-gray-100 hover:bg-gray-50">
                      Worklife
                    </a>
                  </li>
                  <li className="relative">
                    <a href="#" className="block py-2 px-5 border-b border-gray-100 hover:bg-gray-50">
                      Future
                    </a>
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
