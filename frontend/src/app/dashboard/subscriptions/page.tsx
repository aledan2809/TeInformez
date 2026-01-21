'use client';

import { useEffect, useState } from 'react';
import { Plus, Trash2, ToggleLeft, ToggleRight, Loader2, X, Pencil } from 'lucide-react';
import { api } from '@/lib/api';
import type { Subscription, Categories } from '@/types';

// Category labels in Romanian
const CATEGORY_LABELS: Record<string, string> = {
  tech: 'Tehnologie',
  auto: 'Auto',
  finance: 'Finanțe',
  entertainment: 'Divertisment',
  sports: 'Sport',
  science: 'Știință',
  politics: 'Politică',
  business: 'Business',
};

const getCategoryLabel = (slug: string): string => {
  return CATEGORY_LABELS[slug] || slug.charAt(0).toUpperCase() + slug.slice(1).replace('-', ' ');
};

export default function SubscriptionsPage() {
  const [subscriptions, setSubscriptions] = useState<Subscription[]>([]);
  const [categories, setCategories] = useState<Categories>({});
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editingSubscription, setEditingSubscription] = useState<Subscription | null>(null);
  const [newCategory, setNewCategory] = useState('');
  const [newTopic, setNewTopic] = useState('');
  const [editTopic, setEditTopic] = useState('');
  const [isAdding, setIsAdding] = useState(false);
  const [isEditing, setIsEditing] = useState(false);

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [subsData, catsData] = await Promise.all([
        api.getSubscriptions(),
        api.getCategories(),
      ]);
      setSubscriptions(subsData);
      setCategories(catsData);
    } catch (err) {
      setError('Nu s-au putut încărca datele');
    } finally {
      setIsLoading(false);
    }
  };

  const handleAddSubscription = async () => {
    if (!newCategory) return;

    setIsAdding(true);
    try {
      const subscriptionId = await api.addSubscription({
        category_slug: newCategory,
        topic_keyword: newTopic || undefined,
      });

      // Refresh subscriptions list
      const updatedSubs = await api.getSubscriptions();
      setSubscriptions(updatedSubs);

      // Reset form and close modal
      setNewCategory('');
      setNewTopic('');
      setShowModal(false);
    } catch (err) {
      setError('Nu s-a putut adăuga abonamentul');
    } finally {
      setIsAdding(false);
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

  const handleEdit = (sub: Subscription) => {
    setEditingSubscription(sub);
    setEditTopic(sub.topic_keyword || '');
    setShowEditModal(true);
  };

  const handleSaveEdit = async () => {
    if (!editingSubscription) return;

    setIsEditing(true);
    try {
      await api.updateSubscription(editingSubscription.id, {
        topic_keyword: editTopic || undefined,
      });

      // Update local state
      setSubscriptions((prev) =>
        prev.map((sub) =>
          sub.id === editingSubscription.id
            ? { ...sub, topic_keyword: editTopic }
            : sub
        )
      );

      setShowEditModal(false);
      setEditingSubscription(null);
      setEditTopic('');
    } catch (err) {
      setError('Nu s-a putut actualiza abonamentul');
    } finally {
      setIsEditing(false);
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
        <button onClick={() => setShowModal(true)} className="btn-primary">
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
          <p className="text-gray-500 mb-4">Nu ai încă niciun abonament</p>
          <button onClick={() => setShowModal(true)} className="btn-primary">
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
                    <h3 className="font-semibold text-gray-900">
                      {getCategoryLabel(sub.category_slug)}
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
                  {/* Edit button */}
                  <button
                    onClick={() => handleEdit(sub)}
                    className="p-2 hover:bg-blue-50 rounded-lg transition-colors text-gray-400 hover:text-blue-600"
                    title="Editează"
                  >
                    <Pencil className="h-5 w-5" />
                  </button>

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

      {/* Add Subscription Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="flex items-center justify-between p-4 border-b">
              <h2 className="text-lg font-semibold">Adaugă abonament nou</h2>
              <button
                onClick={() => setShowModal(false)}
                className="p-1 hover:bg-gray-100 rounded"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Categorie *
                </label>
                <select
                  value={newCategory}
                  onChange={(e) => setNewCategory(e.target.value)}
                  className="input w-full"
                >
                  <option value="">Selectează o categorie</option>
                  {Object.keys(categories).map((slug) => (
                    <option key={slug} value={slug}>
                      {getCategoryLabel(slug)}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Subiect specific (opțional)
                </label>
                <input
                  type="text"
                  value={newTopic}
                  onChange={(e) => setNewTopic(e.target.value)}
                  placeholder="ex: Tesla, Bitcoin, FCSB..."
                  className="input w-full"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Lasă gol pentru a primi toate știrile din categorie
                </p>
              </div>
            </div>

            <div className="flex justify-end gap-3 p-4 border-t">
              <button
                onClick={() => setShowModal(false)}
                className="btn-outline"
              >
                Anulează
              </button>
              <button
                onClick={handleAddSubscription}
                disabled={!newCategory || isAdding}
                className="btn-primary"
              >
                {isAdding ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    Se adaugă...
                  </>
                ) : (
                  <>
                    <Plus className="h-4 w-4 mr-2" />
                    Adaugă
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Edit Subscription Modal */}
      {showEditModal && editingSubscription && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="flex items-center justify-between p-4 border-b">
              <h2 className="text-lg font-semibold">Editează abonament</h2>
              <button
                onClick={() => {
                  setShowEditModal(false);
                  setEditingSubscription(null);
                }}
                className="p-1 hover:bg-gray-100 rounded"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Categorie
                </label>
                <input
                  type="text"
                  value={getCategoryLabel(editingSubscription.category_slug)}
                  disabled
                  className="input w-full bg-gray-100"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Categoria nu poate fi modificată. Șterge și adaugă una nouă dacă vrei să schimbi categoria.
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Subiect specific
                </label>
                <input
                  type="text"
                  value={editTopic}
                  onChange={(e) => setEditTopic(e.target.value)}
                  placeholder="ex: Tesla, Bitcoin, FCSB..."
                  className="input w-full"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Lasă gol pentru a primi toate știrile din categorie
                </p>
              </div>
            </div>

            <div className="flex justify-end gap-3 p-4 border-t">
              <button
                onClick={() => {
                  setShowEditModal(false);
                  setEditingSubscription(null);
                }}
                className="btn-outline"
              >
                Anulează
              </button>
              <button
                onClick={handleSaveEdit}
                disabled={isEditing}
                className="btn-primary"
              >
                {isEditing ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    Se salvează...
                  </>
                ) : (
                  'Salvează'
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
