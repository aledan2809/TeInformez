import axios, { AxiosInstance, AxiosError } from 'axios';
import Cookies from 'js-cookie';
import type {
  User,
  LoginCredentials,
  RegisterData,
  AuthResponse,
  APIResponse,
  UserPreferences,
  Subscription,
  SubscriptionStats,
  Categories,
  TelegramConfig,
  TelegramGroup,
  TelegramReadReport,
  TelegramSendReport,
  PublicNewsItem,
  PublicNewsListResponse,
  PublicHomepageResponse,
  PersonalizedNewsResponse,
  DeliveryItem,
  DeliveryStats,
  JuridicListResponse,
  JuridicItemResponse,
  JuridicCategoriesResponse,
  JuridicColumnsResponse,
} from '@/types';

const API_BASE_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const API_NAMESPACE = 'teinformez/v1';

// Use local proxy to avoid CORS issues when API is cross-origin
const getSecureApiUrl = () => {
  const url = API_BASE_URL;

  if (typeof window !== 'undefined') {
    // Check if API is cross-origin (different host than current page)
    try {
      const apiHost = new URL(url).host;
      const pageHost = window.location.host;
      if (apiHost !== pageHost) {
        // Route through Next.js rewrite proxy to avoid CORS
        return '/api/wp';
      }
    } catch {
      // If URL parsing fails, fall through to direct URL
    }

    // Same-origin: enforce HTTPS in production
    if (!url.includes('localhost')) {
      return url.replace(/^http:/, 'https:');
    }
  }

  return url;
};

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: `${getSecureApiUrl()}/${API_NAMESPACE}`,
      headers: {
        'Content-Type': 'application/json',
      },
      withCredentials: true,
      timeout: 30000, // 30 second timeout
    });

    // Request interceptor to add auth token
    this.client.interceptors.request.use((config) => {
      const token = Cookies.get('teinformez_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });

    // Response interceptor for comprehensive error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError<APIResponse>) => {
        // Handle network errors (timeout, no connection)
        if (!error.response) {
          const networkError = {
            ...error,
            message: 'Eroare de conexiune. Verifică conexiunea la internet.',
          };
          return Promise.reject(networkError);
        }

        const status = error.response.status;
        const message = error.response.data?.message;

        // Handle specific HTTP status codes
        switch (status) {
          case 401:
            // Unauthorized - clear token
            Cookies.remove('teinformez_token');
            // Only redirect to login from protected pages (dashboard, onboarding)
            if (typeof window !== 'undefined') {
              const path = window.location.pathname;
              const isProtectedPage = path.startsWith('/dashboard') || path.startsWith('/onboarding');
              if (isProtectedPage) {
                window.location.href = '/login';
              }
            }
            error.message = message || 'Sesiune expirată. Te rugăm să te autentifici din nou.';
            break;

          case 403:
            // Forbidden - user doesn't have permission
            error.message = message || 'Nu ai permisiunea să accesezi această resursă.';
            break;

          case 404:
            // Not found
            error.message = message || 'Resursa solicitată nu a fost găsită.';
            break;

          case 422:
            // Validation error
            error.message = message || 'Date invalide. Verifică informațiile introduse.';
            break;

          case 429:
            // Rate limiting
            error.message = message || 'Prea multe cereri. Te rugăm să încerci din nou mai târziu.';
            break;

          case 500:
            // Internal server error
            error.message = message || 'Eroare de server. Te rugăm să încerci din nou.';
            break;

          case 503:
            // Service unavailable
            error.message = message || 'Serviciul este temporar indisponibil. Te rugăm să încerci mai târziu.';
            break;

          default:
            // Generic error
            error.message = message || `Eroare: ${status}. Te rugăm să încerci din nou.`;
        }

        return Promise.reject(error);
      }
    );
  }

  // Auth endpoints — use local proxy to avoid browser console errors on 4xx
  private async authProxy<T>(action: string, body: Record<string, unknown>): Promise<T> {
    const res = await fetch(`/api/auth?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const json = await res.json();
    if (json.success === false) {
      const error = new Error(json.message || 'Auth error');
      throw error;
    }
    return json;
  }

  async register(data: RegisterData): Promise<AuthResponse> {
    const json = await this.authProxy<APIResponse<AuthResponse>>('register', data as unknown as Record<string, unknown>);
    if (json.data?.token) {
      Cookies.set('teinformez_token', json.data.token, { expires: 1 });
    }
    return json.data!;
  }

  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const json = await this.authProxy<APIResponse<AuthResponse>>('login', credentials as unknown as Record<string, unknown>);
    if (json.data?.token) {
      Cookies.set('teinformez_token', json.data.token, { expires: 1 });

      // Also set secure httpOnly cookie (enhanced security)
      try {
        await this.setSecureCookie(json.data.token);
      } catch (error) {
        console.warn('Failed to set secure cookie:', error);
      }
    }
    return json.data!;
  }

  /**
   * Set secure httpOnly cookie (security enhancement)
   */
  async setSecureCookie(token: string): Promise<void> {
    await this.authProxy('set-secure-cookie', { token });
  }

  async logout(): Promise<void> {
    await this.client.post('/auth/logout');
    Cookies.remove('teinformez_token');
  }

  async getCurrentUser(): Promise<User> {
    const response = await this.client.get<APIResponse<{ user: User }>>('/auth/me');
    return response.data.data!.user;
  }

  async refreshToken(): Promise<string> {
    const response = await this.client.post<APIResponse<{ token: string }>>('/auth/refresh');
    const token = response.data.data!.token;
    // Token expires in 24 hours (matches backend)
    Cookies.set('teinformez_token', token, { expires: 1 });
    return token;
  }

  async forgotPassword(email: string): Promise<string> {
    const response = await this.client.post<APIResponse>('/auth/forgot-password', { email });
    return response.data.message || 'Email trimis';
  }

  async resetPassword(email: string, token: string, password: string): Promise<string> {
    const response = await this.client.post<APIResponse>('/auth/reset-password', {
      email,
      token,
      password,
    });
    return response.data.message || 'Parolă resetată cu succes';
  }

  // User preferences endpoints
  async getPreferences(): Promise<UserPreferences> {
    const response = await this.client.get<APIResponse<{ preferences: UserPreferences }>>('/user/preferences');
    return response.data.data!.preferences;
  }

  async updatePreferences(data: Partial<UserPreferences>): Promise<UserPreferences> {
    const response = await this.client.put<APIResponse<{ preferences: UserPreferences }>>('/user/preferences', data);
    return response.data.data!.preferences;
  }

  // Subscription endpoints
  async getSubscriptions(): Promise<Subscription[]> {
    const response = await this.client.get<APIResponse<{ subscriptions: Subscription[] }>>('/user/subscriptions');
    return response.data.data!.subscriptions;
  }

  async addSubscription(data: Partial<Subscription>): Promise<number> {
    const response = await this.client.post<APIResponse<{ subscription_id: number }>>('/user/subscriptions', data);
    return response.data.data!.subscription_id;
  }

  async bulkAddSubscriptions(subscriptions: Partial<Subscription>[]): Promise<number> {
    const response = await this.client.post<APIResponse<{ count: number }>>('/user/subscriptions/bulk', {
      subscriptions,
    });
    return response.data.data!.count;
  }

  async updateSubscription(id: number, data: Partial<Subscription>): Promise<void> {
    await this.client.put(`/user/subscriptions/${id}`, data);
  }

  async deleteSubscription(id: number): Promise<void> {
    await this.client.delete(`/user/subscriptions/${id}`);
  }

  async toggleSubscription(id: number): Promise<void> {
    await this.client.post(`/user/subscriptions/${id}/toggle`);
  }

  async getSubscriptionStats(): Promise<SubscriptionStats> {
    const response = await this.client.get<APIResponse<{ stats: SubscriptionStats }>>('/user/stats');
    return response.data.data!.stats;
  }

  // Account management
  async changePassword(currentPassword: string, newPassword: string): Promise<string> {
    const response = await this.client.post<APIResponse>('/user/change-password', {
      current_password: currentPassword,
      new_password: newPassword,
    });
    return response.data.message || 'Parolă schimbată cu succes';
  }

  async changeEmail(newEmail: string, password: string): Promise<string> {
    const response = await this.client.post<APIResponse<{ email: string }>>('/user/change-email', {
      new_email: newEmail,
      password,
    });
    return response.data.message || 'Email schimbat cu succes';
  }

  // Delivery history
  async getDeliveries(): Promise<{
    deliveries: DeliveryItem[];
    stats: DeliveryStats;
  }> {
    const response = await this.client.get<APIResponse<{
      deliveries: DeliveryItem[];
      stats: DeliveryStats;
    }>>('/user/deliveries');
    return response.data.data!;
  }

  // GDPR endpoints
  async exportUserData(): Promise<Record<string, unknown>> {
    const response = await this.client.get<APIResponse<{ data: Record<string, unknown> }>>('/user/export');
    return response.data.data!.data;
  }

  async deleteAccount(): Promise<void> {
    await this.client.delete('/user/delete');
    Cookies.remove('teinformez_token');
  }

  // Categories endpoint
  async getCategories(): Promise<Categories> {
    const response = await this.client.get<APIResponse<{ categories: Categories }>>('/categories');
    return response.data.data!.categories;
  }

  // News endpoints
  async getNews(params?: { page?: number; per_page?: number; category?: string; search?: string; archive?: boolean }): Promise<PublicNewsListResponse> {
    const response = await this.client.get<APIResponse<PublicNewsListResponse>>('/news', {
      params: { ...params, archive: params?.archive ? 1 : undefined },
    });
    return response.data.data!;
  }

  async getNewsItem(id: number): Promise<PublicNewsItem> {
    const response = await this.client.get<APIResponse<{ news: PublicNewsItem }>>(`/news/${id}`);
    return response.data.data!.news;
  }

  async trackView(id: number): Promise<void> {
    await this.client.post(`/news/${id}/view`);
  }

  async trackAnalyticsEvent(payload: {
    visitor_id: string;
    session_id: string;
    event_type: 'page_view' | 'article_click' | 'time_spent' | 'newsletter_subscribe';
    page_type: 'news' | 'juridic' | 'news_list' | 'juridic_list' | 'home' | 'other';
    page_id?: number;
    page_path?: string;
    duration_seconds?: number;
    metadata?: Record<string, unknown>;
  }): Promise<void> {
    await this.client.post('/analytics/track', payload);
  }

  async getHomepageData(): Promise<PublicHomepageResponse> {
    const response = await this.client.get<APIResponse<PublicHomepageResponse>>('/news/homepage');
    return response.data.data!;
  }

  async subscribeNewsletter(email: string, gdprConsent: boolean, identity?: { visitor_id: string; session_id: string }): Promise<string> {
    const response = await this.client.post<APIResponse<{ message?: string }>>('/newsletter/subscribe', {
      email,
      gdpr_consent: gdprConsent,
      visitor_id: identity?.visitor_id,
      session_id: identity?.session_id,
    });
    return response.data.data?.message || 'Abonat cu succes!';
  }

  async getPersonalizedFeed(params?: { page?: number; per_page?: number }): Promise<PersonalizedNewsResponse> {
    const response = await this.client.get<APIResponse<PersonalizedNewsResponse>>('/news/personalized', { params });
    return response.data.data!;
  }

  // Juridic endpoints
  async getJuridicList(params?: { page?: number; per_page?: number; category?: string; search?: string; column_only?: boolean }): Promise<JuridicListResponse> {
    const response = await this.client.get<APIResponse<JuridicListResponse>>('/juridic', { params });
    return response.data.data!;
  }

  async getJuridicItem(id: number): Promise<JuridicItemResponse> {
    const response = await this.client.get<APIResponse<JuridicItemResponse>>(`/juridic/${id}`);
    return response.data.data!;
  }

  async getJuridicCategories(): Promise<JuridicCategoriesResponse> {
    const response = await this.client.get<APIResponse<JuridicCategoriesResponse>>('/juridic/categories');
    return response.data.data!;
  }

  async getJuridicColumns(params?: { page?: number }): Promise<JuridicColumnsResponse> {
    const response = await this.client.get<APIResponse<JuridicColumnsResponse>>('/juridic/columns', { params });
    return response.data.data!;
  }

  // Telegram endpoints
  async getTelegramConfig(): Promise<TelegramConfig> {
    const response = await this.client.get<APIResponse<TelegramConfig>>('/telegram/config');
    return response.data.data!;
  }

  async saveTelegramConfig(data: { bot_token?: string; groups: TelegramGroup[] }): Promise<TelegramConfig> {
    const response = await this.client.put<APIResponse<TelegramConfig>>('/telegram/config', data);
    return response.data.data!;
  }

  async discoverTelegramGroups(): Promise<{ groups: TelegramGroup[]; discovered_now: number }> {
    const response = await this.client.post<APIResponse<{ groups: TelegramGroup[]; discovered_now: number }>>(
      '/telegram/groups/discover'
    );
    return response.data.data!;
  }

  async readTelegramMessages(data: {
    group_ids: string[];
    mode: 'sequential' | 'parallel';
    limit: number;
  }): Promise<TelegramReadReport> {
    const response = await this.client.post<APIResponse<{ report: TelegramReadReport }>>('/telegram/messages/read', data);
    return response.data.data!.report;
  }

  async sendTelegramMessage(data: {
    group_ids: string[];
    mode: 'sequential' | 'parallel';
    text: string;
    disable_notification?: boolean;
  }): Promise<TelegramSendReport> {
    const response = await this.client.post<APIResponse<{ report: TelegramSendReport }>>('/telegram/messages/send', data);
    return response.data.data!.report;
  }

  // Reading history endpoints
  async markAsRead(newsId: number, timeSpent: number): Promise<void> {
    await this.client.post('/user/reading-history', {
      news_id: newsId,
      time_spent: timeSpent,
    });
  }

  async getReadingHistory(): Promise<{
    history: Array<{ date: string; articlesRead: number[] }>;
    current_streak: number;
    total_read: number;
  }> {
    const response = await this.client.get<APIResponse<{
      history: Array<{ date: string; articlesRead: number[] }>;
      current_streak: number;
      total_read: number;
    }>>('/user/reading-history');
    return response.data.data!;
  }

  // Bookmark endpoints
  async getBookmarks(): Promise<Array<{
    id: number;
    title: string;
    summary: string;
    image: string | null;
    source: string;
    categories: string[];
    published_at: string;
    original_url: string;
    savedAt: string;
  }>> {
    const response = await this.client.get<APIResponse<{
      bookmarks: Array<{
        id: number;
        title: string;
        summary: string;
        image: string | null;
        source: string;
        categories: string[];
        published_at: string;
        original_url: string;
        savedAt: string;
      }>;
    }>>('/user/bookmarks');
    return response.data.data!.bookmarks;
  }

  async addBookmark(newsId: number): Promise<void> {
    await this.client.post('/user/bookmarks', { news_id: newsId });
  }

  async removeBookmark(newsId: number): Promise<void> {
    await this.client.delete(`/user/bookmarks/${newsId}`);
  }

  // Settings endpoints
  async getCategoryOrder(): Promise<{ order: string[]; hidden: string[] }> {
    const response = await this.client.get<APIResponse<{ order: string[]; hidden: string[] }>>('/settings/category-order');
    return response.data.data!;
  }

  async updateCategoryOrder(order: string[], hidden?: string[]): Promise<{ order: string[]; hidden: string[] }> {
    const response = await this.client.post<APIResponse<{ order: string[]; hidden: string[] }>>('/settings/category-order', { order, hidden });
    return response.data.data!;
  }
}

export const api = new ApiClient();
