'use client';

import { Suspense, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { Newspaper, CheckCircle2, XCircle, Loader2 } from 'lucide-react';
import { api } from '@/lib/api';

function ConfirmContent() {
  const searchParams = useSearchParams();
  const token = searchParams.get('token');

  const [status, setStatus] = useState<'loading' | 'success' | 'error'>('loading');
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (!token) {
      setStatus('error');
      setMessage('Link-ul de confirmare nu este valid.');
      return;
    }

    api.newsletterConfirm(token)
      .then((msg) => {
        setMessage(msg || 'Abonamentul tău a fost confirmat cu succes!');
        setStatus('success');
      })
      .catch((err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
        setMessage(msg || 'Link-ul de confirmare nu este valid sau a expirat.');
        setStatus('error');
      });
  }, [token]);

  return (
    <>
      {status === 'loading' && (
        <>
          <Loader2 className="h-16 w-16 text-primary-500 mx-auto mb-4 animate-spin" />
          <p className="text-gray-600 dark:text-gray-400">Se confirmă abonamentul...</p>
        </>
      )}

      {status === 'success' && (
        <>
          <CheckCircle2 className="h-16 w-16 text-green-500 mx-auto mb-4" />
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Abonament confirmat!</h1>
          <p className="text-gray-600 dark:text-gray-400 mb-8">{message}</p>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
            Vrei să alegi categorii, frecvență și canal de livrare?{' '}
            <Link href="/register" className="text-primary-600 hover:underline">
              Creează un cont gratuit
            </Link>
            .
          </p>
          <Link href="/" className="btn-primary">
            Citește știrile
          </Link>
        </>
      )}

      {status === 'error' && (
        <>
          <XCircle className="h-16 w-16 text-red-500 mx-auto mb-4" />
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Link invalid</h1>
          <p className="text-gray-600 dark:text-gray-400 mb-8">{message}</p>
          <Link href="/newsletter" className="btn-primary">
            Încearcă din nou
          </Link>
        </>
      )}
    </>
  );
}

export default function NewsletterConfirmPage() {
  return (
    <main className="min-h-screen bg-gradient-to-b from-primary-50 to-white dark:from-gray-900 dark:to-gray-950 flex flex-col justify-center py-12 px-4">
      <div className="max-w-md mx-auto text-center">
        <Link href="/" className="inline-flex items-center justify-center space-x-2 mb-8">
          <Newspaper className="h-8 w-8 text-primary-600" />
          <span className="text-xl font-bold text-gray-900 dark:text-white">TeInformez.eu</span>
        </Link>
        <Suspense
          fallback={
            <Loader2 className="h-16 w-16 text-primary-500 mx-auto animate-spin" />
          }
        >
          <ConfirmContent />
        </Suspense>
      </div>
    </main>
  );
}
