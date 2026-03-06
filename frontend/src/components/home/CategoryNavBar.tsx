'use client';

import { useState, useRef, useEffect } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { CATEGORIES, CategoryDef } from '@/lib/categories';

interface CategoryNavBarProps {
  activeSections?: string[];
}

export default function CategoryNavBar({ activeSections }: CategoryNavBarProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);
  const [orderedCategories, setOrderedCategories] = useState<CategoryDef[]>([]);

  useEffect(() => {
    const navCats = CATEGORIES.filter(c => c.slug !== '' && !c.hidden);

    // Fetch custom order from API
    const apiUrl = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
    fetch(`${apiUrl.replace(/^http:/, typeof window !== 'undefined' && !apiUrl.includes('localhost') ? 'https:' : 'http:')}/teinformez/v1/settings/category-order`)
      .then(res => res.ok ? res.json() : null)
      .then(data => {
        if (data?.data?.order && Array.isArray(data.data.order) && data.data.order.length > 0) {
          const orderMap = new Map(data.data.order.map((slug: string, i: number) => [slug, i]));
          const sorted = [...navCats].sort((a, b) => {
            const ia = orderMap.has(a.slug) ? (orderMap.get(a.slug) as number) : 999;
            const ib = orderMap.has(b.slug) ? (orderMap.get(b.slug) as number) : 999;
            return ia - ib;
          });
          setOrderedCategories(sorted);
        } else {
          setOrderedCategories(navCats);
        }
      })
      .catch(() => setOrderedCategories(navCats));
  }, []);

  const updateScrollButtons = () => {
    if (!scrollRef.current) return;
    const { scrollLeft, scrollWidth, clientWidth } = scrollRef.current;
    setCanScrollLeft(scrollLeft > 0);
    setCanScrollRight(scrollLeft + clientWidth < scrollWidth - 2);
  };

  useEffect(() => {
    updateScrollButtons();
    window.addEventListener('resize', updateScrollButtons);
    return () => window.removeEventListener('resize', updateScrollButtons);
  }, [orderedCategories]);

  const scroll = (dir: 'left' | 'right') => {
    if (!scrollRef.current) return;
    scrollRef.current.scrollBy({ left: dir === 'left' ? -200 : 200, behavior: 'smooth' });
    setTimeout(updateScrollButtons, 300);
  };

  return (
    <div className="bg-white dark:bg-gray-900 border-b sticky top-14 z-40">
      <div className="container-custom relative">
        {/* Left fade + scroll button */}
        {canScrollLeft && (
          <>
            <div className="absolute left-0 top-0 bottom-0 w-12 bg-gradient-to-r from-white dark:from-gray-900 to-transparent z-10 pointer-events-none" />
            <button
              onClick={() => scroll('left')}
              className="absolute left-1 top-1/2 -translate-y-1/2 z-20 bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-600 rounded-full p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
              <ChevronLeft className="h-4 w-4 text-gray-600 dark:text-gray-300" />
            </button>
          </>
        )}
        {/* Right fade + scroll button */}
        {canScrollRight && (
          <>
            <div className="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-l from-white dark:from-gray-900 to-transparent z-10 pointer-events-none" />
            <button
              onClick={() => scroll('right')}
              className="absolute right-1 top-1/2 -translate-y-1/2 z-20 bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-600 rounded-full p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
              <ChevronRight className="h-4 w-4 text-gray-600 dark:text-gray-300" />
            </button>
          </>
        )}

        {/* Scrollable pills */}
        <div
          ref={scrollRef}
          onScroll={updateScrollButtons}
          className="flex items-center gap-1 overflow-x-auto scrollbar-hide py-2 px-1 scroll-smooth"
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

      </div>
    </div>
  );
}
