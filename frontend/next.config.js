const { withSentryConfig } = require('@sentry/nextjs');

// Parse the already-wired Sentry DSN once — reused for the CSP connect-src
// ingest origin AND the CSP report sink (no new secret/host hardcoded).
function sentryDsnParts() {
  const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN || process.env.SENTRY_DSN || '';
  try {
    const u = new URL(dsn);
    const projectId = u.pathname.replace(/^\//, '');
    if (!u.username || !projectId) return null;
    return { host: u.host, protocol: u.protocol, projectId, key: u.username };
  } catch {
    return null;
  }
}

// Content-Security-Policy in ENFORCING mode: violations are now blocked.
// Flipped from Report-Only 2026-07-12 after a real-runtime observation walk
// (29 page-loads: public + real articles + consented/authenticated dashboard,
// GA4 actively beaconing) surfaced zero violations. Origins curated from the
// real runtime — GA4 (gtag script + beacons), Sentry ingest, WP media/API on
// 'self', arbitrary https images (next/image remotePatterns). 'unsafe-inline'
// on script/style reflects Next.js bootstrap + gtag-init; a nonce migration is
// out of scope. report-uri is retained in enforce mode so any block that slips
// through is still reported to the Sentry Security endpoint (when a DSN is set).
function cspPolicy() {
  const s = sentryDsnParts();
  const connect = [
    "'self'",
    'https://www.google-analytics.com',
    'https://*.google-analytics.com',
    'https://*.analytics.google.com',
    'https://www.googletagmanager.com',
  ];
  if (s) connect.push(`https://${s.host}`);
  const directives = [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "frame-ancestors 'self'",
    "form-action 'self'",
    "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob: https:",
    "font-src 'self' data:",
    `connect-src ${connect.join(' ')}`,
    "worker-src 'self'",
    "manifest-src 'self'",
    "frame-src 'self'",
  ];
  if (s) {
    directives.push(`report-uri ${s.protocol}//${s.host}/api/${s.projectId}/security/?sentry_key=${s.key}`);
  }
  return directives.join('; ') + ';';
}

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  // Pin the file-tracing root to this frontend dir so `output: standalone`
  // always emits at .next/standalone/server.js (non-nested). Next 16 otherwise
  // infers a monorepo workspace root when a stray lockfile exists above this
  // dir, nesting the output under .next/standalone/frontend/ and breaking the
  // deploy (rsync + pm2 expect the non-nested path).
  outputFileTracingRoot: __dirname,
  reactStrictMode: true,
  images: {
    formats: ['image/avif', 'image/webp'],
    minimumCacheTTL: 3600,
    dangerouslyAllowSVG: false,
    contentSecurityPolicy: "default-src 'self'; script-src 'none'; sandbox;",
    remotePatterns: [
      {
        protocol: 'https',
        hostname: '**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '',  // dev-only: allow any port for local WP (MAMP/XAMPP/Docker)
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
        source: '/:path*',
        headers: [
          { key: 'Strict-Transport-Security', value: 'max-age=63072000; includeSubDomains; preload' },
          { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          { key: 'X-XSS-Protection', value: '1; mode=block' },
          { key: 'Permissions-Policy', value: 'geolocation=(), microphone=(), camera=()' },
          { key: 'Content-Security-Policy', value: cspPolicy() },
        ],
      },
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
      { source: '/profile', destination: '/dashboard/settings', permanent: true },
      { source: '/settings', destination: '/dashboard/settings', permanent: true },
      { source: '/account', destination: '/dashboard/settings', permanent: true },
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

module.exports = withSentryConfig(nextConfig, {
  silent: true,
  hideSourceMaps: true,
  widenClientFileUpload: false,
  disableLogger: true,
})
