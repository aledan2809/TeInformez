'use client';

const PUSH_PAGEVIEW_KEY = 'teinformez_push_pageviews';
const PUSH_DISMISSED_KEY = 'teinformez_push_dismissed';
const PUSH_SUBSCRIBED_KEY = 'teinformez_push_subscribed';
const PUSH_PROMPT_THRESHOLD = 3;

export function incrementPageViewCount(): number {
  if (typeof window === 'undefined') return 0;
  const current = parseInt(localStorage.getItem(PUSH_PAGEVIEW_KEY) || '0', 10);
  const next = current + 1;
  localStorage.setItem(PUSH_PAGEVIEW_KEY, String(next));
  return next;
}

export function getPageViewCount(): number {
  if (typeof window === 'undefined') return 0;
  return parseInt(localStorage.getItem(PUSH_PAGEVIEW_KEY) || '0', 10);
}

export function isPushDismissed(): boolean {
  if (typeof window === 'undefined') return true;
  return localStorage.getItem(PUSH_DISMISSED_KEY) === '1';
}

export function dismissPush(): void {
  if (typeof window === 'undefined') return;
  localStorage.setItem(PUSH_DISMISSED_KEY, '1');
}

export function isPushSubscribed(): boolean {
  if (typeof window === 'undefined') return false;
  return localStorage.getItem(PUSH_SUBSCRIBED_KEY) === '1';
}

export function shouldShowPushPrompt(): boolean {
  if (typeof window === 'undefined') return false;
  if (isPushDismissed()) return false;
  if (isPushSubscribed()) return false;
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
  if (Notification.permission === 'denied') return false;
  return getPageViewCount() >= PUSH_PROMPT_THRESHOLD;
}

/** Convert a base64 VAPID public key to BufferSource for PushManager */
function urlBase64ToBufferSource(base64String: string): BufferSource {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const buffer = new ArrayBuffer(rawData.length);
  const view = new Uint8Array(buffer);
  for (let i = 0; i < rawData.length; i++) {
    view[i] = rawData.charCodeAt(i);
  }
  return buffer;
}

export async function registerServiceWorker(): Promise<ServiceWorkerRegistration | null> {
  if (typeof window === 'undefined') return null;
  if (!('serviceWorker' in navigator)) return null;

  try {
    const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    return reg;
  } catch {
    return null;
  }
}

export async function subscribeToPush(): Promise<boolean> {
  const vapidKey = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY;
  if (!vapidKey) {
    console.warn('[Push] NEXT_PUBLIC_VAPID_PUBLIC_KEY not set — skipping push subscription');
    return false;
  }

  try {
    const reg = await navigator.serviceWorker.ready;
    const subscription = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToBufferSource(vapidKey),
    });

    // Send subscription to backend
    const apiBase = process.env.NEXT_PUBLIC_WP_API_URL || '/api/wp';
    const resp = await fetch(`${apiBase}/teinformez/v1/push/subscribe`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(subscription.toJSON()),
    });

    if (resp.ok) {
      localStorage.setItem(PUSH_SUBSCRIBED_KEY, '1');
      return true;
    }
    return false;
  } catch {
    return false;
  }
}
