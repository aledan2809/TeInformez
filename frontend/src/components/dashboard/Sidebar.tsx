'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Home, Bell, Settings, LogOut, Newspaper, BarChart3, Send, Bookmark, Flame, Bot, LayoutGrid } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';
import { cn } from '@/lib/utils';
import { ThemeToggle } from '@/components/ThemeToggle';

const MENU_ITEMS = [
  { href: '/dashboard', icon: Home, label: 'Panou principal' },
  { href: '/news/saved', icon: Bookmark, label: 'Salvate' },
  { href: '/dashboard/subscriptions', icon: Bell, label: 'Abonamente' },
  { href: '/dashboard/deliveries', icon: Send, label: 'Livrări' },
  { href: '/dashboard/telegram', icon: Bot, label: 'Telegram' },
  { href: '/dashboard/stats', icon: BarChart3, label: 'Statistici' },
  { href: '/dashboard/settings', icon: Settings, label: 'Setări' },
];

const ADMIN_MENU_ITEMS = [
  { href: '/dashboard/categories', icon: LayoutGrid, label: 'Ordine categorii' },
];

export default function Sidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuthStore();

  const handleLogout = async () => {
    await logout();
    window.location.href = '/';
  };

  return (
    <div className="w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 min-h-screen flex flex-col">
      {/* Logo */}
      <div className="p-6 border-b border-gray-200 dark:border-gray-700">
        <Link href="/dashboard" className="flex items-center space-x-2">
          <Newspaper className="h-8 w-8 text-primary-600" />
          <span className="text-xl font-bold">TeInformez</span>
        </Link>
      </div>

      {/* User info */}
      <div className="p-6 border-b border-gray-200 dark:border-gray-700">
        <div className="flex items-center space-x-3">
          <div className="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
            <span className="text-primary-600 dark:text-primary-400 font-semibold text-lg">
              {user?.name?.charAt(0).toUpperCase() || user?.email?.charAt(0).toUpperCase() || 'U'}
            </span>
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
              {user?.name || 'Utilizator'}
            </p>
            <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{user?.email}</p>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 p-4 space-y-1">
        {MENU_ITEMS.map((item) => {
          const isActive = pathname === item.href;
          const Icon = item.icon;

          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                'flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors',
                isActive
                  ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
              )}
            >
              <Icon className="h-5 w-5" />
              <span className="text-sm">{item.label}</span>
            </Link>
          );
        })}

        {/* Admin section */}
        {user?.role === 'administrator' && (
          <>
            <div className="pt-3 pb-1 px-4">
              <span className="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Admin</span>
            </div>
            {ADMIN_MENU_ITEMS.map((item) => {
              const isActive = pathname === item.href;
              const Icon = item.icon;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    'flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors',
                    isActive
                      ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium'
                      : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                  )}
                >
                  <Icon className="h-5 w-5" />
                  <span className="text-sm">{item.label}</span>
                </Link>
              );
            })}
          </>
        )}
      </nav>

      {/* Theme toggle + Logout */}
      <div className="p-4 border-t border-gray-200 dark:border-gray-700 space-y-1">
        <div className="flex items-center space-x-3 px-4 py-2">
          <ThemeToggle />
          <span className="text-sm text-gray-500 dark:text-gray-400">Temă</span>
        </div>
        <button
          onClick={handleLogout}
          className="flex items-center space-x-3 px-4 py-3 rounded-lg w-full text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
        >
          <LogOut className="h-5 w-5" />
          <span className="text-sm">Deconectare</span>
        </button>
      </div>
    </div>
  );
}
