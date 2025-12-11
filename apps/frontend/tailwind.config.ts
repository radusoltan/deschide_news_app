import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
    './node_modules/flowbite/**/*.js',
    './node_modules/flowbite-react/**/*.{js,ts,jsx,tsx}',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        heading: ['var(--font-heading)', 'system-ui', 'arial', 'sans-serif'], // League Spartan Bold
        body: ['var(--font-body)', 'system-ui', 'arial', 'sans-serif'], // Poppins
        sans: ['var(--font-inter)', 'system-ui', 'arial', 'sans-serif'], // Fallback for UI
      },
      colors: {
        // Deschide Brand Colors from brandbook
        brand: {
          // Oxford Blue (Primary - 40% usage)
          oxford: {
            50: '#f0f4f9',
            100: '#d9e2f0',
            200: '#b8c9e4',
            300: '#8aa8d3',
            400: '#5883c0',
            500: '#3565a9',
            600: '#274f8f',
            700: '#1f3f74',
            800: '#1c3660',
            900: '#112240', // Primary brand color
            950: '#0a1629',
          },
          // Tomato (Secondary - 40-50% usage)
          tomato: {
            50: '#fef3f2',
            100: '#fde4e1',
            200: '#fbcdc8',
            300: '#f8aba3',
            400: '#f37b6e',
            500: '#f05e45', // Primary tomato
            600: '#dd3c28',
            700: '#ba2e1e',
            800: '#9a291d',
            900: '#80271f',
            950: '#46100c',
          },
          // Red CMYK (Accent - 10-30%, NEVER for text)
          red: {
            50: '#fef2f2',
            100: '#fee2e2',
            200: '#fecaca',
            300: '#fca5a5',
            400: '#f87171',
            500: '#ef4444',
            600: '#e92628', // Primary red accent
            700: '#c81e20',
            800: '#a51d1f',
            900: '#881e1f',
            950: '#4b0d0e',
          },
          // Mindaro (Accent - max 10%, sparingly)
          mindaro: {
            50: '#fafef0',
            100: '#f5fde0',
            200: '#ecfbc2',
            300: '#e1f999',
            400: '#d4fb8c', // Primary mindaro
            500: '#bfe556',
            600: '#a0c63b',
            700: '#7a9a2f',
            800: '#627a29',
            900: '#526725',
            950: '#2c3910',
          },
        },
        // Legacy support (will be phased out)
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554',
        },
      },
      fontSize: {
        // Typography scale from design system
        'hero-title': ['48px', { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '700' }],
        'hero-title-mobile': ['32px', { lineHeight: '1.15', letterSpacing: '-0.01em', fontWeight: '700' }],
        'h1': ['40px', { lineHeight: '1.2', letterSpacing: '-0.01em', fontWeight: '700' }],
        'h1-mobile': ['28px', { lineHeight: '1.25', letterSpacing: '0', fontWeight: '700' }],
        'h2': ['32px', { lineHeight: '1.3', letterSpacing: '0', fontWeight: '600' }],
        'h2-mobile': ['24px', { lineHeight: '1.3', letterSpacing: '0', fontWeight: '600' }],
        'h3': ['24px', { lineHeight: '1.4', letterSpacing: '0', fontWeight: '600' }],
        'h3-mobile': ['20px', { lineHeight: '1.4', letterSpacing: '0', fontWeight: '600' }],
        'h4': ['20px', { lineHeight: '1.5', letterSpacing: '0', fontWeight: '600' }],
        'h4-mobile': ['18px', { lineHeight: '1.5', letterSpacing: '0', fontWeight: '600' }],
        'body-lg': ['19px', { lineHeight: '1.7', letterSpacing: '0', fontWeight: '400' }],
        'body': ['17px', { lineHeight: '1.7', letterSpacing: '0', fontWeight: '400' }],
        'body-sm': ['15px', { lineHeight: '1.6', letterSpacing: '0', fontWeight: '400' }],
        'accent': ['14px', { lineHeight: '1.5', letterSpacing: '0.02em', fontWeight: '500' }],
        'accent-lg': ['16px', { lineHeight: '1.5', letterSpacing: '0.01em', fontWeight: '500' }],
      },
      keyframes: {
        // Fade animations
        'fade-in': {
          '0%': { opacity: '0', transform: 'translateY(-10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in-up': {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in-down': {
          '0%': { opacity: '0', transform: 'translateY(-20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        // Slide animations
        'slide-in-right': {
          '0%': { opacity: '0', transform: 'translateX(20px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        'slide-in-left': {
          '0%': { opacity: '0', transform: 'translateX(-20px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        // Scale animations
        'scale-in': {
          '0%': { opacity: '0', transform: 'scale(0.95)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        'scale-up': {
          '0%': { transform: 'scale(1)' },
          '100%': { transform: 'scale(1.02)' },
        },
        // Brand pulse for breaking news
        'brand-pulse': {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.7' },
        },
        // Shimmer effect
        'shimmer': {
          '0%': { backgroundPosition: '-1000px 0' },
          '100%': { backgroundPosition: '1000px 0' },
        },
        // Skeleton loading
        'skeleton-loading': {
          '0%': { backgroundPosition: '200% 0' },
          '100%': { backgroundPosition: '-200% 0' },
        },
        // Spinner
        'spin-smooth': {
          '0%': { transform: 'rotate(0deg)' },
          '100%': { transform: 'rotate(360deg)' },
        },
        // Text reveal
        'text-reveal': {
          '0%': { clipPath: 'inset(0 100% 0 0)' },
          '100%': { clipPath: 'inset(0 0 0 0)' },
        },
      },
      animation: {
        // Fade animations
        'fade-in': 'fade-in 0.5s ease-out',
        'fade-in-up': 'fade-in-up 0.5s ease-out forwards',
        'fade-in-down': 'fade-in-down 0.5s ease-out forwards',
        // Slide animations
        'slide-in-right': 'slide-in-right 0.4s ease-out',
        'slide-in-left': 'slide-in-left 0.4s ease-out',
        // Scale animations
        'scale-in': 'scale-in 0.3s ease-out',
        'scale-up': 'scale-up 0.3s ease-out forwards',
        // Brand animations
        'brand-pulse': 'brand-pulse 2s ease-in-out infinite',
        'shimmer': 'shimmer 2s infinite linear',
        'skeleton': 'skeleton-loading 1.5s ease-in-out infinite',
        // Utility animations
        'spin-smooth': 'spin-smooth 1s linear infinite',
        'text-reveal': 'text-reveal 0.6s ease-out forwards',
      },
      // Add transition timing functions
      transitionTimingFunction: {
        'brand-ease': 'cubic-bezier(0.4, 0, 0.2, 1)',
        'brand-ease-in': 'cubic-bezier(0.4, 0, 1, 1)',
        'brand-ease-out': 'cubic-bezier(0, 0, 0.2, 1)',
        'brand-ease-in-out': 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
    },
  },
  plugins: [
    require('flowbite/plugin'),
  ],
};

export default config;
