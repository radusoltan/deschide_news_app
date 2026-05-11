'use client';

import Link from 'next/link';
import { useParams, usePathname } from 'next/navigation';
import { useState } from 'react';

export default function Sidebar() {
  const params = useParams();
  const locale = params.locale as string;
  const pathname = usePathname();

  const [usersOpen, setUsersOpen] = useState(false);

  const isActive = (href: string) => {
    if (href === `/${locale}/admin`) {
      return pathname === `/${locale}/admin`;
    }
    return pathname.startsWith(href);
  };

  const linkClass = (href: string) =>
    `flex items-center p-2 text-base rounded-lg group ${
      isActive(href)
        ? 'bg-blue-50 text-blue-700 font-semibold dark:bg-blue-900/30 dark:text-blue-300'
        : 'text-primary hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700'
    }`;

  const iconClass = (href: string) =>
    `w-6 h-6 transition duration-75 ${
      isActive(href)
        ? 'text-blue-700 dark:text-blue-300'
        : 'text-secondary group-hover:text-primary dark:text-gray-400 dark:group-hover:text-white'
    }`;

  return (
    <>
      <aside
        id="sidebar"
        className="fixed top-0 left-0 z-20 flex flex-col flex-shrink-0 hidden w-64 h-full pt-16 font-normal duration-75 lg:flex transition-width"
        aria-label="Sidebar"
      >
        <div className="relative flex flex-col flex-1 min-h-0 pt-0 bg-surface border-r border-gray-200 dark:bg-surface-dark dark:border-gray-700">
          <div className="flex flex-col flex-1 pt-5 pb-4 overflow-y-auto">
            <div className="flex-1 px-3 space-y-1 bg-surface divide-y divide-gray-200 dark:bg-surface-dark dark:divide-gray-700">
              <ul className="pb-2 space-y-2">
                {/* Mobile Search */}
                <li>
                  <form action="#" method="GET" className="lg:hidden">
                    <label htmlFor="mobile-search" className="sr-only">
                      Search
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg
                          className="w-5 h-5 text-secondary"
                          fill="currentColor"
                          viewBox="0 0 20 20"
                          xmlns="http://www.w3.org/2000/svg"
                        >
                          <path
                            fillRule="evenodd"
                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                            clipRule="evenodd"
                          />
                        </svg>
                      </div>
                      <input
                        type="text"
                        name="search"
                        id="mobile-search"
                        className="bg-surface-sunken border border-gray-300 text-primary text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-gray-200 dark:focus:ring-primary-500 dark:focus:border-primary-500"
                        placeholder="Search"
                      />
                    </div>
                  </form>
                </li>

                {/* Dashboard */}
                <li>
                  <Link
                    href={`/${locale}/admin`}
                    className={linkClass(`/${locale}/admin`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z" />
                      <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z" />
                    </svg>
                    <span className="ml-3">Dashboard</span>
                  </Link>
                </li>

                {/* Articles */}
                <li>
                  <Link
                    href={`/${locale}/admin/articles`}
                    className={linkClass(`/${locale}/admin/articles`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/articles`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fillRule="evenodd"
                        d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z"
                        clipRule="evenodd"
                      />
                      <path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z" />
                    </svg>
                    <span className="ml-3">Articles</span>
                  </Link>
                </li>

                {/* Categories */}
                <li>
                  <Link
                    href={`/${locale}/admin/categories`}
                    className={linkClass(`/${locale}/admin/categories`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/categories`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                    </svg>
                    <span className="ml-3">Categories</span>
                  </Link>
                </li>

                {/* Tags */}
                <li>
                  <Link
                    href={`/${locale}/admin/tags`}
                    className={linkClass(`/${locale}/admin/tags`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/tags`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fillRule="evenodd"
                        d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="ml-3">Tags</span>
                  </Link>
                </li>

                {/* Topics */}
                <li>
                  <Link
                    href={`/${locale}/admin/topics`}
                    className={linkClass(`/${locale}/admin/topics`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/topics`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path d="M10 3.5a1.5 1.5 0 013 0V4a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-.5a1.5 1.5 0 000 3h.5a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-.5a1.5 1.5 0 00-3 0v.5a1 1 0 01-1 1H6a1 1 0 01-1-1v-3a1 1 0 00-1-1h-.5a1.5 1.5 0 010-3H4a1 1 0 001-1V6a1 1 0 011-1h3a1 1 0 001-1v-.5z" />
                    </svg>
                    <span className="ml-3">Topics</span>
                  </Link>
                </li>

                {/* Menu Builder */}
                <li>
                  <Link
                    href={`/${locale}/admin/menu-builder`}
                    className={linkClass(`/${locale}/admin/menu-builder`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/menu-builder`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fillRule="evenodd"
                        d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="ml-3">Menu Builder</span>
                  </Link>
                </li>

                {/* Images */}
                <li>
                  <Link
                    href={`/${locale}/admin/images`}
                    className={linkClass(`/${locale}/admin/images`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/images`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fillRule="evenodd"
                        d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="ml-3">Images</span>
                  </Link>
                </li>

                {/* Thumbnail Profiles */}
                <li>
                  <Link
                    href={`/${locale}/admin/thumbnail-profiles`}
                    className={linkClass(`/${locale}/admin/thumbnail-profiles`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/thumbnail-profiles`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path fillRule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z" clipRule="evenodd" />
                      <path d="M11 4a1 1 0 10-2 0v1a1 1 0 002 0V4zM10 7a1 1 0 011 1v1h2a1 1 0 110 2h-3a1 1 0 01-1-1V8a1 1 0 011-1zM16 9a1 1 0 100 2 1 1 0 000-2zM9 13a1 1 0 011-1h1a1 1 0 110 2v2a1 1 0 11-2 0v-3zM16 11a1 1 0 011 1v3a1 1 0 11-2 0v-3a1 1 0 011-1zM12 11a1 1 0 10-2 0v3a1 1 0 102 0v-3zM14 13a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" />
                    </svg>
                    <span className="ml-3">Thumbnail Profiles</span>
                  </Link>
                </li>

                {/* Authors */}
                <li>
                  <Link
                    href={`/${locale}/admin/authors`}
                    className={linkClass(`/${locale}/admin/authors`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/authors`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                    </svg>
                    <span className="ml-3">Authors</span>
                  </Link>
                </li>

                {/* Important Articles */}
                <li>
                  <Link
                    href={`/${locale}/admin/important-articles`}
                    className={linkClass(`/${locale}/admin/important-articles`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/important-articles`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    <span className="ml-3">Important Articles</span>
                  </Link>
                </li>

                {/* Archive */}
                <li>
                  <Link
                    href={`/${locale}/admin/archive`}
                    className={linkClass(`/${locale}/admin/archive`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/archive`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" />
                      <path
                        fillRule="evenodd"
                        d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="ml-3">Archive</span>
                  </Link>
                </li>

                {/* Users */}
                <li>
                  <button
                    type="button"
                    className="flex items-center w-full p-2 text-base text-primary transition duration-75 rounded-lg group hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                    onClick={() => setUsersOpen(!usersOpen)}
                  >
                    <svg
                      className="flex-shrink-0 w-6 h-6 text-secondary transition duration-75 group-hover:text-primary dark:text-gray-400 dark:group-hover:text-white"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                    </svg>
                    <span className="flex-1 ml-3 text-left whitespace-nowrap">
                      Users
                    </span>
                    <svg
                      className={`w-6 h-6 transition-transform ${
                        usersOpen ? 'rotate-180' : ''
                      }`}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fillRule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clipRule="evenodd"
                      />
                    </svg>
                  </button>
                  {usersOpen && (
                    <ul className="py-2 space-y-2">
                      <li>
                        <Link
                          href={`/${locale}/admin/users`}
                          className="flex items-center p-2 text-base text-primary transition duration-75 rounded-lg pl-11 group hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                          All Users
                        </Link>
                      </li>
                      <li>
                        <Link
                          href={`/${locale}/admin/users/new`}
                          className="flex items-center p-2 text-base text-primary transition duration-75 rounded-lg pl-11 group hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                          Add New
                        </Link>
                      </li>
                    </ul>
                  )}
                </li>

                {/* Settings */}
                <li>
                  <Link
                    href={`/${locale}/admin/settings`}
                    className={linkClass(`/${locale}/admin/settings`)}
                  >
                    <svg
                      className={iconClass(`/${locale}/admin/settings`)}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fillRule="evenodd"
                        d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="ml-3">Settings</span>
                  </Link>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </aside>

      {/* Mobile Sidebar Backdrop */}
      <div
        className="fixed inset-0 z-10 hidden bg-gray-900/50 dark:bg-surface-dark/90"
        id="sidebarBackdrop"
      />
    </>
  );
}
