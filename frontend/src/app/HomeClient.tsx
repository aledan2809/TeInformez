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
          <div className="mb-5 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-primary-700 dark:text-primary-300">
                TeInformez — stiri din Romania si lume, sintetizate de AI
              </p>
              <p className="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                Primesti zilnic un rezumat personalizat pe email, fara reclame.
              </p>
            </div>
            <Link href="/register" className="btn-primary text-sm px-4 py-2 whitespace-nowrap self-start sm:self-auto">
              Inregistrare gratuita
            </Link>
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
          <section className="my-8 text-center py-10 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800">
            <h3 className="text-xl font-bold mb-2">Primeste un rezumat personalizat pe email</h3>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4 max-w-md mx-auto">
              Alegi categoriile care te intereseaza si primesti zilnic un digest cu cele mai importante stiri, rezumate de AI. Gratuit, fara reclame.
            </p>
            <Link href="/register" className="btn-primary px-6 py-2.5">
              Inregistreaza-te gratuit
            </Link>
          </section>
        )}
      </main>
    </>
  );
}
