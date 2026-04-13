/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  reactStrictMode: true,
  images: {
    domains: ['teinformez.eu', 'localhost'],
    remotePatterns: [
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
