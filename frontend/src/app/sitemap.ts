import type { MetadataRoute } from 'next';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';
const WP_API_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const API_BASE = `${WP_API_URL}/teinformez/v1`;

const NEWS_PER_PAGE = 100;
// Cap at 104 pages (~10,400 articles) to keep build time bounded
const MAX_NEWS_SITEMAPS = 104;

interface NewsApiItem {
  id: number;
  published_at: string;
}

interface JuridicApiItem {
  id: number;
  published_at: string;
}

async function fetchNewsTotalPages(): Promise<number> {
  try {
    const res = await fetch(`${API_BASE}/news?per_page=${NEWS_PER_PAGE}&page=1`, {
      next: { revalidate: 3600 },
    });
    if (!res.ok) return 1;
    const json = await res.json();
    return (json.data?.total_pages as number) ?? 1;
  } catch {
    return 1;
  }
}

async function fetchNewsPage(page: number): Promise<NewsApiItem[]> {
  try {
    const res = await fetch(`${API_BASE}/news?per_page=${NEWS_PER_PAGE}&page=${page}`, {
      next: { revalidate: 3600 },
    });
    if (!res.ok) return [];
    const json = await res.json();
    return (json.data?.news as NewsApiItem[]) ?? [];
  } catch {
    return [];
  }
}

async function fetchAllJuridicItems(): Promise<JuridicApiItem[]> {
  try {
    const res = await fetch(`${API_BASE}/juridic?per_page=100&page=1`, {
      next: { revalidate: 3600 },
    });
    if (!res.ok) return [];
    const json = await res.json();
    return (json.data?.items as JuridicApiItem[]) ?? [];
  } catch {
    return [];
  }
}

// Called at build time to determine how many sitemap files to generate.
// id=0: static pages + juridic; id=1..N: news pages.
export async function generateSitemaps() {
  const totalPages = await fetchNewsTotalPages();
  const cappedPages = Math.min(totalPages, MAX_NEWS_SITEMAPS);
  return [
    { id: 0 },
    ...Array.from({ length: cappedPages }, (_, i) => ({ id: i + 1 })),
  ];
}

export default async function sitemap({
  id,
}: {
  id: number;
}): Promise<MetadataRoute.Sitemap> {
  if (id === 0) {
    const juridicItems = await fetchAllJuridicItems();

    const staticPages: MetadataRoute.Sitemap = [
      {
        url: SITE_URL,
        lastModified: new Date(),
        changeFrequency: 'daily',
        priority: 1.0,
      },
      {
        url: `${SITE_URL}/news`,
        lastModified: new Date(),
        changeFrequency: 'hourly',
        priority: 0.9,
      },
      {
        url: `${SITE_URL}/juridic`,
        lastModified: new Date(),
        changeFrequency: 'daily',
        priority: 0.8,
      },
      {
        url: `${SITE_URL}/register`,
        lastModified: new Date(),
        changeFrequency: 'monthly',
        priority: 0.5,
      },
      {
        url: `${SITE_URL}/login`,
        lastModified: new Date(),
        changeFrequency: 'monthly',
        priority: 0.5,
      },
      {
        url: `${SITE_URL}/privacy`,
        lastModified: new Date(),
        changeFrequency: 'monthly',
        priority: 0.3,
      },
      {
        url: `${SITE_URL}/terms`,
        lastModified: new Date(),
        changeFrequency: 'monthly',
        priority: 0.3,
      },
      {
        url: `${SITE_URL}/gdpr`,
        lastModified: new Date(),
        changeFrequency: 'monthly',
        priority: 0.3,
      },
    ];

    const juridicPages: MetadataRoute.Sitemap = juridicItems.map((item) => ({
      url: `${SITE_URL}/juridic/${item.id}`,
      lastModified: new Date(item.published_at),
      changeFrequency: 'weekly' as const,
      priority: 0.6,
    }));

    return [...staticPages, ...juridicPages];
  }

  // id >= 1: news page (1-indexed)
  const newsItems = await fetchNewsPage(id);
  return newsItems.map((item) => ({
    url: `${SITE_URL}/news/${item.id}`,
    lastModified: new Date(item.published_at),
    changeFrequency: 'weekly' as const,
    priority: 0.7,
  }));
}
