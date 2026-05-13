'use client';

import { useReportWebVitals } from 'next/web-vitals';

export function WebVitals() {
  useReportWebVitals((metric) => {
    if (typeof window.gtag !== 'function') return;
    window.gtag('event', metric.name, {
      // CLS is a unitless ratio (0–1); multiply by 1000 for GA4 integer storage
      value: Math.round(metric.name === 'CLS' ? metric.value * 1000 : metric.value),
      event_category: 'Web Vitals',
      event_label: metric.id,
      non_interaction: true,
    });
  });
  return null;
}
