import { api } from '@/lib/api';

type PageType = 'news' | 'news_list' | 'home' | 'other';
type EventType = 'page_view' | 'article_click' | 'time_spent';

interface AnalyticsPayload {
  event_type: EventType;
  page_type: PageType;
  page_id?: number;
  page_path?: string;
  duration_seconds?: number;
  metadata?: Record<string, unknown>;
}

const VISITOR_KEY = 'teinformez_visitor_id';
const SESSION_KEY = 'teinformez_session_id';

// AN-02: capture entry-point source signals once per session. document.referrer
// is meaningful only on the first navigation (subsequent same-session
// navigations make it = teinformez.eu). UTM params from the landing URL are
// also persisted so they ride along on every event in the session.
const ENTRY_REFERER_KEY = 'teinformez_entry_referer';
const ENTRY_UTM_KEY     = 'teinformez_entry_utm';

interface EntrySignals {
  referer: string;
  utm_source?: string;
  utm_medium?: string;
  utm_campaign?: string;
  utm_term?: string;
  utm_content?: string;
}

function getEntrySignals(): EntrySignals {
  if (typeof window === 'undefined') return { referer: '' };
  const cached = sessionStorage.getItem(ENTRY_UTM_KEY);
  if (cached) {
    try {
      return JSON.parse(cached) as EntrySignals;
    } catch (_) { /* fall through and recompute */ }
  }
  const referer = document.referrer || '';
  const params = new URLSearchParams(window.location.search);
  const signals: EntrySignals = { referer };
  for (const k of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as const) {
    const v = params.get(k);
    if (v) signals[k] = v.slice(0, 100);  // safety cap
  }
  sessionStorage.setItem(ENTRY_REFERER_KEY, referer);
  sessionStorage.setItem(ENTRY_UTM_KEY, JSON.stringify(signals));
  return signals;
}

function randomId(prefix: string): string {
  const rand = Math.random().toString(36).slice(2, 10);
  return `${prefix}_${Date.now()}_${rand}`;
}

function getVisitorId(): string {
  if (typeof window === 'undefined') return '';

  let visitorId = localStorage.getItem(VISITOR_KEY);
  if (!visitorId) {
    visitorId = randomId('v');
    localStorage.setItem(VISITOR_KEY, visitorId);
  }

  return visitorId;
}

function getSessionId(): string {
  if (typeof window === 'undefined') return '';

  let sessionId = sessionStorage.getItem(SESSION_KEY);
  if (!sessionId) {
    sessionId = randomId('s');
    sessionStorage.setItem(SESSION_KEY, sessionId);
  }

  return sessionId;
}

export function trackEvent(payload: AnalyticsPayload): void {
  if (typeof window === 'undefined') return;

  const visitor_id = getVisitorId();
  const session_id = getSessionId();
  if (!visitor_id || !session_id) return;

  // AN-02: ride entry-source signals on every event. Backend uses these to
  // derive a `source_bucket` field in metadata for the Top Sources dashboard.
  const entry = getEntrySignals();

  api.trackAnalyticsEvent({
    visitor_id,
    session_id,
    page_path: payload.page_path || window.location.pathname,
    ...payload,
    referer: entry.referer,
    utm_source:   entry.utm_source,
    utm_medium:   entry.utm_medium,
    utm_campaign: entry.utm_campaign,
    utm_term:     entry.utm_term,
    utm_content:  entry.utm_content,
  }).catch(() => {});
}

export function getAnalyticsIdentity(): { visitor_id: string; session_id: string } {
  return {
    visitor_id: getVisitorId(),
    session_id: getSessionId(),
  };
}

export function trackPageView(pageType: PageType, pageId?: number, metadata?: Record<string, unknown>): void {
  trackEvent({
    event_type: 'page_view',
    page_type: pageType,
    page_id: pageId,
    metadata,
  });
}

export function trackArticleClick(pageId: number, metadata?: Record<string, unknown>): void {
  trackEvent({
    event_type: 'article_click',
    page_type: 'news',
    page_id: pageId,
    metadata,
  });
}

export function createTimeSpentTracker(pageType: PageType, pageId?: number): () => void {
  const startedAt = Date.now();

  return () => {
    const durationSeconds = Math.floor((Date.now() - startedAt) / 1000);
    if (durationSeconds < 5) {
      return;
    }

    trackEvent({
      event_type: 'time_spent',
      page_type: pageType,
      page_id: pageId,
      duration_seconds: durationSeconds,
    });
  };
}
