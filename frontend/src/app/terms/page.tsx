import Link from 'next/link'

export const metadata = { title: 'Termeni si Conditii — TeInformez' }

export default function TermsPage() {
  return (
    <div className="min-h-screen bg-white">
      <header className="sticky top-0 z-50 border-b bg-white/95 backdrop-blur">
        <div className="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
          <Link href="/" className="text-sm text-slate-500 hover:text-slate-900">&larr; Inapoi</Link>
          <span className="text-xs text-slate-400">Actualizat: 26 Februarie 2026</span>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-10 space-y-8">
        <h1 className="text-3xl font-bold">Termeni si Conditii</h1>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">1. Introducere</h2>
          <p className="text-slate-700 leading-relaxed">
            Bine ati venit pe TeInformez! Acesti Termeni si Conditii (&quot;Termenii&quot;) guverneaza utilizarea
            platformei noastre de agregare si personalizare a stirilor, operata de TechBiz Hub L.L.C-FZ.
            Prin accesarea sau utilizarea serviciului, sunteti de acord cu acesti Termeni.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">2. Descrierea serviciului</h2>
          <p className="text-slate-700 leading-relaxed">
            TeInformez este o platforma de agregare a stirilor care utilizeaza inteligenta artificiala pentru
            a oferi continut personalizat bazat pe interesele dumneavoastra. Serviciul include:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Agregarea stirilor din surse multiple verificate</li>
            <li>Personalizarea continutului pe baza preferintelor utilizatorului</li>
            <li>Rezumate AI ale articolelor</li>
            <li>Livrarea prin email si alte canale de comunicare</li>
            <li>Bookmark-uri si istoricul de citire</li>
            <li>Alerte si notificari personalizate</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">3. Inregistrare si cont</h2>
          <p className="text-slate-700 leading-relaxed">
            Pentru a utiliza serviciile personalizate, trebuie sa va creati un cont. Sunteti responsabil pentru:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Furnizarea de informatii corecte si actualizate</li>
            <li>Pastrarea confidentialitatii parolei</li>
            <li>Toate activitatile care au loc in contul dumneavoastra</li>
            <li>Notificarea imediata in cazul accesului neautorizat</li>
          </ul>
          <p className="text-slate-700 leading-relaxed">
            Ne rezervam dreptul de a suspenda sau sterge conturile care incalca acesti Termeni.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">4. Continut si proprietate intelectuala</h2>
          <p className="text-slate-700 leading-relaxed">
            Stirile agregate provin de la terti si sunt supuse drepturilor de autor ale acestora.
            TeInformez ofera rezumate si link-uri catre sursele originale, nu revendica proprietatea
            asupra continutului original al surselor.
          </p>
          <p className="text-slate-700 leading-relaxed">
            Interfata platformei, algoritmii de personalizare, modelele AI si marca TeInformez sunt
            proprietatea TechBiz Hub L.L.C-FZ.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">5. Utilizare acceptabila</h2>
          <p className="text-slate-700 leading-relaxed">Nu este permis sa:</p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Utilizati serviciul pentru activitati ilegale</li>
            <li>Incercati sa accesati neautorizat sistemele noastre</li>
            <li>Redistribuiti continutul in scopuri comerciale fara permisiune</li>
            <li>Creati conturi multiple sau false</li>
            <li>Utilizati scraping automat sau boti pe platforma</li>
            <li>Incarcati continut ofensator, defaimator sau ilegal</li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">6. Protectia datelor</h2>
          <p className="text-slate-700 leading-relaxed">
            Respectam Regulamentul General privind Protectia Datelor (GDPR). Pentru detalii complete
            despre cum procesam datele dumneavoastra, consultati:
          </p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li><Link href="/privacy" className="text-blue-600 hover:underline">Politica de Confidentialitate</Link></li>
            <li><Link href="/gdpr" className="text-blue-600 hover:underline">Pagina GDPR — Drepturile dumneavoastra</Link></li>
          </ul>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">7. Disponibilitatea serviciului</h2>
          <p className="text-slate-700 leading-relaxed">
            Depunem eforturi rezonabile pentru a mentine serviciul disponibil, dar nu garantam
            functionarea neintrerupta. Putem efectua mentenanta planificata cu notificare prealabila.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">8. Limitarea raspunderii</h2>
          <p className="text-slate-700 leading-relaxed">TeInformez nu este responsabil pentru:</p>
          <ul className="list-disc list-inside text-slate-700 space-y-1">
            <li>Exactitatea stirilor agregate de la terti</li>
            <li>Intreruperi temporare ale serviciului</li>
            <li>Decizii luate pe baza informatiilor din stiri</li>
            <li>Calitatea rezumatelor generate de AI</li>
            <li>Pierderi indirecte sau consecventiale</li>
          </ul>
          <p className="text-slate-700 leading-relaxed">
            Raspunderea noastra totala este limitata la valoarea abonamentului platit in ultimele 12 luni
            (sau 0 pentru utilizatorii gratuiti).
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">9. Modificari ale termenilor</h2>
          <p className="text-slate-700 leading-relaxed">
            Ne rezervam dreptul de a modifica acesti Termeni. Vom notifica utilizatorii despre
            modificarile semnificative cu cel putin 30 de zile inainte prin email sau notificare
            pe platforma. Continuarea utilizarii serviciului dupa notificare constituie acceptarea noilor Termeni.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">10. Rezilierea</h2>
          <p className="text-slate-700 leading-relaxed">
            Puteti sterge contul in orice moment din Setarile Contului. La stergerea contului,
            vom elimina datele dumneavoastra conform{' '}
            <Link href="/privacy" className="text-blue-600 hover:underline">Politicii de Confidentialitate</Link>.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">11. Legislatie aplicabila</h2>
          <p className="text-slate-700 leading-relaxed">
            Acesti Termeni sunt guvernati de legislatia din Romania. Orice disputa va fi solutionata
            de instantele competente din Romania, fara a aduce atingere drepturilor consumatorilor
            din UE de a apela la instantele din tara lor de resedinta.
          </p>
        </section>

        <section className="space-y-3">
          <h2 className="text-xl font-semibold">12. Contact</h2>
          <ul className="list-none text-slate-700 space-y-1">
            <li><strong>Email:</strong> support@4pro.io</li>
            <li><strong>Companie:</strong> TechBiz Hub L.L.C-FZ</li>
          </ul>
        </section>

        <div className="border-t pt-6 text-sm text-slate-500">
          Prin utilizarea serviciului TeInformez, confirmati ca ati citit si acceptat acesti Termeni si Conditii.
        </div>
      </main>
    </div>
  )
}
