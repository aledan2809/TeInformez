'use client';

import { useState } from 'react';
import { TIConsentModal } from './TIConsentModal';
import { setCookieConsent } from '@/lib/cookieConsent';

interface TIConsentBannerProps {
  docType: string;
  versionId: string;
  userId: number | null;
  onAgree: () => void;
  onDismiss: () => void;
}

export function TIConsentBanner({ docType, versionId, userId, onAgree, onDismiss }: TIConsentBannerProps) {
  const [showModal, setShowModal] = useState(false);

  const docLabel =
    docType === 'TOS'
      ? 'Termenii și Condițiile'
      : docType === 'PRIVACY'
        ? 'Politica de Confidențialitate'
        : 'Politica de Cookies';

  async function handleAgree() {
    const consentText = `Am citit și sunt de acord cu ${docLabel} ale platformei TeInformez (versiunea ${versionId}).`;
    if (userId) {
      try {
        await fetch('/api/consent/record', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            documentVersionId: versionId,
            consentText,
            method: 'IN_APP',
            userId,
          }),
        });
      } catch {
        // non-blocking
      }
    }
    localStorage.setItem(`consent_accepted_ti_${versionId}`, 'true');
    if (docType === 'COOKIES') {
      // Open the GA consent gate — analytics may load from this point on.
      setCookieConsent('accepted');
    }
    onAgree();
  }

  function handleDismiss() {
    localStorage.setItem(`consent_dismissed_ti_${docType}`, 'true');
    onDismiss();
  }

  if (showModal) {
    return (
      <TIConsentModal
        docType={docType}
        onAgree={() => {
          setShowModal(false);
          handleAgree();
        }}
        onDisagree={() => {
          setShowModal(false);
          handleDismiss();
        }}
        onClose={() => setShowModal(false)}
      />
    );
  }

  return (
    <div className="fixed bottom-4 left-4 right-4 z-[70] md:left-auto md:right-6 md:max-w-sm">
      <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-xl">
        <p className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
          Am actualizat{' '}
          <button
            onClick={() => setShowModal(true)}
            className="font-semibold text-blue-600 dark:text-blue-400 underline underline-offset-2 hover:text-blue-700 dark:hover:text-blue-300 transition-colors"
          >
            {docLabel}
          </button>
          . Te rugăm să le citești și să confirmi acordul.
        </p>
        <div className="mt-3 flex gap-2">
          <button
            onClick={() => setShowModal(true)}
            className="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700 transition-colors"
          >
            Citește &amp; Acceptă
          </button>
          <button
            onClick={handleDismiss}
            className="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
          >
            Mai târziu
          </button>
        </div>
      </div>
    </div>
  );
}
