'use client';

import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';

export default function TermsPage() {
  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="container-custom max-w-4xl">
        {/* Back button */}
        <Link href="/" className="inline-flex items-center text-primary-600 hover:text-primary-700 mb-8">
          <ArrowLeft className="h-4 w-4 mr-2" />
          Înapoi la pagina principală
        </Link>

        <div className="card prose prose-lg max-w-none">
          <h1 className="text-3xl font-bold text-gray-900 mb-8">Termeni și Condiții</h1>

          <p className="text-gray-600 mb-6">
            <em>Ultima actualizare: Ianuarie 2025</em>
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">1. Introducere</h2>
          <p className="text-gray-700 mb-4">
            Bine ați venit la TeInformez.eu! Acești Termeni și Condiții guvernează utilizarea
            serviciilor noastre de agregare și personalizare a știrilor. Prin utilizarea platformei
            noastre, sunteți de acord cu acești termeni.
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">2. Descrierea Serviciului</h2>
          <p className="text-gray-700 mb-4">
            TeInformez.eu este o platformă de agregare a știrilor care utilizează inteligența
            artificială pentru a vă oferi conținut personalizat bazat pe interesele dumneavoastră.
            Serviciul include:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li>Agregarea știrilor din surse multiple verificate</li>
            <li>Personalizarea conținutului pe baza preferințelor</li>
            <li>Livrarea prin email și alte canale</li>
            <li>Sumarizarea articolelor folosind AI</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">3. Înregistrare și Cont</h2>
          <p className="text-gray-700 mb-4">
            Pentru a utiliza serviciile noastre personalizate, trebuie să vă creați un cont.
            Sunteți responsabil pentru:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li>Furnizarea de informații corecte și actualizate</li>
            <li>Păstrarea confidențialității parolei</li>
            <li>Toate activitățile care au loc în contul dumneavoastră</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">4. Conținut și Proprietate Intelectuală</h2>
          <p className="text-gray-700 mb-4">
            Știrile agregate provin de la terți și sunt supuse drepturilor de autor ale acestora.
            TeInformez.eu oferă sumarizări și link-uri către sursele originale, nu revendică
            proprietatea asupra conținutului original.
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">5. Protecția Datelor (GDPR)</h2>
          <p className="text-gray-700 mb-4">
            Respectăm Regulamentul General privind Protecția Datelor (GDPR). Aveți dreptul să:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li>Accesați datele dumneavoastră personale</li>
            <li>Rectificați informațiile incorecte</li>
            <li>Ștergeți contul și toate datele asociate</li>
            <li>Exportați datele într-un format portabil</li>
            <li>Vă retrageți consimțământul în orice moment</li>
          </ul>
          <p className="text-gray-700 mb-4">
            Pentru detalii complete, consultați <Link href="/privacy" className="text-primary-600 hover:underline">Politica de Confidențialitate</Link>.
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">6. Utilizare Acceptabilă</h2>
          <p className="text-gray-700 mb-4">
            Nu este permis să:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li>Utilizați serviciul pentru activități ilegale</li>
            <li>Încercați să accesați neautorizat sistemele noastre</li>
            <li>Redistribuiți conținutul în scopuri comerciale fără permisiune</li>
            <li>Creați conturi multiple sau false</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">7. Limitarea Răspunderii</h2>
          <p className="text-gray-700 mb-4">
            TeInformez.eu nu este responsabil pentru:
          </p>
          <ul className="list-disc list-inside text-gray-700 mb-4 space-y-2">
            <li>Exactitatea știrilor agregate de la terți</li>
            <li>Întreruperi temporare ale serviciului</li>
            <li>Decizii luate pe baza informațiilor din știri</li>
          </ul>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">8. Modificări ale Termenilor</h2>
          <p className="text-gray-700 mb-4">
            Ne rezervăm dreptul de a modifica acești termeni. Vă vom notifica despre modificările
            semnificative prin email sau prin notificare pe platformă.
          </p>

          <h2 className="text-xl font-semibold text-gray-900 mt-8 mb-4">9. Contact</h2>
          <p className="text-gray-700 mb-4">
            Pentru întrebări sau reclamații, ne puteți contacta la:
          </p>
          <ul className="list-none text-gray-700 mb-4 space-y-1">
            <li><strong>Email:</strong> contact@teinformez.eu</li>
            <li><strong>Adresă:</strong> România</li>
          </ul>

          <div className="mt-8 pt-8 border-t border-gray-200">
            <p className="text-gray-500 text-sm">
              Prin utilizarea serviciului TeInformez.eu, confirmați că ați citit și acceptat
              acești Termeni și Condiții.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
