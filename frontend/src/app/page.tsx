import type { Metadata } from 'next';
import SharedFooter from '@/components/SharedFooter';
import { JsonLd } from '@/components/JsonLd';
import HomeClient from './HomeClient';

const API_BASE = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  title: 'Știri personalizate din România și din lume',
  description: 'TeInformez îți livrează zilnic știrile care contează pentru tine — selectate automat din cele mai importante surse românești și internaționale.',
  alternates: {
    canonical: SITE_URL,
  },
  openGraph: {
    title: 'TeInformez.eu — Știri personalizate',
    description: 'Știri selectate din sursele care contează. Politică, economie, tehnologie, sport — livrate zilnic pe email.',
    type: 'website',
    url: SITE_URL,
    siteName: 'TeInformez.eu',
    locale: 'ro_RO',
    images: [{ url: '/api/og', width: 1200, height: 630, alt: 'TeInformez.eu' }],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'TeInformez.eu — Știri personalizate',
    description: 'Știri selectate din sursele care contează, livrate zilnic pe email.',
    images: ['/api/og'],
  },
};

const organizationJsonLd = {
  '@context': 'https://schema.org',
  '@type': 'Organization',
  name: 'TeInformez.eu',
  url: SITE_URL,
  description: 'Știri din Romania și din lume',
  contactPoint: {
    '@type': 'ContactPoint',
    email: 'contact@teinformez.eu',
    contactType: 'customer service',
  },
};

const websiteJsonLd = {
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: 'TeInformez.eu',
  url: SITE_URL,
  description: 'Știri din Romania și din lume. Actualitate, politică, internațional, business, tehnologie, sport.',
  inLanguage: 'ro',
  publisher: {
    '@type': 'Organization',
    name: 'TeInformez.eu',
  },
};

async function fetchHomepageData() {
  try {
    const url = `${API_BASE}/teinformez/v1/news/homepage`;
    const res = await fetch(url, { next: { revalidate: 300 } });
    if (!res.ok) return null;
    const json = await res.json();
    return json.data;
  } catch {
    return null;
  }
}

export default async function HomePage() {
  const data = await fetchHomepageData();

  return (
    <div className="min-h-screen">
      <JsonLd data={organizationJsonLd} />
      <JsonLd data={websiteJsonLd} />

      <HomeClient
        hero={data?.hero}
        sections={data?.sections || []}
      />

      <SharedFooter />
    </div>
  );
}
