import type { Metadata } from 'next';
import SharedFooter from '@/components/SharedFooter';
import JuridicListClient from './JuridicListClient';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  title: 'Juridic cu Alina - Intrebari și raspunsuri juridice',
  description: 'Raspunsuri la intrebari juridice frecvente. Dreptul muncii, dreptul familiei, drept comercial și multe altele.',
  openGraph: {
    title: 'Juridic cu Alina - TeInformez.eu',
    description: 'Raspunsuri la intrebari juridice frecvente de la specialiștii noștri.',
    url: `${SITE_URL}/juridic`,
    type: 'website',
  },
};

const faqJsonLd = {
  '@context': 'https://schema.org',
  '@type': 'WebPage',
  name: 'Juridic cu Alina',
  description: 'Raspunsuri la intrebari juridice frecvente',
  url: `${SITE_URL}/juridic`,
  publisher: {
    '@type': 'Organization',
    name: 'TeInformez.eu',
  },
};

export default function JuridicPage() {
  return (
    <div className="min-h-screen">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(faqJsonLd) }}
      />
      <JuridicListClient />
      <SharedFooter />
    </div>
  );
}
