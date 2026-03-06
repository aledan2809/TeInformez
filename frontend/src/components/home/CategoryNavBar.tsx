'use client';

import { useState, useRef, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { CATEGORIES, CategoryDef } from '@/lib/categories';
import { useAuthStore } from '@/store/authStore';
import { api } from '@/lib/api';

interface CategoryNavBarProps {
  activeSections?: string[];
}

const allNavCats = CATEGORIES.filter(c => c.slug !== '' && !c.hidden);

function sortByAdminOrder(cats: CategoryDef[], order: string[]): CategoryDef[] {
  if (!order.length) return cats;
  const orderMap = new Map(order.map((slug, i) => [slug, i]));
  return [...cats].sort((a, b) => {
    const ia = orderMap.has(a.slug) ? (orderMap.get(a.slug) as number) : 999;
    const ib = orderMap.has(b.slug) ? (orderMap.get(b.slug) as number) : 999;
    return ia - ib;
  });
}

export default function CategoryNavBar({ activeSections }: CategoryNavBarProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);
  const [orderedCategories, setOrderedCategories] = useState<CategoryDef[]>(allNavCats);
  const { isAuthenticated } = useAuthStore();

  useEffect(() => {
    const loadCategories = async () => {
      // 1. Fetch admin order
      let adminOrder: string[] = [];
      try {
        adminOrder = await api.getCategoryOrder();
      } catch {}

      // 2. For logged users, fetch their subscribed categories
      let userCategorySlugs: Set<string> | null = null;
      if (isAuthenticated) {
        try {
          const subs = await api.getSubscriptions();
          const activeSlugs = subs.filter(s => s.is_active).map(s => s.category_slug);
          if (activeSlugs.length > 0) {
            userCategorySlugs = new Set(activeSlugs);
          }
        } catch {}
      }

      // 3. Filter then sort
      let cats = allNavCats;
      if (userCategorySlugs) {
        cats = allNavCats.filter(c => userCategorySlugs!.has(c.slug));
      }
      cats = sortByAdminOrder(cats, adminOrder);

      setOrderedCategories(cats);
    };

    loadCategories();
  }, [isAuthenticated]);

  const updateScrollButtons = useCallback(() => {
    const el = scrollRef.current;
    if (!el) return;
    setCanScrollLeft(el.scrollLeft > 2);
    setCanScrollRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 2);
  }, []);

  useEffect(() => {
    requestAnimationFrame(() => updateScrollButtons());
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
        {canScrollLeft && (
          <button
            onClick={() => scroll('left')}
            className="flex-shrink-0 mr-1 bg-white dark:bg-gray-800 shadow border border-gray-200 dark:border-gray-600 rounded-full p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            aria-label="Scroll stânga"
          >
            <ChevronLeft className="h-4 w-4 text-gray-600 dark:text-gray-300" />
          </button>
        )}

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
