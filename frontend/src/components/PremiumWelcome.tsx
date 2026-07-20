'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { Sparkles, Check, Loader2, Bot, ArrowRight } from 'lucide-react';
import { api } from '@/lib/api';

/**
 * OP-02 — Premium activation step, shown on the post-checkout success screen.
 * A short 2-step nudge that shrinks time-to-value: fill the digest (subscribe to
 * all categories in one click) + turn on instant Telegram delivery. Reuses the
 * existing categories + bulk-subscribe APIs; the Telegram bind is a deep-link
 * flow, so we link to it rather than complete it here.
 */
export default function PremiumWelcome() {
  const [categorySlugs, setCategorySlugs] = useState<string[]>([]);
  const [activating, setActivating] = useState(false);
  const [activated, setActivated] = useState(false);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    api
      .getCategories()
      .then((cats) => setCategorySlugs(Object.keys(cats)))
      .catch(() => setCategorySlugs([]));
  }, []);

  const activateAllCategories = async () => {
    if (activating || activated || categorySlugs.length === 0) return;
    setActivating(true);
    setFailed(false);
    try {
      await api.bulkAddSubscriptions(
        categorySlugs.map((slug) => ({
          category_slug: slug,
          topic_keyword: '',
          country_filter: 'all',
        }))
      );
      setActivated(true);
    } catch {
      setFailed(true);
    } finally {
      setActivating(false);
    }
  };

  return (
    <div className="mb-6 rounded-2xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-5">
      <div className="flex items-center gap-2 mb-1">
        <Sparkles className="w-5 h-5 text-blue-600 dark:text-blue-400" />
        <h2 className="font-semibold text-gray-900 dark:text-white">
          Premium activat! Hai să-l pornim
        </h2>
      </div>
      <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
        Doi pași rapizi ca să primești tot ce e mai bun de azi:
      </p>

      <div className="space-y-3">
        {/* Step 1 — fill the digest */}
        <div className="flex items-center justify-between gap-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
          <div className="flex items-start gap-3">
            <span
              className={`mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                activated
                  ? 'bg-green-500 text-white'
                  : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'
              }`}
            >
              {activated ? <Check className="w-4 h-4" /> : '1'}
            </span>
            <div>
              <p className="text-sm font-medium text-gray-900 dark:text-white">
                Primește toate categoriile în digest
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Un singur click și digestul tău e complet.
              </p>
            </div>
          </div>
          {activated ? (
            <span className="text-sm font-medium text-green-600 dark:text-green-400 whitespace-nowrap">
              Gata ✓
            </span>
          ) : (
            <button
              onClick={activateAllCategories}
              disabled={activating || categorySlugs.length === 0}
              className="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-60 px-3 py-2 text-sm font-medium text-white transition-colors whitespace-nowrap"
            >
              {activating ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
              Activează toate
            </button>
          )}
        </div>
        {failed && (
          <p className="text-xs text-red-600 dark:text-red-400 pl-9">
            Nu am putut activa categoriile. Încearcă din nou sau setează-le din „Abonamente".
          </p>
        )}

        {/* Step 2 — turn on Telegram */}
        <div className="flex items-center justify-between gap-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
          <div className="flex items-start gap-3">
            <span className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-semibold">
              2
            </span>
            <div>
              <p className="text-sm font-medium text-gray-900 dark:text-white">
                Primește știrile instant pe Telegram
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Gratuit, direct pe telefon, fără să deschizi aplicația.
              </p>
            </div>
          </div>
          <Link
            href="/dashboard/telegram"
            className="inline-flex items-center gap-2 rounded-lg border border-blue-300 dark:border-blue-700 hover:bg-blue-100 dark:hover:bg-blue-900/40 px-3 py-2 text-sm font-medium text-blue-700 dark:text-blue-300 transition-colors whitespace-nowrap"
          >
            <Bot className="w-4 h-4" /> Conectează
            <ArrowRight className="w-3.5 h-3.5" />
          </Link>
        </div>
      </div>
    </div>
  );
}
