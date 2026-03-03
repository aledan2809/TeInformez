'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Newspaper, Calendar, Tag, ExternalLink, Loader2, Clock,
  Sparkles, Languages, ChevronLeft, ChevronRight,
  Search, X, Bookmark, BookmarkCheck, Flame, Trophy,
  Copy, Check,
} from 'lucide-react';
import { api } from '@/lib/api';
import { CATEGORIES, CATEGORY_COLORS as SHARED_CATEGORY_COLORS } from '@/lib/categories';
import { useBookmarkStore } from '@/store/bookmarkStore';
import { useReadingStore } from '@/store/readingStore';
import ScrollToTop from '@/components/ScrollToTop';

interface NewsItem {
  id: number;
  title: string;
  summary: string;
  content: string;
  image: string | null;
  source: string;
  categories: string[];
  tags: string[];
  published_at: string;
  original_url: string;
  language: string;
}

function estimateReadingTime(content: string): number {
  const text = content.replace(/<[^>]*>/g, '');
  const words = text.split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.ceil(words / 200));
}

// Animation variants
const cardVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: (i: number) => ({
    opacity: 1, y: 0,
    transition: { delay: i * 0.05, duration: 0.4, ease: 'easeOut' as const },
  }),
};

const heroVariants = {
  hidden: { opacity: 0, scale: 0.98 },
  visible: {
    opacity: 1, scale: 1,
    transition: { duration: 0.5, ease: 'easeOut' as const },
  },
};

