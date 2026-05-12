'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { Newspaper, CheckCircle2, Loader2, Mail } from 'lucide-react';
import { api } from '@/lib/api';
import type { Categories } from '@/types';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export default function NewsletterPage() {
  const [email, setEmail] = useState('');
  const [selectedCategories, setSelectedCategories] = useState<string[]>([]);
  const [categories, setCategories] = useState<Categories>({});
  const [isLoading, setIsLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api.getCategories().then(setCategories).catch(() => {});
  }, []);

  const toggleCategory = (slug: string) => {
    setSelectedCategories((prev) =>
      prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug]
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim()) return;

    setIsLoading(true);
    setError('');

    try {
      await api.newsletterSubscribe(email.trim(), selectedCategories);
      setSuccess(true);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setError(msg || 'A apărut o eroare. Încearcă din nou.');
    } finally {
      setIsLoading(false);
    }
  };

  const categoryEntries = Object.entries(categories);

  if (success) {
    return (
      <main className="min-h-screen bg-gradient-to-b from-primary-50 to-white dark:from-gray-900 dark:to-gray-950 flex flex-col justify-center py-12 px-4">
        <div className="max-w-md mx-auto text-center">
          <CheckCircle2 className="h-16 w-16 text-green-500 mx-auto mb-4" />
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Abonare reușită!</h1>
          <p className="text-gray-600 dark:text-gray-400 mb-6">
            Vei primi zilnic un rezumat cu cele mai importante știri din categoriile alese, sintetizate de AI.
          </p>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-8">
            Verifică-ți inbox-ul pentru emailul de confirmare. Dacă vrei mai mult control,{' '}
            <Link href="/register" className="text-primary-600 hover:underline">
              creează un cont gratuit
            </Link>
            .
          </p>
          <Link href="/" className="btn-primary">
            Mergi la știri
          </Link>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-gradient-to-b from-primary-50 to-white dark:from-gray-900 dark:to-gray-950 py-12 px-4">
      <div className="max-w-lg mx-auto">
        {/* Brand */}
        <div className="text-center mb-8">
          <Link href="/" className="inline-flex items-center space-x-2 mb-4">
            <Newspaper className="h-8 w-8 text-primary-600" />
            <span className="text-xl font-bold text-gray-900 dark:text-white">TeInformez.eu</span>
          </Link>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-3">
            Newsletter gratuit
          </h1>
          <p className="text-gray-600 dark:text-gray-400 max-w-sm mx-auto">
            Primești zilnic un rezumat cu știrile importante din România și lume, sintetizate de AI. Fără reclame.
          </p>
        </div>

        <form
          onSubmit={handleSubmit}
          className="bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-8 space-y-6"
        >
          {/* Email */}
          <div>
            <label
              htmlFor="email"
              className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Adresă de email
            </label>
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                id="email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="adresa@exemplu.com"
                className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>

          {/* Categories */}
          {categoryEntries.length > 0 && (
            <div>
              <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                Categorii de interes{' '}
                <span className="font-normal text-gray-500">(opțional — alegi ce te interesează)</span>
              </p>
              <div className="flex flex-wrap gap-2">
                {categoryEntries.map(([slug, cat]) => (
                  <button
                    key={slug}
                    type="button"
                    onClick={() => toggleCategory(slug)}
                    className={`inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium border transition-colors ${
                      selectedCategories.includes(slug)
                        ? 'bg-primary-600 border-primary-600 text-white'
                        : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400'
                    }`}
                  >
                    {cat.icon && <span className="mr-1">{cat.icon}</span>}
                    {cat.label}
                  </button>
                ))}
              </div>
              {selectedCategories.length === 0 && (
                <p className="text-xs text-gray-400 mt-2">
                  Dacă nu alegi nicio categorie, vei primi știri din toate domeniile.
                </p>
              )}
            </div>
          )}

          {error && (
            <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
          )}

          <button
            type="submit"
            disabled={isLoading || !email.trim()}
            className="w-full btn-primary py-3 text-base disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? (
              <>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Se procesează...
              </>
            ) : (
              'Abonează-mă gratuit'
            )}
          </button>

          <p className="text-xs text-center text-gray-500 dark:text-gray-400">
            Prin abonare ești de acord cu{' '}
            <Link href="/privacy" className="underline hover:text-gray-700 dark:hover:text-gray-300">
              Politica de confidențialitate
            </Link>
            . Te poți dezabona oricând.{' '}
            <Link href="/register" className="text-primary-600 hover:underline">
              Creează cont pentru mai mult control →
            </Link>
          </p>
        </form>
      </div>
    </main>
  );
}
