'use client';

import { useCasSlot } from '@/lib/useCasSlot';

/**
 * InFeedAd slot — injected every 5th news card in /news.
 *
 * Priority order:
 *   1. AdSense (if NEXT_PUBLIC_ADSENSE_CLIENT + NEXT_PUBLIC_ADSENSE_SLOT set)
 *   2. CAS (Carousel of Ads from MarketingAutomation) — if NEXT_PUBLIC_CAS_ENABLED=true
 *   3. Nothing (slot empty until inventory matches)
 *
 * CAS fetch goes through the TeInformez WP proxy via useCasSlot(); the MA API
 * key never ships in the client bundle.
 */

function CasInFeedSlot() {
  const { html, loaded } = useCasSlot('infeed');
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
    return <CasInFeedSlot />;
  }

  // 3. Default — empty
  return null;
}
