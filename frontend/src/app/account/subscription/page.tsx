'use client';

import { useState, useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Check, Zap, Calendar, AlertCircle, Loader2, ExternalLink } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';

interface SubscriptionStatus {
  tier: 'free' | 'premium';
  status: 'none' | 'active' | 'canceled' | 'past_due' | 'trialing' | 'incomplete' | 'incomplete_expired' | 'unpaid' | 'paused';
  current_period_end: string | null;
  cancel_at_period_end: boolean;
}

export default function SubscriptionPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { user } = useAuthStore();
  const [sub, setSub] = useState<SubscriptionStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [portalLoading, setPortalLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const checkoutSuccess = searchParams.get('checkout') === 'success';

  useEffect(() => {
    if (!user) {
      router.push('/login?redirect=/account/subscription');
      return;
    }
    fetchStatus();
  }, [user]);

  const fetchStatus = async () => {
    try {
      const wpApi = process.env.NEXT_PUBLIC_WP_API_URL ?? 'https://teinformez.eu/wp-json';
      const cookieToken = typeof document !== 'undefined'
        ? document.cookie.split('; ').find(r => r.startsWith('teinformez_token='))?.split('=')[1]
        : undefined;
      const res = await fetch(`${wpApi}/teinformez/v1/subscription/status`, {
        headers: cookieToken ? { Authorization: `Bearer ${cookieToken}` } : {},
        credentials: 'include',
      });
      if (!res.ok) throw new Error('Nu s-a putut încărca statusul abonamentului');
      const data = await res.json();
      setSub(data);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Eroare necunoscută');
    } finally {
      setLoading(false);
    }
  };

  const openBillingPortal = async () => {
    setPortalLoading(true);
    try {
      const res = await fetch('/api/stripe/portal', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: user?.email }),
      });
      const data = await res.json();
      if (!res.ok || !data.url) throw new Error(data.error ?? 'Eroare portal');
      window.location.href = data.url;
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Nu s-a putut deschide portalul de facturare');
      setPortalLoading(false);
    }
  };

  if (!user) return null;

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex items-center justify-center">
        <Loader2 className="w-8 h-8 animate-spin text-blue-500" />
      </div>
    );
  }

  const isPremium = sub?.tier === 'premium' && sub?.status === 'active';
  const isCanceling = isPremium && sub?.cancel_at_period_end;
  const periodEnd = sub?.current_period_end
    ? new Date(sub.current_period_end).toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' })
    : null;

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4">
      <div className="max-w-2xl mx-auto">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-8">
          Abonamentul meu
        </h1>

        {checkoutSuccess && (
          <div className="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg flex items-start gap-3">
            <Check className="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-green-700 dark:text-green-300 font-medium text-sm">Plată procesată cu succes!</p>
              <p className="text-green-600 dark:text-green-400 text-xs mt-1">
                Abonamentul tău Premium este acum activ. Bucură-te de toate funcționalitățile.
              </p>
            </div>
          </div>
        )}

        {error && (
          <div className="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
            <p className="text-red-700 dark:text-red-300 text-sm">{error}</p>
          </div>
        )}

        <div className="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <div className="flex items-center gap-3 mb-4">
            <div className={`p-2 rounded-xl ${isPremium ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-gray-100 dark:bg-gray-700'}`}>
              <Zap className={`w-5 h-5 ${isPremium ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'}`} />
            </div>
            <div>
              <h2 className="font-semibold text-gray-900 dark:text-white">
                {isPremium ? 'TeInformez Premium' : 'Plan Gratuit'}
              </h2>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                {isPremium
                  ? isCanceling
                    ? 'Activ — se anulează'
                    : 'Activ'
                  : 'Fără abonament activ'}
              </p>
            </div>
            <span className={`ml-auto text-xs font-semibold px-3 py-1 rounded-full ${
              isPremium
                ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
            }`}>
              {isPremium ? 'Premium' : 'Free'}
            </span>
          </div>

          {isPremium && periodEnd && (
            <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-4">
              <Calendar className="w-4 h-4" />
              {isCanceling
                ? `Accesul expiră pe ${periodEnd}`
                : `Următoarea reînnoire: ${periodEnd}`}
            </div>
          )}

          {isCanceling && (
            <div className="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-yellow-700 dark:text-yellow-300 text-sm mb-4">
              Abonamentul tău se anulează la sfârșitul perioadei curente. Poți reactiva oricând din portalul de facturare.
            </div>
          )}

          {isPremium ? (
            <button
              onClick={openBillingPortal}
              disabled={portalLoading}
              className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-60"
            >
              {portalLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : <ExternalLink className="w-4 h-4" />}
              Gestionează abonamentul (facturi, anulare, schimbare plan)
            </button>
          ) : (
            <button
              onClick={() => router.push('/subscribe')}
              className="w-full py-3 rounded-xl bg-blue-500 hover:bg-blue-600 text-white font-semibold text-sm transition-colors"
            >
              Upgradează la Premium
            </button>
          )}
        </div>

        {isPremium && (
          <div className="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
            <h3 className="font-semibold text-gray-900 dark:text-white mb-4">Ce include planul tău</h3>
            <ul className="space-y-2">
              {[
                'Filtre avansate de categorii',
                'Export știri în PDF',
                'Notificări prioritare',
                'Fără reclame',
                'Arhivă completă',
              ].map((f) => (
                <li key={f} className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                  <Check className="w-4 h-4 text-green-500 flex-shrink-0" />
                  {f}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
