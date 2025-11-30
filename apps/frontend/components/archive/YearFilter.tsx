'use client';

/**
 * YearFilter Component
 * Displays a list of years with archived article counts
 * Features a vintage newspaper aesthetic with sepia tones
 */

import { useState, useEffect } from 'react';

interface YearData {
  year: number;
  count: number;
}

interface YearFilterProps {
  years: YearData[];
  selectedYear: number | null;
  onYearChange: (year: number | null) => void;
  isLoading?: boolean;
  locale?: string;
}

const translations = {
  ro: {
    allYears: 'Toți anii',
    articles: 'articole',
  },
  en: {
    allYears: 'All years',
    articles: 'articles',
  },
  ru: {
    allYears: 'Все годы',
    articles: 'статей',
  },
};

export default function YearFilter({
  years,
  selectedYear,
  onYearChange,
  isLoading = false,
  locale = 'ro',
}: YearFilterProps) {
  const t = translations[locale as keyof typeof translations] || translations.ro;

  if (isLoading) {
    return (
      <div className="space-y-2">
        {[...Array(6)].map((_, i) => (
          <div
            key={i}
            className="animate-pulse h-12 bg-amber-100/50 rounded-lg"
            style={{ animationDelay: `${i * 100}ms` }}
          />
        ))}
      </div>
    );
  }

  return (
    <nav className="space-y-1" aria-label="Year filter">
      {/* All Years Option */}
      <button
        onClick={() => onYearChange(null)}
        className={`
          w-full text-left px-4 py-3 rounded-lg transition-all duration-300
          flex items-center justify-between group
          ${
            selectedYear === null
              ? 'bg-gradient-to-r from-amber-600 to-amber-700 text-white shadow-lg shadow-amber-600/25'
              : 'bg-amber-50/80 hover:bg-amber-100 text-amber-900 hover:shadow-md'
          }
        `}
      >
        <span className="font-semibold tracking-wide">{t.allYears}</span>
        <span
          className={`
            text-sm px-2 py-0.5 rounded-full
            ${selectedYear === null ? 'bg-white/20' : 'bg-amber-200/50 group-hover:bg-amber-200'}
          `}
        >
          {years.reduce((acc, y) => acc + y.count, 0).toLocaleString()}
        </span>
      </button>

      {/* Year List */}
      <div className="pt-2 space-y-1">
        {years.map((yearData, index) => (
          <button
            key={yearData.year}
            onClick={() => onYearChange(yearData.year)}
            className={`
              w-full text-left px-4 py-3 rounded-lg transition-all duration-300
              flex items-center justify-between group
              ${
                selectedYear === yearData.year
                  ? 'bg-gradient-to-r from-amber-600 to-amber-700 text-white shadow-lg shadow-amber-600/25'
                  : 'bg-amber-50/50 hover:bg-amber-100 text-amber-800 hover:shadow-md border border-amber-100 hover:border-amber-200'
              }
            `}
            style={{
              animationDelay: `${index * 50}ms`,
            }}
          >
            <span className="font-bold text-lg tracking-tight">{yearData.year}</span>
            <div className="flex items-center gap-2">
              <span
                className={`
                  text-sm
                  ${selectedYear === yearData.year ? 'text-amber-100' : 'text-amber-600'}
                `}
              >
                {yearData.count.toLocaleString()} {t.articles}
              </span>
              {/* Visual bar indicator */}
              <div
                className={`
                  h-1.5 rounded-full transition-all duration-500
                  ${selectedYear === yearData.year ? 'bg-white/40' : 'bg-amber-300'}
                `}
                style={{
                  width: `${Math.min((yearData.count / Math.max(...years.map(y => y.count))) * 40, 40)}px`,
                }}
              />
            </div>
          </button>
        ))}
      </div>

      {years.length === 0 && !isLoading && (
        <div className="text-center py-8 text-amber-600/70">
          <svg
            className="w-12 h-12 mx-auto mb-3 opacity-50"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <p className="text-sm italic">No archived years found</p>
        </div>
      )}
    </nav>
  );
}
