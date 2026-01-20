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

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: `${API_BASE_URL}/${API_NAMESPACE}`,
      headers: {
        'Content-Type': 'application/json',
      },
      withCredentials: true,
    });

    // Request interceptor to add auth token
    this.client.interceptors.request.use((config) => {
      const token = Cookies.get('teinformez_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });

    // Response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError<APIResponse>) => {
        if (error.response?.status === 401) {
          // Unauthorized - clear token and redirect to login
          Cookies.remove('teinformez_token');
          if (typeof window !== 'undefined') {
            window.location.href = '/login';
          }
        }
        return Promise.reject(error);
      }
    );
  }

  // Auth endpoints
  async register(data: RegisterData): Promise<AuthResponse> {
    const response = await this.client.post<APIResponse<AuthResponse>>('/auth/register', data);
    if (response.data.data?.token) {
      Cookies.set('teinformez_token', response.data.data.token, { expires: 7 });
    }
    return response.data.data!;
  }

  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await this.client.post<APIResponse<AuthResponse>>('/auth/login', credentials);
    if (response.data.data?.token) {
      Cookies.set('teinformez_token', response.data.data.token, { expires: 7 });
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
    Cookies.set('teinformez_token', token, { expires: 7 });
    return token;
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

  // News endpoints (placeholder for Phase B)
  async getNews(params?: { page?: number; per_page?: number }): Promise<any> {
    const response = await this.client.get('/news', { params });
    return response.data.data;
  }

  async getPersonalizedFeed(params?: { page?: number; per_page?: number }): Promise<any> {
    const response = await this.client.get('/news/personalized', { params });
    return response.data.data;
  }
}

export const api = new ApiClient();
