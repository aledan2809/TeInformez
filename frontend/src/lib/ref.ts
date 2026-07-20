const REF_KEY = 'teinformez_ref';

/**
 * Capture a referral code from the URL (?ref=CODE) into localStorage.
 * Call on landing (e.g. the register page) so it survives until signup.
 */
export function captureRef(): void {
  if (typeof window === 'undefined') return;
  const code = new URLSearchParams(window.location.search).get('ref');
  if (code && code.trim() !== '') {
    try {
      localStorage.setItem(REF_KEY, code.trim());
    } catch {
      // storage unavailable
    }
  }
}

/** Read the stored referral code (null if none). */
export function getStoredRef(): string | null {
  if (typeof window === 'undefined') return null;
  try {
    const v = localStorage.getItem(REF_KEY);
    return v && v.trim() !== '' ? v.trim() : null;
  } catch {
    return null;
  }
}

/** Clear the stored referral code after it has been consumed (post-registration). */
export function clearRef(): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.removeItem(REF_KEY);
  } catch {
    // storage unavailable
  }
}
