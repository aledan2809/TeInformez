import type { Metadata } from 'next';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  title: 'Abonare Newsletter',
  description: 'Abonează-te la newsletter-ul TeInformez și primește zilnic știrile care contează, selectate din cele mai importante surse.',
  alternates: {
    canonical: `${SITE_URL}/newsletter`,
  },
  openGraph: {
    title: 'Abonare Newsletter | TeInformez.eu',
    description: 'Primești zilnic cele mai relevante știri direct în inbox.',
    url: `${SITE_URL}/newsletter`,
    type: 'website',
  },
};

export default function NewsletterLayout({ children }: { children: React.ReactNode }) {
  return children;
}
