'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { Search, Loader2, Calendar, Eye, BookOpen } from 'lucide-react';
import SharedHeader from '@/components/SharedHeader';
import { api } from '@/lib/api';
import { JURIDIC_CATEGORIES, type JuridicQA } from '@/types';

const CATEGORY_COLORS: Record<string, string> = {
  'dreptul-muncii': 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
  'dreptul-familiei': 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300',
  'drept-comercial': 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
  'drept-penal': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
  'protectia-consumatorului': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
  'drept-administrativ': 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300',
  'drept-imobiliar': 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
};

export default function JuridicListClient() {
  const [items, setItems] = useState<JuridicQA[]>([]);
  const [loading, setLoading] = useState(true);
  const [category, setCategory] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  useEffect(() => {
    loadItems();
  }, [category, page]);

  const loadItems = async () => {
    setLoading(true);
    try {
      const data = await api.getJuridicList({
        page,
        per_page: 12,
        category: category || undefined,
        search: search || undefined,
      });
      setItems(data.items || []);
      setTotalPages(data.total_pages || 1);
    } catch {
      setItems([]);
    }
    setLoading(false);
  };

  const handleSearch = () => {
    setPage(1);
    loadItems();
  };

  return (
    <>
      <SharedHeader />

      <main className="container-custom py-8">
        {/* Hero banner */}
        <div className="rounded-xl bg-gradient-to-r from-stone-100 to-stone-50 dark:from-stone-900/40 dark:to-stone-800/20 border border-stone-200 dark:border-stone-700 p-8 mb-8">
          <div className="flex items-center gap-3 mb-3">
            <span className="text-3xl">📋</span>
            <h1 className="text-2xl font-bold">Juridic cu Alina</h1>
          </div>
          <p className="text-gray-600 dark:text-gray-400 max-w-2xl">
            Raspunsuri la intrebari juridice frecvente. Dreptul muncii, dreptul familiei,
            drept comercial și multe altele. Intrebarile sunt anonimizate pentru protecția datelor.
          </p>
        </div>

        {/* Category filter pills */}
        <div className="flex flex-wrap gap-2 mb-6">
          <button
            onClick={() => { setCategory(''); setPage(1); }}
            className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
              !category
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
            }`}
          >
            Toate
          </button>
          {JURIDIC_CATEGORIES.map((cat) => (
            <button
              key={cat.slug}
              onClick={() => { setCategory(cat.slug); setPage(1); }}
              className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                category === cat.slug
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
              }`}
            >
              {cat.label}
            </button>
          ))}
        </div>

        {/* Search */}
        <div className="flex gap-2 mb-8">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
              placeholder="Cauta intrebari..."
              className="input pl-10 w-full"
            />
          </div>
          <button onClick={handleSearch} className="btn-primary px-4">
            Cauta
          </button>
        </div>

        {/* Loading */}
        {loading ? (
          <div className="text-center py-16">
            <Loader2 className="h-8 w-8 animate-spin mx-auto text-primary-600" />
            <p className="mt-3 text-gray-500">Se incarca...</p>
          </div>
        ) : items.length === 0 ? (
          <div className="text-center py-16">
            <BookOpen className="h-12 w-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <p className="text-gray-500 dark:text-gray-400">Nu exista intrebari in aceasta categorie.</p>
            <p className="text-sm text-gray-400 mt-1">Intrebarile vor fi adaugate in curand.</p>
          </div>
        ) : (
          <>
            {/* Q&A Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {items.map((item) => (
                <Link
                  key={item.id}
                  href={`/juridic/${item.id}`}
                  className="card hover:shadow-md transition-shadow p-5 group"
                >
                  {/* Category badge */}
                  <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium mb-3 ${
                    CATEGORY_COLORS[item.category] || 'bg-gray-100 text-gray-700'
                  }`}>
                    {JURIDIC_CATEGORIES.find(c => c.slug === item.category)?.label || item.category}
                  </span>

                  {item.is_weekly_column && item.column_title && (
                    <p className="text-xs font-semibold text-primary-600 mb-1">{item.column_title}</p>
                  )}

                  <h3 className="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors line-clamp-3 leading-snug">
                    {item.question}
                  </h3>

                  {item.answer_summary && (
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                      {item.answer_summary}
                    </p>
                  )}

                  <div className="flex items-center gap-3 mt-3 text-xs text-gray-400">
                    <span className="flex items-center gap-1">
                      <Calendar className="h-3 w-3" />
                      {item.published_at ? new Date(item.published_at).toLocaleDateString('ro-RO') : '—'}
                    </span>
                    <span className="flex items-center gap-1">
                      <Eye className="h-3 w-3" />
                      {item.view_count}
                    </span>
                    <span>{item.author_name}</span>
                  </div>
                </Link>
              ))}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex justify-center gap-2 mt-8">
                {Array.from({ length: totalPages }, (_, i) => i + 1).map((p) => (
                  <button
                    key={p}
                    onClick={() => setPage(p)}
                    className={`px-3 py-1.5 rounded text-sm font-medium ${
                      p === page
                        ? 'bg-primary-600 text-white'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200'
                    }`}
                  >
                    {p}
                  </button>
                ))}
              </div>
            )}
          </>
        )}

        {/* CTA */}
        <div className="mt-12 text-center p-8 rounded-xl bg-stone-50 dark:bg-stone-900/20 border border-stone-200 dark:border-stone-800">
          <h3 className="text-lg font-bold mb-2">Ai o intrebare juridica?</h3>
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Trimite-ne intrebarea ta pe Facebook sau email. Raspunsurile sunt anonimizate și publicate pe site.
          </p>
          <div className="flex justify-center gap-3">
            <a
              href="https://facebook.com/teinformez"
              target="_blank"
              rel="noopener noreferrer"
              className="btn-primary text-sm px-4 py-2"
            >
              Scrie pe Facebook
            </a>
            <a
              href="mailto:juridic@teinformez.eu"
              className="btn-outline text-sm px-4 py-2"
            >
              Trimite email
            </a>
          </div>
        </div>
      </main>
    </>
  );
}
