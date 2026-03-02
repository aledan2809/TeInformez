'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import { ArrowLeft, Bookmark, BookmarkX, Calendar, Clock, Trash2, Newspaper } from 'lucide-react';
import { useBookmarkStore } from '@/store/bookmarkStore';

export default function SavedArticlesPage() {
  const router = useRouter();
  const { bookmarks, removeBookmark } = useBookmarkStore();
  const [removingId, setRemovingId] = useState<number | null>(null);

  const handleRemove = (e: React.MouseEvent, id: number) => {
    e.stopPropagation();
    setRemovingId(id);
    setTimeout(() => {
      removeBookmark(id);
      setRemovingId(null);
    }, 300);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('ro-RO', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const formatSavedDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('ro-RO', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-950">
      {/* Header */}
      <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <div className="container mx-auto px-4 py-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <Link href="/news" className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <ArrowLeft className="h-5 w-5 text-gray-600 dark:text-gray-400" />
              </Link>
              <Bookmark className="h-7 w-7 text-primary-600" />
              <div>
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Articole salvate</h1>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {bookmarks.length} articol{bookmarks.length !== 1 ? 'e' : ''} salvat{bookmarks.length !== 1 ? 'e' : ''}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="container mx-auto px-4 py-8 max-w-4xl">
        {bookmarks.length === 0 ? (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-center py-16"
          >
            <BookmarkX className="h-16 w-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
            <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
              Niciun articol salvat
            </h2>
            <p className="text-gray-500 dark:text-gray-400 mb-6">
              Apasă pe iconița de bookmark de pe orice articol pentru a-l salva aici.
            </p>
            <Link href="/news" className="btn-primary">
              <Newspaper className="h-4 w-4 mr-2" />
              Explorează știrile
            </Link>
          </motion.div>
        ) : (
          <div className="space-y-4">
            <AnimatePresence mode="popLayout">
              {bookmarks.map((article, i) => (
                <motion.article
                  key={article.id}
                  layout
                  initial={{ opacity: 0, x: -20 }}
                  animate={{
                    opacity: removingId === article.id ? 0 : 1,
                    x: removingId === article.id ? 100 : 0,
                  }}
                  exit={{ opacity: 0, x: 100 }}
                  transition={{ duration: 0.3, delay: i * 0.03 }}
                  className="card hover:shadow-lg transition-shadow cursor-pointer overflow-hidden"
                  onClick={() => router.push(`/news/${article.id}`)}
                >
                  <div className="flex gap-4">
                    {article.image && (
                      <div className="flex-shrink-0 w-32 h-24 rounded-lg overflow-hidden">
                        <img src={article.image} alt={article.title} className="w-full h-full object-cover" />
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <h3 className="font-semibold text-gray-900 dark:text-white mb-1 line-clamp-2">{article.title}</h3>
                      <p className="text-sm text-gray-600 dark:text-gray-400 line-clamp-1 mb-2">{article.summary}</p>
                      <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <div className="flex items-center space-x-3">
                          <span className="flex items-center"><Calendar className="h-3 w-3 mr-1" />{formatDate(article.published_at)}</span>
                          <span>{article.source}</span>
                        </div>
                        <span className="flex items-center text-gray-400">
                          <Clock className="h-3 w-3 mr-1" />
                          Salvat {formatSavedDate(article.savedAt)}
                        </span>
                      </div>
                    </div>
                    <button
                      onClick={(e) => handleRemove(e, article.id)}
                      className="flex-shrink-0 p-2 text-gray-400 hover:text-red-500 transition-colors"
                      aria-label="Elimină din salvate"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </motion.article>
              ))}
            </AnimatePresence>
          </div>
        )}
      </div>
    </div>
  );
}
