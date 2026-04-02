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
        <div className="mb-8 bg-surface dark:bg-surface-elevated-dark border border-gray-200 dark:border-border-dark rounded-lg p-4 md:p-6">
          <button
            onClick={() => setShowTOC(!showTOC)}
            className="flex items-center justify-between w-full text-left font-bold text-gray-800 dark:text-primary-dark mb-2 md:mb-0"
          >
            <span className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-brand-tomato-500"
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
                    className="text-sm text-primary dark:text-primary-dark hover:text-brand-tomato-500 hover:underline transition-colors text-left"
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
      <style dangerouslySetInnerHTML={{ __html: `
        .article-body p { margin-bottom: 1.25em; }
        .article-body p:last-child { margin-bottom: 0; }
        .article-body h2 { margin-top: 2em; margin-bottom: 1em; }
        .article-body h3 { margin-top: 1.5em; margin-bottom: 0.75em; }
        .article-body ul, .article-body ol { margin-bottom: 1.25em; }
        .article-body blockquote { margin-top: 1.5em; margin-bottom: 1.5em; }
      `}} />
      <div
        className={`
          article-body
          font-serif leading-relaxed prose prose-lg max-w-none
          text-primary dark:text-primary-dark
          prose-headings:font-bold prose-headings:text-gray-800 dark:prose-headings:text-primary-dark
          prose-h2:text-2xl
          prose-h3:text-xl
          prose-p:text-primary dark:prose-p:text-primary-dark prose-p:leading-relaxed
          prose-a:text-brand-tomato-500 prose-a:no-underline hover:prose-a:underline
          prose-strong:text-primary dark:prose-strong:text-primary-dark prose-strong:font-semibold
          prose-em:text-primary dark:prose-em:text-primary-dark
          prose-ul:list-disc prose-ul:ml-6
          prose-ol:list-decimal prose-ol:ml-6
          prose-li:text-primary dark:prose-li:text-primary-dark prose-li:mb-2
          prose-blockquote:border-l-4 prose-blockquote:border-brand-tomato-500
          prose-blockquote:pl-4 prose-blockquote:italic prose-blockquote:text-gray-600 dark:prose-blockquote:text-secondary-dark
          prose-img:rounded-lg prose-img:shadow-md prose-img:my-6
          prose-table:border-collapse prose-table:w-full prose-table:mb-6
          prose-th:bg-gray-100 dark:prose-th:bg-surface-elevated-dark prose-th:p-3 prose-th:text-left prose-th:font-semibold
          prose-td:border prose-td:border-gray-200 dark:prose-td:border-border-dark prose-td:p-3
          ${className}
        `}
        style={{ fontSize: '1.125rem' }}
        dangerouslySetInnerHTML={createSafeHtml(processedContent)}
      />
    </div>
  );
}
