import { NextResponse } from 'next/server';

export const revalidate = 3600;

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';
const WP_API_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const API_BASE = `${WP_API_URL}/teinformez/v1`;

const NEWS_PER_PAGE = 100;
const MAX_NEWS_SITEMAPS = 104;

export async function GET() {
  let cappedPages = 1;
  try {
    const res = await fetch(`${API_BASE}/news?per_page=${NEWS_PER_PAGE}&page=1`, {
      next: { revalidate: 3600 },
    });
    if (res.ok) {
      const json = await res.json() as { data?: { total_pages?: number } };
      const totalPages = json.data?.total_pages ?? 1;
      cappedPages = Math.min(totalPages, MAX_NEWS_SITEMAPS);
    }
  } catch {
    // use default cappedPages=1
  }

  const locs = [
    `${SITE_URL}/sitemap/0.xml`,
    ...Array.from({ length: cappedPages }, (_, i) => `${SITE_URL}/sitemap/${i + 1}.xml`),
  ];

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${locs.map((loc) => `  <sitemap><loc>${loc}</loc></sitemap>`).join('\n')}
</sitemapindex>`;

  return new NextResponse(xml, {
    headers: {
      'Content-Type': 'application/xml; charset=utf-8',
      'Cache-Control': 'public, max-age=3600, s-maxage=3600',
    },
  });
}
