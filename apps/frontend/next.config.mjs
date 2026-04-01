import { withSentryConfig } from '@sentry/nextjs';
import bundleAnalyzer from '@next/bundle-analyzer';

const withBundleAnalyzer = bundleAnalyzer({
  enabled: process.env.ANALYZE === 'true',
});

/** @type {import('next').NextConfig} */
const nextConfig = {
  // Standalone output for production deployment (Docker, PM2 cluster)
  output: 'standalone',

  // Configure port via environment variable or default to 3005
  env: {
    PORT: process.env.PORT || '3005',
  },

  // Image optimization - allow images from backend and CDN
  images: {
    // DEVELOPMENT: Disable optimization to allow local CDN (127.0.0.1)
    // Next.js 16 blocks private IPs in image optimization for security
    // In production, set this to false and use a public CDN hostname
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

  // Enable strict mode to help detect problems in development
  reactStrictMode: true,

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

  // Rewrites for static files that may be requested with locale prefix
  async rewrites() {
    return {
      beforeFiles: [
        // Handle manifest file requests with locale prefix
        {
          source: '/:locale(ro|en|ru)/site.webmanifest',
          destination: '/site.webmanifest',
        },
        // Handle favicon requests with locale prefix
        {
          source: '/:locale(ro|en|ru)/favicon.ico',
          destination: '/favicon.ico',
        },
        {
          source: '/:locale(ro|en|ru)/favicon-16x16.png',
          destination: '/favicon-16x16.png',
        },
        {
          source: '/:locale(ro|en|ru)/favicon-32x32.png',
          destination: '/favicon-32x32.png',
        },
        {
          source: '/:locale(ro|en|ru)/apple-touch-icon.png',
          destination: '/apple-touch-icon.png',
        },
      ],
    };
  },

  // Security headers
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          {
            key: 'Content-Security-Policy',
            value: [
              "default-src 'self'",
              `script-src 'self' 'unsafe-inline'${process.env.NODE_ENV === 'development' ? " 'unsafe-eval'" : ''}`,
              "style-src 'self' 'unsafe-inline'",
              "img-src 'self' data: https: http://127.0.0.1:8082 http://127.0.0.1:8081",
              "font-src 'self' data:",
              "connect-src 'self' http://127.0.0.1:8081 http://127.0.0.1:8082 ws://localhost:3000 http://localhost:3000",
              "frame-ancestors 'none'",
              "base-uri 'self'",
              "form-action 'self'",
            ].join('; '),
          },
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'X-Frame-Options', value: 'DENY' },
          { key: 'X-XSS-Protection', value: '1; mode=block' },
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          { key: 'Permissions-Policy', value: 'geolocation=(), microphone=(), camera=()' },
        ],
      },
    ];
  },
};

const config = withBundleAnalyzer(nextConfig);

// Only wrap with Sentry if DSN is configured (empty DSN = disabled)
export default process.env.NEXT_PUBLIC_SENTRY_DSN
  ? withSentryConfig(config, {
      // Suppresses source map upload logs during build
      silent: true,
      org: "deschide-news",
      project: "deschide-frontend",
    })
  : config;
