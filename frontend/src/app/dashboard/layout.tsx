'use client';

import { useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import Link from 'next/link';
import { useAuthStore } from '@/store/authStore';
import Sidebar from '@/components/dashboard/Sidebar';
import { Loader2, Menu, Newspaper, X } from 'lucide-react';

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { isAuthenticated, isLoading } = useAuthStore();
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  // Gate the auth check on zustand-persist rehydration. On a hard load / refresh
  // / deep-link the store starts at isAuthenticated=false and only rehydrates
  // from localStorage a tick later — redirecting before that bounces a
  // logged-in user to /login on every refresh. Wait for hydration first.
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    const markHydrated = () => setHydrated(true);
    const unsub = useAuthStore.persist.onFinishHydration(markHydrated);
    if (useAuthStore.persist.hasHydrated()) markHydrated();
    return unsub;
  }, []);

  useEffect(() => {
    if (hydrated && !isLoading && !isAuthenticated) {
      router.push('/login');
    }
  }, [hydrated, isAuthenticated, isLoading, router]);

  // Close the mobile drawer on any route change (covers back/forward too).
  useEffect(() => {
    setMobileNavOpen(false);
  }, [pathname]);

  if (!hydrated || isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return null;
  }

  return (
    <div className="flex min-h-screen bg-gray-50 dark:bg-gray-950">
      {/* Desktop sidebar */}
      <div className="hidden md:block">
        <Sidebar />
      </div>

      {/* Mobile drawer */}
      {mobileNavOpen && (
        <div className="fixed inset-0 z-50 md:hidden">
          <div
            className="absolute inset-0 bg-black/50"
            onClick={() => setMobileNavOpen(false)}
            aria-hidden="true"
          />
          <div className="absolute inset-y-0 left-0 w-64 max-w-[85vw] overflow-y-auto shadow-xl">
            <Sidebar onNavigate={() => setMobileNavOpen(false)} />
            <button
              onClick={() => setMobileNavOpen(false)}
              aria-label="Închide meniul"
              className="absolute top-4 right-3 p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>
      )}

      <div className="flex-1 flex flex-col min-w-0">
        {/* Mobile top bar */}
        <div className="md:hidden sticky top-0 z-40 flex items-center justify-between bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-4 py-3">
          <Link href="/dashboard" className="flex items-center gap-2">
            <Newspaper className="h-6 w-6 text-primary-600" />
            <span className="text-lg font-bold">TeInformez</span>
          </Link>
          <button
            onClick={() => setMobileNavOpen(true)}
            aria-label="Deschide meniul"
            className="p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
          >
            <Menu className="h-6 w-6" />
          </button>
        </div>

        <main className="flex-1 overflow-auto">
          {children}
        </main>
      </div>
    </div>
  );
}
