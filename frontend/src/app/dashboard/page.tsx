'use client';

import { useEffect, useState } from 'react';
import { Bell, Calendar, Mail, TrendingUp } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { api } from '@/lib/api';
import type { SubscriptionStats } from '@/types';

export default function DashboardPage() {
  const { user } = useAuthStore();
  const [stats, setStats] = useState<SubscriptionStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const data = await api.getSubscriptionStats();
        setStats(data);
      } catch (err) {
        console.error('Failed to fetch stats:', err);
      } finally {
        setIsLoading(false);
      }
    };
    fetchStats();
  }, []);

  return (
    <div className="p-8">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-2">
          Bun venit înapoi, {user?.name || 'utilizator'}! 👋
        </h1>
        <p className="text-gray-600">Aici găsești un rezumat al preferințelor tale de știri</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <StatCard
          icon={<Bell className="h-6 w-6 text-primary-600" />}
          label="Abonamente active"
          value={stats?.active || 0}
          isLoading={isLoading}
        />
        <StatCard
          icon={<Calendar className="h-6 w-6 text-blue-600" />}
          label="Total abonamente"
          value={stats?.total || 0}
          isLoading={isLoading}
        />
        <StatCard
          icon={<Mail className="h-6 w-6 text-green-600" />}
          label="Canale active"
          value={user?.preferences?.delivery_channels?.length || 0}
          isLoading={isLoading}
        />
        <StatCard
          icon={<TrendingUp className="h-6 w-6 text-purple-600" />}
          label="Categorii urmărite"
          value={stats?.by_category?.length || 0}
          isLoading={isLoading}
        />
      </div>

      {/* Categories breakdown */}
      {stats && stats.by_category && stats.by_category.length > 0 && (
        <div className="card mb-8">
          <h2 className="text-xl font-semibold mb-4">Abonamente pe categorii</h2>
          <div className="space-y-3">
            {stats.by_category.map((cat) => (
              <div key={cat.category_slug} className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 capitalize">
                  {cat.category_slug.replace('-', ' ')}
                </span>
                <span className="text-sm font-semibold text-primary-600">{cat.count}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Delivery Schedule */}
      {user?.preferences?.delivery_schedule && (
        <div className="card mb-8">
          <h2 className="text-xl font-semibold mb-4">Programul tău de livrare</h2>
          <div className="flex items-start space-x-4">
            <div className="flex-shrink-0 w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
              <Calendar className="h-6 w-6 text-primary-600" />
            </div>
            <div>
              <p className="font-medium text-gray-900">
                {user.preferences.delivery_schedule.frequency === 'daily' && 'Zilnic'}
                {user.preferences.delivery_schedule.frequency === 'weekly' && 'Săptămânal'}
                {user.preferences.delivery_schedule.frequency === 'monthly' && 'Lunar'}
                {user.preferences.delivery_schedule.frequency === 'hourly' && 'La fiecare oră'}
                {user.preferences.delivery_schedule.frequency === 'realtime' && 'În timp real'}
                {['daily', 'weekly', 'monthly'].includes(
                  user.preferences.delivery_schedule.frequency
                ) && ` la ora ${user.preferences.delivery_schedule.time}`}
              </p>
              <p className="text-sm text-gray-500 mt-1">
                Fus orar: {user.preferences.delivery_schedule.timezone}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Quick Actions */}
      <div className="card">
        <h2 className="text-xl font-semibold mb-4">Acțiuni rapide</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <QuickActionButton
            href="/dashboard/subscriptions"
            label="Gestionează abonamente"
            description="Adaugă sau șterge categorii și topicuri"
          />
          <QuickActionButton
            href="/dashboard/settings"
            label="Modifică setări"
            description="Schimbă frecvența și canalele de livrare"
          />
        </div>
      </div>
    </div>
  );
}

function StatCard({
  icon,
  label,
  value,
  isLoading,
}: {
  icon: React.ReactNode;
  label: string;
  value: number;
  isLoading: boolean;
}) {
  return (
    <div className="card">
      <div className="flex items-center space-x-4">
        <div className="flex-shrink-0">{icon}</div>
        <div className="flex-1">
          <p className="text-sm text-gray-600">{label}</p>
          {isLoading ? (
            <div className="h-8 w-16 bg-gray-200 animate-pulse rounded mt-1" />
          ) : (
            <p className="text-2xl font-bold text-gray-900 mt-1">{value}</p>
          )}
        </div>
      </div>
    </div>
  );
}

function QuickActionButton({
  href,
  label,
  description,
}: {
  href: string;
  label: string;
  description: string;
}) {
  return (
    <a
      href={href}
      className="block p-4 border-2 border-gray-200 rounded-lg hover:border-primary-600 hover:bg-primary-50 transition-all"
    >
      <h3 className="font-semibold text-gray-900 mb-1">{label}</h3>
      <p className="text-sm text-gray-600">{description}</p>
    </a>
  );
}
