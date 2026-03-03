'use client';

import { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
  ArrowLeft, Calendar, ExternalLink, Tag, Loader2, Share2,
  Bookmark, BookmarkCheck, Copy, Check, Clock, Sparkles,
} from 'lucide-react';
import { api } from '@/lib/api';
import { useBookmarkStore } from '@/store/bookmarkStore';
import { useReadingStore } from '@/store/readingStore';
import ReadingProgressBar from '@/components/ReadingProgressBar';
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

export default function NewsDetailClient() {
  const params = useParams();
  const [news, setNews] = useState<NewsItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [relatedArticles, setRelatedArticles] = useState<NewsItem[]>([]);

  const { isBookmarked, toggleBookmark } = useBookmarkStore();
  const { markAsRead } = useReadingStore();

  useEffect(() => {
    fetchNews();
  }, [params.id]);

  const fetchNews = async () => {
    setLoading(true);
    setError(null);

    try {
      const newsId = parseInt(params.id as string);
      const data = await api.getNewsItem(newsId);
      setNews(data);
      markAsRead(newsId);
      api.trackView(newsId).catch(() => {});

      // Fetch related articles by same category
      if (data.categories && data.categories.length > 0) {
        try {
          const related = await api.getNews({
            category: data.categories[0],
            per_page: 4,
            page: 1,
          });
          setRelatedArticles(
            (related.news || []).filter((item: NewsItem) => item.id !== newsId).slice(0, 3)
          );
        } catch {
          // Not critical
        }
      }
    } catch (err: any) {
      setError(err.message || 'Eroare la încărcarea știrii');
    } finally {
      setLoading(false);
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

  const handleShare = async () => {
    if (navigator.share && news) {
      try {
        await navigator.share({
          title: news.title,
          text: news.summary,
          url: window.location.href
        });
      } catch (err) {
        // User cancelled
      }
    }
  };

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      const input = document.createElement('input');
      input.value = window.location.href;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      document.body.removeChild(input);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  const handleToggleBookmark = () => {
    if (!news) return;
    toggleBookmark({
      id: news.id,
      title: news.title,
      summary: news.summary,
      image: news.image,
      source: news.source,
      categories: news.categories,
      published_at: news.published_at,
      original_url: news.original_url,
    });
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className="text-center"
        >
          <Loader2 className="h-12 w-12 animate-spin text-primary-600 mx-auto mb-4" />
          <p className="text-gray-600 dark:text-gray-400">Se încarcă știrea...</p>
        </motion.div>
      </div>
    );
  }

  if (error || !news) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center"
        >
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Știre negăsită</h2>
          <p className="text-gray-600 dark:text-gray-400 mb-6">{error || 'Această știre nu există sau nu este disponibilă.'}</p>
          <Link href="/news" className="btn-primary">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Înapoi la știri
          </Link>
        </motion.div>
      </div>
    );
  }

  const bookmarked = isBookmarked(news.id);

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-950">
      {/* Reading progress bar */}
      <ReadingProgressBar />

      {/* Scroll to top */}
      <ScrollToTop />

      {/* Header */}
      <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <Link
              href="/news"
              className="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Înapoi la știri
            </Link>
            <div className="flex items-center space-x-2">
              {/* Bookmark button */}
              <motion.button
                whileTap={{ scale: 0.9 }}
                onClick={handleToggleBookmark}
                className={`inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
                  bookmarked
                    ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                    : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                }`}
              >
                {bookmarked ? (
                  <BookmarkCheck className="h-4 w-4 mr-1.5" />
                ) : (
                  <Bookmark className="h-4 w-4 mr-1.5" />
                )}
                {bookmarked ? 'Salvat' : 'Salvează'}
              </motion.button>

              {/* Copy link */}
              <button
                onClick={handleCopyLink}
                className="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
              >
                {copied ? (
                  <>
                    <Check className="h-4 w-4 mr-1.5 text-green-600" />
                    Copiat!
                  </>
                ) : (
                  <>
                    <Copy className="h-4 w-4 mr-1.5" />
                    Copiază link
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Article */}
      <motion.article
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4 }}
        className="container mx-auto px-4 py-8 max-w-4xl"
      >
        <div className="bg-white dark:bg-gray-900 rounded-lg shadow-lg p-8">
          {/* Title */}
          <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            {news.title}
          </h1>

          {/* Meta */}
          <div className="flex flex-wrap items-center justify-between mb-6 pb-6 border-b border-gray-200 dark:border-gray-700 gap-3">
            <div className="flex items-center text-sm text-gray-500 dark:text-gray-400 space-x-4">
              <div className="flex items-center">
                <Calendar className="h-4 w-4 mr-1" />
                <span>{formatDate(news.published_at)}</span>
              </div>
              <div className="flex items-center">
                <Clock className="h-4 w-4 mr-1" />
                <span>{estimateReadingTime(news.content)} min citire</span>
              </div>
              <div>
                Sursă: <span className="font-medium">{news.source}</span>
              </div>
            </div>
            <div className="flex items-center space-x-2 flex-wrap gap-1">
              {news.original_url && (
                <a
                  href={news.original_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn-secondary text-sm"
                >
                  <ExternalLink className="h-4 w-4 mr-1" />
                  Sursă originală
                </a>
              )}
              <ShareButtons title={news.title} onShare={handleShare} />
            </div>
          </div>

          {/* Categories and Tags */}
          {(news.categories.length > 0 || news.tags.length > 0) && (
            <div className="mb-6">
              {news.categories.length > 0 && (
                <div className="mb-3">
                  <span className="text-sm font-semibold text-gray-700 dark:text-gray-300 mr-2">Categorii:</span>
                  <div className="inline-flex flex-wrap gap-2">
                    {news.categories.map((category) => (
                      <span
                        key={category}
                        className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300"
                      >
                        <Tag className="h-3 w-3 mr-1" />
                        {category}
                      </span>
                    ))}
                  </div>
                </div>
              )}
              {news.tags.length > 0 && (
                <div>
                  <span className="text-sm font-semibold text-gray-700 dark:text-gray-300 mr-2">Tag-uri:</span>
                  <div className="inline-flex flex-wrap gap-2">
                    {news.tags.map((tag) => (
                      <span
                        key={tag}
                        className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Image */}
          {news.image && (
            <motion.div
              initial={{ opacity: 0, scale: 0.98 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: 0.2, duration: 0.4 }}
              className="mb-6 rounded-lg overflow-hidden"
            >
              <img
                src={news.image}
                alt={news.title}
                className="w-full h-auto"
              />
            </motion.div>
          )}

          {/* Summary */}
          {news.summary && (
            <motion.div
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.3, duration: 0.4 }}
              className="mb-6 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border-l-4 border-purple-500"
            >
              <div className="flex items-center gap-2 mb-2">
                <Sparkles className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                <span className="text-sm font-semibold text-purple-700 dark:text-purple-300">Rezumat</span>
              </div>
              <p className="text-base font-medium text-gray-900 dark:text-gray-100 italic">
                {news.summary}
              </p>
            </motion.div>
          )}

          {/* Content */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.4, duration: 0.5 }}
            className="prose prose-lg dark:prose-invert max-w-none"
            dangerouslySetInnerHTML={{ __html: news.content }}
          />
        </div>

        {/* Related Articles */}
        {relatedArticles.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6, duration: 0.4 }}
            className="mt-8"
          >
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-4">Articole similare</h2>
            <div className="grid gap-4 md:grid-cols-3">
              {relatedArticles.map((article) => (
                <Link
                  key={article.id}
                  href={`/news/${article.id}`}
                  className="card hover:shadow-lg transition-shadow overflow-hidden group"
                >
                  {article.image && (
                    <div className="-mx-6 -mt-6 mb-4">
                      <img
                        src={article.image}
                        alt={article.title}
                        className="w-full h-36 object-cover group-hover:scale-105 transition-transform duration-300"
                      />
                    </div>
                  )}
                  <h3 className="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2 group-hover:text-primary-600 transition-colors">
                    {article.title}
                  </h3>
                  <p className="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{article.summary}</p>
                  <div className="mt-3 flex items-center text-xs text-gray-500 dark:text-gray-400 space-x-2">
                    <Clock className="h-3 w-3" />
                    <span>{estimateReadingTime(article.content)} min</span>
                    <span>·</span>
                    <span>{article.source}</span>
                  </div>
                </Link>
              ))}
            </div>
          </motion.div>
        )}

        {/* Back button */}
        <div className="mt-8 text-center">
          <Link href="/news" className="btn-secondary">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Înapoi la toate știrile
          </Link>
        </div>
      </motion.article>
    </div>
  );
}

