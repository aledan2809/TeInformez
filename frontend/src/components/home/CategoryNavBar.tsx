'use client';

import { useState, useRef, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { CATEGORIES, CategoryDef } from '@/lib/categories';

interface CategoryNavBarProps {
  activeSections?: string[];
}

const defaultNavCats = CATEGORIES.filter(c => c.slug !== '' && !c.hidden);

export default function CategoryNavBar({ activeSections }: CategoryNavBarProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);
  const [orderedCategories, setOrderedCategories] = useState<CategoryDef[]>(defaultNavCats);

  // Fetch custom order from API (non-blocking, enhances default)
  useEffect(() => {
    const apiUrl = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
    const secureUrl = typeof window !== 'undefined' && !apiUrl.includes('localhost')
      ? apiUrl.replace(/^http:/, 'https:')
      : apiUrl;

    fetch(`${secureUrl}/teinformez/v1/settings/category-order`)
      .then(res => res.ok ? res.json() : null)
      .then(data => {
        if (data?.data?.order && Array.isArray(data.data.order) && data.data.order.length > 0) {
          const orderMap = new Map(data.data.order.map((slug: string, i: number) => [slug, i]));
          const sorted = [...defaultNavCats].sort((a, b) => {
            const ia = orderMap.has(a.slug) ? (orderMap.get(a.slug) as number) : 999;
            const ib = orderMap.has(b.slug) ? (orderMap.get(b.slug) as number) : 999;
            return ia - ib;
          });
          setOrderedCategories(sorted);
        }
      })
      .catch(() => {});
  }, []);

  const updateScrollButtons = useCallback(() => {
    const el = scrollRef.current;
    if (!el) return;
    setCanScrollLeft(el.scrollLeft > 2);
    setCanScrollRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 2);
  }, []);

  // Re-check scroll after render and on resize
  useEffect(() => {
    // Wait for DOM paint
    requestAnimationFrame(() => {
      updateScrollButtons();
    });
    window.addEventListener('resize', updateScrollButtons);
    return () => window.removeEventListener('resize', updateScrollButtons);
  }, [orderedCategories, updateScrollButtons]);

  const scroll = (dir: 'left' | 'right') => {
    if (!scrollRef.current) return;
    scrollRef.current.scrollBy({ left: dir === 'left' ? -250 : 250, behavior: 'smooth' });
    setTimeout(updateScrollButtons, 350);
  };

  return (
    <div className="bg-white dark:bg-gray-900 border-b sticky top-14 z-40">
      <div className="container-custom relative flex items-center">
        {/* Left scroll button */}
        {canScrollLeft && (
          <button
            onClick={() => scroll('left')}
            className="flex-shrink-0 mr-1 bg-white dark:bg-gray-800 shadow border border-gray-200 dark:border-gray-600 rounded-full p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            aria-label="Scroll stânga"
          >
            <ChevronLeft className="h-4 w-4 text-gray-600 dark:text-gray-300" />
          </button>
        )}

        {/* Scrollable pills */}
        <div
          ref={scrollRef}
          onScroll={updateScrollButtons}
          className="flex-1 flex items-center gap-1.5 overflow-x-auto scrollbar-hide py-2.5 scroll-smooth min-w-0"
        >
          {orderedCategories.map((cat) => {
            const hasArticles = !activeSections || activeSections.includes(cat.slug);

            if (cat.slug === 'juridic') {
              return (
                <Link
                  key={cat.slug}
                  href="/juridic"
                  className="flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors bg-gray-100 dark:bg-gray-800 hover:bg-primary-100 dark:hover:bg-primary-900/30 text-gray-700 dark:text-gray-300"
                >
                  <span>{cat.emoji}</span>
                  <span>{cat.label}</span>
                </Link>
              );
            }

            return (
              <button
                key={cat.slug}
                onClick={() => {
                  const el = document.getElementById(`cat-${cat.slug}`);
                  if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                  } else {
                    window.location.href = `/news?category=${cat.slug}`;
                  }
                }}
                className={`flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors ${
                  hasArticles
                    ? 'bg-gray-100 dark:bg-gray-800 hover:bg-primary-100 dark:hover:bg-primary-900/30 text-gray-700 dark:text-gray-300'
                    : 'bg-gray-50 dark:bg-gray-900 text-gray-400 dark:text-gray-600'
                }`}
              >
                <span>{cat.emoji}</span>
                <span>{cat.label}</span>
              </button>
            );
          })}
        </div>

        {/* Right scroll button */}
        {canScrollRight && (
          <button
            onClick={() => scroll('right')}
            className="flex-shrink-0 ml-1 bg-white dark:bg-gray-800 shadow border border-gray-200 dark:border-gray-600 rounded-full p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            aria-label="Scroll dreapta"
          >
            <ChevronRight className="h-4 w-4 text-gray-600 dark:text-gray-300" />
          </button>
        )}
      </div>
    </div>
  );
}
