'use client';

import { Fragment, useMemo, useState } from 'react';
import { ChevronDown, ChevronUp, Scale } from 'lucide-react';
import type { JuridicItem } from './page';
import InFeedAd from '@/components/home/InFeedAd';

/**
 * Client list for the public Juridic Q&A page: category filter chips +
 * per-item expand (question + summary always visible, full answer on demand).
 */
export default function JuridicList({ initialItems }: { initialItems: JuridicItem[] }) {
  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);

  const categories = useMemo(() => {
    const set = new Set(initialItems.map((i) => i.category).filter(Boolean));
    return [...set].sort((a, b) => a.localeCompare(b, 'ro'));
  }, [initialItems]);

  const items = useMemo(
    () => (activeCategory ? initialItems.filter((i) => i.category === activeCategory) : initialItems),
    [initialItems, activeCategory]
  );

  if (initialItems.length === 0) {
    return (
      <div className="text-center py-16 text-gray-500 dark:text-gray-400">
        <Scale className="h-12 w-12 mx-auto mb-4 opacity-50" />
        <p>Momentan nu sunt întrebări publicate. Revino în curând.</p>
      </div>
    );
  }

  return (
    <>
      {categories.length > 1 && (
        <div className="flex flex-wrap gap-2 mb-6">
          <button
            onClick={() => setActiveCategory(null)}
            className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
              activeCategory === null
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
            }`}
          >
            Toate
          </button>
          {categories.map((cat) => (
            <button
              key={cat}
              onClick={() => setActiveCategory(cat)}
              className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                activeCategory === cat
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>
      )}

      <div className="space-y-4">
        {items.map((item, i) => {
          const isOpen = openId === item.id;
          return (
            <Fragment key={item.id}>
            <article
              className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5"
            >
              <div className="flex items-start gap-2 mb-1">
                <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300">
                  {item.category}
                </span>
                {item.is_weekly_column && item.column_title && (
                  <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300">
                    {item.column_title}
                  </span>
                )}
              </div>
              <h2 className="font-semibold text-gray-900 dark:text-white leading-snug">
                {item.question}
              </h2>
              {item.answer_summary && (
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{item.answer_summary}</p>
              )}

              {isOpen && (
                <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                  {item.answer}
                  <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">
                    Răspuns de {item.author_name} · informativ, nu ține loc de consultanță juridică
                  </p>
                </div>
              )}

              <button
                onClick={() => setOpenId(isOpen ? null : item.id)}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
              >
                {isOpen ? (
                  <>Ascunde răspunsul <ChevronUp className="h-4 w-4" /></>
                ) : (
                  <>Citește răspunsul complet <ChevronDown className="h-4 w-4" /></>
                )}
              </button>
            </article>
            {/* Slot CAS după fiecare al 4-lea răspuns — același mecanism ca pe /news
                (proxy WP server-to-server, deci fără cheie în bundle-ul din browser). */}
            {(i + 1) % 4 === 0 && i < items.length - 1 ? <InFeedAd index={Math.floor(i / 4)} /> : null}
            </Fragment>
          );
        })}
      </div>
    </>
  );
}
