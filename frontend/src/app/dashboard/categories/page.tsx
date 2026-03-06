'use client';

import { useEffect, useState, useCallback } from 'react';
import { GripVertical, Save, RotateCcw, Loader2, Check, AlertCircle } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { api } from '@/lib/api';
import { CATEGORIES, CategoryDef } from '@/lib/categories';

export default function CategoriesOrderPage() {
  const { user } = useAuthStore();
  const isAdmin = user?.role === 'administrator';

  const navCategories = CATEGORIES.filter(c => c.slug !== '' && !c.hidden);

  const [categories, setCategories] = useState<CategoryDef[]>(navCategories);
  const [saving, setSaving] = useState(false);
  const [status, setStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null);
  const [dragIndex, setDragIndex] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.getCategoryOrder()
      .then(order => {
        if (order && order.length > 0) {
          const orderMap = new Map(order.map((slug, i) => [slug, i]));
          const sorted = [...navCategories].sort((a, b) => {
            const ia = orderMap.has(a.slug) ? (orderMap.get(a.slug) as number) : 999;
            const ib = orderMap.has(b.slug) ? (orderMap.get(b.slug) as number) : 999;
            return ia - ib;
          });
          setCategories(sorted);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const handleDragStart = (index: number) => {
    setDragIndex(index);
  };

  const handleDragOver = useCallback((e: React.DragEvent, index: number) => {
    e.preventDefault();
    if (dragIndex === null || dragIndex === index) return;

    setCategories(prev => {
      const newList = [...prev];
      const [dragged] = newList.splice(dragIndex, 1);
      newList.splice(index, 0, dragged);
      return newList;
    });
    setDragIndex(index);
  }, [dragIndex]);

  const handleDragEnd = () => {
    setDragIndex(null);
  };

  const handleSave = async () => {
    setSaving(true);
    setStatus(null);
    try {
      const order = categories.map(c => c.slug);
      await api.updateCategoryOrder(order);
      setStatus({ type: 'success', message: 'Ordinea categoriilor a fost salvata!' });
    } catch {
      setStatus({ type: 'error', message: 'Eroare la salvare. Incearca din nou.' });
    } finally {
      setSaving(false);
    }
  };

  const handleReset = () => {
    setCategories(navCategories);
    setStatus(null);
  };

  // Move item up/down for mobile-friendly reorder
  const moveItem = (index: number, direction: 'up' | 'down') => {
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= categories.length) return;
    setCategories(prev => {
      const newList = [...prev];
      [newList[index], newList[newIndex]] = [newList[newIndex], newList[index]];
      return newList;
    });
  };

  if (!isAdmin) {
    return (
      <div className="p-8">
        <div className="card text-center py-12">
          <AlertCircle className="h-12 w-12 text-red-400 mx-auto mb-4" />
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">Acces restrictionat</h2>
          <p className="text-gray-600 dark:text-gray-400">Aceasta pagina este disponibila doar pentru administratori.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Ordine categorii</h1>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Trage categoriile pentru a schimba ordinea afisarii pe site.</p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={handleReset}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          >
            <RotateCcw className="h-4 w-4" />
            Resetare
          </button>
          <button
            onClick={handleSave}
            disabled={saving}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 transition-colors"
          >
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            Salveaza
          </button>
        </div>
      </div>

      {status && (
        <div className={`mb-4 p-3 rounded-lg flex items-center gap-2 text-sm ${
          status.type === 'success'
            ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800'
            : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800'
        }`}>
          {status.type === 'success' ? <Check className="h-4 w-4" /> : <AlertCircle className="h-4 w-4" />}
          {status.message}
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
        </div>
      ) : (
        <div className="card">
          <div className="space-y-1">
            {categories.map((cat, index) => (
              <div
                key={cat.slug}
                draggable
                onDragStart={() => handleDragStart(index)}
                onDragOver={(e) => handleDragOver(e, index)}
                onDragEnd={handleDragEnd}
                className={`flex items-center gap-3 p-3 rounded-lg border transition-colors cursor-grab active:cursor-grabbing ${
                  dragIndex === index
                    ? 'border-primary-400 bg-primary-50 dark:bg-primary-900/20 shadow-md'
                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600'
                }`}
              >
                <GripVertical className="h-5 w-5 text-gray-400 flex-shrink-0" />
                <span className="text-lg flex-shrink-0">{cat.emoji}</span>
                <span className="font-medium text-gray-900 dark:text-white flex-1">{cat.label}</span>
                <span className="text-xs text-gray-400 dark:text-gray-500 font-mono">{cat.slug}</span>
                <div className="flex gap-1 ml-2">
                  <button
                    onClick={() => moveItem(index, 'up')}
                    disabled={index === 0}
                    className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30 transition-colors"
                    title="Muta sus"
                  >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 15l-6-6-6 6"/></svg>
                  </button>
                  <button
                    onClick={() => moveItem(index, 'down')}
                    disabled={index === categories.length - 1}
                    className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30 transition-colors"
                    title="Muta jos"
                  >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 9l6 6 6-6"/></svg>
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
