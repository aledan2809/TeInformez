'use client';

import { useEffect, useState } from 'react';
import { useAuthStore } from '@/store/authStore';
import { TIConsentBanner } from './TIConsentBanner';
import { getCookieConsent, setCookieConsent } from '@/lib/cookieConsent';

interface ConsentItem {
  type: string;
  latestVersionId: string;
  accepted: boolean;
}

const CACHE_KEY = 'ti_consent_status_cache';
const CACHE_TTL_MS = 24 * 60 * 60 * 1000;

function loadCache(): { items: ConsentItem[]; ts: number } | null {
  try {
    const raw = localStorage.getItem(CACHE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function saveCache(items: ConsentItem[]) {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify({ items, ts: Date.now() }));
  } catch {
    // storage unavailable
  }
}

export function TIConsentGate() {
  const user = useAuthStore((s) => s.user);
  const [pending, setPending] = useState<ConsentItem[]>([]);
  const [checked, setChecked] = useState(false);

  useEffect(() => {
    async function check() {
      let items: ConsentItem[] = [];

      if (user?.id) {
        try {
          const res = await fetch(`/api/consent/status?userId=${user.id}`, { cache: 'no-store' });
          if (res.ok) {
            const data = await res.json();
            items = Array.isArray(data.status) ? data.status : [];
            saveCache(items);
          } else {
            throw new Error(`status ${res.status}`);
          }
        } catch {
          const cached = loadCache();
          if (cached && Date.now() - cached.ts < CACHE_TTL_MS) {
            items = cached.items;
          } else {
            setChecked(true);
            return;
          }
        }
      } else {
        // Anonymous visitor — check only localStorage for locally-dismissed items
        // No server-side recording possible without a user ID
        const cached = loadCache();
        if (cached && Date.now() - cached.ts < CACHE_TTL_MS) {
          items = cached.items;
        } else {
          setChecked(true);
          return;
        }
      }

      // If Legal already holds an accepted COOKIES consent for this user,
      // mirror it into the local cookie-consent flag so the GA gate opens
      // without re-asking (server record is authoritative).
      const cookiesItem = items.find((i) => i.type === 'COOKIES');
      if (
        cookiesItem &&
        (cookiesItem.accepted ||
          localStorage.getItem(`consent_accepted_ti_${cookiesItem.latestVersionId}`) === 'true') &&
        getCookieConsent() !== 'accepted'
      ) {
        setCookieConsent('accepted');
      }

      const needsConsent = items.filter((item) => {
        if (item.accepted) return false;
        if (localStorage.getItem(`consent_accepted_ti_${item.latestVersionId}`) === 'true') return false;
        if (localStorage.getItem(`consent_dismissed_ti_${item.type}`) === 'true') return false;
        return true;
      });

      setPending(needsConsent);
      setChecked(true);
    }

    check();
  }, [user?.id]);

  if (!checked || pending.length === 0) return null;

  const current = pending[0];

  function handleResolved() {
    setPending((prev) => prev.slice(1));
  }

  return (
    <TIConsentBanner
      key={current.latestVersionId}
      docType={current.type}
      versionId={current.latestVersionId}
      userId={user?.id ?? null}
      onAgree={handleResolved}
      onDismiss={handleResolved}
    />
  );
}
