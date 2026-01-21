'use client';

import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';

export default function PrivacyPage() {
  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="container-custom max-w-4xl">
        {/* Back button */}
        <Link href="/" className="inline-flex items-center text-primary-600 hover:text-primary-700 mb-8">
          <ArrowLeft className="h-4 w-4 mr-2" />
          Înapoi la pagina principală
        </Link>

        <div className="card prose prose-lg max-w-none">
          <h1 className="text-3xl font-bold text-gray-900 mb-8">Politica de Confidențialitate</h1>

          <p className="text-gray-600 mb-6">
            <em>Ultima actualizare: Ianuarie 2025</em>
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">1. Introducere</h2>
          <p className="text-gray-700 mb-4">
            La TeInformez.eu, protecția datelor dumneavoastră personale este o prioritate.
            Această politică explică cum colectăm, utilizăm și protejăm informațiile dumneavoastră,
            în conformitate cu Regulamentul General privind Protecția Datelor (GDPR).
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">2. Date Colectate</h2>
          <p className="text-gray-700 mb-4">
            Colectăm următoarele categorii de date:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li><strong>Date de identificare:</strong> Nume, adresă de email</li>
            <li><strong>Date de preferințe:</strong> Categorii de interes, subiecte urmărite, frecvența de livrare</li>
            <li><strong>Date tehnice:</strong> Adresa IP, tipul browserului, data și ora accesării</li>
            <li><strong>Date de utilizare:</strong> Articolele citite, interacțiunile cu platforma</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">3. Scopul Procesării</h2>
          <p className="text-gray-700 mb-4">
            Utilizăm datele dumneavoastră pentru:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li>Furnizarea serviciului de știri personalizate</li>
            <li>Trimiterea newsletter-ului conform preferințelor</li>
            <li>Îmbunătățirea algoritmilor de recomandare</li>
            <li>Comunicări privind serviciul (actualizări importante)</li>
            <li>Respectarea obligațiilor legale</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">4. Temeiul Legal</h2>
          <p className="text-gray-700 mb-4">
            Procesăm datele pe baza:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li><strong>Consimțământului:</strong> Pentru newsletter și comunicări de marketing</li>
            <li><strong>Executării contractului:</strong> Pentru furnizarea serviciului</li>
            <li><strong>Interesului legitim:</strong> Pentru îmbunătățirea serviciului și securitate</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">5. Drepturile Dumneavoastră (GDPR)</h2>
          <p className="text-gray-700 mb-4">
            Aveți următoarele drepturi:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li><strong>Dreptul de acces:</strong> Puteți solicita o copie a datelor dumneavoastră</li>
            <li><strong>Dreptul la rectificare:</strong> Puteți corecta datele incorecte</li>
            <li><strong>Dreptul la ștergere:</strong> Puteți solicita ștergerea contului și datelor</li>
            <li><strong>Dreptul la portabilitate:</strong> Puteți exporta datele în format JSON</li>
            <li><strong>Dreptul de retragere a consimțământului:</strong> În orice moment</li>
            <li><strong>Dreptul de a depune plângere:</strong> La ANSPDCP (autoritatea română)</li>
          </ul>
          <p className="text-gray-700 mb-4">
            Pentru a vă exercita aceste drepturi, accesați{' '}
            <Link href="/dashboard/settings" className="text-primary-600 hover:underline">
              Setările Contului
            </Link>{' '}
            sau contactați-ne la privacy@teinformez.eu.
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">6. Perioada de Stocare</h2>
          <p className="text-gray-700 mb-4">
            Păstrăm datele dumneavoastră:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li><strong>Date de cont:</strong> Până la ștergerea contului</li>
            <li><strong>Date de utilizare:</strong> Maximum 2 ani</li>
            <li><strong>Log-uri tehnice:</strong> Maximum 90 de zile</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">7. Partajarea Datelor</h2>
          <p className="text-gray-700 mb-4">
            Nu vindem datele dumneavoastră. Le putem partaja doar cu:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li><strong>Furnizori de servicii:</strong> Hosting (Hostico), Email (în viitor)</li>
            <li><strong>Autorități:</strong> Doar la cerere legală</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">8. Securitate</h2>
          <p className="text-gray-700 mb-4">
            Protejăm datele dumneavoastră prin:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li>Criptare HTTPS pentru toate comunicările</li>
            <li>Parole hash-uite (nu stocăm parole în text clar)</li>
            <li>Acces restricționat la baza de date</li>
            <li>Monitorizare continuă pentru vulnerabilități</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">9. Cookie-uri</h2>
          <p className="text-gray-700 mb-4">
            Folosim cookie-uri esențiale pentru funcționarea serviciului:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li><strong>Cookie de autentificare:</strong> Pentru a vă menține logat</li>
            <li><strong>Cookie de preferințe:</strong> Pentru a salva setările</li>
          </ul>
          <p className="text-gray-700 mb-4">
            Nu folosim cookie-uri de tracking sau publicitate.
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">10. Contact DPO</h2>
          <p className="text-gray-700 mb-4">
            Pentru orice întrebări privind protecția datelor:
          </p>
          <ul className="list-none text-gray-700 mb-4 space-y-1">
            <li><strong>Email:</strong> privacy@teinformez.eu</li>
            <li><strong>Adresă:</strong> România</li>
          </ul>

          <div className="mt-8 pt-8 border-t border-gray-200">
            <p className="text-gray-500 text-sm">
              Această politică poate fi actualizată periodic. Vă vom notifica despre
              modificările semnificative prin email.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
