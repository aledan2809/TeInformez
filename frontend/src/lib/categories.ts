export interface CategoryDef {
  slug: string;
  label: string;
  emoji: string;
  subcategories?: string[];
  hidden?: boolean; // Legacy slugs hidden from nav bar
}

export const CATEGORIES: CategoryDef[] = [
  { slug: '', label: 'Toate', emoji: '📰' },
  { slug: 'actualitate', label: 'Actualitate', emoji: '📰', subcategories: ['Breaking', 'Social', 'Educație', 'Cultură', 'România'] },
  { slug: 'politics', label: 'Politică', emoji: '🏛️', subcategories: ['România', 'UE', 'SUA', 'Internațional'] },
  { slug: 'international', label: 'Internațional', emoji: '🌍', subcategories: ['Europa', 'SUA', 'Orientul Mijlociu', 'Asia', 'Africa'] },
  { slug: 'justitie', label: 'Justiție', emoji: '⚖️', subcategories: ['Instanțe', 'DNA', 'Legislație', 'Cazuri penale'] },
  { slug: 'business', label: 'Business', emoji: '📊', subcategories: ['Startup-uri', 'Corporate', 'Antreprenoriat', 'Economie'] },
  { slug: 'finance', label: 'Finanțe', emoji: '💰', subcategories: ['Crypto', 'Bursă', 'Bănci', 'Imobiliare'] },
  { slug: 'tech', label: 'Tehnologie', emoji: '💻', subcategories: ['Smartphone', 'Laptop', 'AI', 'Software', 'Gadget-uri'] },
  { slug: 'sanatate', label: 'Sănătate', emoji: '🏥', subcategories: ['Medicină', 'Nutriție', 'Fitness', 'Sănătate mintală'] },
  { slug: 'science', label: 'Știință', emoji: '🔬', subcategories: ['Spațiu', 'Medicină', 'Mediu', 'Cercetare'] },
  { slug: 'sports', label: 'Sport', emoji: '⚽', subcategories: ['Fotbal', 'Tenis', 'F1', 'Baschet'] },
  { slug: 'entertainment', label: 'Divertisment', emoji: '🎬', subcategories: ['Filme', 'Muzică', 'Gaming', 'Celebrități'] },
  { slug: 'auto', label: 'Auto', emoji: '🚗', subcategories: ['Mașini electrice', 'Clasice', 'Motorsport', 'Recenzii'] },
  { slug: 'lifestyle', label: 'Lifestyle', emoji: '✨', subcategories: ['Travel', 'Food', 'Fashion', 'Home', 'Parenting'] },
  { slug: 'opinii', label: 'Opinii', emoji: '💬', subcategories: ['Editoriale', 'Analize', 'Comentarii', 'Interviuri'] },
  { slug: 'juridic', label: 'Juridic', emoji: '📋', subcategories: ['Dreptul muncii', 'Dreptul familiei', 'Drept comercial', 'Drept penal'] },
  // Legacy slugs used by the AI classifier (hidden from nav, used for label resolution only)
  { slug: 'news', label: 'Actualitate', emoji: '📰', hidden: true },
  { slug: 'world', label: 'Internațional', emoji: '🌍', hidden: true },
  { slug: 'health', label: 'Sănătate', emoji: '🏥', hidden: true },
  { slug: 'history', label: 'Istorie', emoji: '📜', hidden: true },
  { slug: 'local', label: 'Local', emoji: '📍', hidden: true },
  { slug: 'culture', label: 'Cultură', emoji: '🎭', hidden: true },
  { slug: 'education', label: 'Educație', emoji: '🎓', hidden: true },
  { slug: 'media', label: 'Media', emoji: '📺', hidden: true },
  { slug: 'military', label: 'Militar', emoji: '🎖️', hidden: true },
];

export const CATEGORY_COLORS: Record<string, string> = {
  tech: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
  auto: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
  finance: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
  entertainment: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300',
  sports: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
  science: 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300',
  politics: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
  business: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
  actualitate: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300',
  international: 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300',
  justitie: 'bg-slate-100 dark:bg-slate-900/30 text-slate-700 dark:text-slate-300',
  sanatate: 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
  lifestyle: 'bg-fuchsia-100 dark:bg-fuchsia-900/30 text-fuchsia-700 dark:text-fuchsia-300',
  opinii: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
  juridic: 'bg-stone-100 dark:bg-stone-900/30 text-stone-700 dark:text-stone-300',
  // Legacy slugs
  news: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300',
  world: 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300',
  health: 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
  history: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
  local: 'bg-lime-100 dark:bg-lime-900/30 text-lime-700 dark:text-lime-300',
  culture: 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300',
  education: 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300',
  media: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
  military: 'bg-zinc-100 dark:bg-zinc-900/30 text-zinc-700 dark:text-zinc-300',
};

export function getCategoryLabel(slug: string): string {
  const cat = CATEGORIES.find(c => c.slug === slug);
  return cat?.label || slug;
}

export function getCategoryEmoji(slug: string): string {
  const cat = CATEGORIES.find(c => c.slug === slug);
  return cat?.emoji || '📰';
}
