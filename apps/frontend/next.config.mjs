/** @type {import('next').NextConfig} */
const nextConfig = {
  // Configure port via environment variable or default to 3005
  env: {
    PORT: process.env.PORT || '3005',
  },

  // Image optimization - allow images from backend
  images: {
    remotePatterns: [
      {
        protocol: 'http',
        hostname: '127.0.0.1',
        port: '8081',
        pathname: '/media/**',
      },
      {
        protocol: 'http',
        hostname: 'api.deschide.local',
        port: '',
        pathname: '/media/**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '8081',
        pathname: '/media/**',
      },
    ],
  },

  // Turbopack enabled by default in Next.js 16
  turbopack: {},

  // Disable strict mode in development to avoid double rendering
  reactStrictMode: false,
};

export default nextConfig;
