'use client';

import { useState, useRef, useEffect } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight, ChevronDown } from 'lucide-react';
import { CATEGORIES, type CategoryDef } from '@/lib/categories';

interface CategoryNavBarProps {
  activeSections?: string[];
}

export default function CategoryNavBar({ activeSections }: CategoryNavBarProps) {
  const [expanded, setExpanded] = useState<string | null>(null);
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

  const toggleSubcategories = (slug: string) => {
    setExpanded(expanded === slug ? null : slug);
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
            return (
              <button
                key={cat.slug}
                onClick={() => {
                  if (cat.subcategories && cat.subcategories.length > 0) {
                    toggleSubcategories(cat.slug);
                  } else {
                    const el = document.getElementById(`cat-${cat.slug}`);
                    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                  }
                }}
                className={`flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors ${
                  hasArticles
                    ? 'bg-gray-100 dark:bg-gray-800 hover:bg-primary-100 dark:hover:bg-primary-900/30 text-gray-700 dark:text-gray-300'
                    : 'bg-gray-50 dark:bg-gray-900 text-gray-400 dark:text-gray-600'
                } ${expanded === cat.slug ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : ''}`}
              >
                <span>{cat.emoji}</span>
                <span>{cat.label}</span>
                {cat.subcategories && cat.subcategories.length > 0 && (
                  <ChevronDown className={`h-3 w-3 transition-transform ${expanded === cat.slug ? 'rotate-180' : ''}`} />
                )}
              </button>
            );
          })}
        </div>

        {/* Expanded subcategories */}
        {expanded && (
          <SubcategoryDropdown
            category={navCategories.find(c => c.slug === expanded)!}
            onClose={() => setExpanded(null)}
          />
        )}
      </div>
    </div>
  );
}

function SubcategoryDropdown({ category, onClose }: { category: CategoryDef; onClose: () => void }) {
  if (!category.subcategories || category.subcategories.length === 0) return null;

  return (
    <div className="pb-2 flex flex-wrap gap-1.5">
      {category.subcategories.map((sub) => (
        <Link
          key={sub}
          href={`/news?category=${category.slug}&sub=${sub.toLowerCase()}`}
          onClick={onClose}
          className="px-2.5 py-1 rounded-md text-xs font-medium bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 transition-colors"
        >
          {sub}
        </Link>
      ))}
    </div>
  );
}
