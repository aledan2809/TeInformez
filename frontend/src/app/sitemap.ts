import type { MetadataRoute } from 'next';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';
const WP_API_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const API_BASE = `${WP_API_URL}/teinformez/v1`;

interface NewsApiItem {
  id: number;
  published_at: string;
}

interface NewsApiResponse {
  data?: {
    news?: NewsApiItem[];
  };
}

interface JuridicApiItem {
  id: number;
  published_at: string;
}

interface JuridicApiResponse {
  data?: {
    items?: JuridicApiItem[];
  };
}

async function fetchPublishedNews(): Promise<NewsApiItem[]> {
  try {
    const res = await fetch(`${API_BASE}/news?per_page=50&page=1`, {
      next: { revalidate: 3600 },
    });
    if (!res.ok) return [];
    const json = (await res.json()) as NewsApiResponse;
    return json.data?.news ?? [];
  } catch {
    return [];
  }
}

async function fetchJuridicItems(): Promise<JuridicApiItem[]> {
  try {
    const res = await fetch(`${API_BASE}/juridic?per_page=50&page=1`, {
      next: { revalidate: 3600 },
    });
    if (!res.ok) return [];
    const json = (await res.json()) as JuridicApiResponse;
    return json.data?.items ?? [];
  } catch {
    return [];
  }
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [newsItems, juridicItems] = await Promise.all([
    fetchPublishedNews(),
    fetchJuridicItems(),
  ]);

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

  const newsPages: MetadataRoute.Sitemap = newsItems.map((item) => ({
    url: `${SITE_URL}/news/${item.id}`,
    lastModified: new Date(item.published_at),
    changeFrequency: 'weekly' as const,
    priority: 0.7,
  }));

  const juridicPages: MetadataRoute.Sitemap = juridicItems.map((item) => ({
    url: `${SITE_URL}/juridic/${item.id}`,
    lastModified: new Date(item.published_at),
    changeFrequency: 'weekly' as const,
    priority: 0.6,
  }));

  return [...staticPages, ...newsPages, ...juridicPages];
}
