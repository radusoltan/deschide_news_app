/**
 * Article Body Component
 * Renders article content with proper typography and image support
 */

'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { createSafeHtml } from '@/lib/sanitize';

interface ArticleBodyProps {
  content: string;
  className?: string;
  enableTableOfContents?: boolean;
}

/**
 * Extract headings from HTML content for table of contents
 */
function extractHeadings(html: string): Array<{ id: string; text: string; level: number }> {
  const headings: Array<{ id: string; text: string; level: number }> = [];
  const headingRegex = /<h([2-3])[^>]*>(.*?)<\/h[2-3]>/gi;
  let match;

  while ((match = headingRegex.exec(html)) !== null) {
    const level = parseInt(match[1]);
    const text = match[2].replace(/<[^>]*>/g, ''); // Strip HTML from heading text
    const id = `heading-${headings.length}`;
    headings.push({ id, text, level });
  }

  return headings;
}

/**
 * Add IDs to headings for anchor links
 */
function processContent(html: string): string {
  let headingIndex = 0;

  return html.replace(/<h([2-3])([^>]*)>(.*?)<\/h[2-3]>/gi, (match, level, attrs, text) => {
    const id = `heading-${headingIndex++}`;
    return `<h${level}${attrs} id="${id}">${text}</h${level}>`;
  });
}

export default function ArticleBody({
  content,
  className = '',
  enableTableOfContents = false,
}: ArticleBodyProps) {
  const [showTOC, setShowTOC] = useState(false);
  const headings = enableTableOfContents ? extractHeadings(content) : [];
  const processedContent = enableTableOfContents ? processContent(content) : content;

  const scrollToHeading = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
      setShowTOC(false);
    }
  };

  return (
    <div className={`max-w-full ${className}`}>
      {/* Table of Contents - show only if enabled and headings exist */}
      {enableTableOfContents && headings.length > 0 && (
        <div className="mb-8 bg-white border border-gray-200 rounded-lg p-4 md:p-6">
          <button
            onClick={() => setShowTOC(!showTOC)}
            className="flex items-center justify-between w-full text-left font-bold text-gray-800 mb-2 md:mb-0"
          >
            <span className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M4 6h16M4 12h16M4 18h16"
                />
              </svg>
              Table of Contents
            </span>
            <svg
              className={`w-5 h-5 transition-transform md:hidden ${showTOC ? 'rotate-180' : ''}`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M19 9l-7 7-7-7"
              />
            </svg>
          </button>

          <nav className={`mt-3 ${showTOC ? 'block' : 'hidden md:block'}`}>
            <ul className="space-y-2">
              {headings.map((heading, index) => (
                <li
                  key={index}
                  className={heading.level === 3 ? 'ml-4' : ''}
                >
                  <button
                    onClick={() => scrollToHeading(heading.id)}
                    className="text-sm text-gray-700 hover:text-red-600 hover:underline transition-colors text-left"
                  >
                    {heading.text}
                  </button>
                </li>
              ))}
            </ul>
          </nav>
        </div>
      )}

      {/* Article Content */}
      <div
        className={`
          leading-relaxed prose prose-lg max-w-none
          prose-headings:font-bold prose-headings:text-gray-800
          prose-h2:text-2xl prose-h2:mt-8 prose-h2:mb-4
          prose-h3:text-xl prose-h3:mt-6 prose-h3:mb-3
          prose-p:text-gray-700 prose-p:mb-5 prose-p:leading-relaxed
          prose-a:text-red-600 prose-a:no-underline hover:prose-a:underline
          prose-strong:text-gray-900 prose-strong:font-semibold
          prose-em:text-gray-700
          prose-ul:list-disc prose-ul:ml-6 prose-ul:mb-5
          prose-ol:list-decimal prose-ol:ml-6 prose-ol:mb-5
          prose-li:text-gray-700 prose-li:mb-2
          prose-blockquote:border-l-4 prose-blockquote:border-red-600
          prose-blockquote:pl-4 prose-blockquote:italic prose-blockquote:text-gray-600
          prose-img:rounded-lg prose-img:shadow-md prose-img:my-6
          prose-table:border-collapse prose-table:w-full prose-table:mb-6
          prose-th:bg-gray-100 prose-th:p-3 prose-th:text-left prose-th:font-semibold
          prose-td:border prose-td:border-gray-200 prose-td:p-3
          ${className}
        `}
        dangerouslySetInnerHTML={createSafeHtml(processedContent)}
      />
    </div>
  );
}
