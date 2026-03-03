'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { X, Mail, Bell } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { useLocalPreferences } from '@/hooks/useLocalPreferences';

export default function SoftRegistrationBanner() {
  const { isAuthenticated } = useAuthStore();
  const { isBannerDismissed, dismissBanner } = useLocalPreferences();
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (isAuthenticated || isBannerDismissed()) return;

    // Show after 3 seconds on the page
    const timer = setTimeout(() => setVisible(true), 3000);
    return () => clearTimeout(timer);
  }, [isAuthenticated]);

  if (!visible || isAuthenticated) return null;

  const handleDismiss = () => {
    dismissBanner();
    setVisible(false);
  };

  return (
    <div className="fixed bottom-0 left-0 right-0 z-50 p-4 sm:p-0">
      <div className="container-custom">
        <div className="relative bg-white dark:bg-gray-800 rounded-t-xl shadow-2xl border border-b-0 border-gray-200 dark:border-gray-700 p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center gap-3">
          <button
            onClick={handleDismiss}
            className="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <X className="h-4 w-4" />
          </button>

          <div className="flex items-center gap-3 flex-1">
            <div className="flex-shrink-0 h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
              <Bell className="h-5 w-5 text-primary-600" />
            </div>
            <div>
              <p className="font-semibold text-sm">Primește știri personalizate</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Inscrie-te gratuit și primește cele mai importante știri pe email.
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 w-full sm:w-auto">
            <Link
              href="/register"
              className="btn-primary text-sm px-4 py-2 flex-1 sm:flex-none text-center"
            >
              <Mail className="h-4 w-4 mr-1.5 inline" />
              Inscrie-te gratuit
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
