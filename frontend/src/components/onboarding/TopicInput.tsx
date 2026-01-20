'use client';

import { useState, KeyboardEvent } from 'react';
import { X, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Topic {
  category: string;
  keyword: string;
}

interface TopicInputProps {
  selectedCategories: string[];
  categoryLabels: Record<string, string>;
  topics: Topic[];
  onAddTopic: (topic: Topic) => void;
  onRemoveTopic: (index: number) => void;
}

export default function TopicInput({
  selectedCategories,
  categoryLabels,
  topics,
  onAddTopic,
  onRemoveTopic,
}: TopicInputProps) {
  const [selectedCategory, setSelectedCategory] = useState(selectedCategories[0] || '');
  const [keyword, setKeyword] = useState('');

  const handleAddTopic = () => {
    if (!selectedCategory || !keyword.trim()) return;

    onAddTopic({
      category: selectedCategory,
      keyword: keyword.trim(),
    });

    setKeyword('');
  };

  const handleKeyPress = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleAddTopic();
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold mb-2">Adaugă topicuri specifice</h2>
        <p className="text-gray-600">
          Personalizează și mai mult - adaugă subiecte precise care te interesează
          (ex: Tesla, iPhone 16, Formula 1)
        </p>
      </div>

      {/* Input form */}
      <div className="card">
        <div className="space-y-4">
          <div>
            <label className="label">Categorie</label>
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="input"
              disabled={selectedCategories.length === 0}
            >
              {selectedCategories.length === 0 ? (
                <option>Selectează mai întâi categorii</option>
              ) : (
                selectedCategories.map((slug) => (
                  <option key={slug} value={slug}>
                    {categoryLabels[slug] || slug}
                  </option>
                ))
              )}
            </select>
          </div>

          <div>
            <label className="label">Subiect specific</label>
            <div className="flex gap-2">
              <input
                type="text"
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                onKeyPress={handleKeyPress}
                placeholder="ex: Tesla, iPhone 16, Formula 1..."
                className="input flex-1"
                disabled={!selectedCategory}
              />
              <button
                onClick={handleAddTopic}
                disabled={!selectedCategory || !keyword.trim()}
                className="btn-primary"
              >
                <Plus className="h-4 w-4" />
                Adaugă
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Topics list */}
      {topics.length > 0 && (
        <div className="space-y-3">
          <h3 className="font-semibold text-gray-900">Topicurile tale ({topics.length})</h3>
          <div className="space-y-2">
            {topics.map((topic, index) => (
              <div
                key={index}
                className="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg"
              >
                <div className="flex items-center gap-3">
                  <span className="text-sm font-medium text-gray-500">
                    {categoryLabels[topic.category]}
                  </span>
                  <span className="text-gray-300">→</span>
                  <span className="text-sm font-semibold text-gray-900">
                    {topic.keyword}
                  </span>
                </div>
                <button
                  onClick={() => onRemoveTopic(index)}
                  className="text-gray-400 hover:text-red-600 transition-colors"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {topics.length === 0 && (
        <div className="text-center py-8 border-2 border-dashed border-gray-200 rounded-lg">
          <p className="text-gray-500">
            Încă nu ai adăugat topicuri specifice.<br />
            Poți sări acest pas dacă vrei știri generale pe categoriile alese.
          </p>
        </div>
      )}
    </div>
  );
}