export default function NewsListClient() {
  const router = useRouter();
  const tabsRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const [news, setNews] = useState<NewsItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [selectedCategory, setSelectedCategory] = useState<string>('');

  // Search state
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();

  // Bookmarks
  const { isBookmarked, toggleBookmark } = useBookmarkStore();

  // Reading streaks
  const { currentStreak, totalRead, getReadToday, markAsRead, recalculateStreak } = useReadingStore();
  const [readToday, setReadToday] = useState(0);

  // Trending
  const [trendingNews, setTrendingNews] = useState<NewsItem[]>([]);

  useEffect(() => {
    recalculateStreak();
    setReadToday(getReadToday());
    // Fetch trending (latest news for sidebar)
    api.getNews({ page: 1, per_page: 5 }).then((data) => {
      setTrendingNews(data.news || []);
    }).catch(() => {});
  }, []);

  // Debounce search
  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
      setPage(1);
    }, 400);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [searchQuery]);

  useEffect(() => {
    fetchNews();
  }, [page, selectedCategory, debouncedQuery]);

  const fetchNews = async () => {
    setLoading(true);
    setError(null);

    try {
      const params: any = { page, per_page: 20 };
      if (selectedCategory) params.category = selectedCategory;
      if (debouncedQuery.trim()) params.search = debouncedQuery.trim();

      const data = await api.getNews(params);
      setNews(data.news || []);
      setTotalPages(data.total_pages || 1);
    } catch (err: any) {
      setError(err.message || 'Eroare la încărcarea știrilor');
    } finally {
      setLoading(false);
    }
  };

  const handleCategoryChange = (slug: string) => {
    setSelectedCategory(slug);
    setPage(1);
  };

  const scrollTabs = (direction: 'left' | 'right') => {
    if (tabsRef.current) {
      tabsRef.current.scrollBy({ left: direction === 'left' ? -200 : 200, behavior: 'smooth' });
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ro-RO', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const handleArticleClick = (item: NewsItem) => {
    markAsRead(item.id);
    setReadToday(getReadToday());
    router.push(`/news/${item.id}`);
  };

  const handleToggleBookmark = (e: React.MouseEvent, item: NewsItem) => {
    e.stopPropagation();
    toggleBookmark({
      id: item.id,
      title: item.title,
      summary: item.summary,
      image: item.image,
      source: item.source,
      categories: item.categories,
      published_at: item.published_at,
      original_url: item.original_url,
    });
  };

  const toggleSearch = () => {
    setSearchOpen(!searchOpen);
    if (!searchOpen) {
      setTimeout(() => searchInputRef.current?.focus(), 100);
    } else {
      setSearchQuery('');
      setDebouncedQuery('');
    }
  };

  if (loading && news.length === 0 && !searchQuery) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center"
        >
          <Loader2 className="h-12 w-12 animate-spin text-primary-600 mx-auto mb-4" />
          <p className="text-gray-600 dark:text-gray-400">Se încarcă știrile...</p>
        </motion.div>
      </div>
    );
  }

  const heroItem = news[0];
  const featuredItems = news.slice(1, 3);
  const gridItems = news.slice(3);

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-950">
      {/* Header */}
      <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <div className="container mx-auto px-4 py-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <Newspaper className="h-8 w-8 text-primary-600" />
              <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Știri</h1>
            </div>
            <div className="flex items-center space-x-3">
              {/* Reading streak badge */}
              <ReadingStreakBadge streak={currentStreak} readToday={readToday} totalRead={totalRead} />

              {/* Bookmarks link */}
              <BookmarksButton />

              {/* Search toggle */}
              <button
                onClick={toggleSearch}
                className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                aria-label="Caută"
              >
                <Search className="h-5 w-5 text-gray-600 dark:text-gray-400" />
              </button>

              <Link href="/dashboard" className="btn-secondary">
                Dashboard
              </Link>
            </div>
          </div>

          {/* Search bar */}
          <AnimatePresence>
            {searchOpen && (
              <motion.div
                initial={{ height: 0, opacity: 0 }}
                animate={{ height: 'auto', opacity: 1 }}
                exit={{ height: 0, opacity: 0 }}
                transition={{ duration: 0.25 }}
                className="overflow-hidden"
              >
                <div className="mt-4 relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    ref={searchInputRef}
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Caută știri... ex: Bitcoin, Tesla, FCSB"
                    className="input w-full pl-10 pr-10"
                  />
                  {searchQuery && (
                    <button
                      onClick={() => { setSearchQuery(''); setDebouncedQuery(''); }}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      <X className="h-5 w-5" />
                    </button>
                  )}
                </div>
                {debouncedQuery && (
                  <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">
                    Rezultate pentru &quot;{debouncedQuery}&quot;
                    {!loading && ` — ${news.length} articol${news.length !== 1 ? 'e' : ''}`}
                  </p>
                )}
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>

      {/* Category Tabs */}
      <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
        <div className="container mx-auto px-4">
          <div className="relative flex items-center">
            <button
              onClick={() => scrollTabs('left')}
              className="absolute left-0 z-10 p-1 bg-white dark:bg-gray-900 shadow-md rounded-full md:hidden"
              aria-label="Scroll left"
            >
              <ChevronLeft className="h-4 w-4 text-gray-500" />
            </button>
            <div
              ref={tabsRef}
              className="flex items-center space-x-1 overflow-x-auto scrollbar-hide py-3 px-6 md:px-0"
            >
              {CATEGORIES.filter(c => !c.hidden).map((cat) => (
                <button
                  key={cat.slug}
                  onClick={() => handleCategoryChange(cat.slug)}
                  className={`flex items-center space-x-1.5 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-colors ${
                    selectedCategory === cat.slug
                      ? 'bg-primary-600 text-white'
                      : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                  }`}
                >
                  <span>{cat.emoji}</span>
                  <span>{cat.label}</span>
                </button>
              ))}
            </div>
            <button
              onClick={() => scrollTabs('right')}
              className="absolute right-0 z-10 p-1 bg-white dark:bg-gray-900 shadow-md rounded-full md:hidden"
              aria-label="Scroll right"
            >
              <ChevronRight className="h-4 w-4 text-gray-500" />
            </button>
          </div>
        </div>
      </div>

      {/* Scroll to top */}
      <ScrollToTop />

      {/* Content */}
      <div className="container mx-auto px-4 py-8">
        <div className="lg:flex lg:gap-8">
        {/* Main column */}
        <div className="flex-1 min-w-0">
        {error && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 p-4 text-red-800 dark:text-red-300"
          >
            {error}
          </motion.div>
        )}

        {news.length === 0 && !loading && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="text-center py-12"
          >
            <Newspaper className="h-16 w-16 text-gray-400 mx-auto mb-4" />
            <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
              {debouncedQuery ? `Niciun rezultat pentru "${debouncedQuery}"` : 'Nu sunt știri disponibile'}
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              {debouncedQuery ? 'Încearcă alt cuvânt cheie.' : 'Verifică mai târziu pentru știri noi.'}
            </p>
          </motion.div>
        )}

        {loading && news.length === 0 && searchQuery && (
          <div className="flex justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
          </div>
        )}

        {news.length > 0 && (
          <>
            {/* Hero Card */}
            {heroItem && (
              <motion.article
                variants={heroVariants}
                initial="hidden"
                animate="visible"
                className="card hover:shadow-xl transition-shadow cursor-pointer mb-8 overflow-hidden relative group"
                onClick={() => handleArticleClick(heroItem)}
              >
                {/* Bookmark button */}
                <button
                  onClick={(e) => handleToggleBookmark(e, heroItem)}
                  className="absolute top-4 right-4 z-10 p-2 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm shadow-md hover:scale-110 transition-transform"
                  aria-label={isBookmarked(heroItem.id) ? 'Elimină din salvate' : 'Salvează articolul'}
                >
                  {isBookmarked(heroItem.id) ? (
                    <BookmarkCheck className="h-5 w-5 text-primary-600" />
                  ) : (
                    <Bookmark className="h-5 w-5 text-gray-500 group-hover:text-primary-600 transition-colors" />
                  )}
                </button>

                <div className="md:flex">
                  {heroItem.image && (
                    <div className="md:w-1/2 flex-shrink-0">
                      <img
                        src={heroItem.image}
                        alt={heroItem.title}
                        className="w-full h-64 md:h-full object-cover"
                      />
                    </div>
                  )}
                  <div className={`p-6 flex flex-col justify-center ${heroItem.image ? 'md:w-1/2' : 'w-full'}`}>
                    <div className="flex items-center gap-2 mb-3 flex-wrap">
                      <BadgesRow item={heroItem} />
                    </div>
                    <h2 className="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white mb-3 line-clamp-3">
                      {heroItem.title}
                    </h2>
                    <p className="text-gray-600 dark:text-gray-400 mb-4 line-clamp-3 text-lg">
                      {heroItem.summary}
                    </p>
                    <div className="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                      <div className="flex items-center space-x-4">
                        <span className="flex items-center"><Calendar className="h-4 w-4 mr-1" />{formatDate(heroItem.published_at)}</span>
                        <span className="flex items-center"><Clock className="h-4 w-4 mr-1" />{estimateReadingTime(heroItem.content)} min</span>
                      </div>
                      <span>Sursă: {heroItem.source}</span>
                    </div>
                    {heroItem.categories.length > 0 && (
                      <div className="mt-4 flex flex-wrap gap-2">
                        {heroItem.categories.slice(0, 4).map((category) => (
                          <CategoryBadge key={category} category={category} />
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </motion.article>
            )}

            {/* Featured Row (2 cards) */}
            {featuredItems.length > 0 && (
              <div className="grid gap-6 md:grid-cols-2 mb-8">
                {featuredItems.map((item, i) => (
                  <motion.article
                    key={item.id}
                    variants={cardVariants}
                    initial="hidden"
                    animate="visible"
                    custom={i}
                    className="card hover:shadow-lg transition-shadow cursor-pointer overflow-hidden relative group"
                    onClick={() => handleArticleClick(item)}
                  >
                    {/* Bookmark */}
                    <button
                      onClick={(e) => handleToggleBookmark(e, item)}
                      className="absolute top-4 right-4 z-10 p-1.5 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
                      aria-label={isBookmarked(item.id) ? 'Elimină din salvate' : 'Salvează'}
                    >
                      {isBookmarked(item.id) ? (
                        <BookmarkCheck className="h-4 w-4 text-primary-600" />
                      ) : (
                        <Bookmark className="h-4 w-4 text-gray-500" />
                      )}
                    </button>

                    {item.image && (
                      <div className="-mx-6 -mt-6 mb-4">
                        <img src={item.image} alt={item.title} className="w-full h-52 object-cover" />
                      </div>
                    )}
                    <div className="flex items-center gap-2 mb-2 flex-wrap">
                      <BadgesRow item={item} />
                    </div>
                    <h2 className="text-xl font-bold text-gray-900 dark:text-white mb-2 line-clamp-2">{item.title}</h2>
                    <p className="text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">{item.summary}</p>
                    <div className="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                      <div className="flex items-center space-x-3">
                        <span className="flex items-center"><Calendar className="h-3.5 w-3.5 mr-1" />{formatDate(item.published_at)}</span>
                        <span className="flex items-center"><Clock className="h-3.5 w-3.5 mr-1" />{estimateReadingTime(item.content)} min</span>
                      </div>
                      {item.original_url && (
                        <a href={item.original_url} target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:text-primary-700" onClick={(e) => e.stopPropagation()}>
                          <ExternalLink className="h-4 w-4" />
                        </a>
                      )}
                    </div>
                    {item.categories.length > 0 && (
                      <div className="mt-3 flex flex-wrap gap-2">
                        {item.categories.slice(0, 3).map((category) => (
                          <CategoryBadge key={category} category={category} />
                        ))}
                      </div>
                    )}
                  </motion.article>
                ))}
              </div>
            )}

            {/* Grid (rest) */}
            {gridItems.length > 0 && (
              <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                {gridItems.map((item, i) => (
                  <motion.article
                    key={item.id}
                    variants={cardVariants}
                    initial="hidden"
                    animate="visible"
                    custom={i}
                    className="card hover:shadow-lg transition-shadow cursor-pointer overflow-hidden relative group"
                    onClick={() => handleArticleClick(item)}
                  >
                    {/* Bookmark */}
                    <button
                      onClick={(e) => handleToggleBookmark(e, item)}
                      className="absolute top-4 right-4 z-10 p-1.5 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
                      aria-label={isBookmarked(item.id) ? 'Elimină din salvate' : 'Salvează'}
                    >
                      {isBookmarked(item.id) ? (
                        <BookmarkCheck className="h-4 w-4 text-primary-600" />
                      ) : (
                        <Bookmark className="h-4 w-4 text-gray-500" />
                      )}
                    </button>

                    {item.image && (
                      <div className="-mx-6 -mt-6 mb-4">
                        <img src={item.image} alt={item.title} className="w-full h-48 object-cover" />
                      </div>
                    )}
                    <div className="flex items-center gap-2 mb-2 flex-wrap">
                      <BadgesRow item={item} />
                    </div>
                    <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-2 line-clamp-2">{item.title}</h2>
                    <p className="text-gray-600 dark:text-gray-400 mb-3 line-clamp-2 text-sm">{item.summary}</p>
                    <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                      <div className="flex items-center space-x-2">
                        <span className="flex items-center"><Clock className="h-3 w-3 mr-1" />{estimateReadingTime(item.content)} min</span>
                        <span>· {item.source}</span>
                      </div>
                      {item.original_url && (
                        <a href={item.original_url} target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:text-primary-700" onClick={(e) => e.stopPropagation()}>
                          <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                      )}
                    </div>
                    {item.categories.length > 0 && (
                      <div className="mt-3 flex flex-wrap gap-1.5">
                        {item.categories.slice(0, 2).map((category) => (
                          <CategoryBadge key={category} category={category} size="sm" />
                        ))}
                      </div>
                    )}
                  </motion.article>
                ))}
              </div>
            )}
          </>
        )}

        {/* Pagination */}
        {totalPages > 1 && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.3 }}
            className="mt-8 flex justify-center items-center space-x-4"
          >
            <button
              onClick={() => setPage(Math.max(1, page - 1))}
              disabled={page === 1}
              className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Anterior
            </button>
            <span className="text-gray-700 dark:text-gray-300">
              Pagina {page} din {totalPages}
            </span>
            <button
              onClick={() => setPage(Math.min(totalPages, page + 1))}
              disabled={page === totalPages}
              className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Următoarea
            </button>
          </motion.div>
        )}
        </div>{/* end main column */}

        {/* Trending Sidebar */}
        <TrendingSidebar items={trendingNews} onArticleClick={handleArticleClick} />
        </div>{/* end lg:flex */}
      </div>
    </div>
  );
}

