'use client';

/**
 * InFeedAd slot — injected every 5th news card in /news.
 *
 * Priority order:
 *   1. AdSense (if NEXT_PUBLIC_ADSENSE_CLIENT + NEXT_PUBLIC_ADSENSE_SLOT set)
 *   2. CAS (Carousel of Ads from MarketingAutomation) — if NEXT_PUBLIC_CAS_ENABLED=true
 *   3. Nothing (slot empty until CAS integration ships)
 *
 * Internal 4PRO ecosystem carousel REMOVED 2026-05-14 — reserved for CAS.
 */
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

  // 2. CAS placeholder (when MA exposes /api/cas/render?slot=infeed)
  if (casEnabled) {
    // TODO(CAS): fetch from MA `${process.env.NEXT_PUBLIC_MA_API}/api/cas/render?slot=infeed`
    // and render returned HTML; for now render a minimal placeholder so layout doesn't collapse.
    return (
      <div className="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-4 my-2 text-center text-xs text-gray-400">
        CAS slot
      </div>
    );
  }

  // 3. Default — empty (no banner until CAS or AdSense is wired)
  return null;
}
