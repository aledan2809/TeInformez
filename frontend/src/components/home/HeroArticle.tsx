import Link from 'next/link';
import { Calendar, ExternalLink } from 'lucide-react';
import { CATEGORY_COLORS, getCategoryLabel } from '@/lib/categories';
import { trackArticleClick } from '@/lib/visitorAnalytics';

interface HeroArticleProps {
  id: number;
  title: string;
  summary: string;
  image?: string | null;
  imageSource?: string | null;
  source: string;
  categories: string[];
  published_at: string;
}

export default function HeroArticle({ id, title, summary, image, imageSource, source, categories, published_at }: HeroArticleProps) {
  const primaryCat = categories[0] || '';
  const colorClass = CATEGORY_COLORS[primaryCat] || 'bg-gray-100 text-gray-700';

  return (
    <Link href={`/news/${id}`} className="group block" onClick={() => trackArticleClick(id, { source: 'hero_article' })}>
      <div className="relative rounded-xl overflow-hidden bg-gray-900">
        {image ? (
          <img
            src={image}
            alt={title}
            className="w-full h-64 sm:h-80 md:h-96 object-cover opacity-80 group-hover:opacity-70 group-hover:scale-105 transition-all duration-500"
          />
        ) : (
          <div className="w-full h-64 sm:h-80 md:h-96 bg-gradient-to-br from-primary-600 to-primary-900" />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent" />
        <div className="absolute bottom-0 left-0 right-0 p-5 sm:p-8">
          {primaryCat && (
            <span className={`inline-block px-3 py-1 rounded-full text-xs font-semibold mb-3 ${colorClass}`}>
              {getCategoryLabel(primaryCat)}
            </span>
          )}
          <h2 className="text-xl sm:text-2xl md:text-3xl font-bold text-white leading-tight group-hover:text-primary-300 transition-colors">
            {title}
          </h2>
          <p className="mt-2 text-sm sm:text-base text-gray-200 line-clamp-2 max-w-2xl">
            {summary}
          </p>
          <div className="flex items-center gap-3 mt-3 text-xs text-gray-300">
            <span className="flex items-center gap-1">
              <ExternalLink className="h-3 w-3" />
              {source}
            </span>
            {imageSource && (
              <>
                <span>·</span>
                <span>Foto: {imageSource}</span>
              </>
            )}
            <span>·</span>
            <span className="flex items-center gap-1">
              <Calendar className="h-3 w-3" />
              {new Date(published_at).toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })}
            </span>
          </div>
        </div>
      </div>
    </Link>
  );
}
