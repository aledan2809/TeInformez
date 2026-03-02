import type { Metadata } from 'next';
import NewsListClient from './NewsListClient';

export const metadata: Metadata = {
  title: 'Știri',
  description: 'Ultimele știri personalizate de AI din România și din lume. Tehnologie, auto, finanțe, sport, politică și multe altele.',
  openGraph: {
    title: 'Știri - TeInformez.eu',
    description: 'Ultimele știri personalizate de AI din România și din lume.',
    type: 'website',
  },
  alternates: {
    canonical: '/news',
  },
};

export default function NewsPage() {
  return <NewsListClient />;
}
