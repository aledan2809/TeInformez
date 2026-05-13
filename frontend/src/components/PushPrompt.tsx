'use client';

import { useState, useEffect } from 'react';
import {
  shouldShowPushPrompt,
  incrementPageViewCount,
  dismissPush,
  subscribeToPush,
  registerServiceWorker,
} from '@/lib/pushNotifications';

export default function PushPrompt() {
  const [visible, setVisible] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Register SW on every load (idempotent)
    registerServiceWorker();

    // Increment visit count and check if we should show the prompt
    incrementPageViewCount();

    // Small delay so the page content loads first
    const timer = setTimeout(() => {
      if (shouldShowPushPrompt()) {
        setVisible(true);
      }
    }, 3000);

    return () => clearTimeout(timer);
  }, []);

  if (!visible) return null;

  const handleAllow = async () => {
    setLoading(true);
    try {
      const permission = await Notification.requestPermission();
      if (permission === 'granted') {
        await subscribeToPush();
      } else {
        dismissPush();
      }
    } finally {
      setLoading(false);
      setVisible(false);
    }
  };

  const handleDismiss = () => {
    dismissPush();
    setVisible(false);
  };

  return (
    <div
      role="dialog"
      aria-label="Activează notificările"
      className="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-80 z-50 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl p-4"
    >
      <div className="flex items-start gap-3">
        <div className="flex-shrink-0 text-2xl" aria-hidden>🔔</div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-gray-900 dark:text-white leading-snug">
            Fii primul care află știrile
          </p>
          <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            Activează notificările și primești alertele pentru categoriile tale.
          </p>
          <div className="mt-3 flex gap-2">
            <button
              onClick={handleAllow}
              disabled={loading}
              className="flex-1 py-1.5 px-3 rounded-lg bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors disabled:opacity-50"
            >
              {loading ? 'Se activează…' : 'Activează'}
            </button>
            <button
              onClick={handleDismiss}
              className="py-1.5 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
              Nu, mulțumesc
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
