'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { Mail, ArrowLeft, CheckCircle, Loader2 } from 'lucide-react';

interface ForgotPasswordForm {
  email: string;
}

export default function ForgotPasswordPage() {
  const [isLoading, setIsLoading] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [error, setError] = useState('');

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ForgotPasswordForm>();

  const onSubmit = async (data: ForgotPasswordForm) => {
    setIsLoading(true);
    setError('');

    try {
      // For now, we'll simulate the request
      // In Phase C, this will connect to WordPress password reset
      await new Promise((resolve) => setTimeout(resolve, 1500));

      // TODO: Implement actual password reset API call
      // await api.requestPasswordReset(data.email);

      setIsSuccess(true);
    } catch (err: any) {
      setError('A apărut o eroare. Te rugăm să încerci din nou.');
    } finally {
      setIsLoading(false);
    }
  };

  if (isSuccess) {
    return (
      <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div className="sm:mx-auto sm:w-full sm:max-w-md">
          <div className="flex justify-center">
            <CheckCircle className="h-16 w-16 text-green-500" />
          </div>
          <h2 className="mt-6 text-center text-3xl font-bold text-gray-900">
            Verifică-ți email-ul
          </h2>
          <p className="mt-4 text-center text-gray-600">
            Dacă există un cont asociat cu această adresă de email,
            vei primi un link pentru resetarea parolei.
          </p>
          <p className="mt-2 text-center text-sm text-gray-500">
            Nu ai primit email-ul? Verifică și folder-ul Spam.
          </p>
          <div className="mt-8 text-center">
            <Link
              href="/login"
              className="text-primary-600 hover:text-primary-700 font-medium"
            >
              ← Înapoi la autentificare
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <Link
          href="/login"
          className="flex items-center justify-center text-primary-600 hover:text-primary-700 mb-6"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Înapoi la autentificare
        </Link>

        <h2 className="text-center text-3xl font-bold text-gray-900">
          Ai uitat parola?
        </h2>
        <p className="mt-2 text-center text-gray-600">
          Introdu adresa de email și îți vom trimite un link pentru resetare.
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="card">
          {error && (
            <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            <div>
              <label htmlFor="email" className="label">
                Adresa de email
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  className={`input pl-10 ${errors.email ? 'border-red-500' : ''}`}
                  placeholder="email@exemplu.com"
                  {...register('email', {
                    required: 'Email-ul este obligatoriu',
                    pattern: {
                      value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                      message: 'Adresă de email invalidă',
                    },
                  })}
                />
              </div>
              {errors.email && (
                <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>
              )}
            </div>

            <button
              type="submit"
              disabled={isLoading}
              className="btn-primary w-full justify-center"
            >
              {isLoading ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Se trimite...
                </>
              ) : (
                'Trimite link de resetare'
              )}
            </button>
          </form>

          <div className="mt-6 text-center text-sm text-gray-500">
            Ți-ai amintit parola?{' '}
            <Link href="/login" className="text-primary-600 hover:text-primary-700 font-medium">
              Autentifică-te
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
