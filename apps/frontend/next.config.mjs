import bundleAnalyzer from '@next/bundle-analyzer';

const withBundleAnalyzer = bundleAnalyzer({
  enabled: process.env.ANALYZE === 'true',
});

/** @type {import('next').NextConfig} */
const nextConfig = {
  // Configure port via environment variable or default to 3005
  env: {
    PORT: process.env.PORT || '3005',
  },

  // Image optimization - allow images from backend and CDN
  images: {
    // Disable optimization for development to avoid private IP blocking
    // In production, use public CDN or configure proper remote patterns
    unoptimized: process.env.NODE_ENV === 'development',

    // Supported formats (WebP and AVIF for modern browsers)
    formats: ['image/webp', 'image/avif'],

    // Remote patterns for CDN and API images
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
      // Also allow CDN hostname variants
      {
        protocol: 'http',
        hostname: '127.0.0.1',
        port: '',
        pathname: '/uploads/**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '',
        pathname: '/uploads/**',
      },
    ],

    // Responsive image sizes
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],

    // Cache optimized images for 30 days
    minimumCacheTTL: 60 * 60 * 24 * 30,

    // Dangerously allow SVG (only if needed for logos)
    dangerouslyAllowSVG: true,
    contentDispositionType: 'attachment',
    contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
  },

  // Turbopack enabled by default in Next.js 16
  turbopack: {},

  // Disable strict mode in development to avoid double rendering
  reactStrictMode: false,

  // Production optimizations
  productionBrowserSourceMaps: false, // Disable source maps in production for smaller bundles

  // Compiler optimizations
  compiler: {
    // Remove console logs in production
    removeConsole: process.env.NODE_ENV === 'production' ? {
      exclude: ['error', 'warn'],
    } : false,
  },

  // Experimental features for better performance
  experimental: {
    optimizePackageImports: ['lucide-react', 'date-fns', '@heroicons/react'],
  },
};

export default withBundleAnalyzer(nextConfig);
