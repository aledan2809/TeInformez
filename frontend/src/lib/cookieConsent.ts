/**
 * Cookie-consent state shared between the consent surfaces (Legal-Hub banner
 * for logged-in users, anonymous cookie banner) and the GA4 loader.
 *
 * GA must NOT load before an explicit 'accepted' — ANSPDCP/GDPR requirement.
 * Absence of a choice (null) is treated the same as 'declined' by the loader.
 */

export const COOKIE_CONSENT_KEY = 'ti_cookies_consent';
export const COOKIE_CONSENT_EVENT = 'ti-cookies-consent-changed';

export type CookieChoice = 'accepted' | 'declined';

export function getCookieConsent(): CookieChoice | null {
  try {
    const v = localStorage.getItem(COOKIE_CONSENT_KEY);
    return v === 'accepted' || v === 'declined' ? v : null;
  } catch {
    return null;
  }
}

export function setCookieConsent(choice: CookieChoice): void {
  try {
    localStorage.setItem(COOKIE_CONSENT_KEY, choice);
  } catch {
    // storage unavailable — the event still lets the current page react
  }
  try {
    window.dispatchEvent(new CustomEvent(COOKIE_CONSENT_EVENT, { detail: choice }));
  } catch {
    // non-blocking
  }
}
