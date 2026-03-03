import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface LocalPreferencesState {
  preferredCategories: string[];
  bannerDismissedAt: string | null;
  newsletterDismissedAt: string | null;

  setPreferredCategories: (cats: string[]) => void;
  dismissBanner: () => void;
  dismissNewsletter: () => void;
  isBannerDismissed: () => boolean;
  isNewsletterDismissed: () => boolean;
}

const DISMISS_DAYS = 7;

export const useLocalPreferences = create<LocalPreferencesState>()(
  persist(
    (set, get) => ({
      preferredCategories: [],
      bannerDismissedAt: null,
      newsletterDismissedAt: null,

      setPreferredCategories: (cats) => set({ preferredCategories: cats }),

      dismissBanner: () => set({ bannerDismissedAt: new Date().toISOString() }),

      dismissNewsletter: () => set({ newsletterDismissedAt: new Date().toISOString() }),

      isBannerDismissed: () => {
        const dismissed = get().bannerDismissedAt;
        if (!dismissed) return false;
        const diff = Date.now() - new Date(dismissed).getTime();
        return diff < DISMISS_DAYS * 24 * 60 * 60 * 1000;
      },

      isNewsletterDismissed: () => {
        const dismissed = get().newsletterDismissedAt;
        if (!dismissed) return false;
        const diff = Date.now() - new Date(dismissed).getTime();
        return diff < DISMISS_DAYS * 24 * 60 * 60 * 1000;
      },
    }),
    {
      name: 'teinformez-local-prefs',
    }
  )
);
