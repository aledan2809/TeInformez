/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  reactStrictMode: true,
  images: {
    formats: ['image/avif', 'image/webp'],
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'teinformez.eu',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '',  // dev-only: allow any port for local WP (MAMP/XAMPP/Docker)
      },
      {
        protocol: 'https',
        hostname: '**.openai.com',
      },
    ],
  },
  env: {
    NEXT_PUBLIC_WP_API_URL: process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json',
    NEXT_PUBLIC_SITE_URL: process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000',
  },
  async headers() {
    return [
      {
        source: '/_next/static/(.*)',
        headers: [
          { key: 'Cache-Control', value: 'public, max-age=31536000, immutable' },
        ],
      },
      {
        source: '/(.*)\\.(ico|png|jpg|jpeg|svg|webp|avif|woff|woff2)',
        headers: [
          { key: 'Cache-Control', value: 'public, max-age=86400, stale-while-revalidate=604800' },
        ],
      },
    ];
  },
  async redirects() {
    return [
      { source: '/profile', destination: '/dashboard/settings', permanent: false },
      { source: '/settings', destination: '/dashboard/settings', permanent: false },
    ];
  },
  async rewrites() {
    const wpApiUrl = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
    return [
      {
        source: '/api/wp/:path*',
        destination: `${wpApiUrl}/:path*`,
      },
    ];
  },
}

module.exports = nextConfig