/* ── Reading Streak Badge ── */
function ReadingStreakBadge({ streak, readToday, totalRead }: { streak: number; readToday: number; totalRead: number }) {
  const [showTooltip, setShowTooltip] = useState(false);

  if (totalRead === 0) return null;

  return (
    <div className="relative">
      <button
        onMouseEnter={() => setShowTooltip(true)}
        onMouseLeave={() => setShowTooltip(false)}
        className="flex items-center space-x-1.5 px-3 py-1.5 rounded-full bg-gradient-to-r from-orange-100 to-amber-100 dark:from-orange-900/30 dark:to-amber-900/30 text-orange-700 dark:text-orange-300 text-sm font-medium"
      >
        <Flame className="h-4 w-4" />
        <span>{streak}</span>
      </button>

      <AnimatePresence>
        {showTooltip && (
          <motion.div
            initial={{ opacity: 0, y: 5 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 5 }}
            className="absolute top-full right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 z-50"
          >
            <div className="space-y-3">
              <div className="flex items-center space-x-2">
                <Flame className="h-5 w-5 text-orange-500" />
                <div>
                  <p className="text-sm font-semibold text-gray-900 dark:text-white">Serie de {streak} zile</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Continuă să citești!</p>
                </div>
              </div>
              <div className="border-t border-gray-100 dark:border-gray-700 pt-2 space-y-1.5">
                <div className="flex justify-between text-xs">
                  <span className="text-gray-500 dark:text-gray-400">Citite azi</span>
                  <span className="font-medium text-gray-900 dark:text-white">{readToday}</span>
                </div>
                <div className="flex justify-between text-xs">
                  <span className="text-gray-500 dark:text-gray-400">Total citite</span>
                  <span className="font-medium text-gray-900 dark:text-white">{totalRead}</span>
                </div>
              </div>
              {streak >= 7 && (
                <div className="flex items-center space-x-1.5 text-xs bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 px-2 py-1 rounded">
                  <Trophy className="h-3.5 w-3.5" />
                  <span>Cititor dedicat!</span>
                </div>
              )}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

/* ── Bookmarks Button ── */
function BookmarksButton() {
  const count = useBookmarkStore((s) => s.bookmarks.length);

  return (
    <Link
      href="/news/saved"
      className="relative p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
      aria-label="Articole salvate"
    >
      <Bookmark className="h-5 w-5 text-gray-600 dark:text-gray-400" />
      {count > 0 && (
        <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] flex items-center justify-center bg-primary-600 text-white text-[10px] font-bold rounded-full px-1">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Link>
  );
}

/* ── Badges Row ── */
function BadgesRow({ item }: { item: NewsItem }) {
  return (
    <>
      {item.summary && (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
          <Sparkles className="h-3 w-3" />
          Rezumat
        </span>
      )}
      {item.language && item.language !== 'ro' && (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
          <Languages className="h-3 w-3" />
          Tradus din {item.language.toUpperCase()}
        </span>
      )}
    </>
  );
}

/* ── Category Badge ── */
const CATEGORY_COLORS = SHARED_CATEGORY_COLORS;

/* ── Trending Sidebar ── */
function TrendingSidebar({ items, onArticleClick }: { items: NewsItem[]; onArticleClick: (item: NewsItem) => void }) {
  if (items.length === 0) return null;

  return (
    <aside className="hidden lg:block w-80 flex-shrink-0">
      <div className="sticky top-16">
        <div className="card">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
            <TrendingUpIcon className="h-5 w-5 text-primary-600 mr-2" />
            Trending
          </h3>
          <div className="space-y-4">
            {items.map((item, i) => (
              <motion.div
                key={item.id}
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: i * 0.1, ease: 'easeOut' as const }}
                onClick={() => onArticleClick(item)}
                className="flex gap-3 cursor-pointer group"
              >
                <span className="flex-shrink-0 w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 flex items-center justify-center text-sm font-bold">
                  {i + 1}
                </span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900 dark:text-white line-clamp-2 group-hover:text-primary-600 transition-colors">
                    {item.title}
                  </p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {item.source} · {estimateReadingTime(item.content)} min
                  </p>
                </div>
                {item.image && (
                  <div className="flex-shrink-0 w-16 h-12 rounded overflow-hidden">
                    <img src={item.image} alt="" className="w-full h-full object-cover" />
                  </div>
                )}
              </motion.div>
            ))}
          </div>
        </div>
      </div>
    </aside>
  );
}

function TrendingUpIcon({ className }: { className?: string }) {
  return (
    <svg className={className} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
      <polyline points="17 6 23 6 23 12" />
    </svg>
  );
}

function CategoryBadge({ category, size = 'md' }: { category: string; size?: 'sm' | 'md' }) {
  const slug = category.toLowerCase().replace(/\s+/g, '-');
  const colors = CATEGORY_COLORS[slug] || 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
  const sizeClasses = size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs';

  return (
    <span className={`inline-flex items-center rounded-full font-medium ${colors} ${sizeClasses}`}>
      <Tag className={size === 'sm' ? 'h-2.5 w-2.5 mr-0.5' : 'h-3 w-3 mr-1'} />
      {category}
    </span>
  );
}
