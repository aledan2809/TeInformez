import Link from 'next/link'

export const metadata = { title: 'Politica de Confidentialitate — TeInformez' }

export default function PrivacyPage() {
  return (
    <div className="min-h-screen bg-white">
      <header className="sticky top-0 z-50 border-b bg-white/95 backdrop-blur">
        <div className="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
          <Link href="/" className="text-sm text-slate-500 hover:text-slate-900">&larr; Inapoi</Link>
          <span className="text-xs text-slate-400">Actualizat: 26 Februarie 2026</span>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-10 space-y-8">
        <h1 className="text-3xl font-bold">Politica de Confidentialitate</h1>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">1. Introducere</h2>
          <p className="text-slate-700 leading-relaxed">
            TeInformez (&quot;noi&quot;, &quot;platforma&quot;) operat de TechBiz Hub L.L.C-FZ, se angajeaza sa protejeze
            datele dumneavoastra personale in conformitate cu Regulamentul General privind Protectia Datelor
            (GDPR - Regulamentul UE 2016/679) si legislatia romaneasca aplicabila.
          </p>
          <p className="text-slate-700 leading-relaxed">
            Aceasta politica explica ce date colectam, de ce, cum le protejam si ce drepturi aveti.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">2. Operator de date</h2>
          <ul className="list-none text-slate-700 space-y-1">
            <li><strong>Companie:</strong> TechBiz Hub L.L.C-FZ</li>
            <li><strong>Email contact:</strong> support@4pro.io</li>
            <li><strong>Email DPO:</strong> gdpr@4pro.io</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">3. Date colectate</h2>
          <p className="text-slate-700 leading-relaxed">Colectam urmatoarele categorii de date:</p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><strong>Date de identificare:</strong> Nume, adresa de email, parola (hash-uita)</li>
            <li><strong>Date de preferinte:</strong> Categorii de interes, subiecte urmarite, frecventa de livrare, limba preferata</li>
            <li><strong>Date de utilizare:</strong> Articolele citite, bookmark-uri, istoricul de citire, interactiunile cu platforma</li>
            <li><strong>Date de notificari:</strong> Setari de notificari, canale preferate de comunicare</li>
            <li><strong>Date tehnice:</strong> Adresa IP, tipul browserului, sistemul de operare, data si ora accesarii</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">4. Scopul procesarii</h2>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Furnizarea serviciului de stiri personalizate si agregate</li>
            <li>Trimiterea newsletter-ului conform preferintelor dumneavoastra</li>
            <li>Imbunatatirea algoritmilor de recomandare si personalizare</li>
            <li>Generarea de rezumate AI ale articolelor</li>
            <li>Comunicari privind serviciul (actualizari, alerte importante)</li>
            <li>Analiza statistica si imbunatatirea platformei</li>
            <li>Respectarea obligatiilor legale</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">5. Temeiul legal al procesarii</h2>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><strong>Consimtamantul (Art. 6(1)(a) GDPR):</strong> Pentru newsletter, comunicari de marketing si cookie-uri non-esentiale</li>
            <li><strong>Executarea contractului (Art. 6(1)(b) GDPR):</strong> Pentru furnizarea serviciului de stiri personalizate</li>
            <li><strong>Interesul legitim (Art. 6(1)(f) GDPR):</strong> Pentru imbunatatirea serviciului, securitate si prevenirea fraudei</li>
            <li><strong>Obligatie legala (Art. 6(1)(c) GDPR):</strong> Pentru respectarea legislatiei aplicabile</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">6. Stocarea si securitatea datelor</h2>
          <p className="text-slate-700 leading-relaxed">
            Datele sunt stocate pe servere situate in Uniunea Europeana. Aplicam masuri tehnice si organizatorice
            adecvate pentru protectia datelor:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Criptare HTTPS/TLS pentru toate comunicarile</li>
            <li>Parole hash-uite cu algoritmi securizati (nu stocam parole in text clar)</li>
            <li>Acces restricionat la baza de date pe baza de roluri</li>
            <li>Backup-uri regulate si criptate</li>
            <li>Monitorizare continua pentru vulnerabilitati</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">7. Perioada de stocare</h2>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><strong>Date de cont:</strong> Pana la stergerea contului de catre utilizator</li>
            <li><strong>Date de utilizare:</strong> Maximum 2 ani de la ultima activitate</li>
            <li><strong>Log-uri tehnice:</strong> Maximum 90 de zile</li>
            <li><strong>Date de marketing:</strong> Pana la retragerea consimtamantului</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">8. Partajarea datelor</h2>
          <p className="text-slate-700 leading-relaxed">
            Nu vindem si nu inchiriem datele dumneavoastra personale. Le putem partaja doar cu:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><strong>Furnizori de hosting:</strong> Servere in UE pentru gazduirea platformei</li>
            <li><strong>Furnizori de email:</strong> Pentru trimiterea newsletter-ului (doar cu consimtamant)</li>
            <li><strong>Autoritati publice:</strong> Doar la cerere legala motivata</li>
          </ul>
          <p className="text-slate-700 leading-relaxed">
            Toti furnizorii nostri sunt conformi cu GDPR si au semnat acorduri de procesare a datelor (DPA).
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">9. Cookie-uri</h2>
          <p className="text-slate-700 leading-relaxed">Folosim urmatoarele categorii de cookie-uri:</p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><strong>Cookie-uri esentiale:</strong> Necesare pentru functionarea serviciului (autentificare, preferinte)</li>
            <li><strong>Cookie-uri de performanta:</strong> Pentru analiza utilizarii platformei (doar cu consimtamant)</li>
          </ul>
          <p className="text-slate-700 leading-relaxed">
            Nu folosim cookie-uri de tracking publicitar sau cookie-uri ale tertilor pentru profilare.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">10. Drepturile dumneavoastra</h2>
          <p className="text-slate-700 leading-relaxed">
            Conform GDPR, aveti urmatoarele drepturi (detalii complete pe pagina{' '}
            <Link href="/gdpr" className="text-blue-600 hover:underline">GDPR</Link>):
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Dreptul de acces la datele personale</li>
            <li>Dreptul la rectificarea datelor incorecte</li>
            <li>Dreptul la stergerea datelor (&quot;dreptul de a fi uitat&quot;)</li>
            <li>Dreptul la restrictionarea procesarii</li>
            <li>Dreptul la portabilitatea datelor (export JSON)</li>
            <li>Dreptul de opozitie la procesare</li>
            <li>Dreptul de a retrage consimtamantul in orice moment</li>
            <li>Dreptul de a depune plangere la ANSPDCP</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">11. Contact</h2>
          <p className="text-slate-700 leading-relaxed">
            Pentru orice intrebari privind protectia datelor sau pentru a va exercita drepturile:
          </p>
          <ul className="list-none text-slate-700 space-y-1">
            <li><strong>Email general:</strong> support@4pro.io</li>
            <li><strong>Email DPO:</strong> gdpr@4pro.io</li>
            <li><strong>Autoritate de supraveghere:</strong> ANSPDCP — <a href="https://www.dataprotection.ro" className="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">www.dataprotection.ro</a></li>
          </ul>
        </section>

        <div className="border-t pt-6 text-sm text-slate-500">
          Aceasta politica poate fi actualizata periodic. Vom notifica utilizatorii despre
          modificarile semnificative prin email sau prin notificare pe platforma.
        </div>
      </main>
    </div>
  )
}
