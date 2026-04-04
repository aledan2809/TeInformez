import { api } from '@/lib/api';

type PageType = 'news' | 'juridic' | 'news_list' | 'juridic_list' | 'home' | 'other';
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

  api.trackAnalyticsEvent({
    visitor_id,
    session_id,
    page_path: payload.page_path || window.location.pathname,
    ...payload,
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
