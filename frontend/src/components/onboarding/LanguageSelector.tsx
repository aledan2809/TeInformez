'use client';

import { CheckCircle2, Circle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Language {
  code: string;
  label: string;
  flag: string;
}

interface LanguageSelectorProps {
  selectedLanguage: string;
  onSelectLanguage: (code: string) => void;
}

const LANGUAGES: Language[] = [
  { code: 'ro', label: 'Română', flag: '🇷🇴' },
  { code: 'en', label: 'English', flag: '🇬🇧' },
  { code: 'de', label: 'Deutsch', flag: '🇩🇪' },
  { code: 'fr', label: 'Français', flag: '🇫🇷' },
  { code: 'es', label: 'Español', flag: '🇪🇸' },
  { code: 'it', label: 'Italiano', flag: '🇮🇹' },
];

export default function LanguageSelector({ selectedLanguage, onSelectLanguage }: LanguageSelectorProps) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold mb-2">Alege limba conținutului</h2>
        <p className="text-gray-600 dark:text-gray-400">
          În ce limbă vrei să primești știrile? Poți schimba oricând din setări.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {LANGUAGES.map((lang) => {
          const isSelected = selectedLanguage === lang.code;

          return (
            <button
              key={lang.code}
              onClick={() => onSelectLanguage(lang.code)}
              className={cn(
                'relative p-6 rounded-lg border-2 transition-all text-left',
                'hover:shadow-md',
                isSelected
                  ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20'
                  : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600'
              )}
            >
              {/* Checkbox indicator */}
              <div className="absolute top-4 right-4">
                {isSelected ? (
                  <CheckCircle2 className="h-6 w-6 text-primary-600" />
                ) : (
                  <Circle className="h-6 w-6 text-gray-300" />
                )}
              </div>

              {/* Flag */}
              <div className="text-4xl mb-3">{lang.flag}</div>

              {/* Label */}
              <h3 className="text-lg font-semibold">{lang.label}</h3>
            </button>
          );
        })}
      </div>

      {/* Selection summary */}
      <div className="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg p-4">
        <p className="text-sm font-medium text-primary-900 dark:text-primary-200">
          🌐 Limba selectată:{' '}
          <strong>{LANGUAGES.find((l) => l.code === selectedLanguage)?.label || selectedLanguage}</strong>
        </p>
      </div>
    </div>
  );
}
