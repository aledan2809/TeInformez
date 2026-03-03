'use client';

import Link from 'next/link';
import { ArrowLeft, Calendar, Eye, Share2, Facebook, Mail } from 'lucide-react';
import SharedHeader from '@/components/SharedHeader';
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

interface Props {
  item: JuridicQA | null;
}

export default function JuridicDetailClient({ item }: Props) {
  if (!item) {
    return (
      <>
        <SharedHeader />
        <main className="container-custom py-16 text-center">
          <p className="text-gray-500">Intrebarea nu a fost gasita.</p>
          <Link href="/juridic" className="text-primary-600 hover:underline mt-4 inline-block">
            Inapoi la lista
          </Link>
        </main>
      </>
    );
  }

  const catLabel = JURIDIC_CATEGORIES.find(c => c.slug === item.category)?.label || item.category;
  const shareUrl = typeof window !== 'undefined' ? window.location.href : '';
  const shareText = `${item.question} — Juridic cu Alina pe TeInformez.eu`;

  return (
    <>
      <SharedHeader />

      <main className="container-custom py-8 max-w-3xl mx-auto">
        {/* Back link */}
        <Link href="/juridic" className="flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600 mb-6">
          <ArrowLeft className="h-4 w-4" />
          Inapoi la toate intrebarile
        </Link>

        {/* Category + column badge */}
        <div className="flex flex-wrap items-center gap-2 mb-4">
          <span className={`inline-block px-3 py-1 rounded-full text-xs font-semibold ${
            CATEGORY_COLORS[item.category] || 'bg-gray-100 text-gray-700'
          }`}>
            {catLabel}
          </span>
          {item.is_weekly_column && (
            <span className="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300">
              Alina Raspunde
            </span>
          )}
        </div>

        {/* Column title */}
        {item.column_title && (
          <p className="text-sm font-semibold text-primary-600 mb-2">{item.column_title}</p>
        )}

        {/* Question */}
        <div className="bg-stone-50 dark:bg-stone-900/20 rounded-lg p-5 mb-6 border-l-4 border-stone-400">
          <p className="text-xs uppercase tracking-wide text-stone-500 mb-2 font-semibold">Intrebarea</p>
          <h1 className="text-xl font-bold leading-snug text-gray-900 dark:text-white">
            {item.question}
          </h1>
        </div>

        {/* Answer */}
        <div className="mb-8">
          <p className="text-xs uppercase tracking-wide text-stone-500 mb-3 font-semibold">
            Raspunsul — {item.author_name}
          </p>
          <div
            className="prose dark:prose-invert max-w-none"
            dangerouslySetInnerHTML={{ __html: item.answer }}
          />
        </div>

        {/* Meta info */}
        <div className="flex items-center gap-4 text-sm text-gray-400 border-t pt-4 mb-6">
          <span className="flex items-center gap-1">
            <Calendar className="h-4 w-4" />
            {item.published_at ? new Date(item.published_at).toLocaleDateString('ro-RO', {
              day: 'numeric', month: 'long', year: 'numeric'
            }) : '—'}
          </span>
          <span className="flex items-center gap-1">
            <Eye className="h-4 w-4" />
            {item.view_count} vizualizari
          </span>
        </div>

        {/* Tags */}
        {item.tags && item.tags.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mb-6">
            {item.tags.map((tag) => (
              <span key={tag} className="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-xs text-gray-600 dark:text-gray-400">
                #{tag}
              </span>
            ))}
          </div>
        )}

        {/* Share buttons */}
        <div className="flex items-center gap-3 mb-8">
          <span className="text-sm font-medium text-gray-500 flex items-center gap-1">
            <Share2 className="h-4 w-4" />
            Distribuie:
          </span>
          <a
            href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`}
            target="_blank"
            rel="noopener noreferrer"
            className="p-2 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 hover:bg-blue-200 transition-colors"
          >
            <Facebook className="h-4 w-4" />
          </a>
          <a
            href={`whatsapp://send?text=${encodeURIComponent(shareText + ' ' + shareUrl)}`}
            className="p-2 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 hover:bg-green-200 transition-colors"
          >
            <span className="text-sm font-bold">W</span>
          </a>
          <a
            href={`mailto:?subject=${encodeURIComponent('Intrebare juridica interesanta')}&body=${encodeURIComponent(shareText + '\n\n' + shareUrl)}`}
            className="p-2 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 hover:bg-gray-200 transition-colors"
          >
            <Mail className="h-4 w-4" />
          </a>
        </div>

        {/* CTA */}
        <div className="text-center p-6 rounded-xl bg-stone-50 dark:bg-stone-900/20 border border-stone-200 dark:border-stone-800">
          <h3 className="font-bold mb-2">Ai o intrebare juridica?</h3>
          <p className="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Scrie-ne pe Facebook sau trimite un email. Raspunsurile sunt anonimizate.
          </p>
          <a
            href="https://facebook.com/teinformez"
            target="_blank"
            rel="noopener noreferrer"
            className="btn-primary text-sm px-4 py-2"
          >
            Scrie pe Facebook
          </a>
        </div>
      </main>
    </>
  );
}
