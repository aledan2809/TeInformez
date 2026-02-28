'use client';

import { useState, useEffect } from 'react';
import { BarChart3, Loader2, Tag, Bell, Clock, TrendingUp } from 'lucide-react';
import { api } from '@/lib/api';
import { useAuthStore } from '@/store/authStore';
import type { SubscriptionStats } from '@/types';

export default function StatsPage() {
  const { user } = useAuthStore();
  const [stats, setStats] = useState<SubscriptionStats | null>(null);
  const [subscriptions, setSubscriptions] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [statsData, subsData] = await Promise.all([
        api.getSubscriptionStats(),
        api.getSubscriptions(),
      ]);
      setStats(statsData);
      setSubscriptions(subsData);
    } catch (err) {
      console.error('Failed to load stats:', err);
    } finally {
      setLoading(false);
    }
  };

  const getActivePercentage = () => {
    if (!stats || stats.total === 0) return 0;
    return Math.round((stats.active / stats.total) * 100);
  };

  if (loading) {
    return (
      <div className="p-8 flex items-center justify-center min-h-[400px]">
        <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
      </div>
    );
  }

  const channels = user?.preferences?.delivery_channels || [];
  const schedule = user?.preferences?.delivery_schedule;

  return (
    <div className="p-8 max-w-4xl">
      <h1 className="text-3xl font-bold text-gray-900 mb-8 flex items-center gap-3">
        <BarChart3 className="h-8 w-8 text-primary-600" />
        Statistici
      </h1>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div className="card text-center">
          <p className="text-3xl font-bold text-primary-600">{stats?.total || 0}</p>
          <p className="text-sm text-gray-600">Total abonamente</p>
        </div>
        <div className="card text-center">
          <p className="text-3xl font-bold text-green-600">{stats?.active || 0}</p>
          <p className="text-sm text-gray-600">Active</p>
        </div>
        <div className="card text-center">
          <p className="text-3xl font-bold text-gray-400">{stats?.inactive || 0}</p>
          <p className="text-sm text-gray-600">Inactive</p>
        </div>
      </div>

      {/* Activity Score */}
      <div className="card mb-6">
        <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
          <TrendingUp className="h-5 w-5 text-primary-600" />
          Scor activitate
        </h2>
        <div className="flex items-center gap-4">
          <div className="flex-1 bg-gray-200 rounded-full h-4">
            <div
              className="bg-primary-600 h-4 rounded-full transition-all"
              style={{ width: `${getActivePercentage()}%` }}
            />
          </div>
          <span className="text-lg font-bold text-primary-600">{getActivePercentage()}%</span>
        </div>
        <p className="text-sm text-gray-500 mt-2">
          {stats?.active || 0} din {stats?.total || 0} abonamente sunt active
        </p>
      </div>

      {/* Categories Breakdown */}
      {stats?.by_category && stats.by_category.length > 0 && (
        <div className="card mb-6">
          <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
            <Tag className="h-5 w-5 text-primary-600" />
            Categorii urmărite
          </h2>
          <div className="space-y-3">
            {stats.by_category
              .sort((a, b) => b.count - a.count)
              .map((item) => (
                <div key={item.category_slug} className="flex items-center justify-between">
                  <span className="text-gray-700 capitalize">{item.category_slug}</span>
                  <div className="flex items-center gap-2">
                    <div className="w-32 bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-primary-500 h-2 rounded-full"
                        style={{
                          width: `${Math.round((item.count / stats.total) * 100)}%`,
                        }}
                      />
                    </div>
                    <span className="text-sm font-medium text-gray-600 w-8 text-right">{item.count}</span>
                  </div>
                </div>
              ))}
          </div>
        </div>
      )}

      {/* Delivery Config */}
      <div className="card mb-6">
        <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
          <Bell className="h-5 w-5 text-primary-600" />
          Configurare livrare
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p className="text-sm text-gray-500 mb-1">Canale active</p>
            <div className="flex flex-wrap gap-2">
              {channels.length > 0 ? (
                channels.map((ch: string) => (
                  <span key={ch} className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm capitalize">
                    {ch}
                  </span>
                ))
              ) : (
                <span className="text-gray-400">Niciun canal selectat</span>
              )}
            </div>
          </div>
          <div>
            <p className="text-sm text-gray-500 mb-1">Program livrare</p>
            <div className="flex items-center gap-2">
              <Clock className="h-4 w-4 text-gray-400" />
              <span className="text-gray-700">
                {schedule ? `${schedule.frequency === 'daily' ? 'Zilnic' : schedule.frequency === 'weekly' ? 'Săptămânal' : 'Lunar'} la ${schedule.time}` : 'Neconfigurat'}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
