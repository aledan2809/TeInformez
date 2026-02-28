'use client';

import { useState, useEffect } from 'react';
import { Send, Loader2, CheckCircle, XCircle, Clock, Mail } from 'lucide-react';
import { api } from '@/lib/api';
import Link from 'next/link';

interface DeliveryItem {
  id: number;
  channel: string;
  status: string;
  sent_at: string | null;
  created_at: string;
  news_title: string | null;
  news_id: number | null;
}

interface DeliveryStats {
  total_delivered: number;
  sent: number;
  failed: number;
  last_delivery: string | null;
}

export default function DeliveriesPage() {
  const [deliveries, setDeliveries] = useState<DeliveryItem[]>([]);
  const [stats, setStats] = useState<DeliveryStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const data = await api.getDeliveries();
      setDeliveries(data.deliveries);
      setStats(data.stats);
    } catch (err) {
      console.error('Failed to load deliveries:', err);
    } finally {
      setLoading(false);
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'sent':
        return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'failed':
        return <XCircle className="h-4 w-4 text-red-500" />;
      case 'pending':
        return <Clock className="h-4 w-4 text-yellow-500" />;
      default:
        return <Mail className="h-4 w-4 text-gray-400" />;
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'sent': return 'Trimis';
      case 'failed': return 'Eșuat';
      case 'pending': return 'În așteptare';
      case 'opened': return 'Deschis';
      case 'clicked': return 'Click';
      default: return status;
    }
  };

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString('ro-RO', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (loading) {
    return (
      <div className="p-8 flex items-center justify-center min-h-[400px]">
        <Loader2 className="h-8 w-8 animate-spin text-primary-600" />
      </div>
    );
  }

  return (
    <div className="p-8 max-w-4xl">
      <h1 className="text-3xl font-bold text-gray-900 mb-8 flex items-center gap-3">
        <Send className="h-8 w-8 text-primary-600" />
        Istoric livrări
      </h1>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div className="card text-center">
          <p className="text-3xl font-bold text-primary-600">{stats?.total_delivered || 0}</p>
          <p className="text-sm text-gray-600">Total trimise</p>
        </div>
        <div className="card text-center">
          <p className="text-3xl font-bold text-green-600">{stats?.sent || 0}</p>
          <p className="text-sm text-gray-600">Livrate</p>
        </div>
        <div className="card text-center">
          <p className="text-3xl font-bold text-red-500">{stats?.failed || 0}</p>
          <p className="text-sm text-gray-600">Eșuate</p>
        </div>
        <div className="card text-center">
          <p className="text-sm font-medium text-gray-900">
            {stats?.last_delivery ? formatDate(stats.last_delivery) : 'Niciodată'}
          </p>
          <p className="text-sm text-gray-600">Ultima livrare</p>
        </div>
      </div>

      {/* Delivery List */}
      {deliveries.length === 0 ? (
        <div className="card text-center py-12">
          <Send className="h-12 w-12 text-gray-300 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-2">Nicio livrare încă</h3>
          <p className="text-gray-500">
            Când vei primi primul digest cu știri, va apărea aici.
          </p>
        </div>
      ) : (
        <div className="card">
          <h2 className="text-xl font-semibold mb-4">Ultimele livrări</h2>
          <div className="divide-y divide-gray-100">
            {deliveries.map((item) => (
              <div key={item.id} className="py-3 flex items-center justify-between">
                <div className="flex items-center gap-3 flex-1 min-w-0">
                  {getStatusIcon(item.status)}
                  <div className="min-w-0 flex-1">
                    {item.news_title ? (
                      <Link
                        href={`/news/${item.news_id}`}
                        className="text-sm font-medium text-gray-900 hover:text-primary-600 truncate block"
                      >
                        {item.news_title}
                      </Link>
                    ) : (
                      <span className="text-sm text-gray-400">Știre ștearsă</span>
                    )}
                    <p className="text-xs text-gray-500">
                      {item.channel === 'email' ? 'Email' : item.channel} · {formatDate(item.sent_at || item.created_at)}
                    </p>
                  </div>
                </div>
                <span className={`text-xs px-2 py-1 rounded-full ${
                  item.status === 'sent' ? 'bg-green-100 text-green-700' :
                  item.status === 'failed' ? 'bg-red-100 text-red-700' :
                  'bg-yellow-100 text-yellow-700'
                }`}>
                  {getStatusLabel(item.status)}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
