'use client';

import { useEffect } from 'react';
import SharedHeader from '@/components/SharedHeader';
import CategoryNavBar from '@/components/home/CategoryNavBar';
import HeroArticle from '@/components/home/HeroArticle';
import CategorySection from '@/components/home/CategorySection';
import Link from 'next/link';
import { Newspaper } from 'lucide-react';
import { createTimeSpentTracker, trackPageView } from '@/lib/visitorAnalytics';
import { useAuthStore } from '@/store/authStore';

interface Article {
  id: number;
  title: string;
  summary: string;
  image: string | null;
  image_source: string | null;
  source: string;
  categories: string[];
  published_at: string;
}

interface Section {
  slug: string;
  label: string;
  emoji: string;
  articles: Article[];
}

interface HomeClientProps {
  hero: Article | null;
  sections: Section[];
}

export default function HomeClient({ hero, sections }: HomeClientProps) {
  const activeSlugs = sections.map(s => s.slug);
  const isAuthenticated = useAuthStore(s => s.isAuthenticated);

  useEffect(() => {
    trackPageView('home');
    const flushTimeSpent = createTimeSpentTracker('home');
    return () => {
      flushTimeSpent();
    };
  }, []);

  return (
    <>
      <SharedHeader />
      <CategoryNavBar activeSections={activeSlugs} />

      <main className="container-custom py-6">
        {/* Value proposition banner — hidden for logged-in users */}
        {!isAuthenticated && (
          <div className="mb-6 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 px-5 py-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div className="flex-1">
                <p className="text-base font-bold text-primary-800 dark:text-primary-200 leading-snug">
                  Știri din România, rezumate de AI. Zero zgomot.
                </p>
                <p className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                  Selectăm zilnic știrile care contează din 10 surse românești și internaționale — și le trimitem pe email, personalizat pe categoriile tale.
                </p>
                <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">✓ Gratuit</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">✓ Fără reclame</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">✓ Anulezi oricând</span>
                </div>
              </div>
              <Link href="/register" className="btn-primary text-sm px-5 py-2.5 whitespace-nowrap self-start sm:self-auto">
                Înregistrare gratuită →
              </Link>
            </div>
          </div>
        )}

        {/* Hero */}
        {hero ? (
          <HeroArticle
            id={hero.id}
            title={hero.title}
            summary={hero.summary}
            image={hero.image}
            imageSource={hero.image_source}
            source={hero.source}
            categories={hero.categories}
            published_at={hero.published_at}
          />
        ) : (
          <div className="text-center py-20 text-gray-500 dark:text-gray-400">
            <Newspaper className="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p className="text-lg">Se incarca stirile...</p>
          </div>
        )}

        {/* Category sections */}
        {sections.map((section) => (
          <div key={section.slug} id={`cat-${section.slug}`}>
            <CategorySection
              slug={section.slug}
              label={section.label}
              emoji={section.emoji}
              articles={section.articles}
            />
          </div>
        ))}

        {/* Newsletter CTA — hidden for logged-in users */}
        {!isAuthenticated && (
          <section className="my-8 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 px-6 py-10 text-center">
            <h3 className="text-xl font-bold mb-2 text-gray-900 dark:text-gray-100">
              Vrei să primești doar știrile care contează?
            </h3>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
              Îți creezi un cont gratuit, alegi categoriile și primești zilnic un digest cu cele mai relevante știri, rezumate de AI.
            </p>
            <div className="flex justify-center gap-8 mb-6 text-sm text-gray-600 dark:text-gray-400">
              <span className="flex flex-col items-center gap-1">
                <span className="text-xl">📰</span>
                <span>Selecție AI<br/>zilnică</span>
              </span>
              <span className="flex flex-col items-center gap-1">
                <span className="text-xl">📧</span>
                <span>Email<br/>personalizat</span>
              </span>
              <span className="flex flex-col items-center gap-1">
                <span className="text-xl">🚫</span>
                <span>Zero<br/>reclame</span>
              </span>
            </div>
            <Link href="/register" className="btn-primary px-7 py-3 text-sm font-semibold">
              Înregistrare gratuită — 2 minute
            </Link>
          </section>
        )}
      </main>
    </>
  );
}
