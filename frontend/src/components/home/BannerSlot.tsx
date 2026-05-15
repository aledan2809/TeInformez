'use client';

import { useCasSlot } from '@/lib/useCasSlot';

/**
 * Homepage banner CAS slot — rendered between the 2nd and 3rd category section
 * on the homepage. Only fires when NEXT_PUBLIC_CAS_ENABLED=true; renders
 * nothing (no layout shift, no skeleton) when no inventory matches.
 *
 * Container is constrained to typical newsletter-banner dimensions so a
 * future creative HTML payload renders inside a predictable max-width box.
 */
export default function BannerSlot() {
  const casEnabled = process.env.NEXT_PUBLIC_CAS_ENABLED === 'true';
  return casEnabled ? <BannerInner /> : null;
}

function BannerInner() {
  const { html, loaded } = useCasSlot('banner');
  if (!loaded || !html) return null;
  return (
    <div
      className="my-6 max-w-3xl mx-auto"
      role="complementary"
      aria-label="Reclamă"
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
