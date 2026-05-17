'use client';

import { useState, useEffect } from 'react';
import { useAuthStore } from '@/store/authStore';

export type SubscriptionTier = 'free' | 'premium';
export type SubscriptionStatus =
  | 'none'
  | 'active'
  | 'canceled'
  | 'past_due'
  | 'trialing'
  | 'incomplete'
  | 'incomplete_expired'
  | 'unpaid'
  | 'paused';

export interface SubscriptionState {
  tier: SubscriptionTier;
  status: SubscriptionStatus;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
  isPremium: boolean;
  loading: boolean;
  error: string | null;
}

// Module-level cache: avoids re-fetching on every page that mounts the hook
let cachedSub: { tier: SubscriptionTier; status: SubscriptionStatus; current_period_end: string | null; cancel_at_period_end: boolean } | null = null;
let cacheExpiry = 0;
const CACHE_TTL_MS = 60_000; // 1 minute

function clearSubscriptionCache() {
  cachedSub = null;
  cacheExpiry = 0;
}

export { clearSubscriptionCache };

export function useSubscription(): SubscriptionState {
  const { user } = useAuthStore();
  const [sub, setSub] = useState(cachedSub);
  const [loading, setLoading] = useState(!cachedSub);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user) {
      cachedSub = null;
      cacheExpiry = 0;
      setSub(null);
      setLoading(false);
      return;
    }

    // Return cached value if still fresh
    if (cachedSub && Date.now() < cacheExpiry) {
      setSub(cachedSub);
      setLoading(false);
      return;
    }

    setLoading(true);
    const wpApi = process.env.NEXT_PUBLIC_WP_API_URL ?? 'https://teinformez.eu/wp-json';
    const cookieToken =
      typeof document !== 'undefined'
        ? document.cookie.split('; ').find(r => r.startsWith('teinformez_token='))?.split('=')[1]
        : undefined;

    fetch(`${wpApi}/teinformez/v1/subscription/status`, {
      headers: cookieToken ? { Authorization: `Bearer ${cookieToken}` } : {},
      credentials: 'include',
    })
      .then(res => {
        if (!res.ok) throw new Error('Nu s-a putut încărca statusul abonamentului');
        return res.json();
      })
      .then(data => {
        cachedSub = data;
        cacheExpiry = Date.now() + CACHE_TTL_MS;
        setSub(data);
        setError(null);
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Eroare necunoscută');
      })
      .finally(() => {
        setLoading(false);
      });
  }, [user]);

  const tier = sub?.tier ?? 'free';
  const status = sub?.status ?? 'none';

  return {
    tier,
    status,
    current_period_end: sub?.current_period_end ?? null,
    cancel_at_period_end: sub?.cancel_at_period_end ?? false,
    isPremium: tier === 'premium' && (status === 'active' || status === 'trialing'),
    loading,
    error,
  };
}