/* ── Social Share Buttons ── */
function ShareButtons({ title, onShare }: { title: string; onShare: () => void }) {
  const getUrl = () => typeof window !== 'undefined' ? window.location.href : '';

  const shareLinks = [
    {
      label: 'Facebook',
      href: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(getUrl())}`,
      className: 'bg-blue-600 hover:bg-blue-700 text-white',
    },
    {
      label: 'X',
      href: `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(getUrl())}`,
      className: 'bg-black hover:bg-gray-800 text-white',
    },
    {
      label: 'WhatsApp',
      href: `https://wa.me/?text=${encodeURIComponent(title + ' ' + getUrl())}`,
      className: 'bg-green-600 hover:bg-green-700 text-white',
    },
    {
      label: 'Telegram',
      href: `https://t.me/share/url?url=${encodeURIComponent(getUrl())}&text=${encodeURIComponent(title)}`,
      className: 'bg-sky-500 hover:bg-sky-600 text-white',
    },
    {
      label: 'LinkedIn',
      href: `https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(getUrl())}&title=${encodeURIComponent(title)}`,
      className: 'bg-blue-800 hover:bg-blue-900 text-white',
    },
  ];

  return (
    <>
      {shareLinks.map((link) => (
        <a
          key={link.label}
          href={link.href}
          target="_blank"
          rel="noopener noreferrer"
          className={`inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium ${link.className}`}
        >
          {link.label}
        </a>
      ))}
      {typeof window !== 'undefined' && typeof navigator !== 'undefined' && 'share' in navigator && (
        <button
          onClick={onShare}
          className="btn-secondary text-sm"
        >
          <Share2 className="h-4 w-4 mr-1" />
          Mai mult
        </button>
      )}
    </>
  );
}
