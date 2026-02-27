import Link from 'next/link'

export const metadata = { title: 'GDPR — Drepturile Tale — TeInformez' }

export default function GdprPage() {
  return (
    <div className="min-h-screen bg-white">
      <header className="sticky top-0 z-50 border-b bg-white/95 backdrop-blur">
        <div className="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
          <Link href="/" className="text-sm text-slate-500 hover:text-slate-900">&larr; Inapoi</Link>
          <span className="text-xs text-slate-400">Actualizat: 26 Februarie 2026</span>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-10 space-y-8">
        <h1 className="text-3xl font-bold">GDPR — Drepturile Tale</h1>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">1. Ce este GDPR?</h2>
          <p className="text-slate-700 leading-relaxed">
            Regulamentul General privind Protectia Datelor (GDPR - Regulamentul UE 2016/679) este
            legislatia europeana care protejeaza datele personale ale cetatenilor UE. TeInformez,
            operat de TechBiz Hub L.L.C-FZ, respecta integral aceste reglementari.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">2. Ce date procesam</h2>
          <p className="text-slate-700 leading-relaxed">
            In contextul platformei de stiri personalizate, procesam:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><strong>Date de identificare:</strong> Nume, email, parola (hash-uita)</li>
            <li><strong>Preferinte:</strong> Categorii de stiri, subiecte urmarite, frecventa de livrare, limba</li>
            <li><strong>Activitate:</strong> Articole citite, bookmark-uri, istoricul de citire</li>
            <li><strong>Notificari:</strong> Setari de notificari, canale de comunicare preferate</li>
            <li><strong>Tehnice:</strong> Adresa IP, browser, dispozitiv (colectate automat)</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">3. Drepturile tale conform GDPR</h2>

          <div className="rounded-lg border p-4 space-y-2">
            <h3 className="font-semibold">Dreptul de acces (Art. 15)</h3>
            <p className="text-slate-700 text-sm">
              Poti solicita o copie completa a tuturor datelor personale pe care le detinem despre tine.
              Vom raspunde in maximum 30 de zile.
            </p>
          </div>

          <div className="rounded-lg border p-4 space-y-2">
            <h3 className="font-semibold">Dreptul la rectificare (Art. 16)</h3>
            <p className="text-slate-700 text-sm">
              Poti corecta orice date personale incorecte sau incomplete. Poti face acest lucru direct
              din Setarile Contului sau prin contact la gdpr@4pro.io.
            </p>
          </div>

          <div className="rounded-lg border p-4 space-y-2">
            <h3 className="font-semibold">Dreptul la stergere (Art. 17) — &quot;Dreptul de a fi uitat&quot;</h3>
            <p className="text-slate-700 text-sm">
              Poti solicita stergerea completa a contului si a tuturor datelor asociate. Stergerea este
              definitiva si ireversibila. Datele vor fi eliminate din toate sistemele noastre in maximum 30 de zile.
            </p>
          </div>

          <div className="rounded-lg border p-4 space-y-2">
            <h3 className="font-semibold">Dreptul la restrictionarea procesarii (Art. 18)</h3>
            <p className="text-slate-700 text-sm">
              Poti solicita restrictionarea procesarii datelor tale in timp ce verificam acuratetea
              datelor sau legitimitatea procesarii.
            </p>
          </div>

          <div className="rounded-lg border p-4 space-y-2">
            <h3 className="font-semibold">Dreptul la portabilitate (Art. 20)</h3>
            <p className="text-slate-700 text-sm">
              Poti exporta toate datele tale intr-un format structurat, utilizat in mod curent si
              care poate fi citit automat (JSON). Functia de export este disponibila in Setarile Contului.
            </p>
          </div>

          <div className="rounded-lg border p-4 space-y-2">
            <h3 className="font-semibold">Dreptul de opozitie (Art. 21)</h3>
            <p className="text-slate-700 text-sm">
              Poti obiecta la procesarea datelor tale bazata pe interesul nostru legitim. In cazul
              marketing-ului direct, opozitia va fi respectata imediat.
            </p>
          </div>

          <div className="rounded-lg border p-4 space-y-2">
            <h3 className="font-semibold">Dreptul de a retrage consimtamantul (Art. 7(3))</h3>
            <p className="text-slate-700 text-sm">
              Poti retrage consimtamantul in orice moment, fara a afecta legalitatea procesarii
              anterioare retragerii. Dezabonarea de la newsletter si notificari este disponibila
              din Setarile Contului.
            </p>
          </div>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">4. Cum iti exerciti drepturile</h2>
          <p className="text-slate-700 leading-relaxed">Ai mai multe optiuni:</p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><strong>Self-service:</strong> Acceseaza Setarile Contului pentru rectificare, export sau stergere</li>
            <li><strong>Email:</strong> Trimite cerere la <strong>gdpr@4pro.io</strong></li>
          </ul>
          <p className="text-slate-700 leading-relaxed">
            Vom confirma primirea cererii in 48 de ore si vom raspunde in maximum 30 de zile calendaristice.
            In cazuri complexe, termenul poate fi extins cu inca 60 de zile, cu notificare prealabila.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">5. Stocarea datelor</h2>
          <p className="text-slate-700 leading-relaxed">
            Toate datele sunt stocate pe servere situate in <strong>Uniunea Europeana</strong>.
            Nu transferam date in afara SEE (Spatiul Economic European) fara garantii adecvate
            conform Capitolului V din GDPR.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">6. Incalcari ale securitatii datelor</h2>
          <p className="text-slate-700 leading-relaxed">
            In cazul unei incalcari a securitatii datelor care prezinta un risc ridicat pentru
            drepturile si libertatile dumneavoastra, vom:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Notifica ANSPDCP in maximum 72 de ore de la constatare</li>
            <li>Va informa personal prin email fara intarzieri nejustificate</li>
            <li>Documenta incidentul si masurile luate</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">7. Procesare automata si profilare</h2>
          <p className="text-slate-700 leading-relaxed">
            Folosim algoritmi AI pentru personalizarea stirilor (recomandari bazate pe preferinte si
            istoric). Aceasta profilare:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Nu produce efecte juridice sau similare semnificative</li>
            <li>Poate fi dezactivata din Setarile Contului (revenire la stiri generale)</li>
            <li>Se bazeaza pe consimtamantul dumneavoastra explicit</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">8. Plangeri</h2>
          <p className="text-slate-700 leading-relaxed">
            Daca nu sunteti multumit de modul in care va procesam datele, aveti dreptul de a depune
            plangere la autoritatea de supraveghere:
          </p>
          <ul className="list-none text-slate-700 space-y-1">
            <li><strong>ANSPDCP</strong> (Autoritatea Nationala de Supraveghere a Prelucrarii Datelor cu Caracter Personal)</li>
            <li><strong>Website:</strong> <a href="https://www.dataprotection.ro" className="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">www.dataprotection.ro</a></li>
            <li><strong>Email:</strong> anspdcp@dataprotection.ro</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">9. Contact DPO</h2>
          <ul className="list-none text-slate-700 space-y-1">
            <li><strong>Email DPO:</strong> gdpr@4pro.io</li>
            <li><strong>Email general:</strong> support@4pro.io</li>
            <li><strong>Companie:</strong> TechBiz Hub L.L.C-FZ</li>
          </ul>
        </section>

        <div className="border-t pt-6 text-sm text-slate-500">
          Aceasta pagina este actualizata periodic. Consultati si{' '}
          <Link href="/privacy" className="text-blue-600 hover:underline">Politica de Confidentialitate</Link> si{' '}
          <Link href="/terms" className="text-blue-600 hover:underline">Termenii si Conditiile</Link> pentru informatii complete.
        </div>
      </main>
    </div>
  )
}
