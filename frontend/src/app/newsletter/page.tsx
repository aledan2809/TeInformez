'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { Newspaper, CheckCircle2, Loader2, Mail } from 'lucide-react';
import { api } from '@/lib/api';
import { captureUTM, getStoredUTM } from '@/lib/utm';

export default function NewsletterPage() {
  const [email, setEmail] = useState('');
  const [gdprConsent, setGdprConsent] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    captureUTM();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !gdprConsent) return;

    setIsLoading(true);
    setError('');

    try {
      const utm = getStoredUTM();
      await api.newsletterSubscribe(email.trim(), gdprConsent, utm || undefined);
      setSuccess(true);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setError(msg || 'A apărut o eroare. Încearcă din nou.');
    } finally {
      setIsLoading(false);
    }
  };

  if (success) {
    return (
      <main className="min-h-screen bg-gradient-to-b from-primary-50 to-white dark:from-gray-900 dark:to-gray-950 flex flex-col justify-center py-12 px-4">
        <div className="max-w-md mx-auto text-center">
          <CheckCircle2 className="h-16 w-16 text-green-500 mx-auto mb-4" />
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Verifică inbox-ul!</h1>
          <p className="text-gray-600 dark:text-gray-400 mb-6">
            Ți-am trimis un email de confirmare. Dă clic pe linkul din email pentru a activa abonamentul.
          </p>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-8">
            Vrei mai mult control?{' '}
            <Link href="/register" className="text-primary-600 hover:underline">
              Creează un cont gratuit
            </Link>{' '}
            și alegi categorii, frecvență și canal de livrare.
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
            Primești zilnic un rezumat cu știrile importante din România și lume, sintetizate de AI.
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

          {/* Account upsell */}
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Vrei să alegi categorii, frecvență sau canal Telegram?{' '}
            <Link href="/register" className="text-primary-600 hover:underline">
              Creează cont gratuit →
            </Link>
          </p>

          {/* GDPR consent */}
          <div className="flex items-start gap-3">
            <input
              id="gdpr"
              type="checkbox"
              required
              checked={gdprConsent}
              onChange={(e) => setGdprConsent(e.target.checked)}
              className="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer"
            />
            <label htmlFor="gdpr" className="text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
              Am citit și sunt de acord cu{' '}
              <Link href="/privacy" className="underline hover:text-gray-800 dark:hover:text-gray-200">
                Politica de confidențialitate
              </Link>
              . Îmi pot retrage consimțământul oricând prin dezabonare.
            </label>
          </div>

          {error && (
            <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
          )}

          <button
            type="submit"
            disabled={isLoading || !email.trim() || !gdprConsent}
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
            Vei primi un email de confirmare. Te poți dezabona oricând.
          </p>
        </form>
      </div>
    </main>
  );
}
