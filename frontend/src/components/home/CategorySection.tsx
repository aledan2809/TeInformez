import Link from 'next/link';
import { ChevronRight } from 'lucide-react';
import ArticleCard from './ArticleCard';

interface Article {
  id: number;
  title: string;
  summary: string;
  image: string | null;
  image_source: string | null;
  source: string;
  categories: string[];
  published_at: string;
}

interface CategorySectionProps {
  slug: string;
  label: string;
  emoji: string;
  articles: Article[];
}

export default function CategorySection({ slug, label, emoji, articles }: CategorySectionProps) {
  if (!articles || articles.length === 0) return null;

  const [lead, ...rest] = articles;

  return (
    <section className="py-6">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-bold flex items-center gap-2">
          <span>{emoji}</span>
          <span>{label}</span>
        </h2>
        <Link
          href={`/news?category=${slug}`}
          className="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-0.5"
        >
          Vezi toate
          <ChevronRight className="h-4 w-4" />
        </Link>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Lead article — larger with image */}
        <div className="md:col-span-2">
          <ArticleCard
            id={lead.id}
            title={lead.title}
            summary={lead.summary}
            image={lead.image}
            imageSource={lead.image_source}
            source={lead.source}
            categories={lead.categories}
            published_at={lead.published_at}
            showImage={true}
          />
        </div>

        {/* Side articles — compact list */}
        <div className="space-y-4 md:pt-0">
          {rest.map((article) => (
            <ArticleCard
              key={article.id}
              id={article.id}
              title={article.title}
              imageSource={article.image_source}
              source={article.source}
              categories={article.categories}
              published_at={article.published_at}
              compact={true}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
