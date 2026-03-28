'use client';

import { useEffect } from 'react';
import SharedHeader from '@/components/SharedHeader';
import CategoryNavBar from '@/components/home/CategoryNavBar';
import HeroArticle from '@/components/home/HeroArticle';
import CategorySection from '@/components/home/CategorySection';
import Link from 'next/link';
import { Newspaper } from 'lucide-react';
import { createTimeSpentTracker, trackPageView } from '@/lib/visitorAnalytics';

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
            <p className="text-lg">Se incarca știrile...</p>
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

        {/* Newsletter CTA for non-logged */}
        <section className="my-8 text-center py-10 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800">
          <h3 className="text-xl font-bold mb-2">Primește știri pe email</h3>
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-4 max-w-md mx-auto">
            Aboneaza-te gratuit și primește cele mai importante știri direct in inbox.
          </p>
          <Link href="/register" className="btn-primary px-6 py-2.5">
            Inscrie-te gratuit
          </Link>
        </section>
      </main>
    </>
  );
}
