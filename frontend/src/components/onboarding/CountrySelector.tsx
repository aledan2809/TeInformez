'use client';

import { CheckCircle2, Circle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Country {
  code: string;
  label: string;
  flag: string;
}

interface CountrySelectorProps {
  selectedCountries: string[];
  onToggleCountry: (code: string) => void;
}

const COUNTRIES: Country[] = [
  { code: 'romania', label: 'România', flag: '🇷🇴' },
  { code: 'eu', label: 'Uniunea Europeană', flag: '🇪🇺' },
  { code: 'usa', label: 'SUA', flag: '🇺🇸' },
  { code: 'uk', label: 'Marea Britanie', flag: '🇬🇧' },
  { code: 'germany', label: 'Germania', flag: '🇩🇪' },
  { code: 'france', label: 'Franța', flag: '🇫🇷' },
  { code: 'international', label: 'Internațional', flag: '🌍' },
];

export default function CountrySelector({ selectedCountries, onToggleCountry }: CountrySelectorProps) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold mb-2">Țări și piețe de interes</h2>
        <p className="text-gray-600 dark:text-gray-400">
          Din ce țări sau regiuni vrei să primești știri? Selectează una sau mai multe.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {COUNTRIES.map((country) => {
          const isSelected = selectedCountries.includes(country.code);

          return (
            <button
              key={country.code}
              onClick={() => onToggleCountry(country.code)}
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
              <div className="text-4xl mb-3">{country.flag}</div>

              {/* Label */}
              <h3 className="text-lg font-semibold">{country.label}</h3>
            </button>
          );
        })}
      </div>

      {/* Selection summary */}
      {selectedCountries.length > 0 && (
        <div className="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg p-4">
          <p className="text-sm font-medium text-primary-900 dark:text-primary-200">
            🗺️ {selectedCountries.length}{' '}
            {selectedCountries.length === 1 ? 'țară selectată' : 'țări selectate'}:{' '}
            <strong>
              {selectedCountries
                .map((code) => COUNTRIES.find((c) => c.code === code)?.label)
                .filter(Boolean)
                .join(', ')}
            </strong>
          </p>
        </div>
      )}

      {selectedCountries.length === 0 && (
        <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
          <p className="text-sm text-yellow-800 dark:text-yellow-200">
            Selectează cel puțin o țară sau regiune de interes
          </p>
        </div>
      )}
    </div>
  );
}
