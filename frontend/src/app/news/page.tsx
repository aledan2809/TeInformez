import type { Metadata } from 'next';
import { Suspense } from 'react';
import NewsListClient from './NewsListClient';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  title: 'Știri',
  description: 'Ultimele știri personalizate de AI din România și din lume. Tehnologie, auto, finanțe, sport, politică și multe altele.',
  openGraph: {
    title: 'Știri - TeInformez.eu',
    description: 'Ultimele știri personalizate de AI din România și din lume.',
    type: 'website',
    url: `${SITE_URL}/news`,
    siteName: 'TeInformez.eu',
    locale: 'ro_RO',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Știri - TeInformez.eu',
    description: 'Ultimele știri personalizate de AI din România și din lume.',
  },
  alternates: {
    canonical: `${SITE_URL}/news`,
  },
};

const breadcrumbJsonLd = {
  '@context': 'https://schema.org',
  '@type': 'BreadcrumbList',
  itemListElement: [
    { '@type': 'ListItem', position: 1, name: 'Acasa', item: SITE_URL },
    { '@type': 'ListItem', position: 2, name: 'Știri', item: `${SITE_URL}/news` },
  ],
};

export default function NewsPage() {
  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(breadcrumbJsonLd) }}
      />
      <Suspense fallback={null}>
        <NewsListClient />
      </Suspense>
    </>
  );
}
