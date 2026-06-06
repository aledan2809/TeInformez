import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface SimpleModeState {
  simpleMode: boolean;
  toggleSimpleMode: () => void;
  setSimpleMode: (value: boolean) => void;
}

export const useSimpleModeStore = create<SimpleModeState>()(
  persist(
    (set) => ({
      simpleMode: false,
      toggleSimpleMode: () => set((state) => ({ simpleMode: !state.simpleMode })),
      setSimpleMode: (value: boolean) => set({ simpleMode: value }),
    }),
    {
      name: 'teinformez-simple-mode',
    }
  )
);
