'use client';

import { useEffect, useState } from 'react';
import { Plus, Trash2, ToggleLeft, ToggleRight, Loader2 } from 'lucide-react';
import { api } from '@/lib/api';
import type { Subscription } from '@/types';

export default function SubscriptionsPage() {
  const [subscriptions, setSubscriptions] = useState<Subscription[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchSubscriptions();
  }, []);

  const fetchSubscriptions = async () => {
    try {
      const data = await api.getSubscriptions();
      setSubscriptions(data);
    } catch (err) {
      setError('Failed to load subscriptions');
    } finally {
      setIsLoading(false);
    }
  };

  const handleToggle = async (id: number) => {
    try {
      await api.toggleSubscription(id);
      // Update local state
      setSubscriptions((prev) =>
        prev.map((sub) =>
          sub.id === id ? { ...sub, is_active: !sub.is_active } : sub
        )
      );
    } catch (err) {
      setError('Failed to toggle subscription');
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Sigur vrei să ștergi acest abonament?')) return;

    try {
      await api.deleteSubscription(id);
      setSubscriptions((prev) => prev.filter((sub) => sub.id !== id));
    } catch (err) {
      setError('Failed to delete subscription');
    }
  };

  if (isLoading) {
    return (
      <div className="p-8 flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
      </div>
    );
  }

  return (
    <div className="p-8">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Abonamentele tale</h1>
          <p className="text-gray-600">
            Gestionează categoriile și topicurile la care ești abonat
          </p>
        </div>
        <button className="btn-primary">
          <Plus className="h-4 w-4 mr-2" />
          Adaugă abonament
        </button>
      </div>

      {/* Error message */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
          {error}
        </div>
      )}

      {/* Subscriptions list */}
      {subscriptions.length === 0 ? (
        <div className="card text-center py-12">
          <p className="text-gray-500 mb-4">Nu ai încă niciun abonament activ</p>
          <button className="btn-primary">
            <Plus className="h-4 w-4 mr-2" />
            Adaugă primul abonament
          </button>
        </div>
      ) : (
        <div className="space-y-4">
          {subscriptions.map((sub) => (
            <div
              key={sub.id}
              className={`card ${
                sub.is_active ? '' : 'bg-gray-50 border-gray-300'
              }`}
            >
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center space-x-3 mb-2">
                    <h3 className="font-semibold text-gray-900 capitalize">
                      {sub.category_slug.replace('-', ' ')}
                    </h3>
                    {!sub.is_active && (
                      <span className="px-2 py-1 text-xs font-medium bg-gray-200 text-gray-700 rounded">
                        Inactiv
                      </span>
                    )}
                  </div>
                  {sub.topic_keyword && (
                    <p className="text-sm text-gray-600">
                      Subiect: <strong>{sub.topic_keyword}</strong>
                    </p>
                  )}
                  <p className="text-xs text-gray-500 mt-1">
                    Creat la {new Date(sub.created_at).toLocaleDateString('ro-RO')}
                  </p>
                </div>

                <div className="flex items-center space-x-2">
                  {/* Toggle button */}
                  <button
                    onClick={() => handleToggle(sub.id)}
                    className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                    title={sub.is_active ? 'Dezactivează' : 'Activează'}
                  >
                    {sub.is_active ? (
                      <ToggleRight className="h-6 w-6 text-primary-600" />
                    ) : (
                      <ToggleLeft className="h-6 w-6 text-gray-400" />
                    )}
                  </button>

                  {/* Delete button */}
                  <button
                    onClick={() => handleDelete(sub.id)}
                    className="p-2 hover:bg-red-50 rounded-lg transition-colors text-gray-400 hover:text-red-600"
                    title="Șterge"
                  >
                    <Trash2 className="h-5 w-5" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Stats summary */}
      {subscriptions.length > 0 && (
        <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="card text-center">
            <p className="text-sm text-gray-600 mb-1">Total</p>
            <p className="text-2xl font-bold text-gray-900">{subscriptions.length}</p>
          </div>
          <div className="card text-center">
            <p className="text-sm text-gray-600 mb-1">Active</p>
            <p className="text-2xl font-bold text-primary-600">
              {subscriptions.filter((s) => s.is_active).length}
            </p>
          </div>
          <div className="card text-center">
            <p className="text-sm text-gray-600 mb-1">Inactive</p>
            <p className="text-2xl font-bold text-gray-400">
              {subscriptions.filter((s) => !s.is_active).length}
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
