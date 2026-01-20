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

// API Response types
export interface APIResponse<T = any> {
  success: boolean;
  message?: string;
  data?: T;
}

export interface APIError {
  code: string;
  message: string;
  data?: any;
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
