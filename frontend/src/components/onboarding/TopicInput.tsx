'use client';

import { useState, KeyboardEvent } from 'react';
import { X, Plus, Lightbulb } from 'lucide-react';
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
  const [topicInputs, setTopicInputs] = useState<Record<string, string>>({});
  const [suggestedCategory, setSuggestedCategory] = useState('');
  const [showSuggestionForm, setShowSuggestionForm] = useState(false);

  const handleInputChange = (category: string, value: string) => {
    setTopicInputs(prev => ({ ...prev, [category]: value }));
  };

  const handleAddTopic = (category: string) => {
    const keyword = topicInputs[category]?.trim();
    if (!keyword) return;

    onAddTopic({ category, keyword });
    setTopicInputs(prev => ({ ...prev, [category]: '' }));
  };

  const handleKeyPress = (e: KeyboardEvent<HTMLInputElement>, category: string) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleAddTopic(category);
    }
  };

  const getTopicsForCategory = (category: string) => {
    return topics.filter(t => t.category === category);
  };

  const handleSuggestCategory = () => {
    if (suggestedCategory.trim()) {
      // For now, just show a confirmation. In future, this could send to backend
      alert(`Mulțumim pentru sugestie! Am notat categoria "${suggestedCategory}". O vom analiza și adăuga în curând.`);
      setSuggestedCategory('');
      setShowSuggestionForm(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold mb-2">Adaugă subiecte specifice</h2>
        <p className="text-gray-600">
          Pentru fiecare categorie selectată, poți adăuga subiecte precise care te interesează.
          Astfel vei primi știri mai relevante!
        </p>
      </div>

      {/* Topics for each selected category */}
      {selectedCategories.length > 0 ? (
        <div className="space-y-6">
          {selectedCategories.map((categorySlug) => (
            <div key={categorySlug} className="card">
              <h3 className="font-semibold text-lg text-gray-900 mb-4">
                {categoryLabels[categorySlug] || categorySlug}
              </h3>

              {/* Existing topics for this category */}
              {getTopicsForCategory(categorySlug).length > 0 && (
                <div className="flex flex-wrap gap-2 mb-4">
                  {getTopicsForCategory(categorySlug).map((topic, idx) => {
                    const globalIndex = topics.findIndex(
                      t => t.category === topic.category && t.keyword === topic.keyword
                    );
                    return (
                      <span
                        key={idx}
                        className="inline-flex items-center gap-1 px-3 py-1 bg-primary-100 text-primary-800 rounded-full text-sm"
                      >
                        {topic.keyword}
                        <button
                          onClick={() => onRemoveTopic(globalIndex)}
                          className="ml-1 hover:text-red-600"
                        >
                          <X className="h-3 w-3" />
                        </button>
                      </span>
                    );
                  })}
                </div>
              )}

              {/* Add new topic input */}
              <div className="flex gap-2">
                <input
                  type="text"
                  value={topicInputs[categorySlug] || ''}
                  onChange={(e) => handleInputChange(categorySlug, e.target.value)}
                  onKeyPress={(e) => handleKeyPress(e, categorySlug)}
                  placeholder={`ex: ${getPlaceholder(categorySlug)}`}
                  className="input flex-1"
                />
                <button
                  onClick={() => handleAddTopic(categorySlug)}
                  disabled={!topicInputs[categorySlug]?.trim()}
                  className="btn-primary"
                >
                  <Plus className="h-4 w-4 mr-1" />
                  Adaugă
                </button>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="text-center py-8 border-2 border-dashed border-gray-200 rounded-lg">
          <p className="text-gray-500">
            Nu ai selectat nicio categorie.<br />
            Mergi înapoi și selectează cel puțin o categorie.
          </p>
        </div>
      )}

      {/* Suggest new category */}
      <div className="card bg-gray-50">
        <div className="flex items-start gap-3">
          <Lightbulb className="h-5 w-5 text-amber-500 mt-0.5" />
          <div className="flex-1">
            <p className="text-sm text-gray-700 mb-2">
              Nu găsești categoria sau subiectul dorit?
            </p>
            {showSuggestionForm ? (
              <div className="flex gap-2">
                <input
                  type="text"
                  value={suggestedCategory}
                  onChange={(e) => setSuggestedCategory(e.target.value)}
                  placeholder="Sugerează o categorie nouă..."
                  className="input flex-1 text-sm"
                />
                <button
                  onClick={handleSuggestCategory}
                  disabled={!suggestedCategory.trim()}
                  className="btn-primary text-sm"
                >
                  Trimite
                </button>
                <button
                  onClick={() => setShowSuggestionForm(false)}
                  className="btn-outline text-sm"
                >
                  Anulează
                </button>
              </div>
            ) : (
              <button
                onClick={() => setShowSuggestionForm(true)}
                className="text-sm text-primary-600 hover:text-primary-700 font-medium"
              >
                Sugerează o categorie nouă →
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Summary */}
      {topics.length > 0 && (
        <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
          <p className="text-sm text-green-800">
            <strong>Rezumat:</strong> Ai adăugat {topics.length} subiect{topics.length !== 1 ? 'e' : ''} specific{topics.length !== 1 ? 'e' : ''}.
            Vei primi știri personalizate pe aceste teme!
          </p>
        </div>
      )}
    </div>
  );
}

function getPlaceholder(category: string): string {
  const placeholders: Record<string, string> = {
    tech: 'Tesla, iPhone, ChatGPT, Starlink...',
    auto: 'BMW M5, Formula 1, Dacia Duster...',
    finance: 'Bitcoin, BVB, EUR/RON, inflație...',
    entertainment: 'Netflix, Oscar 2025, Smiley...',
    sports: 'FCSB, Real Madrid, Simona Halep...',
    science: 'NASA, schimbări climatice, AI...',
    politics: 'alegeri, UE, NATO...',
    business: 'eMAG, Dedeman, startup-uri...',
  };
  return placeholders[category] || 'subiect specific...';
}
