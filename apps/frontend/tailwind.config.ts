import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
    './node_modules/flowbite/**/*.js',
    './node_modules/flowbite-react/**/*.{js,ts,jsx,tsx}',
  ],
  // Dark mode uses data-theme attribute — @custom-variant in globals.css handles this
  darkMode: ['variant', '&:is([data-theme="dark"], [data-theme="dark"] *)'],
  theme: {
    extend: {
      // Transition timing functions
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
