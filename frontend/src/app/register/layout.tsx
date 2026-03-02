import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Înregistrare gratuită',
  description: 'Creează un cont gratuit pe TeInformez.eu și primește știri personalizate cu AI, livrate pe email.',
  openGraph: {
    title: 'Înregistrare gratuită - TeInformez.eu',
    description: 'Creează un cont gratuit și primește știri personalizate cu AI.',
  },
  alternates: {
    canonical: '/register',
  },
};

export default function RegisterLayout({ children }: { children: React.ReactNode }) {
  return children;
}
