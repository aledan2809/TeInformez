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
  image_source: string | null;
  youtube_url: string | null;
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

  const ogImage = news.image
    ? news.image
    : `${SITE_URL}/api/og?title=${encodeURIComponent(news.title.slice(0, 100))}&source=${encodeURIComponent(news.source)}&category=${encodeURIComponent(news.categories?.[0] || '')}`;

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
      images: [{ url: ogImage, width: 1200, height: 630, alt: news.title }],
    },
    twitter: {
      card: 'summary_large_image',
      title: news.title,
      description,
      images: [ogImage],
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
          logo: {
            '@type': 'ImageObject',
            url: `${SITE_URL}/logo.svg`,
            width: 280,
            height: 60,
          },
        },
        dateModified: news.published_at,
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
      {news?.image && (
        <link
          rel="preload"
          as="image"
          href={news.image}
          fetchPriority="high"
        />
      )}
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
