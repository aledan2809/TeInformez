'use client';

import { useEffect, useState } from 'react';
import { Gift, Copy, Check, Users, Sparkles, Loader2 } from 'lucide-react';
import { api } from '@/lib/api';
import type { Referral } from '@/types';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

export default function InvitePage() {
  const [referral, setReferral] = useState<Referral | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    api
      .getReferral()
      .then(setReferral)
      .catch(() => setError('Nu am putut încărca link-ul de invitație. Încearcă din nou.'))
      .finally(() => setIsLoading(false));
  }, []);

  const inviteLink = referral ? `${SITE_URL}/register?ref=${referral.code}` : '';

  const copyLink = async () => {
    if (!inviteLink) return;
    try {
      await navigator.clipboard.writeText(inviteLink);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // clipboard unavailable — the field is selectable as a fallback
    }
  };

  const formatDate = (iso: string | null): string | null => {
    if (!iso) return null;
    const d = new Date(iso.replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) return null;
    return d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-primary-500" />
      </div>
    );
  }

  if (error || !referral) {
    return (
      <div className="max-w-2xl">
        <p className="text-red-600 dark:text-red-400">{error || 'A apărut o eroare.'}</p>
      </div>
    );
  }

  const premiumUntil = formatDate(referral.granted_until);

  return (
    <div className="max-w-2xl space-y-6">
      {/* Header */}
      <div className="flex items-start gap-3">
        <div className="rounded-xl bg-primary-100 dark:bg-primary-900/40 p-3">
          <Gift className="h-7 w-7 text-primary-600 dark:text-primary-400" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Invită prieteni</h1>
          <p className="text-gray-600 dark:text-gray-400">
            Pentru fiecare prieten care își face cont prin link-ul tău, primiți{' '}
            <strong>amândoi {referral.reward_days} zile de Premium gratuit</strong>. Poți aduna
            până la {referral.cap_days} de zile.
          </p>
        </div>
      </div>

      {/* Invite link */}
      <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Link-ul tău de invitație
        </label>
        <div className="flex flex-col sm:flex-row gap-2">
          <input
            type="text"
            readOnly
            value={inviteLink}
            onFocus={(e) => e.target.select()}
            className="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 px-3 py-2 text-sm text-gray-800 dark:text-gray-200"
          />
          <button
            onClick={copyLink}
            className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-700 px-4 py-2 text-sm font-medium text-white transition-colors"
          >
            {copied ? (
              <>
                <Check className="h-4 w-4" /> Copiat!
              </>
            ) : (
              <>
                <Copy className="h-4 w-4" /> Copiază
              </>
            )}
          </button>
        </div>
        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Trimite-l pe WhatsApp, Facebook sau oriunde. Prietenul se înscrie, apăsați amândoi
          „start" la Premium.
        </p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
          <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm">
            <Users className="h-4 w-4" /> Prieteni aduși
          </div>
          <div className="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
            {referral.referred}
          </div>
        </div>
        <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
          <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm">
            <Sparkles className="h-4 w-4" /> Premium gratuit
          </div>
          <div className="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
            {premiumUntil ? `Activ până la ${premiumUntil}` : 'Încă niciunul'}
          </div>
        </div>
      </div>
    </div>
  );
}
