'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { ArrowLeft, Calendar, ExternalLink, Tag, Loader2, Share2 } from 'lucide-react';
import { api } from '@/lib/api';

interface NewsItem {
  id: number;
  title: string;
  summary: string;
  content: string;
  image: string | null;
  source: string;
  categories: string[];
  tags: string[];
  published_at: string;
  original_url: string;
  language: string;
}

export default function NewsDetailPage() {
  const params = useParams();
  const router = useRouter();
  const [news, setNews] = useState<NewsItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchNews();
  }, [params.id]);

  const fetchNews = async () => {
    setLoading(true);
    setError(null);

    try {
      const newsId = parseInt(params.id as string);
      const data = await api.getNewsItem(newsId);
      setNews(data);
    } catch (err: any) {
      setError(err.message || 'Eroare la încărcarea știrii');
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ro-RO', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const handleShare = async () => {
    if (navigator.share && news) {
      try {
        await navigator.share({
          title: news.title,
          text: news.summary,
          url: window.location.href
        });
      } catch (err) {
        console.log('Error sharing:', err);
      }
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="h-12 w-12 animate-spin text-primary-600 mx-auto mb-4" />
          <p className="text-gray-600">Se încarcă știrea...</p>
        </div>
      </div>
    );
  }

  if (error || !news) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Știre negăsită</h2>
          <p className="text-gray-600 mb-6">{error || 'Această știre nu există sau nu este disponibilă.'}</p>
          <Link href="/news" className="btn-primary">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Înapoi la știri
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="container mx-auto px-4 py-4">
          <Link
            href="/news"
            className="inline-flex items-center text-gray-600 hover:text-gray-900"
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Înapoi la știri
          </Link>
        </div>
      </div>

      {/* Article */}
      <article className="container mx-auto px-4 py-8 max-w-4xl">
        <div className="bg-white rounded-lg shadow-lg p-8">
          {/* Title */}
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            {news.title}
          </h1>

          {/* Meta */}
          <div className="flex items-center justify-between mb-6 pb-6 border-b border-gray-200">
            <div className="flex items-center text-sm text-gray-500 space-x-4">
              <div className="flex items-center">
                <Calendar className="h-4 w-4 mr-1" />
                <span>{formatDate(news.published_at)}</span>
              </div>
              <div>
                Sursă: <span className="font-medium">{news.source}</span>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              {news.original_url && (
                <a
                  href={news.original_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn-secondary text-sm"
                >
                  <ExternalLink className="h-4 w-4 mr-1" />
                  Sursă originală
                </a>
              )}
              {typeof window !== 'undefined' && typeof navigator !== 'undefined' && 'share' in navigator && (
                <button
                  onClick={handleShare}
                  className="btn-secondary text-sm"
                >
                  <Share2 className="h-4 w-4 mr-1" />
                  Distribuie
                </button>
              )}
            </div>
          </div>

          {/* Categories and Tags */}
          {(news.categories.length > 0 || news.tags.length > 0) && (
            <div className="mb-6">
              {news.categories.length > 0 && (
                <div className="mb-3">
                  <span className="text-sm font-semibold text-gray-700 mr-2">Categorii:</span>
                  <div className="inline-flex flex-wrap gap-2">
                    {news.categories.map((category) => (
                      <span
                        key={category}
                        className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800"
                      >
                        <Tag className="h-3 w-3 mr-1" />
                        {category}
                      </span>
                    ))}
                  </div>
                </div>
              )}
              {news.tags.length > 0 && (
                <div>
                  <span className="text-sm font-semibold text-gray-700 mr-2">Tag-uri:</span>
                  <div className="inline-flex flex-wrap gap-2">
                    {news.tags.map((tag) => (
                      <span
                        key={tag}
                        className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Image */}
          {news.image && (
            <div className="mb-6 rounded-lg overflow-hidden">
              <img
                src={news.image}
                alt={news.title}
                className="w-full h-auto"
              />
            </div>
          )}

          {/* Summary */}
          {news.summary && (
            <div className="mb-6 p-4 bg-gray-50 rounded-lg border-l-4 border-primary-600">
              <p className="text-lg font-medium text-gray-900 italic">
                {news.summary}
              </p>
            </div>
          )}

          {/* Content */}
          <div
            className="prose prose-lg max-w-none"
            dangerouslySetInnerHTML={{ __html: news.content }}
          />
        </div>

        {/* Back button */}
        <div className="mt-8 text-center">
          <Link href="/news" className="btn-secondary">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Înapoi la toate știrile
          </Link>
        </div>
      </article>
    </div>
  );
}
