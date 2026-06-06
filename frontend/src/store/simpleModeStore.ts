import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface SimpleModeState {
  simpleMode: boolean;
  toggleSimpleMode: () => void;
  setSimpleMode: (value: boolean) => void;
  // Whether the user has ever used the per-article "Explică pe scurt" button.
  // Once true, the button stops pulsing (they already understand it).
  eli12Discovered: boolean;
  markEli12Discovered: () => void;
}

export const useSimpleModeStore = create<SimpleModeState>()(
  persist(
    (set) => ({
      simpleMode: false,
      toggleSimpleMode: () => set((state) => ({ simpleMode: !state.simpleMode })),
      setSimpleMode: (value: boolean) => set({ simpleMode: value }),
      eli12Discovered: false,
      markEli12Discovered: () => set({ eli12Discovered: true }),
    }),
    {
      name: 'teinformez-simple-mode',
    }
  )
);
