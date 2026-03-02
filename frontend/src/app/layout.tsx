import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import { ThemeProvider } from '@/components/ThemeProvider';
import './globals.css';

const inter = Inter({ subsets: ['latin'], variable: '--font-inter' });

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: 'TeInformez.eu - Știri personalizate, livrate când vrei tu',
    template: '%s | TeInformez.eu',
  },
  description: 'Platformă de știri personalizate cu AI. Alege categoriile tale preferate și primește știri rezumate, traduse în limba dorită, direct pe email.',
  keywords: ['știri personalizate', 'news', 'AI', 'România', 'newsletter', 'email digest', 'știri ro'],
  authors: [{ name: 'TeInformez' }],
  creator: 'TeInformez',
  publisher: 'TeInformez',
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      'max-video-preview': -1,
      'max-image-preview': 'large',
      'max-snippet': -1,
    },
  },
  openGraph: {
    title: 'TeInformez.eu - Știri personalizate, livrate când vrei tu',
    description: 'Alege categoriile tale preferate și primește știri rezumate de AI, traduse în limba dorită, direct pe email.',
    type: 'website',
    locale: 'ro_RO',
    url: SITE_URL,
    siteName: 'TeInformez.eu',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'TeInformez.eu - Știri personalizate',
    description: 'Știri personalizate cu AI, livrate când vrei tu.',
  },
  alternates: {
    canonical: SITE_URL,
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ro" className={inter.variable} suppressHydrationWarning>
      <body className="min-h-screen bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <ThemeProvider>{children}</ThemeProvider>
      </body>
    </html>
  );
}
