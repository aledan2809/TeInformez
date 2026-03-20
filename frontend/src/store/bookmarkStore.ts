import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { api } from '@/lib/api';
import { useAuthStore } from '@/store/authStore';

interface BookmarkedArticle {
  id: number;
  title: string;
  summary: string;
  image: string | null;
  source: string;
  categories: string[];
  published_at: string;
  original_url: string;
  savedAt: string;
}

interface BookmarkState {
  bookmarks: BookmarkedArticle[];
  isSyncing: boolean;
  isBookmarked: (id: number) => boolean;
  addBookmark: (article: Omit<BookmarkedArticle, 'savedAt'>) => void;
  removeBookmark: (id: number) => void;
  toggleBookmark: (article: Omit<BookmarkedArticle, 'savedAt'>) => void;
  getBookmarkCount: () => number;
  syncWithBackend: () => Promise<void>;
}

const isAuthenticated = (): boolean => {
  return useAuthStore.getState().isAuthenticated;
};

export const useBookmarkStore = create<BookmarkState>()(
  persist(
    (set, get) => ({
      bookmarks: [],
      isSyncing: false,

      isBookmarked: (id: number) => {
        return get().bookmarks.some((b) => b.id === id);
      },

      addBookmark: (article) => {
        const exists = get().bookmarks.some((b) => b.id === article.id);
        if (!exists) {
          set((state) => ({
            bookmarks: [
              { ...article, savedAt: new Date().toISOString() },
              ...state.bookmarks,
            ],
          }));
        }

        // Sync with backend if authenticated (fire-and-forget)
        if (isAuthenticated()) {
          api.addBookmark(article.id).catch(() => {
            // Silently fail — local state is the source of truth
          });
        }
      },

      removeBookmark: (id: number) => {
        set((state) => ({
          bookmarks: state.bookmarks.filter((b) => b.id !== id),
        }));

        // Sync with backend if authenticated (fire-and-forget)
        if (isAuthenticated()) {
          api.removeBookmark(id).catch(() => {
            // Silently fail — local state is the source of truth
          });
        }
      },

      toggleBookmark: (article) => {
        const exists = get().bookmarks.some((b) => b.id === article.id);
        if (exists) {
          get().removeBookmark(article.id);
        } else {
          get().addBookmark(article);
        }
      },

      getBookmarkCount: () => {
        return get().bookmarks.length;
      },

      syncWithBackend: async () => {
        if (!isAuthenticated()) return;

        set({ isSyncing: true });
        try {
          const serverBookmarks = await api.getBookmarks();
          const localBookmarks = get().bookmarks;

          // Merge: combine server and local, preferring server data for duplicates
          const mergedMap = new Map<number, BookmarkedArticle>();

          // Add server bookmarks first
          for (const bookmark of serverBookmarks) {
            mergedMap.set(bookmark.id, bookmark);
          }

          // Add local-only bookmarks (not on server) and push them to backend
          for (const bookmark of localBookmarks) {
            if (!mergedMap.has(bookmark.id)) {
              mergedMap.set(bookmark.id, bookmark);
              // Push local-only bookmark to server
              api.addBookmark(bookmark.id).catch(() => {
                // Silently fail
              });
            }
          }

          // Sort by savedAt descending
          const merged = Array.from(mergedMap.values()).sort(
            (a, b) => new Date(b.savedAt).getTime() - new Date(a.savedAt).getTime()
          );

          set({ bookmarks: merged, isSyncing: false });
        } catch {
          set({ isSyncing: false });
        }
      },
    }),
    {
      name: 'teinformez-bookmarks',
    }
  )
);
