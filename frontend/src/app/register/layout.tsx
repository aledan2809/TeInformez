import type { Metadata } from 'next';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  title: 'Înregistrare gratuită',
  description: 'Creează un cont gratuit pe TeInformez.eu și primești știri personalizate, livrate pe email.',
  openGraph: {
    title: 'Înregistrare gratuită - TeInformez.eu',
    description: 'Creează un cont gratuit și primești știri personalizate.',
    url: `${SITE_URL}/register`,
    siteName: 'TeInformez.eu',
    locale: 'ro_RO',
  },
  twitter: {
    card: 'summary',
    title: 'Înregistrare gratuită - TeInformez.eu',
    description: 'Creează un cont gratuit și primești știri personalizate.',
  },
  alternates: {
    canonical: `${SITE_URL}/register`,
  },
};

export default function RegisterLayout({ children }: { children: React.ReactNode }) {
  return children;
}
