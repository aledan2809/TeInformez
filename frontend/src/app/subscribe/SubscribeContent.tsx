'use client';

import { useState, useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Check, Loader2, Zap, Star } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';

const PLANS = [
  {
    id: 'monthly',
    name: 'Premium Lunar',
    price: '9 RON',
    period: 'pe lună',
    priceEnv: 'NEXT_PUBLIC_STRIPE_PRICE_PREMIUM_MONTHLY',
    popular: false,
    features: [
      'Filtre avansate de categorii',
      'Export știri în PDF',
      'Notificări prioritare',
      'Fără reclame',
      'Arhivă completă',
    ],
  },
  {
    id: 'yearly',
    name: 'Premium Anual',
    price: '99 RON',
    period: 'pe an',
    priceEnv: 'NEXT_PUBLIC_STRIPE_PRICE_PREMIUM_YEARLY',
    popular: true,
    savings: 'Economisești 9 RON',
    features: [
      'Filtre avansate de categorii',
      'Export știri în PDF',
      'Notificări prioritare',
      'Fără reclame',
      'Arhivă completă',
      '2 luni gratuite',
    ],
  },
];

export default function SubscribeContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { user } = useAuthStore();
  const [loading, setLoading] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const canceledCheckout = searchParams.get('checkout') === 'canceled';

  useEffect(() => {
    if (!user) {
      router.push('/login?redirect=/subscribe');
    }
  }, [user, router]);

  const handleSubscribe = async (planId: string) => {
    if (!user) {
      router.push('/login?redirect=/subscribe');
      return;
    }

    const plan = PLANS.find(p => p.id === planId);
    if (!plan) return;

    const priceId = process.env[plan.priceEnv as keyof typeof process.env] as string | undefined;
    if (!priceId) {
      setError('Prețul nu este configurat încă. Revino curând.');
      return;
    }

    setLoading(planId);
    setError(null);

    try {
      const res = await fetch('/api/stripe/checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          priceId,
          userId: user.id,
          email: user.email,
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.url) {
        throw new Error(data.error ?? 'Eroare la inițializarea plății');
      }

      window.location.href = data.url;
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Ceva a mers greșit');
      setLoading(null);
    }
  };

  if (!user) return null;

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4">
      <div className="max-w-3xl mx-auto">
        <div className="text-center mb-10">
          <div className="inline-flex items-center gap-2 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 px-4 py-1.5 rounded-full text-sm font-medium mb-4">
            <Zap className="w-4 h-4" />
            TeInformez Premium
          </div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-3">
            Știri fără limite
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-lg">
            Deblochează toate funcționalitățile și personalizează-ți experiența
          </p>
        </div>

        {canceledCheckout && (
          <div className="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-yellow-700 dark:text-yellow-300 text-sm text-center">
            Plata a fost anulată. Poți încerca din nou oricând.
          </div>
        )}

        {error && (
          <div className="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300 text-sm text-center">
            {error}
          </div>
        )}

        <div className="grid md:grid-cols-2 gap-6">
          {PLANS.map((plan) => (
            <div
              key={plan.id}
              className={`relative bg-white dark:bg-gray-800 rounded-2xl border-2 p-6 flex flex-col ${
                plan.popular
                  ? 'border-blue-500 shadow-lg shadow-blue-500/10'
                  : 'border-gray-200 dark:border-gray-700'
              }`}
            >
              {plan.popular && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <span className="inline-flex items-center gap-1 bg-blue-500 text-white text-xs font-semibold px-3 py-1 rounded-full">
                    <Star className="w-3 h-3 fill-white" />
                    Cel mai popular
                  </span>
                </div>
              )}

              <div className="mb-5">
                <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-1">
                  {plan.name}
                </h2>
                <div className="flex items-baseline gap-1">
                  <span className="text-3xl font-extrabold text-gray-900 dark:text-white">
                    {plan.price}
                  </span>
                  <span className="text-gray-400 text-sm">{plan.period}</span>
                </div>
                {plan.savings && (
                  <span className="text-green-600 dark:text-green-400 text-sm font-medium">
                    {plan.savings}
                  </span>
                )}
              </div>

              <ul className="flex-1 space-y-2 mb-6">
                {plan.features.map((f) => (
                  <li key={f} className="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <Check className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                    {f}
                  </li>
                ))}
              </ul>

              <button
                onClick={() => handleSubscribe(plan.id)}
                disabled={loading !== null}
                className={`w-full py-3 rounded-xl font-semibold text-sm transition-all flex items-center justify-center gap-2 ${
                  plan.popular
                    ? 'bg-blue-500 hover:bg-blue-600 text-white'
                    : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white'
                } disabled:opacity-60 disabled:cursor-not-allowed`}
              >
                {loading === plan.id ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Se încarcă...
                  </>
                ) : (
                  'Abonează-te acum'
                )}
              </button>
            </div>
          ))}
        </div>

        <p className="text-center text-xs text-gray-400 mt-6">
          Plăți procesate securizat prin Stripe. Poți anula oricând din contul tău.
        </p>
      </div>
    </div>
  );
}
