'use client';

import { CheckCircle2, Circle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Category {
  slug: string;
  label: string;
  icon: string;
  subcategories: string[];
}

interface CategorySelectorProps {
  categories: Record<string, Category>;
  selectedCategories: string[];
  onToggleCategory: (slug: string) => void;
}

export default function CategorySelector({
  categories,
  selectedCategories,
  onToggleCategory,
}: CategorySelectorProps) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold mb-2">Alege categoriile tale preferate</h2>
        <p className="text-gray-600">
          Selectează subiectele care te interesează. Poți alege câte vrei!
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {Object.entries(categories).map(([slug, category]) => {
          const isSelected = selectedCategories.includes(slug);

          return (
            <button
              key={slug}
              onClick={() => onToggleCategory(slug)}
              className={cn(
                'relative p-6 rounded-lg border-2 transition-all text-left',
                'hover:shadow-md',
                isSelected
                  ? 'border-primary-600 bg-primary-50'
                  : 'border-gray-200 bg-white hover:border-gray-300'
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

              {/* Icon */}
              <div className="text-4xl mb-3">{category.icon}</div>

              {/* Label */}
              <h3 className="text-lg font-semibold mb-2">{category.label}</h3>

              {/* Subcategories preview */}
              <p className="text-sm text-gray-500">
                {category.subcategories.slice(0, 3).join(', ')}
                {category.subcategories.length > 3 && '...'}
              </p>
            </button>
          );
        })}
      </div>

      {selectedCategories.length > 0 && (
        <div className="bg-primary-50 border border-primary-200 rounded-lg p-4">
          <p className="text-sm font-medium text-primary-900">
            {selectedCategories.length} {selectedCategories.length === 1 ? 'categorie selectată' : 'categorii selectate'}
          </p>
        </div>
      )}
    </div>
  );
}
