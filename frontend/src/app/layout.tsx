import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import './globals.css';

const inter = Inter({ subsets: ['latin'], variable: '--font-inter' });

export const metadata: Metadata = {
  title: 'TeInformez.eu - Știri personalizate, livrate când vrei tu',
  description: 'Platformă de știri personalizate cu AI. Alege categoriile tale preferate și primește știri când vrei tu.',
  keywords: 'știri, news, personalizat, AI, România, email, newsletter',
  authors: [{ name: 'TeInformez' }],
  openGraph: {
    title: 'TeInformez.eu - Știri personalizate',
    description: 'Alege categoriile tale preferate și primește știri când vrei tu.',
    type: 'website',
    locale: 'ro_RO',
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ro" className={inter.variable}>
      <body className="min-h-screen bg-gray-50 text-gray-900 antialiased">
        {children}
      </body>
    </html>
  );
}
