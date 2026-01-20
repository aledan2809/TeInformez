'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowLeft, ArrowRight, Check, Loader2 } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { api } from '@/lib/api';
import CategorySelector from '@/components/onboarding/CategorySelector';
import TopicInput from '@/components/onboarding/TopicInput';
import ScheduleSelector from '@/components/onboarding/ScheduleSelector';
import ChannelSelector from '@/components/onboarding/ChannelSelector';

const STEPS = [
  { id: 1, title: 'Categorii', description: 'Alege ce te interesează' },
  { id: 2, title: 'Topicuri', description: 'Personalizează și mai mult' },
  { id: 3, title: 'Frecvență', description: 'Când vrei să primești știri' },
  { id: 4, title: 'Canale', description: 'Unde vrei să primești știri' },
];

interface Topic {
  category: string;
  keyword: string;
}

interface DeliverySchedule {
  frequency: 'realtime' | 'hourly' | 'daily' | 'weekly' | 'monthly';
  time: string;
  timezone: string;
}

export default function OnboardingPage() {
  const router = useRouter();
  const { user, isAuthenticated } = useAuthStore();
  const [currentStep, setCurrentStep] = useState(1);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  // Categories state
  const [categories, setCategories] = useState<any>({});
  const [selectedCategories, setSelectedCategories] = useState<string[]>([]);

  // Topics state
  const [topics, setTopics] = useState<Topic[]>([]);

  // Schedule state
  const [schedule, setSchedule] = useState<DeliverySchedule>({
    frequency: 'daily',
    time: '14:00',
    timezone: 'Europe/Bucharest',
  });

  // Channels state
  const [selectedChannels, setSelectedChannels] = useState<string[]>(['email']);

  // Fetch categories on mount
  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const data = await api.getCategories();
        setCategories(data);
      } catch (err) {
        console.error('Failed to fetch categories:', err);
      }
    };
    fetchCategories();
  }, []);

  // Redirect if not authenticated
  useEffect(() => {
    if (!isAuthenticated) {
      router.push('/login');
    }
  }, [isAuthenticated, router]);

  const handleToggleCategory = (slug: string) => {
    setSelectedCategories((prev) =>
      prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug]
    );
  };

  const handleAddTopic = (topic: Topic) => {
    setTopics((prev) => [...prev, topic]);
  };

  const handleRemoveTopic = (index: number) => {
    setTopics((prev) => prev.filter((_, i) => i !== index));
  };

  const handleToggleChannel = (channelId: string) => {
    setSelectedChannels((prev) =>
      prev.includes(channelId) ? prev.filter((c) => c !== channelId) : [...prev, channelId]
    );
  };

  const canProceed = () => {
    switch (currentStep) {
      case 1:
        return selectedCategories.length > 0;
      case 2:
        return true; // Topics are optional
      case 3:
        return true; // Schedule always has default
      case 4:
        return selectedChannels.length > 0;
      default:
        return false;
    }
  };

  const handleNext = () => {
    if (currentStep < STEPS.length) {
      setCurrentStep((prev) => prev + 1);
    }
  };

  const handleBack = () => {
    if (currentStep > 1) {
      setCurrentStep((prev) => prev - 1);
    }
  };

  const handleFinish = async () => {
    setIsLoading(true);
    setError('');

    try {
      // 1. Update user preferences (schedule + channels)
      await api.updatePreferences({
        delivery_schedule: schedule,
        delivery_channels: selectedChannels,
      });

      // 2. Create subscriptions (bulk)
      const subscriptions = [
        // Category subscriptions
        ...selectedCategories.map((categorySlug) => ({
          category_slug: categorySlug,
          topic_keyword: '',
          country_filter: 'all',
        })),
        // Topic subscriptions
        ...topics.map((topic) => ({
          category_slug: topic.category,
          topic_keyword: topic.keyword,
          country_filter: 'all',
        })),
      ];

      if (subscriptions.length > 0) {
        await api.bulkAddSubscriptions(subscriptions);
      }

      // 3. Redirect to dashboard
      router.push('/dashboard');
    } catch (err: any) {
      setError(err.response?.data?.message || 'A apărut o eroare. Încearcă din nou.');
    } finally {
      setIsLoading(false);
    }
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
      </div>
    );
  }

  const categoryLabels = Object.entries(categories).reduce((acc, [slug, cat]: [string, any]) => {
    acc[slug] = cat.label;
    return acc;
  }, {} as Record<string, string>);

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="container-custom max-w-4xl">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            Bun venit, {user?.name || 'utilizator'}! 👋
          </h1>
          <p className="text-gray-600">
            Hai să personalizăm experiența ta de știri în câțiva pași simpli
          </p>
        </div>

        {/* Progress indicator */}
        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            {STEPS.map((step, index) => (
              <div key={step.id} className="flex items-center flex-1">
                <div className="flex flex-col items-center">
                  <div
                    className={`flex items-center justify-center w-10 h-10 rounded-full border-2 ${
                      currentStep > step.id
                        ? 'bg-primary-600 border-primary-600 text-white'
                        : currentStep === step.id
                        ? 'border-primary-600 bg-white text-primary-600'
                        : 'border-gray-300 bg-white text-gray-400'
                    }`}
                  >
                    {currentStep > step.id ? (
                      <Check className="h-5 w-5" />
                    ) : (
                      <span className="font-semibold">{step.id}</span>
                    )}
                  </div>
                  <div className="mt-2 text-center">
                    <p className="text-xs font-medium text-gray-900">{step.title}</p>
                    <p className="text-xs text-gray-500 hidden sm:block">{step.description}</p>
                  </div>
                </div>
                {index < STEPS.length - 1 && (
                  <div
                    className={`flex-1 h-0.5 mx-2 ${
                      currentStep > step.id ? 'bg-primary-600' : 'bg-gray-300'
                    }`}
                  />
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Error message */}
        {error && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
            {error}
          </div>
        )}

        {/* Step content */}
        <div className="card mb-8">
          {currentStep === 1 && (
            <CategorySelector
              categories={categories}
              selectedCategories={selectedCategories}
              onToggleCategory={handleToggleCategory}
            />
          )}

          {currentStep === 2 && (
            <TopicInput
              selectedCategories={selectedCategories}
              categoryLabels={categoryLabels}
              topics={topics}
              onAddTopic={handleAddTopic}
              onRemoveTopic={handleRemoveTopic}
            />
          )}

          {currentStep === 3 && (
            <ScheduleSelector schedule={schedule} onScheduleChange={setSchedule} />
          )}

          {currentStep === 4 && (
            <ChannelSelector
              selectedChannels={selectedChannels}
              onToggleChannel={handleToggleChannel}
            />
          )}
        </div>

        {/* Navigation buttons */}
        <div className="flex justify-between">
          <button
            onClick={handleBack}
            disabled={currentStep === 1}
            className="btn-outline disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Înapoi
          </button>

          {currentStep < STEPS.length ? (
            <button onClick={handleNext} disabled={!canProceed()} className="btn-primary">
              Următorul
              <ArrowRight className="h-4 w-4 ml-2" />
            </button>
          ) : (
            <button
              onClick={handleFinish}
              disabled={!canProceed() || isLoading}
              className="btn-primary"
            >
              {isLoading ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Salvare...
                </>
              ) : (
                <>
                  <Check className="h-4 w-4 mr-2" />
                  Finalizare
                </>
              )}
            </button>
          )}
        </div>

        {/* Skip option */}
        {currentStep === 2 && (
          <div className="mt-4 text-center">
            <button
              onClick={handleNext}
              className="text-sm text-gray-500 hover:text-gray-700 underline"
            >
              Sari peste - nu vreau să adaug topicuri specifice
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
