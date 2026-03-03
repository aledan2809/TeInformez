import Link from 'next/link';
import { ArrowRight, Newspaper, Zap, Globe, Mail } from 'lucide-react';
import { ThemeToggle } from '@/components/ThemeToggle';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

const organizationJsonLd = {
  '@context': 'https://schema.org',
  '@type': 'Organization',
  name: 'TeInformez.eu',
  url: SITE_URL,
  description: 'Platformă de știri personalizate pentru România',
  contactPoint: {
    '@type': 'ContactPoint',
    email: 'contact@teinformez.eu',
    contactType: 'customer service',
  },
};

const websiteJsonLd = {
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: 'TeInformez.eu',
  url: SITE_URL,
  description: 'Știri personalizate, livrate când vrei tu',
  inLanguage: 'ro',
  publisher: {
    '@type': 'Organization',
    name: 'TeInformez.eu',
  },
};

export default function HomePage() {
  return (
    <div className="min-h-screen">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(organizationJsonLd) }}
      />
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(websiteJsonLd) }}
      />
      {/* Header */}
      <header className="border-b bg-white dark:bg-gray-900">
        <div className="container-custom flex h-16 items-center justify-between">
          <div className="flex items-center space-x-2">
            <Newspaper className="h-8 w-8 text-primary-600" />
            <span className="text-xl font-bold">TeInformez.eu</span>
          </div>

          <nav className="flex items-center space-x-4">
            <ThemeToggle />
            <Link href="/login" className="text-sm font-medium hover:text-primary-600">
              Autentificare
            </Link>
            <Link href="/register" className="btn-primary">
              Înregistrare gratuită
            </Link>
          </nav>
        </div>
      </header>

      {/* Hero Section */}
      <section className="bg-gradient-to-b from-primary-50 to-white dark:from-gray-900 dark:to-gray-950 py-20">
        <div className="container-custom">
          <div className="mx-auto max-w-3xl text-center">
            <h1 className="mb-6 text-5xl font-bold tracking-tight text-gray-900 dark:text-white">
              Știri personalizate,<br />
              livrate când vrei tu
            </h1>
            <p className="mb-8 text-xl text-gray-600 dark:text-gray-400">
              Alege categoriile tale preferate și primește știri rezumate,
              traduse în limba dorită, direct pe email sau social media.
            </p>
            <div className="flex flex-col sm:flex-row justify-center gap-4">
              <Link href="/register" className="btn-primary text-lg px-8 py-3">
                Începe gratuit
                <ArrowRight className="ml-2 h-5 w-5" />
              </Link>
              <Link href="#features" className="btn-outline text-lg px-8 py-3">
                Află mai multe
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-20">
        <div className="container-custom">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="mb-4">De ce TeInformez?</h2>
            <p className="text-lg text-gray-600 dark:text-gray-400">
              Platforma ta de știri personalizate
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <FeatureCard
              icon={<Zap className="h-10 w-10 text-primary-600" />}
              title="100% Personalizat"
              description="Alege exact ce tipuri de știri vrei să primești, de la tehnologie la sport."
            />
            <FeatureCard
              icon={<Globe className="h-10 w-10 text-primary-600" />}
              title="Multilingv"
              description="Știri din întreaga lume, traduse automat în limba ta preferată."
            />
            <FeatureCard
              icon={<Mail className="h-10 w-10 text-primary-600" />}
              title="Livrare Flexibilă"
              description="Primește știri pe email, Facebook, Twitter, când și cum vrei tu."
            />
            <FeatureCard
              icon={<Newspaper className="h-10 w-10 text-primary-600" />}
              title="Rezumate Inteligente"
              description="Știri concise și clare, procesate automat pentru tine."
            />
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="bg-gray-50 dark:bg-gray-900 py-20">
        <div className="container-custom">
          <div className="mx-auto max-w-2xl text-center mb-16">
            <h2 className="mb-4">Cum funcționează?</h2>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
            <StepCard
              number="1"
              title="Înregistrează-te gratuit"
              description="Creează cont în 30 de secunde. Nicio plată, niciun card necesar."
            />
            <StepCard
              number="2"
              title="Alege preferințele"
              description="Selectează categorii, topicuri specifice și frecvența dorită."
            />
            <StepCard
              number="3"
              title="Primește știri"
              description="Începi să primești știri personalizate exact când vrei tu."
            />
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="bg-primary-600 py-16">
        <div className="container-custom text-center">
          <h2 className="mb-4 text-white">Gata să începi?</h2>
          <p className="mb-8 text-xl text-primary-100">
            Înregistrează-te gratuit și primește prima ta selecție de știri în câteva minute.
          </p>
          <Link href="/register" className="btn bg-white text-primary-600 hover:bg-gray-100 text-lg px-8 py-3">
            Înregistrare gratuită
            <ArrowRight className="ml-2 h-5 w-5" />
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t bg-white dark:bg-gray-900 py-12">
        <div className="container-custom">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
              <div className="flex items-center space-x-2 mb-4">
                <Newspaper className="h-6 w-6 text-primary-600" />
                <span className="font-bold">TeInformez.eu</span>
              </div>
              <p className="text-sm text-gray-600 dark:text-gray-400">
                Știri personalizate, livrate când vrei tu
              </p>
            </div>

            <div>
              <h4 className="font-semibold mb-4">Legal</h4>
              <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <li><Link href="/privacy" className="hover:text-primary-600">Politica de confidențialitate</Link></li>
                <li><Link href="/terms" className="hover:text-primary-600">Termeni și condiții</Link></li>
                <li><Link href="/gdpr" className="hover:text-primary-600">GDPR</Link></li>
              </ul>
            </div>

            <div>
              <h4 className="font-semibold mb-4">Contact</h4>
              <ul className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <li>Email: contact@teinformez.eu</li>
              </ul>
            </div>
          </div>

          <div className="mt-8 pt-8 border-t text-center text-sm text-gray-500 dark:text-gray-400">
            <p>&copy; {new Date().getFullYear()} TeInformez.eu. Toate drepturile rezervate.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}

function FeatureCard({ icon, title, description }: { icon: React.ReactNode; title: string; description: string }) {
  return (
    <div className="text-center">
      <div className="mb-4 flex justify-center">{icon}</div>
      <h3 className="mb-2 text-lg font-semibold">{title}</h3>
      <p className="text-gray-600 dark:text-gray-400">{description}</p>
    </div>
  );
}

function StepCard({ number, title, description }: { number: string; title: string; description: string }) {
  return (
    <div className="text-center">
      <div className="mb-4 mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-600 text-xl font-bold text-white">
        {number}
      </div>
      <h3 className="mb-2 text-lg font-semibold">{title}</h3>
      <p className="text-gray-600 dark:text-gray-400">{description}</p>
    </div>
  );
}
