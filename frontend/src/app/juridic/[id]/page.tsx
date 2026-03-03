import type { Metadata } from 'next';
import SharedFooter from '@/components/SharedFooter';
import JuridicDetailClient from './JuridicDetailClient';

const API_BASE = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

async function fetchItem(id: string) {
  try {
    const res = await fetch(`${API_BASE}/teinformez/v1/juridic/${id}`, { next: { revalidate: 300 } });
    if (!res.ok) return null;
    const json = await res.json();
    return json.data?.item;
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: { params: { id: string } }): Promise<Metadata> {
  const item = await fetchItem(params.id);
  if (!item) {
    return { title: 'Intrebare juridica' };
  }

  const title = item.question.length > 60 ? item.question.slice(0, 57) + '...' : item.question;

  return {
    title: `${title} - Juridic cu Alina`,
    description: item.answer_summary || item.question,
    openGraph: {
      title: `${title} - Juridic cu Alina`,
      description: item.answer_summary || item.question,
      url: `${SITE_URL}/juridic/${params.id}`,
      type: 'article',
    },
  };
}

export default async function JuridicDetailPage({ params }: { params: { id: string } }) {
  const item = await fetchItem(params.id);

  // QAPage schema for SEO
  const qaJsonLd = item ? {
    '@context': 'https://schema.org',
    '@type': 'QAPage',
    mainEntity: {
      '@type': 'Question',
      name: item.question,
      text: item.question,
      answerCount: 1,
      acceptedAnswer: {
        '@type': 'Answer',
        text: item.answer_summary || (item.answer || '').replace(/<[^>]*>/g, '').slice(0, 300),
        author: {
          '@type': 'Person',
          name: item.author_name || 'Alina',
        },
        dateCreated: item.published_at,
      },
    },
  } : null;

  return (
    <div className="min-h-screen">
      {qaJsonLd && (
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(qaJsonLd) }}
        />
      )}
      <JuridicDetailClient item={item} />
      <SharedFooter />
    </div>
  );
}
