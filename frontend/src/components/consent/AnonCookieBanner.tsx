'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useAuthStore } from '@/store/authStore';
import { getCookieConsent, setCookieConsent } from '@/lib/cookieConsent';

/**
 * Cookie banner for ANONYMOUS visitors (GDPR/ANSPDCP).
 *
 * TIConsentGate only ever shows Legal-Hub consent banners to logged-in users —
 * anonymous visitors previously got NO cookie prompt while GA loaded
 * unconditionally. This banner closes that gap: analytics cookies are asked
 * for explicitly; GA loads only after "Accept" (see GoogleAnalytics gate).
 * Logged-in users are handled by the Legal flow, so this stays hidden for them.
 */
export function AnonCookieBanner() {
  const user = useAuthStore((s) => s.user);
  const isLoading = useAuthStore((s) => s.isLoading);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    // Wait for auth hydration so the banner never flashes for logged-in users;
    // only when no choice was ever made. localStorage is client-only → effect.
    if (isLoading) return;
    setVisible(!user && getCookieConsent() === null);
  }, [user, isLoading]);

  if (!visible) return null;

  const choose = (choice: 'accepted' | 'declined') => {
    setCookieConsent(choice);
    setVisible(false);
  };

  return (
    <div className="fixed bottom-4 left-4 right-4 z-[60] md:left-auto md:right-6 md:max-w-sm">
      <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-xl">
        <p className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
          Folosim cookie-uri doar pentru statistici de trafic. Detalii în{' '}
          <Link
            href="/gdpr"
            className="font-semibold text-blue-600 dark:text-blue-400 underline underline-offset-2 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
          >
            politica de confidențialitate
          </Link>
          .
        </p>
        <div className="mt-3 flex gap-2">
          <button
            onClick={() => choose('accepted')}
            className="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700 transition-colors"
          >
            Accept
          </button>
          <button
            onClick={() => choose('declined')}
            className="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
          >
            Refuz
          </button>
        </div>
      </div>
    </div>
  );
}
