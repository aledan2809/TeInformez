const UTM_KEY = 'teinformez_utm';

export interface UTMData {
  utm_source?: string;
  utm_medium?: string;
  utm_campaign?: string;
  utm_content?: string;
}

/** Call on first page load to capture UTM params from URL → localStorage. */
export function captureUTM(): void {
  if (typeof window === 'undefined') return;

  const params = new URLSearchParams(window.location.search);
  const source = params.get('utm_source');
  const medium = params.get('utm_medium');
  const campaign = params.get('utm_campaign');
  // utm_content carries the CAS trackingCode → needed for MA signup attribution.
  const content = params.get('utm_content');

  if (source || medium || campaign || content) {
    const data: UTMData = {};
    if (source) data.utm_source = source;
    if (medium) data.utm_medium = medium;
    if (campaign) data.utm_campaign = campaign;
    if (content) data.utm_content = content;
    try {
      localStorage.setItem(UTM_KEY, JSON.stringify(data));
    } catch {
      // storage unavailable
    }
  }
}

/** Read stored UTM data (null if not present). */
export function getStoredUTM(): UTMData | null {
  if (typeof window === 'undefined') return null;
  try {
    const raw = localStorage.getItem(UTM_KEY);
    return raw ? (JSON.parse(raw) as UTMData) : null;
  } catch {
    return null;
  }
}

/** Clear stored UTM after it has been consumed (e.g. after registration). */
export function clearUTM(): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.removeItem(UTM_KEY);
  } catch {
    // storage unavailable
  }
}
