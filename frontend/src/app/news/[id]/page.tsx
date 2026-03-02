import type { Metadata } from 'next';
import NewsDetailClient from './NewsDetailClient';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';
const WP_API_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';

interface NewsItem {
  id: number;
  title: string;
  summary: string;
  content: string;
  image: string | null;
  source: string;
  categories: string[];
  tags: string[];
  published_at: string;
  original_url: string;
  language: string;
}

async function getNewsItem(id: string): Promise<NewsItem | null> {
  try {
    const res = await fetch(`${WP_API_URL}/teinformez/v1/news/${id}`, {
      next: { revalidate: 300 },
    });
    if (!res.ok) return null;
    const json = await res.json();
    return json.data?.news || null;
  } catch {
    return null;
  }
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ id: string }>;
}): Promise<Metadata> {
  const { id } = await params;
  const news = await getNewsItem(id);

  if (!news) {
    return {
      title: 'Știre negăsită',
      description: 'Această știre nu există sau nu este disponibilă.',
    };
  }

  const description = news.summary?.slice(0, 160) || news.title;
  const url = `${SITE_URL}/news/${news.id}`;

  return {
    title: news.title,
    description,
    keywords: [...(news.categories || []), ...(news.tags || [])],
    openGraph: {
      title: news.title,
      description,
      type: 'article',
      url,
      locale: 'ro_RO',
      siteName: 'TeInformez.eu',
      publishedTime: news.published_at,
      authors: [news.source],
      tags: news.tags,
      ...(news.image ? { images: [{ url: news.image, alt: news.title }] } : {}),
    },
    twitter: {
      card: news.image ? 'summary_large_image' : 'summary',
      title: news.title,
      description,
      ...(news.image ? { images: [news.image] } : {}),
    },
    alternates: {
      canonical: url,
    },
  };
}

export default async function NewsDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  const news = await getNewsItem(id);

  const jsonLd = news
    ? {
        '@context': 'https://schema.org',
        '@type': 'NewsArticle',
        headline: news.title,
        description: news.summary,
        image: news.image || undefined,
        datePublished: news.published_at,
        author: {
          '@type': 'Organization',
          name: news.source,
        },
        publisher: {
          '@type': 'Organization',
          name: 'TeInformez.eu',
          url: SITE_URL,
        },
        mainEntityOfPage: {
          '@type': 'WebPage',
          '@id': `${SITE_URL}/news/${news.id}`,
        },
        articleSection: news.categories?.join(', '),
        keywords: news.tags?.join(', '),
        inLanguage: news.language || 'ro',
      }
    : null;

  return (
    <>
      {jsonLd && (
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
        />
      )}
      <NewsDetailClient />
    </>
  );
}
