/** @type {import('next').NextConfig} */
const nextConfig = {
  // Configure port via environment variable or default to 3005
  env: {
    PORT: process.env.PORT || '3005',
  },

  // Image optimization - allow images from backend and CDN
  images: {
    // Disable Next.js image optimization for external CDN images
    // CDN already serves optimized images
    unoptimized: false,

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
      // CDN server for images and thumbnails
      {
        protocol: 'http',
        hostname: '127.0.0.1',
        port: '8082',
        pathname: '/uploads/**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '8082',
        pathname: '/uploads/**',
      },
    ],

    // Increase device sizes for responsive images
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],
  },

  // Turbopack enabled by default in Next.js 16
  turbopack: {},

  // Disable strict mode in development to avoid double rendering
  reactStrictMode: false,
};

export default nextConfig;
