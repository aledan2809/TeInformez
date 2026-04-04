import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import { ThemeProvider } from '@/components/ThemeProvider';
import GoogleAnalytics from '@/components/GoogleAnalytics';
import './globals.css';

const inter = Inter({ subsets: ['latin'], variable: '--font-inter' });

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: 'TeInformez.eu - Știri din Romania și din lume',
    template: '%s | TeInformez.eu',
  },
  description: 'Știri din Romania și din lume. Actualitate, politică, internațional, business, tehnologie, sport și multe altele.',
  keywords: ['știri', 'news', 'România', 'actualitate', 'politică', 'business', 'tehnologie', 'sport', 'știri ro'],
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
    title: 'TeInformez.eu - Știri din Romania și din lume',
    description: 'Știri din Romania și din lume. Actualitate, politică, internațional, business, tehnologie, sport.',
    type: 'website',
    locale: 'ro_RO',
    url: SITE_URL,
    siteName: 'TeInformez.eu',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'TeInformez.eu - Știri personalizate',
    description: 'Știri personalizate, livrate când vrei tu.',
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
        <GoogleAnalytics />
        <ThemeProvider>{children}</ThemeProvider>
      </body>
    </html>
  );
}
