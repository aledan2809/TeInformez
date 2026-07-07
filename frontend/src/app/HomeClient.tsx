'use client';

import { useEffect, useState } from 'react';
import SharedHeader from '@/components/SharedHeader';
import CategoryNavBar from '@/components/home/CategoryNavBar';
import HeroArticle from '@/components/home/HeroArticle';
import CategorySection from '@/components/home/CategorySection';
import BannerSlot from '@/components/home/BannerSlot';
import Link from 'next/link';
import { Newspaper } from 'lucide-react';
import { createTimeSpentTracker, trackPageView } from '@/lib/visitorAnalytics';
import { useAuthStore } from '@/store/authStore';
import { api } from '@/lib/api';

interface Article {
  id: number;
  title: string;
  summary: string;
  simple_explanation?: string | null;
  image: string | null;
  image_source: string | null;
  source: string;
  categories: string[];
  published_at: string;
  is_premium?: boolean;
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

export default function HomeClient({ hero: ssrHero, sections: ssrSections }: HomeClientProps) {
  const [hero, setHero] = useState<Article | null>(ssrHero);
  const [sections, setSections] = useState<Section[]>(ssrSections);
  const activeSlugs = sections.map(s => s.slug);
  const isAuthenticated = useAuthStore(s => s.isAuthenticated);

  useEffect(() => {
    trackPageView('home');
    const flushTimeSpent = createTimeSpentTracker('home');
    return () => {
      flushTimeSpent();
    };
  }, []);

  // SSR fallback: if the server-side homepage fetch returned null (e.g. ISR cached an empty
  // build-time render), recover on the client so the page never stays stuck on the spinner.
  useEffect(() => {
    if (ssrHero || (ssrSections && ssrSections.length > 0)) return;
    let cancelled = false;
    api.getHomepageData()
      .then((data) => {
        if (cancelled) return;
        if (data?.hero) setHero(data.hero as unknown as Article);
        if (data?.sections) setSections(data.sections as unknown as Section[]);
      })
      .catch(() => { /* keep spinner; ISR will revalidate */ });
    return () => { cancelled = true; };
  }, [ssrHero, ssrSections]);

  return (
    <>
      <SharedHeader />
      <CategoryNavBar activeSections={activeSlugs} />

      <main className="container-custom py-6">
        {/* Page h1 (SEO/a11y) — visually hidden; article titles stay h3, section labels h2 */}
        <h1 className="sr-only">TeInformez — știri din România și din lume, explicate pe scurt</h1>

        {/* Quick-start band (WI-4) — proactive guidance instead of a static value prop; hidden for logged-in users.
            Single signup path: every CTA on this page leads to /register. */}
        {!isAuthenticated && (
          <div className="cta-card-glow mb-6 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 px-5 py-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div className="flex-1">
                <p className="text-base font-bold text-primary-800 dark:text-primary-200 leading-snug">
                  ⚡ Pornește în 20 de secunde
                </p>
                <ol className="flex flex-col sm:flex-row flex-wrap gap-y-1 gap-x-5 mt-2 text-sm text-gray-700 dark:text-gray-300">
                  <li className="flex items-center gap-1.5">
                    <span className="flex-shrink-0 w-5 h-5 rounded-full bg-primary-600 text-white text-xs font-bold flex items-center justify-center">1</span>
                    Îți faci cont gratuit
                  </li>
                  <li className="flex items-center gap-1.5">
                    <span className="flex-shrink-0 w-5 h-5 rounded-full bg-primary-600 text-white text-xs font-bold flex items-center justify-center">2</span>
                    Alegi categoriile care te interesează
                  </li>
                  <li className="flex items-center gap-1.5">
                    <span className="flex-shrink-0 w-5 h-5 rounded-full bg-primary-600 text-white text-xs font-bold flex items-center justify-center">3</span>
                    Primești zilnic esențialul pe email
                  </li>
                </ol>
                <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2">
                  <span className="text-xs text-gray-500 dark:text-gray-400">✓ Gratuit</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">✓ 10 surse verificate</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">✓ Anulezi oricând</span>
                </div>
              </div>
              <Link href="/register" className="cta-pulse btn-primary text-sm px-5 py-2.5 whitespace-nowrap self-start sm:self-auto font-semibold">
                Începe acum →
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

        {/* Category sections — CAS banner injected after sections 2, 5, 8 (idx 1, 4, 7).
            Each slot rotates independently across the active CAS inventory. */}
        {sections.map((section, idx) => (
          <div key={section.slug}>
            <div id={`cat-${section.slug}`}>
              <CategorySection
                slug={section.slug}
                label={section.label}
                emoji={section.emoji}
                articles={section.articles}
              />
            </div>
            {[1, 4, 7].includes(idx) && sections.length > idx + 1 ? <BannerSlot index={[1, 4, 7].indexOf(idx)} /> : null}
          </div>
        ))}

        {/* Newsletter CTA — hidden for logged-in users */}
        {!isAuthenticated && (
          <section className="my-8 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 px-6 py-10 text-center">
            <h3 className="text-xl font-bold mb-2 text-gray-900 dark:text-gray-100">
              Vrei să primești doar știrile care contează?
            </h3>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
              Îți creezi un cont gratuit, alegi categoriile și primești zilnic un digest cu cele mai relevante știri, rezumate pe scurt.
            </p>
            <div className="flex justify-center gap-8 mb-6 text-sm text-gray-600 dark:text-gray-400">
              <span className="flex flex-col items-center gap-1">
                <span className="text-xl">📰</span>
                <span>Selecție<br/>zilnică</span>
              </span>
              <span className="flex flex-col items-center gap-1">
                <span className="text-xl">📧</span>
                <span>Email<br/>personalizat</span>
              </span>
              <span className="flex flex-col items-center gap-1">
                <span className="text-xl">🇷🇴</span>
                <span>Știri<br/>românești</span>
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
