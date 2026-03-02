import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Autentificare',
  description: 'Autentifică-te în contul tău TeInformez pentru a accesa știrile personalizate.',
  robots: { index: false, follow: true },
};

export default function LoginLayout({ children }: { children: React.ReactNode }) {
  return children;
}
