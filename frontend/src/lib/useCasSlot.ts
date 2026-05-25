'use client';

import { useEffect, useState } from 'react';
import DOMPurify from 'isomorphic-dompurify';

/**
 * Shared CAS (Carousel of Ads) fetch hook for browser-side slots.
 *
 * Calls the TeInformez WP REST proxy at /wp-json/teinformez/v1/cas/render
 * which forwards to MarketingAutomation server-to-server (MA API key never
 * ships in the client bundle).
 *
 * Visitor token is generated once per browser session (sessionStorage UUID,
 * no PII) and stays stable across all CAS slots on the same page so MA can
 * dedupe impressions per visitor + placement.
 *
 * Consumers: InFeedAd (/news) + BannerSlot (homepage).
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

export type CasPlacement = 'infeed' | 'banner';

export interface CasSlotResult {
  /** HTML returned by MA — null until fetch resolves or when no inventory. */
  html: string | null;
  /** True once the fetch has resolved (success OR failure). */
  loaded: boolean;
}

export function useCasSlot(placement: CasPlacement, index?: number): CasSlotResult {
  const [html, setHtml] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    const visitor = getOrCreateVisitorToken();
    const apiBase = process.env.NEXT_PUBLIC_WP_API_URL || 'https://teinformez.eu/wp-json';
    // Carousel: pass the slot index so MA returns a distinct campaign per slot on the page.
    const nParam = typeof index === 'number' ? `&n=${index}` : '';
    const url = `${apiBase}/teinformez/v1/cas/render?placement=${placement}&visitor=${encodeURIComponent(visitor)}${nParam}`;

    fetch(url, { signal: controller.signal, credentials: 'omit' })
      .then(async (resp) => {
        if (!resp.ok) return '';
        return resp.text();
      })
      .then((body) => {
        if (body && body.length > 0) {
          // G-TI-CAS-XSS-RISK: sanitize MA creative HTML before render.
          // Strips <script>, inline event handlers (default DOMPurify behavior)
          // plus iframe/object/embed (ad slots use img/a only).
          const clean = DOMPurify.sanitize(body, {
            FORBID_TAGS: ['iframe', 'object', 'embed'],
          });
          setHtml(clean);
        }
        setLoaded(true);
      })
      .catch(() => {
        setLoaded(true);
      });

    return () => controller.abort();
  }, [placement, index]);

  return { html, loaded };
}
