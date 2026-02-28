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
} from '@/types';

const API_BASE_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';
const API_NAMESPACE = 'teinformez/v1';

// Enforce HTTPS in production
const getSecureApiUrl = () => {
  const url = API_BASE_URL;

  // In production (non-localhost), enforce HTTPS
  if (typeof window !== 'undefined' && !url.includes('localhost')) {
    return url.replace(/^http:/, 'https:');
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
            // Unauthorized - clear token and redirect to login
            Cookies.remove('teinformez_token');
            if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
              window.location.href = '/login';
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

  // Auth endpoints
  async register(data: RegisterData): Promise<AuthResponse> {
    const response = await this.client.post<APIResponse<AuthResponse>>('/auth/register', data);
    if (response.data.data?.token) {
      // Token expires in 24 hours (matches backend)
      Cookies.set('teinformez_token', response.data.data.token, { expires: 1 });
    }
    return response.data.data!;
  }

  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await this.client.post<APIResponse<AuthResponse>>('/auth/login', credentials);
    if (response.data.data?.token) {
      // Token expires in 24 hours (matches backend)
      Cookies.set('teinformez_token', response.data.data.token, { expires: 1 });
    }
    return response.data.data!;
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

  // GDPR endpoints
  async exportUserData(): Promise<any> {
    const response = await this.client.get<APIResponse<{ data: any }>>('/user/export');
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
  async getNews(params?: { page?: number; per_page?: number; category?: string; search?: string }): Promise<any> {
    const response = await this.client.get<APIResponse<{
      news: any[];
      total: number;
      page: number;
      per_page: number;
      total_pages: number;
    }>>('/news', { params });
    return response.data.data;
  }

  async getNewsItem(id: number): Promise<any> {
    const response = await this.client.get<APIResponse<{ news: any }>>(`/news/${id}`);
    return response.data.data!.news;
  }

  async getPersonalizedFeed(params?: { page?: number; per_page?: number }): Promise<any> {
    const response = await this.client.get<APIResponse<{
      news: any[];
      total: number;
      page: number;
      per_page: number;
      total_pages: number;
      subscriptions_count: number;
    }>>('/news/personalized', { params });
    return response.data.data;
  }
}

export const api = new ApiClient();
