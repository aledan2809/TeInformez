import type { Metadata } from 'next';
import SharedFooter from '@/components/SharedFooter';
import { JsonLd } from '@/components/JsonLd';
import JuridicListClient from './JuridicListClient';

const API_BASE = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  title: 'Juridic cu Alina — Întrebări și răspunsuri juridice',
  description: 'Răspunsuri la întrebări juridice frecvente. Dreptul muncii, dreptul familiei, drept comercial și multe altele.',
  openGraph: {
    title: 'Juridic cu Alina — TeInformez.eu',
    description: 'Răspunsuri la întrebări juridice frecvente de la specialiștii noștri.',
    url: `${SITE_URL}/juridic`,
    type: 'website',
    siteName: 'TeInformez.eu',
    locale: 'ro_RO',
    images: [{ url: '/api/og', width: 1200, height: 630, alt: 'Juridic cu Alina — TeInformez.eu' }],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Juridic cu Alina — TeInformez.eu',
    description: 'Răspunsuri la întrebări juridice frecvente de la specialiștii noștri.',
    images: ['/api/og'],
  },
  alternates: {
    canonical: `${SITE_URL}/juridic`,
  },
};

async function fetchTopItems() {
  try {
    const res = await fetch(`${API_BASE}/teinformez/v1/juridic?per_page=5`, {
      next: { revalidate: 3600 },
    });
    if (!res.ok) return [];
    const json = await res.json();
    return (json.data?.items ?? []) as Array<{ question: string; answer_summary?: string; answer?: string }>;
  } catch {
    return [];
  }
}

export default async function JuridicPage() {
  const topItems = await fetchTopItems();

  const faqJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    name: 'Juridic cu Alina',
    description: 'Răspunsuri la întrebări juridice frecvente',
    url: `${SITE_URL}/juridic`,
    publisher: {
      '@type': 'Organization',
      name: 'TeInformez.eu',
      url: SITE_URL,
    },
    breadcrumb: {
      '@type': 'BreadcrumbList',
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'Acasa', item: SITE_URL },
        { '@type': 'ListItem', position: 2, name: 'Juridic', item: `${SITE_URL}/juridic` },
      ],
    },
    ...(topItems.length > 0 && {
      mainEntity: topItems.map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: {
          '@type': 'Answer',
          text: item.answer_summary || (item.answer || '').replace(/<[^>]*>/g, '').slice(0, 300),
        },
      })),
    }),
  };

  return (
    <div className="min-h-screen">
      <JsonLd data={faqJsonLd} />
      <JuridicListClient />
      <SharedFooter />
    </div>
  );
}
