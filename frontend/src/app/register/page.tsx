'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { Newspaper, Loader2, CheckCircle2 } from 'lucide-react';
import { useAuthStore } from '@/store/authStore';

interface RegisterForm {
  email: string;
  password: string;
  confirmPassword: string;
  name: string;
  gdprConsent: boolean;
}

export default function RegisterPage() {
  const router = useRouter();
  const { register: registerUser, error, clearError } = useAuthStore();
  const [isLoading, setIsLoading] = useState(false);

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<RegisterForm>();

  const password = watch('password');

  const onSubmit = async (data: RegisterForm) => {
    clearError();
    setIsLoading(true);

    try {
      await registerUser({
        email: data.email,
        password: data.password,
        name: data.name || undefined,
        preferred_language: 'ro',
        gdpr_consent: data.gdprConsent,
      });

      // Redirect to onboarding
      router.push('/onboarding');
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
          Creează cont gratuit
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          Sau{' '}
          <Link href="/login" className="font-medium text-primary-600 hover:text-primary-500">
            autentifică-te dacă ai deja cont
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
              <label htmlFor="name" className="label">
                Nume (opțional)
              </label>
              <input
                {...register('name')}
                id="name"
                type="text"
                autoComplete="name"
                className="input"
                placeholder="Ion Popescu"
              />
            </div>

            <div>
              <label htmlFor="email" className="label">
                Email *
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
                Parolă *
              </label>
              <input
                {...register('password', {
                  required: 'Parola este obligatorie',
                  minLength: {
                    value: 8,
                    message: 'Parola trebuie să aibă minim 8 caractere',
                  },
                })}
                id="password"
                type="password"
                autoComplete="new-password"
                className="input"
                placeholder="••••••••"
              />
              {errors.password && (
                <p className="mt-1 text-sm text-red-600">{errors.password.message}</p>
              )}
            </div>

            <div>
              <label htmlFor="confirmPassword" className="label">
                Confirmă parola *
              </label>
              <input
                {...register('confirmPassword', {
                  required: 'Confirmarea parolei este obligatorie',
                  validate: (value) =>
                    value === password || 'Parolele nu se potrivesc',
                })}
                id="confirmPassword"
                type="password"
                autoComplete="new-password"
                className="input"
                placeholder="••••••••"
              />
              {errors.confirmPassword && (
                <p className="mt-1 text-sm text-red-600">{errors.confirmPassword.message}</p>
              )}
            </div>

            <div className="flex items-start">
              <div className="flex items-center h-5">
                <input
                  {...register('gdprConsent', {
                    required: 'Trebuie să accepți politica de confidențialitate',
                  })}
                  id="gdprConsent"
                  type="checkbox"
                  className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
              </div>
              <div className="ml-3 text-sm">
                <label htmlFor="gdprConsent" className="text-gray-700">
                  Accept{' '}
                  <Link href="/privacy" className="text-primary-600 hover:text-primary-500" target="_blank">
                    Politica de confidențialitate
                  </Link>{' '}
                  și sunt de acord ca datele mele să fie procesate pentru a primi știri personalizate. *
                </label>
                {errors.gdprConsent && (
                  <p className="mt-1 text-red-600">{errors.gdprConsent.message}</p>
                )}
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
                  Creare cont...
                </>
              ) : (
                <>
                  <CheckCircle2 className="mr-2 h-4 w-4" />
                  Creează cont gratuit
                </>
              )}
            </button>
          </form>

          <div className="mt-6">
            <div className="relative">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-gray-300" />
              </div>
              <div className="relative flex justify-center text-sm">
                <span className="bg-white px-2 text-gray-500">
                  Avantajele contului gratuit
                </span>
              </div>
            </div>

            <ul className="mt-4 space-y-2 text-sm text-gray-600">
              <li className="flex items-center">
                <CheckCircle2 className="mr-2 h-4 w-4 text-green-500" />
                Știri personalizate după interesele tale
              </li>
              <li className="flex items-center">
                <CheckCircle2 className="mr-2 h-4 w-4 text-green-500" />
                Livrare pe email și social media
              </li>
              <li className="flex items-center">
                <CheckCircle2 className="mr-2 h-4 w-4 text-green-500" />
                Fără reclame (deocamdată)
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
