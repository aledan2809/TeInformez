# Strategy — TeInformez
Last Updated: 2026-05-31

## Vision
AI-powered personalized news platform for the Romanian market. Aggregates news from RSS feeds, processes with OpenAI (translate, summarize, categorize), delivers personalized digests to users via email and social channels.

Cloneable architecture: configurable language/country/sources for replication to other markets.

## Scope

### In Scope
- News aggregation from RSS feeds (10+ sources configured)
- AI processing: translation, summarization, categorization (OpenAI GPT-4)
- User registration with GDPR consent + onboarding wizard
- Personalized news feed based on subscriptions
- Admin review queue (approve/reject/auto-approve)
- Email delivery via Brevo (daily/weekly digest)
- GDPR compliance (export, delete, consent tracking)
- WordPress backend (PHP plugin) + Next.js frontend (Vercel)

### Out of Scope (for now)
- Mobile app (future)
- Web scraping (only RSS for now)
- Paid subscriptions / monetization
- Referral system
- Multi-language frontend UI (backend supports it, UI is Romanian only)

## Key Goals
- [x] Phase A: User registration, onboarding, dashboard — COMPLETE
- [x] Phase B: News aggregation, AI processing, admin review, news pages — COMPLETE
- [x] Phase C: Email delivery system (scheduled digests, Brevo + wp_mail fallback) — COMPLETE
- [x] Phase D: Analytics, SEO canonical/JSON-LD, LCP image priority, WP transient caching, security H-04–H-08 — COMPLETE
- [x] Phase E: Juridic section, Telegram integration, social posting — COMPLETE

## Constraints
- **Technical**: WordPress backend (PHP 8.3 + MariaDB), Next.js frontend (Vercel)
- **Hosting**: VPS2 (72.62.155.74) for backend, Vercel for frontend
- **Budget**: OpenAI target ~$10-25/month (10-30 articles/day)
- **Email**: Brevo free tier (300 emails/day)
- **DNS**: teinformez.eu registered at Hostico, A record → VPS2

## Architecture
- **Frontend**: Next.js on Vercel (`teinformez.vercel.app`)
- **Backend**: WordPress + `teinformez-core` plugin on VPS2
- **DB**: MariaDB on VPS2 (9 custom tables)
- **API**: 53 REST endpoints under `/wp-json/teinformez/v1/`
- **Deploy**: `deploy.sh teinformez` on VPS2 (git pull + PHP-FPM restart)

---

## WOW / Viralitate — Plan (added 2026-05-31)

### Diagnostic onest al stării curente
Produsul (Phase A–E complete) e un **agregator de știri competent tehnic, dar commodity** — reformulează știri disponibile gratuit oriunde. Scorul realist pe "WOW / viral ușor" ≈ **2/10**. Cauze:
1. **Conținut commodity** — zero motiv să-l distribui (un rezumat neutru nu oferă status/utilitate/emoție celui care dă share).
2. **Era anti-viral prin prospețime** — backlog de ~3.3 zile (REZOLVAT 2026-05-31, vezi `news-throughput` în TODO).
3. **"Planul de viralitate" = instalație, nu motivație** — butoane de share + referral amplifică un impuls de share care nu exista.
4. Niciun artefact distribuibil, niciun cârlig de identitate/emoție, niciun flywheel.

**Principiu**: viral = oamenii distribuie *neîntrebați* pentru că share-ul îi avantajează pe EI. Nu lipim mecanică virală pe un commodity — întâi un motiv să existe, apoi bucla de share în jurul lui.

### Diferențiatorul ales: „Explică-mi simplu" (ELI12)
Fiecare știre primește un strat AI generat: **„Pe scurt, pe înțelesul tuturor"** — 3 fraze pe care le înțelege un copil de 12 ani — plus **„Ce înseamnă pentru tine"** (impact concret: preț, taxe, lege, viață de zi cu zi).

De ce ELI12 (vs bias-meter / audio digest):
- **Apolitic** → audiență mult mai largă (bias-meter e tribal).
- **Cel mai simplu tehnic** → un câmp AI în plus pe pipeline-ul existent (groq, ~3s/item).
- **Foarte distribuibil** → explicația simplă e exact ce trimiți unui prieten/părinte care „nu înțelege ce se întâmplă".

### Plan pe niveluri
- **Nivel 0 — prospețime** ✅ DONE 2026-05-31 (batch 30 + groq-first; news fresh, nu de 3 zile). Prerequisit absolut.
- **Nivel 1 — ELI12 (motivul de a exista)**: câmpuri AI noi `simple_explanation` + `why_it_matters` per articol; UI **show-first neutral** (pivot 2026-07-07, aprobat user) — cardul „Pe scurt" + „De ce contează" e afișat **din oficiu** pe pagina de știre, iar articolul integral trece în spatele unui expander („Citește articolul complet"). Cadrarea e neutră (nu „îți explicăm pentru că nu înțelegi", ci „esențialul întâi, detaliul la un click"), plus toggle „Mod simplu" pe tot site-ul. *Istoric: până la 2026-07-07 era opt-in (buton „💡 Explică pe scurt" + argumentul anti-condescendență). **Off-ramp**: dacă show-first nu prinde / percepția devine condescendentă, revert WI-2 (`NewsDetailClient.tsx`) readuce butonul opt-in — implementarea e păstrată în istoricul git.*
- **Nivel 2 — mecanica virală (construită ÎN JURUL ELI12)**:
  - **Share cards** (via `next/og`, nu AICR — zero deps, $0): o imagine per știre cu explicația ELI12 = artefactul distribuibil (story FB/IG/WhatsApp), NU link-ul.
  - ~~**„Explică-mi asta" interactiv** (tool public unde lipești orice)~~ — **ABANDONAT 2026-06-06**: fără moat vs Google/ChatGPT. Înlocuit cu butonul „Explică pe scurt" direct pe fiecare știre (Nivel 1) — noi facem efortul pentru cititor, doar pe știri.
  - **Share-to-unlock / referral** cu payoff real (digest fără reclame / premium), nu leaderboard sec.
- **Nivel 3 — distribuție activă**:
  - Clipuri scurte verticale (ELI12 → 20-30s TikTok/Reels/Shorts, text-on-screen + TTS).
  - SEO cu conținut **unic** (ELI12, nu rephrase duplicat) → rank pe „ce înseamnă X explicat simplu".
  - Auto-post pe Telegram/FB al **cardului ELI12**, nu al link-ului.

### Metrici de „viral" (altele decât pageviews)
- **Share rate** (% articole citite → distribuite), **K-factor** (invitați → signup-uri), trafic organic din share-uri, timp pe site, retenție digest.

### Referral: mutat din Out-of-Scope → In-Scope (parte din Nivel 2).

### Idei opționale / rezervă (NU obligatorii, NU pe drumul critic)
- **Buton „Ascultă știrea" (TTS)** — feature de accesibilitate hands-free (ex: la volan, ocupat). Reutilizează textul ELI12 sau articolul integral. Opțional, prioritate joasă; **nu** e produs flagship, **nu** blochează nimic.
- **Bias / spin-meter** — rezervă. Se activează DOAR dacă ELI12 prinde tracțiune și vrem un al doilea cârlig. Mai greu (multi-sursă + cadrare politică) și divizant — de aceea nu acum.

**Milestones detaliate + estimări**: vezi `TODO_PERSISTENT.md` item `🚀 WOW/VIRAL — ELI12`.
