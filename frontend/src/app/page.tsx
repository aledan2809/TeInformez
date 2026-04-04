import SharedFooter from '@/components/SharedFooter';
import HomeClient from './HomeClient';

const API_BASE = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

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
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(organizationJsonLd) }}
      />
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(websiteJsonLd) }}
      />

      <HomeClient
        hero={data?.hero}
        sections={data?.sections || []}
      />

      <SharedFooter />
    </div>
  );
}
