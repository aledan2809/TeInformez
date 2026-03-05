'use client';

import SharedHeader from '@/components/SharedHeader';
import CategoryNavBar from '@/components/home/CategoryNavBar';
import HeroArticle from '@/components/home/HeroArticle';
import CategorySection from '@/components/home/CategorySection';
import Link from 'next/link';
import { Newspaper } from 'lucide-react';

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

        {/* Juridic promo banner */}
        <section className="my-8 rounded-xl bg-gradient-to-r from-stone-100 to-stone-50 dark:from-stone-900/30 dark:to-stone-800/20 border border-stone-200 dark:border-stone-800 p-6">
          <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div className="flex-1">
              <h3 className="text-lg font-bold flex items-center gap-2">
                <span>📋</span> Juridic cu Alina
              </h3>
              <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Raspunsuri la intrebari juridice frecvente. Dreptul muncii, dreptul familiei, drept comercial și multe altele.
              </p>
            </div>
            <Link href="/juridic" className="btn-primary text-sm px-4 py-2 whitespace-nowrap">
              Citește mai mult
            </Link>
          </div>
        </section>

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
