import { create } from 'zustand';
import { persist } from 'zustand/middleware';

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
  isBookmarked: (id: number) => boolean;
  addBookmark: (article: Omit<BookmarkedArticle, 'savedAt'>) => void;
  removeBookmark: (id: number) => void;
  toggleBookmark: (article: Omit<BookmarkedArticle, 'savedAt'>) => void;
  getBookmarkCount: () => number;
}

export const useBookmarkStore = create<BookmarkState>()(
  persist(
    (set, get) => ({
      bookmarks: [],

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
      },

      removeBookmark: (id: number) => {
        set((state) => ({
          bookmarks: state.bookmarks.filter((b) => b.id !== id),
        }));
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
    }),
    {
      name: 'teinformez-bookmarks',
    }
  )
);
