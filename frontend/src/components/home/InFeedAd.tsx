'use client';

import { useEffect, useState } from 'react';

/**
 * InFeedAd slot — injected every 5th news card in /news.
 *
 * Priority order:
 *   1. AdSense (if NEXT_PUBLIC_ADSENSE_CLIENT + NEXT_PUBLIC_ADSENSE_SLOT set)
 *   2. CAS (Carousel of Ads from MarketingAutomation) — if NEXT_PUBLIC_CAS_ENABLED=true
 *   3. Nothing (slot empty until inventory matches)
 *
 * CAS fetch goes through the TeInformez WP proxy
 * (/wp-json/teinformez/v1/cas/render?placement=infeed) so the MA API key never
 * ships in the client bundle. Visitor token stays in sessionStorage (no PII).
 */

const CAS_VISITOR_KEY = 'teinformez_cas_visitor';

function getOrCreateVisitorToken(): string {
  if (typeof window === 'undefined') return '';
  try {
    let token = sessionStorage.getItem(CAS_VISITOR_KEY);
    if (!token) {
      token = crypto.randomUUID();
      sessionStorage.setItem(CAS_VISITOR_KEY, token);
    }
    return token;
  } catch {
    return '';
  }
}

function CasSlot() {
  const [html, setHtml] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    const visitor = getOrCreateVisitorToken();
    const apiBase = process.env.NEXT_PUBLIC_WP_API_URL || 'https://teinformez.eu/wp-json';
    const url = `${apiBase}/teinformez/v1/cas/render?placement=infeed&visitor=${encodeURIComponent(visitor)}`;

    fetch(url, { signal: controller.signal, credentials: 'omit' })
      .then(async (resp) => {
        if (!resp.ok) return '';
        return resp.text();
      })
      .then((body) => {
        if (body && body.length > 0) setHtml(body);
        setLoaded(true);
      })
      .catch(() => {
        setLoaded(true);
      });

    return () => controller.abort();
  }, []);

  if (!loaded || !html) return null;
  return <div className="my-2" dangerouslySetInnerHTML={{ __html: html }} />;
}

export default function InFeedAd() {
  const adsenseClient = process.env.NEXT_PUBLIC_ADSENSE_CLIENT;
  const adsenseSlot = process.env.NEXT_PUBLIC_ADSENSE_SLOT;
  const casEnabled = process.env.NEXT_PUBLIC_CAS_ENABLED === 'true';

  // 1. AdSense (priority — pays real money)
  if (adsenseClient && adsenseSlot) {
    return (
      <div className="my-2">
        <ins
          className="adsbygoogle"
          style={{ display: 'block' }}
          data-ad-client={adsenseClient}
          data-ad-slot={adsenseSlot}
          data-ad-format="auto"
          data-full-width-responsive="true"
        />
      </div>
    );
  }

  // 2. CAS — dynamic fetch through WP proxy
  if (casEnabled) {
    return <CasSlot />;
  }

  // 3. Default — empty
  return null;
}
