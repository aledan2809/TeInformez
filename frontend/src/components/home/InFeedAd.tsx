'use client';

import { useState, useEffect, useRef } from 'react';
import Link from 'next/link';

interface PromoItem {
  label: string;
  title: string;
  description: string;
  url: string;
  cta: string;
}

const INTERNAL_PROMOS: PromoItem[] = [
  {
    label: '4pro.io',
    title: 'Gestionează afacerea ta cu 4PRO',
    description: 'Platformă all-in-one pentru fitness, nutriție și scheduling. Înregistrare gratuită.',
    url: 'https://4pro.io?utm_source=teinformez&utm_medium=infeed&utm_campaign=promo',
    cta: 'Află mai mult →',
  },
  {
    label: 'app.techbiz.ae',
    title: 'Auditează-ți site-ul cu AVE AI',
    description: 'Analiză completă: performanță, SEO, securitate, UX. Raport detaliat în 2 minute.',
    url: 'https://app.techbiz.ae?utm_source=teinformez&utm_medium=infeed&utm_campaign=promo',
    cta: 'Testează gratuit →',
  },
  {
    label: 'eat.4pro.io',
    title: 'Nutriție personalizată cu AI',
    description: 'Fotografiezi mâncarea, AI-ul calculează macronutrienții și îți oferă recomandări.',
    url: 'https://eat.4pro.io?utm_source=teinformez&utm_medium=infeed&utm_campaign=promo',
    cta: 'Încearcă acum →',
  },
  {
    label: 'procuchain.com',
    title: 'Optimizează-ți lanțul de aprovizionare',
    description: 'ProcuChain conectează cumpărători și furnizori. Automatizează comenzile și livrările.',
    url: 'https://procuchain.com?utm_source=teinformez&utm_medium=infeed&utm_campaign=promo',
    cta: 'Descoperă →',
  },
];

export default function InFeedAd() {
  const adsenseClient = process.env.NEXT_PUBLIC_ADSENSE_CLIENT;
  const adsenseSlot = process.env.NEXT_PUBLIC_ADSENSE_SLOT;

  // If both AdSense env vars are set, render the AdSense block
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

  return <InternalPromoCarousel />;
}

function InternalPromoCarousel() {
  const [activeIndex, setActiveIndex] = useState(0);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const startAutoRotate = () => {
    if (intervalRef.current) clearInterval(intervalRef.current);
    intervalRef.current = setInterval(() => {
      setActiveIndex((prev) => (prev + 1) % INTERNAL_PROMOS.length);
    }, 4000);
  };

  useEffect(() => {
    startAutoRotate();
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, []);

  const goTo = (index: number) => {
    setActiveIndex(index);
    startAutoRotate();
  };

  const promo = INTERNAL_PROMOS[activeIndex];

  return (
    <div className="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 my-2">
      {/* Promoted label */}
      <div className="flex items-center justify-between mb-2">
        <span className="text-xs text-gray-400 dark:text-gray-500 font-medium">{promo.label}</span>
        <span className="text-xs text-gray-400 dark:text-gray-500 italic">(P)</span>
      </div>

      {/* Title */}
      <p className="text-sm font-bold text-gray-900 dark:text-white leading-snug mb-1">
        {promo.title}
      </p>

      {/* Description */}
      <p className="text-xs text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
        {promo.description}
      </p>

      {/* CTA link */}
      <Link
        href={promo.url}
        target="_blank"
        rel="noopener noreferrer sponsored"
        className="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline"
      >
        {promo.cta}
      </Link>

      {/* Dots indicator + navigation */}
      <div className="flex items-center justify-center gap-1.5 mt-3">
        {INTERNAL_PROMOS.map((_, i) => (
          <button
            key={i}
            onClick={() => goTo(i)}
            aria-label={`Promo ${i + 1}`}
            className={`w-1.5 h-1.5 rounded-full transition-colors ${
              i === activeIndex
                ? 'bg-primary-600 dark:bg-primary-400'
                : 'bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500'
            }`}
          />
        ))}
      </div>
    </div>
  );
}
