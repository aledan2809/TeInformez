import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Înregistrare gratuită',
  description: 'Creează un cont gratuit pe TeInformez.eu și primește știri personalizate, livrate pe email.',
  openGraph: {
    title: 'Înregistrare gratuită - TeInformez.eu',
    description: 'Creează un cont gratuit și primește știri personalizate.',
  },
  alternates: {
    canonical: '/register',
  },
};

export default function RegisterLayout({ children }: { children: React.ReactNode }) {
  return children;
}
