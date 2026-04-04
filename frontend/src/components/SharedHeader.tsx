'use client';

import Link from 'next/link';
import { Newspaper, User, LogIn } from 'lucide-react';
import { ThemeToggle } from '@/components/ThemeToggle';
import { useAuthStore } from '@/store/authStore';

export default function SharedHeader() {
  const { isAuthenticated, user } = useAuthStore();

  return (
    <header className="border-b bg-white dark:bg-gray-900 sticky top-0 z-50">
      <div className="container-custom flex h-14 items-center justify-between">
        <Link href="/" className="flex items-center space-x-2">
          <Newspaper className="h-7 w-7 text-primary-600" />
          <span className="text-lg font-bold">TeInformez.eu</span>
        </Link>

        <nav className="flex items-center space-x-3">
          <Link href="/news" className="text-sm font-medium hover:text-primary-600 hidden sm:block">
            Toate știrile
          </Link>
          <ThemeToggle />
          {isAuthenticated ? (
            <Link href="/dashboard" className="flex items-center gap-1.5 text-sm font-medium hover:text-primary-600">
              <User className="h-4 w-4" />
              <span className="hidden sm:inline">{user?.name || 'Panou'}</span>
            </Link>
          ) : (
            <>
              <Link href="/login" className="text-sm font-medium hover:text-primary-600 hidden sm:block">
                <span className="flex items-center gap-1">
                  <LogIn className="h-4 w-4" />
                  Autentificare
                </span>
              </Link>
              <Link href="/register" className="btn-primary text-sm px-3 py-1.5">
                Inscrie-te
              </Link>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}
