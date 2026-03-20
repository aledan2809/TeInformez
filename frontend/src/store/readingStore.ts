import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { api } from '@/lib/api';
import { useAuthStore } from '@/store/authStore';

interface ReadingDay {
  date: string; // YYYY-MM-DD
  articlesRead: number[];
}

interface ReadingState {
  history: ReadingDay[];
  currentStreak: number;
  longestStreak: number;
  totalRead: number;
  isSyncing: boolean;

  markAsRead: (articleId: number, timeSpent?: number) => void;
  getReadToday: () => number;
  isArticleRead: (articleId: number) => boolean;
  recalculateStreak: () => void;
  syncWithBackend: () => Promise<void>;
}

function getToday(): string {
  return new Date().toISOString().split('T')[0];
}

function calculateStreak(history: ReadingDay[]): number {
  if (history.length === 0) return 0;

  const sorted = [...history]
    .filter((d) => d.articlesRead.length > 0)
    .sort((a, b) => b.date.localeCompare(a.date));

  if (sorted.length === 0) return 0;

  const today = getToday();
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  const yesterdayStr = yesterday.toISOString().split('T')[0];

  // Streak must include today or yesterday
  if (sorted[0].date !== today && sorted[0].date !== yesterdayStr) return 0;

  let streak = 1;
  for (let i = 0; i < sorted.length - 1; i++) {
    const current = new Date(sorted[i].date);
    const prev = new Date(sorted[i + 1].date);
    const diffDays = (current.getTime() - prev.getTime()) / (1000 * 60 * 60 * 24);
    if (diffDays === 1) {
      streak++;
    } else {
      break;
    }
  }
  return streak;
}

const isAuthenticated = (): boolean => {
  return useAuthStore.getState().isAuthenticated;
};

export const useReadingStore = create<ReadingState>()(
  persist(
    (set, get) => ({
      history: [],
      currentStreak: 0,
      longestStreak: 0,
      totalRead: 0,
      isSyncing: false,

      markAsRead: (articleId: number, timeSpent: number = 0) => {
        const today = getToday();
        const state = get();

        // Check if already read
        const todayEntry = state.history.find((d) => d.date === today);
        if (todayEntry?.articlesRead.includes(articleId)) return;

        const updatedHistory = todayEntry
          ? state.history.map((d) =>
              d.date === today
                ? { ...d, articlesRead: [...d.articlesRead, articleId] }
                : d
            )
          : [...state.history, { date: today, articlesRead: [articleId] }];

        // Keep only last 90 days
        const cutoff = new Date();
        cutoff.setDate(cutoff.getDate() - 90);
        const cutoffStr = cutoff.toISOString().split('T')[0];
        const trimmedHistory = updatedHistory.filter((d) => d.date >= cutoffStr);

        const newStreak = calculateStreak(trimmedHistory);
        const newTotal = state.totalRead + 1;

        set({
          history: trimmedHistory,
          currentStreak: newStreak,
          longestStreak: Math.max(state.longestStreak, newStreak),
          totalRead: newTotal,
        });

        // Sync with backend if authenticated (fire-and-forget)
        if (isAuthenticated()) {
          api.markAsRead(articleId, timeSpent).catch(() => {
            // Silently fail — local state is the source of truth
          });
        }
      },

      getReadToday: () => {
        const today = getToday();
        const todayEntry = get().history.find((d) => d.date === today);
        return todayEntry?.articlesRead.length || 0;
      },

      isArticleRead: (articleId: number) => {
        return get().history.some((d) => d.articlesRead.includes(articleId));
      },

      recalculateStreak: () => {
        const state = get();
        const newStreak = calculateStreak(state.history);
        set({
          currentStreak: newStreak,
          longestStreak: Math.max(state.longestStreak, newStreak),
        });
      },

      syncWithBackend: async () => {
        if (!isAuthenticated()) return;

        set({ isSyncing: true });
        try {
          const serverData = await api.getReadingHistory();
          const localHistory = get().history;

          // Merge server and local history by date
          const mergedMap = new Map<string, Set<number>>();

          // Add server history
          for (const day of serverData.history) {
            const existing = mergedMap.get(day.date) || new Set<number>();
            for (const id of day.articlesRead) {
              existing.add(id);
            }
            mergedMap.set(day.date, existing);
          }

          // Add local history
          for (const day of localHistory) {
            const existing = mergedMap.get(day.date) || new Set<number>();
            for (const id of day.articlesRead) {
              existing.add(id);
            }
            mergedMap.set(day.date, existing);
          }

          // Find local-only articles and push them to backend
          const serverArticleIds = new Set<number>();
          for (const day of serverData.history) {
            for (const id of day.articlesRead) {
              serverArticleIds.add(id);
            }
          }
          for (const day of localHistory) {
            for (const id of day.articlesRead) {
              if (!serverArticleIds.has(id)) {
                api.markAsRead(id, 0).catch(() => {
                  // Silently fail
                });
              }
            }
          }

          // Keep only last 90 days
          const cutoff = new Date();
          cutoff.setDate(cutoff.getDate() - 90);
          const cutoffStr = cutoff.toISOString().split('T')[0];

          const mergedHistory: ReadingDay[] = [];
          for (const [date, ids] of mergedMap) {
            if (date >= cutoffStr) {
              mergedHistory.push({
                date,
                articlesRead: Array.from(ids),
              });
            }
          }

          // Sort by date descending
          mergedHistory.sort((a, b) => b.date.localeCompare(a.date));

          const newStreak = calculateStreak(mergedHistory);
          const state = get();

          // Total read: use the larger of server total or local total
          const totalRead = Math.max(serverData.total_read, state.totalRead);

          set({
            history: mergedHistory,
            currentStreak: newStreak,
            longestStreak: Math.max(state.longestStreak, newStreak),
            totalRead,
            isSyncing: false,
          });
        } catch {
          set({ isSyncing: false });
        }
      },
    }),
    {
      name: 'teinformez-reading',
    }
  )
);
