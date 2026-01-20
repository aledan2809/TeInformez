'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { Newspaper, Loader2, LogIn } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';

interface LoginForm {
  email: string;
  password: string;
  remember: boolean;
}

export default function LoginPage() {
  const router = useRouter();
  const { login, error, clearError } = useAuthStore();
  const [isLoading, setIsLoading] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginForm>({
    defaultValues: {
      remember: true,
    },
  });

  const onSubmit = async (data: LoginForm) => {
    clearError();
    setIsLoading(true);

    try {
      await login(data.email, data.password, data.remember);

      // Redirect to dashboard
      router.push('/dashboard');
    } catch (err) {
      // Error is handled by store
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-primary-50 to-white flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <Link href="/" className="flex justify-center items-center space-x-2 mb-6">
          <Newspaper className="h-10 w-10 text-primary-600" />
          <span className="text-2xl font-bold">TeInformez.eu</span>
        </Link>
        <h2 className="text-center text-3xl font-bold tracking-tight text-gray-900">
          Autentificare
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          Sau{' '}
          <Link href="/register" className="font-medium text-primary-600 hover:text-primary-500">
            creează cont gratuit
          </Link>
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="card">
          {error && (
            <div className="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            <div>
              <label htmlFor="email" className="label">
                Email
              </label>
              <input
                {...register('email', {
                  required: 'Email-ul este obligatoriu',
                  pattern: {
                    value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                    message: 'Email invalid',
                  },
                })}
                id="email"
                type="email"
                autoComplete="email"
                className="input"
                placeholder="nume@exemplu.ro"
              />
              {errors.email && (
                <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="label">
                Parolă
              </label>
              <input
                {...register('password', {
                  required: 'Parola este obligatorie',
                })}
                id="password"
                type="password"
                autoComplete="current-password"
                className="input"
                placeholder="••••••••"
              />
              {errors.password && (
                <p className="mt-1 text-sm text-red-600">{errors.password.message}</p>
              )}
            </div>

            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <input
                  {...register('remember')}
                  id="remember"
                  type="checkbox"
                  className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
                <label htmlFor="remember" className="ml-2 block text-sm text-gray-700">
                  Ține-mă minte
                </label>
              </div>

              <div className="text-sm">
                <Link href="/forgot-password" className="font-medium text-primary-600 hover:text-primary-500">
                  Ai uitat parola?
                </Link>
              </div>
            </div>

            <button
              type="submit"
              disabled={isLoading}
              className="btn-primary w-full"
            >
              {isLoading ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Autentificare...
                </>
              ) : (
                <>
                  <LogIn className="mr-2 h-4 w-4" />
                  Autentificare
                </>
              )}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
