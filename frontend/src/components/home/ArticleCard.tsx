import Link from 'next/link';
import { Calendar, ExternalLink } from 'lucide-react';
import { CATEGORY_COLORS, getCategoryLabel } from '@/lib/categories';

interface ArticleCardProps {
  id: number;
  title: string;
  summary?: string;
  image?: string | null;
  imageSource?: string | null;
  source: string;
  categories: string[];
  published_at: string;
  showImage?: boolean;
  compact?: boolean;
}

export default function ArticleCard({
  id, title, summary, image, imageSource, source, categories, published_at,
  showImage = true, compact = false,
}: ArticleCardProps) {
  const primaryCat = categories[0] || '';
  const colorClass = CATEGORY_COLORS[primaryCat] || 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300';

  const timeAgo = formatTimeAgo(published_at);

  if (compact) {
    return (
      <Link href={`/news/${id}`} className="group block">
        <h4 className="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors line-clamp-2 leading-snug">
          {title}
        </h4>
        <div className="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-gray-400">
          <span>{source}</span>
          <span>·</span>
          <span>{timeAgo}</span>
        </div>
      </Link>
    );
  }

  return (
    <Link href={`/news/${id}`} className="group block card hover:shadow-md transition-shadow overflow-hidden">
      {showImage && image && (
        <div className="aspect-video overflow-hidden bg-gray-100 dark:bg-gray-800">
          <img
            src={image}
            alt={title}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            loading="lazy"
          />
        </div>
      )}
      <div className="p-4">
        {primaryCat && (
          <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium mb-2 ${colorClass}`}>
            {getCategoryLabel(primaryCat)}
          </span>
        )}
        <h3 className="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors line-clamp-2 leading-snug">
          {title}
        </h3>
        {summary && (
          <p className="mt-1.5 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
            {summary}
          </p>
        )}
        <div className="flex items-center gap-2 mt-3 text-xs text-gray-500 dark:text-gray-400">
          <ExternalLink className="h-3 w-3" />
          <span>{source}</span>
          <span>·</span>
          <Calendar className="h-3 w-3" />
          <span>{timeAgo}</span>
        </div>
        {imageSource && (
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Foto: {imageSource}</p>
        )}
      </div>
    </Link>
  );
}

function formatTimeAgo(dateStr: string): string {
  const now = new Date();
  const date = new Date(dateStr);
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);

  if (diffMins < 60) return `acum ${diffMins} min`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `acum ${diffHours}h`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) return `acum ${diffDays}z`;

  return date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short' });
}
