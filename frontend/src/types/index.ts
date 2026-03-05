// User types
export interface User {
  id: number;
  email: string;
  name: string;
  registered_at: string;
  preferences: UserPreferences;
  role: string;
}

export interface UserPreferences {
  id: number;
  user_id: number;
  preferred_language: string;
  delivery_channels: string[];
  delivery_schedule: DeliverySchedule;
  gdpr_consent: boolean;
  gdpr_consent_date: string | null;
  created_at: string;
  updated_at: string;
}

export interface DeliverySchedule {
  frequency: 'realtime' | 'hourly' | 'daily' | 'weekly' | 'monthly';
  time: string;
  timezone: string;
}

// Subscription types
export interface Subscription {
  id: number;
  user_id: number;
  category_slug: string;
  topic_keyword: string;
  country_filter: string;
  source_filter: string[] | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface SubscriptionStats {
  total: number;
  active: number;
  inactive: number;
  by_category: Array<{
    category_slug: string;
    count: number;
  }>;
}

// Category types
export interface Category {
  label: string;
  icon: string;
  subcategories: string[];
}

export interface Categories {
  [key: string]: Category;
}

// News types
export interface NewsItem {
  id: number;
  original_url: string;
  original_title: string;
  processed_title: string;
  processed_summary: string;
  processed_content: string;
  target_language: string;
  ai_generated_image_url: string | null;
  youtube_embed: string | null;
  status: 'fetched' | 'processing' | 'pending_review' | 'approved' | 'rejected' | 'published';
  categories: string[];
  tags: string[];
  published_at: string;
}

export interface PublicNewsItem {
  id: number;
  title: string;
  summary: string;
  content: string;
  image: string | null;
  image_source: string | null;
  youtube_url: string | null;
  source: string;
  categories: string[];
  tags: string[];
  published_at: string;
  original_url: string;
  language: string;
  view_count?: number;
}

export interface PublicNewsListResponse {
  news: PublicNewsItem[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface PublicHomepageSection {
  slug: string;
  label: string;
  emoji: string;
  articles: PublicNewsItem[];
}

export interface PublicHomepageResponse {
  hero: PublicNewsItem | null;
  sections: PublicHomepageSection[];
  total_articles: number;
}

export interface PersonalizedNewsResponse extends PublicNewsListResponse {
  subscriptions_count: number;
}

// API Response types
export interface APIResponse<T = unknown> {
  success: boolean;
  message?: string;
  data?: T;
}

export interface APIError {
  code: string;
  message: string;
  data?: unknown;
}

export interface ApiErrorShape {
  message?: string;
  response?: {
    data?: {
      message?: string;
    };
  };
}

// Auth types
export interface LoginCredentials {
  email: string;
  password: string;
  remember?: boolean;
}

export interface RegisterData {
  email: string;
  password: string;
  name?: string;
  preferred_language?: string;
  gdpr_consent: boolean;
}

export interface AuthResponse {
  user: User;
  token: string;
}

// Form types
export interface OnboardingFormData {
  categories: string[];
  topics: Array<{
    category: string;
    keyword: string;
  }>;
  countries: string[];
  frequency: DeliverySchedule['frequency'];
  time: string;
  channels: string[];
}

// Delivery channel types
export interface DeliveryChannel {
  label: string;
  icon: string;
  enabled: boolean;
}

export interface DeliveryChannels {
  [key: string]: DeliveryChannel;
}

// Language types
export interface AvailableLanguages {
  [key: string]: string;
}

// Telegram types
export interface TelegramGroup {
  id: string;
  title: string;
}

export interface TelegramConfig {
  has_token: boolean;
  token_mask: string | null;
  groups: TelegramGroup[];
}

export interface TelegramReadMessage {
  message_id: number;
  date: string | null;
  from: string;
  text: string;
  type: 'text' | 'media';
}

export interface TelegramReadGroupReport {
  group_id: string;
  title: string;
  messages_count: number;
  messages: TelegramReadMessage[];
}

export interface TelegramReadReport {
  report_type: 'read';
  generated_at: string;
  mode: 'sequential' | 'parallel';
  groups_count: number;
  messages_total: number;
  groups: TelegramReadGroupReport[];
}

export interface TelegramSendResult {
  group_id: string;
  title: string;
  success: boolean;
  error: string | null;
  message_id: number | null;
}

export interface TelegramSendReport {
  report_type: 'send';
  generated_at: string;
  mode: 'sequential' | 'parallel';
  requested_groups: number;
  sent_count: number;
  failed_count: number;
  results: TelegramSendResult[];
}

export interface DeliveryItem {
  id: number;
  channel: string;
  status: string;
  sent_at: string | null;
  created_at: string;
  news_title: string | null;
  news_id: number | null;
}

export interface DeliveryStats {
  total_delivered: number;
  sent: number;
  failed: number;
  last_delivery: string | null;
}

// Juridic Q&A types
export interface JuridicQA {
  id: number;
  question: string;          // anonymized version only
  answer: string;
  answer_summary: string | null;
  category: string;
  subcategory: string | null;
  tags: string[];
  is_weekly_column: boolean;
  column_title: string | null;
  column_date: string | null;
  author_name: string;
  view_count: number;
  published_at: string;
}

export interface JuridicCategory {
  slug: string;
  label: string;
}

export interface JuridicListResponse {
  items: JuridicQA[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface JuridicItemResponse {
  item: JuridicQA;
}

export interface JuridicCategoriesResponse {
  categories: JuridicCategory[];
}

export interface JuridicColumnsResponse {
  columns: JuridicQA[];
  total: number;
  page: number;
}

export const JURIDIC_CATEGORIES = [
  { slug: 'dreptul-muncii', label: 'Dreptul muncii' },
  { slug: 'dreptul-familiei', label: 'Dreptul familiei' },
  { slug: 'drept-comercial', label: 'Drept comercial' },
  { slug: 'drept-penal', label: 'Drept penal' },
  { slug: 'protectia-consumatorului', label: 'Protecția consumatorului' },
  { slug: 'drept-administrativ', label: 'Drept administrativ' },
  { slug: 'drept-imobiliar', label: 'Drept imobiliar' },
] as const;
