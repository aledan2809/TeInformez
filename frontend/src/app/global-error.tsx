'use client';

import { useEffect } from 'react';
import * as Sentry from '@sentry/nextjs';

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    Sentry.captureException(error);
    fetch('/api/errors/report', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: error.message,
        digest: error.digest,
        url: typeof window !== 'undefined' ? window.location.href : 'unknown',
        ts: new Date().toISOString(),
        level: 'critical',
      }),
    }).catch(() => {});
  }, [error]);

  return (
    <html lang="ro">
      <body>
        <main style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: 'sans-serif', textAlign: 'center', padding: '1rem' }}>
          <div>
            <h2 style={{ fontSize: '1.25rem', fontWeight: 'bold', marginBottom: '0.5rem' }}>Eroare critică</h2>
            <p style={{ color: '#666', marginBottom: '1rem', fontSize: '0.875rem' }}>
              A apărut o eroare neașteptată. Am fost notificați.
            </p>
            <button onClick={reset} style={{ padding: '0.5rem 1rem', background: '#2563eb', color: '#fff', border: 'none', borderRadius: '0.375rem', cursor: 'pointer' }}>
              Încearcă din nou
            </button>
          </div>
        </main>
      </body>
    </html>
  );
}
