'use client';

import { Lightbulb } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useSimpleModeStore } from '@/store/simpleModeStore';

export function SimpleModeToggle({ className }: { className?: string }) {
  const { simpleMode, toggleSimpleMode } = useSimpleModeStore();
  const [mounted, setMounted] = useState(false);

  useEffect(() => setMounted(true), []);

  if (!mounted) return <div className="w-9 h-9" />;

  return (
    <button
      onClick={toggleSimpleMode}
      className={`inline-flex items-center gap-1.5 px-2.5 py-1.5 min-h-[44px] min-w-[44px] rounded-lg text-sm font-medium transition-colors ${
        simpleMode
          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
          : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
      } ${className || ''}`}
      aria-pressed={simpleMode}
      aria-label={simpleMode ? 'Dezactivează modul simplu' : 'Activează modul simplu'}
      title="Mod simplu — explicații pe scurt"
    >
      <Lightbulb className={`h-4 w-4 ${simpleMode ? 'fill-emerald-500' : ''}`} />
      <span className="hidden sm:inline">Mod simplu</span>
      {/* Toggle pill indicator */}
      <span
        className={`relative inline-flex h-4 w-7 items-center rounded-full transition-colors ${
          simpleMode ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'
        }`}
      >
        <span
          className={`inline-block h-3 w-3 rounded-full bg-white shadow-sm transition-transform ${
            simpleMode ? 'translate-x-3.5' : 'translate-x-0.5'
          }`}
        />
      </span>
    </button>
  );
}
