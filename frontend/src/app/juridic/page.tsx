import type { Metadata } from 'next';
import SharedHeader from '@/components/SharedHeader';
import SharedFooter from '@/components/SharedFooter';
import JuridicList from './JuridicList';

const API_BASE = process.env.NEXT_PUBLIC_WP_API_URL || 'https://teinformez.eu/wp-json';
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export const metadata: Metadata = {
  title: 'Juridic — Întrebări și răspunsuri',
  description:
    'Întrebări juridice reale, cu răspunsuri pe înțelesul tuturor. Muncă, chirie, consum, familie — explicate simplu.',
  alternates: { canonical: `${SITE_URL}/juridic` },
  openGraph: {
    title: 'Juridic — Întrebări și răspunsuri | TeInformez.eu',
    description: 'Întrebări juridice reale, cu răspunsuri pe înțelesul tuturor.',
    type: 'website',
    url: `${SITE_URL}/juridic`,
    siteName: 'TeInformez.eu',
    locale: 'ro_RO',
  },
};

export interface JuridicItem {
  id: number;
  question: string;
  answer: string;
  answer_summary: string | null;
  category: string;
  subcategory: string | null;
  tags: string[];
  is_weekly_column: boolean;
  column_title: string | null;
  author_name: string;
  published_at: string;
}

async function fetchJuridic(): Promise<{ items: JuridicItem[]; total: number } | null> {
  try {
    const res = await fetch(`${API_BASE}/teinformez/v1/juridic?per_page=50`, {
      next: { revalidate: 300 },
    });
    if (!res.ok) return null;
    const json = await res.json();
    return json.data ?? null;
  } catch {
    return null;
  }
}

export default async function JuridicPage() {
  const data = await fetchJuridic();

  return (
    <div className="min-h-screen">
      <SharedHeader />
      <main className="container-custom py-8 max-w-3xl">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
          ⚖️ Juridic — întrebări și răspunsuri
        </h1>
        <p className="text-gray-600 dark:text-gray-400 mb-8">
          Întrebări reale de la cititori, cu răspunsuri pe înțelesul tuturor. Situațiile sunt
          anonimizate; răspunsurile sunt informative, nu țin loc de consultanță juridică.
        </p>
        <JuridicList initialItems={data?.items ?? []} />
      </main>
      <SharedFooter />
    </div>
  );
}
