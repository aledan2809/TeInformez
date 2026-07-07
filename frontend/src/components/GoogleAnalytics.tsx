'use client';

import Script from 'next/script';
import { usePathname, useSearchParams } from 'next/navigation';
import { useEffect, useRef, useState, Suspense } from 'react';
import { getCookieConsent, COOKIE_CONSENT_EVENT } from '@/lib/cookieConsent';

const GA_MEASUREMENT_ID = process.env.NEXT_PUBLIC_GA_MEASUREMENT_ID;

function PageViewTracker() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const isFirstRender = useRef(true);

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return; // initial page_view handled by gtag('config') in Script
    }
    if (!GA_MEASUREMENT_ID || typeof window.gtag !== 'function') return;
    const url = pathname + (searchParams.toString() ? `?${searchParams.toString()}` : '');
    window.gtag('event', 'page_view', {
      page_location: window.location.href,
      page_path: url,
      send_to: GA_MEASUREMENT_ID,
    });
  }, [pathname, searchParams]);

  return null;
}

/**
 * Sets GA4 user_property `user_type` once we know whether the visitor is a
 * test/dev user (logged-in WP user flagged via teinformez_is_test_user). Uses
 * sessionStorage to avoid refetching on every page nav. Lets the admin filter
 * `user_type = test` out of GA4 reports (per user choice "option B").
 */
function UserTypeTagger() {
  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.gtag !== 'function') return;
    const apply = (t: string) => {
      try { window.gtag?.('set', 'user_properties', { user_type: t }); } catch {}
    };
    let cached: string | null = null;
    try { cached = sessionStorage.getItem('teinformez_user_type'); } catch {}
    if (cached) { apply(cached); return; }
    const base = process.env.NEXT_PUBLIC_WP_API_URL || 'https://teinformez.eu/wp-json';
    fetch(`${base}/teinformez/v1/user/preferences`, { credentials: 'include' })
      .then((r) => (r.ok ? r.json() : null))
      .then((json) => {
        if (!json) return; // not logged-in or upstream error → leave unset (real visitor by default)
        const flag = json?.data?.is_test_user ?? json?.is_test_user;
        const t = flag ? 'test' : 'real';
        try { sessionStorage.setItem('teinformez_user_type', t); } catch {}
        apply(t);
      })
      .catch(() => {});
  }, []);
  return null;
}

export default function GoogleAnalytics() {
  // Consent gate (GDPR/ANSPDCP): GA scripts mount ONLY after an explicit
  // 'accepted' cookie choice. No choice yet == declined. The consent surfaces
  // (TIConsentGate/TIConsentBanner for logged-in, AnonCookieBanner for
  // visitors) write the choice + dispatch COOKIE_CONSENT_EVENT.
  const [consented, setConsented] = useState(false);

  useEffect(() => {
    setConsented(getCookieConsent() === 'accepted');
    const onChange = () => setConsented(getCookieConsent() === 'accepted');
    window.addEventListener(COOKIE_CONSENT_EVENT, onChange);
    return () => window.removeEventListener(COOKIE_CONSENT_EVENT, onChange);
  }, []);

  if (!GA_MEASUREMENT_ID || process.env.NODE_ENV !== 'production') return null;
  if (!consented) return null;

  return (
    <>
      <Script
        src={`https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`}
        strategy="afterInteractive"
      />
      <Script id="gtag-init" strategy="afterInteractive">
        {`
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '${GA_MEASUREMENT_ID}');
        `}
      </Script>
      <Suspense fallback={null}>
        <PageViewTracker />
      </Suspense>
      <UserTypeTagger />
    </>
  );
}
