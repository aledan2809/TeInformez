'use client';

import { useState, useRef, useEffect } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { CATEGORIES } from '@/lib/categories';

interface CategoryNavBarProps {
  activeSections?: string[];
}

export default function CategoryNavBar({ activeSections }: CategoryNavBarProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);

  const navCategories = CATEGORIES.filter(c => c.slug !== '' && !c.hidden);

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
  }, []);

  const scroll = (dir: 'left' | 'right') => {
    if (!scrollRef.current) return;
    scrollRef.current.scrollBy({ left: dir === 'left' ? -200 : 200, behavior: 'smooth' });
    setTimeout(updateScrollButtons, 300);
  };

  return (
    <div className="bg-white dark:bg-gray-900 border-b sticky top-14 z-40">
      <div className="container-custom relative">
        {/* Scroll buttons */}
        {canScrollLeft && (
          <button
            onClick={() => scroll('left')}
            className="absolute left-0 top-1/2 -translate-y-1/2 z-10 bg-white dark:bg-gray-900 shadow-md rounded-full p-1"
          >
            <ChevronLeft className="h-4 w-4" />
          </button>
        )}
        {canScrollRight && (
          <button
            onClick={() => scroll('right')}
            className="absolute right-0 top-1/2 -translate-y-1/2 z-10 bg-white dark:bg-gray-900 shadow-md rounded-full p-1"
          >
            <ChevronRight className="h-4 w-4" />
          </button>
        )}

        {/* Scrollable pills */}
        <div
          ref={scrollRef}
          onScroll={updateScrollButtons}
          className="flex items-center gap-1 overflow-x-auto scrollbar-hide py-2 px-1"
        >
          {navCategories.map((cat) => {
            const hasArticles = !activeSections || activeSections.includes(cat.slug);

            // Juridic links to its own section
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
                    // Category not on homepage, go to news filtered
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
