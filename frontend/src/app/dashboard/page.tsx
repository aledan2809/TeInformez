'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { Bell, Calendar, Mail, TrendingUp, Newspaper, ExternalLink, Flame, Bookmark, Sparkles } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { api } from '@/lib/api';
import { useReadingStore } from '@/store/readingStore';
import { useBookmarkStore } from '@/store/bookmarkStore';
import { useAnimatedCounter } from '@/hooks/useAnimatedCounter';
import type { PublicNewsItem, SubscriptionStats } from '@/types';
import { getCategoryLabel } from '@/lib/categories';

export default function DashboardPage() {
  const { user } = useAuthStore();
  const router = useRouter();
  const [stats, setStats] = useState<SubscriptionStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [personalizedNews, setPersonalizedNews] = useState<PublicNewsItem[]>([]);
  const [newsLoading, setNewsLoading] = useState(true);

  const { currentStreak, totalRead, recalculateStreak } = useReadingStore();
  const bookmarkCount = useBookmarkStore((s) => s.bookmarks.length);

  useEffect(() => {
    recalculateStreak();
  }, []);

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

  useEffect(() => {
    const fetchPersonalizedNews = async () => {
      setNewsLoading(true);
      try {
        const data = await api.getPersonalizedFeed({ page: 1, per_page: 6 });
        setPersonalizedNews(data.news || []);
      } catch (err) {
        console.error('Failed to fetch personalized news:', err);
      } finally {
        setNewsLoading(false);
      }
    };
    fetchPersonalizedNews();
  }, []);

  // Next-best-action: surface the single most useful next step from account state,
  // so the user is guided instead of having to hunt through the menu.
  const [nbaDismissed, setNbaDismissed] = useState<string[]>([]);
  useEffect(() => {
    try { setNbaDismissed(JSON.parse(localStorage.getItem('ti_nba_dismissed') || '[]')); } catch { /* ignore */ }
  }, []);
  const dismissNba = (key: string) => {
    setNbaDismissed((prev) => {
      const next = prev.includes(key) ? prev : [...prev, key];
      try { localStorage.setItem('ti_nba_dismissed', JSON.stringify(next)); } catch { /* ignore */ }
      return next;
    });
  };

  const channels = user?.preferences?.delivery_channels || [];
  const hasTelegram = channels.some((c) => String(c).toLowerCase().includes('telegram'));
  const setupSteps = [
    { key: 'topics', done: (stats?.by_category?.length || 0) >= 1 },
    { key: 'telegram', done: hasTelegram },
    { key: 'schedule', done: !!user?.preferences?.delivery_schedule },
    { key: 'bookmark', done: bookmarkCount >= 1 },
  ];
  const doneCount = setupSteps.filter((s) => s.done).length;
  const nbaCatalog: Record<string, { q: string; cta: string; href: string }> = {
    topics: { q: 'Alege primul subiect ca să primești știri exact pe interesele tale.', cta: 'Alege subiecte', href: '/dashboard/subscriptions' },
    telegram: { q: 'Activează Telegram — primești știrile instant și gratuit, fără să deschizi emailul.', cta: 'Conectează Telegram', href: '/dashboard/telegram' },
    schedule: { q: 'Spune-ne când vrei rezumatul — zilnic, săptămânal sau în timp real.', cta: 'Setează livrarea', href: '/dashboard/settings' },
    bookmark: { q: 'Salvează o știre cu semnul 🔖 ca s-o citești mai târziu, de pe orice dispozitiv.', cta: 'Descoperă știri', href: '/news' },
  };
  const nextStep = setupSteps.find((s) => !s.done && !nbaDismissed.includes(s.key));
  const nextAction = nextStep ? nbaCatalog[nextStep.key] : null;

  return (
    <div className="p-8">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
          Bun venit înapoi, {user?.name || 'utilizator'}! 👋
        </h1>
        <p className="text-gray-600 dark:text-gray-400">Aici găsești un rezumat al preferințelor tale de știri</p>
      </div>

      {!isLoading && nextAction && (
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.35 }}
          className="mb-8 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-900/20 p-5"
        >
          <div className="flex items-center gap-2 mb-2">
            <Sparkles className="h-4 w-4 text-primary-600 dark:text-primary-400" />
            <span className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Ce urmează pentru tine</span>
          </div>
          <p className="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">{nextAction.q}</p>
          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={() => router.push(nextAction.href)}
              className="px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition-colors"
            >
              {nextAction.cta}
            </button>
            <button
              onClick={() => nextStep && dismissNba(nextStep.key)}
              className="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-white/60 dark:hover:bg-gray-800/60 transition-colors"
            >
              Mai târziu
            </button>
          </div>
          <div className="mt-4">
            <div className="h-1.5 rounded-full bg-primary-100 dark:bg-primary-900/40 overflow-hidden">
              <div className="h-full bg-primary-600 rounded-full transition-all" style={{ width: `${(doneCount / setupSteps.length) * 100}%` }} />
            </div>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1.5 tabular-nums">Cont configurat — {doneCount} din {setupSteps.length} pași</p>
          </div>
        </motion.div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
        <StatCard icon={<Bell className="h-6 w-6 text-primary-600" />} label="Abonamente active" value={stats?.active || 0} isLoading={isLoading} hint="subiecte care îți aduc știri acum" />
        <StatCard icon={<Calendar className="h-6 w-6 text-blue-600" />} label="Total abonamente" value={stats?.total || 0} isLoading={isLoading} hint="toate subiectele, active sau pe pauză" />
        <StatCard icon={<Mail className="h-6 w-6 text-green-600" />} label="Canale active" value={user?.preferences?.delivery_channels?.length || 0} isLoading={isLoading} hint="unde primești știrile (email, Telegram)" />
        <StatCard icon={<TrendingUp className="h-6 w-6 text-purple-600" />} label="Categorii urmărite" value={stats?.by_category?.length || 0} isLoading={isLoading} hint="domenii diferite pe care le urmărești" />
        <StatCard icon={<Flame className="h-6 w-6 text-orange-500" />} label="Serie citire" value={currentStreak} isLoading={false} suffix={currentStreak === 1 ? 'zi' : 'zile'} hint="zile la rând în care ai citit" />
        <StatCard icon={<Bookmark className="h-6 w-6 text-amber-500" />} label="Articole salvate" value={bookmarkCount} isLoading={false} hint="apasă 🔖 pe o știre ca s-o salvezi" />
      </div>

      <div className="card mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold flex items-center">
            <Newspaper className="h-5 w-5 mr-2 text-primary-600" />
            Știrile tale personalizate
          </h2>
          <a href="/news" className="text-sm text-primary-600 hover:text-primary-700 font-medium">Vezi toate →</a>
        </div>

        {newsLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="bg-gray-200 dark:bg-gray-700 h-40 rounded-lg mb-3" />
                <div className="bg-gray-200 dark:bg-gray-700 h-4 rounded w-3/4 mb-2" />
                <div className="bg-gray-200 dark:bg-gray-700 h-3 rounded w-full" />
              </div>
            ))}
          </div>
        ) : personalizedNews.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {personalizedNews.map((item) => (
              <div
                key={item.id}
                className="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
                onClick={() => router.push(`/news/${item.id}`)}
              >
                {item.image && (
                  <img src={item.image} alt={item.title} className="w-full h-32 object-cover rounded-lg mb-3" />
                )}
                <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-2 line-clamp-2">{item.title}</h3>
                <p className="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-2">{item.summary}</p>
                <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                  <span>{item.source}</span>
                  {item.original_url && <ExternalLink className="h-3 w-3" />}
                </div>
                {item.image_source && (
                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Foto: {item.image_source}</p>
                )}
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-8">
            <Newspaper className="h-12 w-12 text-gray-400 mx-auto mb-3" />
            <p className="text-gray-600 dark:text-gray-400 mb-2">Nu sunt știri personalizate disponibile</p>
            <p className="text-sm text-gray-500">Adaugă mai multe abonamente pentru a primi știri personalizate.</p>
          </div>
        )}
      </div>

      {/* AI Digest Preview */}
      {personalizedNews.length > 0 && (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="card mb-8 bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/10 dark:to-indigo-900/10 border-purple-200 dark:border-purple-800"
        >
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold flex items-center">
              <Sparkles className="h-5 w-5 mr-2 text-purple-600" />
              Rezumatul zilei
            </h2>
            <span className="text-xs text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-900/30 px-2 py-1 rounded-full font-medium">
              Auto-generat
            </span>
          </div>
          <div className="space-y-3">
            {personalizedNews.slice(0, 3).map((item, i) => (
              <div
                key={item.id}
                className="flex items-start gap-3 cursor-pointer group"
                onClick={() => router.push(`/news/${item.id}`)}
              >
                <span className="flex-shrink-0 mt-0.5 w-6 h-6 rounded-full bg-purple-200 dark:bg-purple-800 text-purple-700 dark:text-purple-300 flex items-center justify-center text-xs font-bold">
                  {i + 1}
                </span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900 dark:text-white group-hover:text-purple-600 transition-colors line-clamp-1">
                    {item.title}
                  </p>
                  <p className="text-xs text-gray-600 dark:text-gray-400 line-clamp-1 mt-0.5">
                    {item.summary}
                  </p>
                </div>
              </div>
            ))}
          </div>
          <a
            href="/news"
            className="inline-flex items-center mt-4 text-sm text-purple-600 dark:text-purple-400 hover:text-purple-700 font-medium"
          >
            Citește digest-ul complet →
          </a>
        </motion.div>
      )}

      {stats && stats.by_category && stats.by_category.length > 0 && (
        <div className="card mb-8">
          <h2 className="text-xl font-semibold mb-4">Abonamente pe categorii</h2>
          <div className="space-y-3">
            {stats.by_category.map((cat) => (
              <div key={cat.category_slug} className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{getCategoryLabel(cat.category_slug)}</span>
                <span className="text-sm font-semibold text-primary-600">{cat.count}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {user?.preferences?.delivery_schedule && (
        <div className="card mb-8">
          <h2 className="text-xl font-semibold mb-4">Programul tău de livrare</h2>
          <div className="flex items-start space-x-4">
            <div className="flex-shrink-0 w-12 h-12 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
              <Calendar className="h-6 w-6 text-primary-600" />
            </div>
            <div>
              <p className="font-medium text-gray-900 dark:text-gray-100">
                {user.preferences.delivery_schedule.frequency === 'daily' && 'Zilnic'}
                {user.preferences.delivery_schedule.frequency === 'weekly' && 'Săptămânal'}
                {user.preferences.delivery_schedule.frequency === 'monthly' && 'Lunar'}
                {user.preferences.delivery_schedule.frequency === 'hourly' && 'La fiecare oră'}
                {user.preferences.delivery_schedule.frequency === 'realtime' && 'În timp real'}
                {['daily', 'weekly', 'monthly'].includes(user.preferences.delivery_schedule.frequency) && ` la ora ${user.preferences.delivery_schedule.time}`}
              </p>
              <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Fus orar: {user.preferences.delivery_schedule.timezone}</p>
            </div>
          </div>
        </div>
      )}

      <div className="card">
        <h2 className="text-xl font-semibold mb-4">Acțiuni rapide</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <QuickActionButton href="/news" label="Citește știri" description="Descoperă ultimele știri personalizate" />
          <QuickActionButton href="/news/saved" label="Articole salvate" description="Vezi articolele pe care le-ai salvat" />
          <QuickActionButton href="/dashboard/subscriptions" label="Gestionează abonamente" description="Adaugă sau șterge categorii și topicuri" />
          <QuickActionButton href="/dashboard/settings" label="Modifică setări" description="Schimbă frecvența și canalele de livrare" />
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon, label, value, isLoading, suffix, hint }: { icon: React.ReactNode; label: string; value: number; isLoading: boolean; suffix?: string; hint?: string }) {
  const animatedValue = useAnimatedCounter(isLoading ? 0 : value, 800);

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="card"
    >
      <div className="flex items-center space-x-4">
        <div className="flex-shrink-0">{icon}</div>
        <div className="flex-1">
          <p className="text-sm text-gray-600 dark:text-gray-400">{label}</p>
          {isLoading ? (
            <div className="h-8 w-16 bg-gray-200 dark:bg-gray-700 animate-pulse rounded mt-1" />
          ) : (
            <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
              {animatedValue}{suffix ? <span className="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">{suffix}</span> : null}
            </p>
          )}
          {hint ? <p className="text-xs text-gray-400 dark:text-gray-500 mt-1 leading-snug">{hint}</p> : null}
        </div>
      </div>
    </motion.div>
  );
}

function QuickActionButton({ href, label, description }: { href: string; label: string; description: string }) {
  return (
    <a href={href} className="block p-4 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all">
      <h3 className="font-semibold text-gray-900 dark:text-gray-100 mb-1">{label}</h3>
      <p className="text-sm text-gray-600 dark:text-gray-400">{description}</p>
    </a>
  );
}
