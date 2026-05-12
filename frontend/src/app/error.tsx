'use client';

import { useEffect } from 'react';
import * as Sentry from '@sentry/nextjs';
import Link from 'next/link';
import { AlertTriangle } from 'lucide-react';

export default function ErrorBoundary({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    Sentry.captureException(error);
  }, [error]);

  return (
    <main className="min-h-screen flex items-center justify-center px-4">
      <div className="text-center max-w-md">
        <AlertTriangle className="h-12 w-12 text-red-500 mx-auto mb-4" />
        <h2 className="text-xl font-bold text-gray-900 dark:text-white mb-2">
          Ceva nu a mers bine
        </h2>
        <p className="text-gray-600 dark:text-gray-400 mb-6 text-sm">
          A apărut o eroare neașteptată. Am fost notificați și lucrăm la rezolvare.
        </p>
        <div className="flex gap-3 justify-center">
          <button onClick={reset} className="btn-primary text-sm px-4 py-2">
            Încearcă din nou
          </button>
          <Link href="/" className="btn-secondary text-sm px-4 py-2">
            Înapoi acasă
          </Link>
        </div>
      </div>
    </main>
  );
}
