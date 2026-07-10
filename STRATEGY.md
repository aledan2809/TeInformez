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
- Multi-language frontend UI (backend supports it, UI is Romanian only)

### Formerly out-of-scope, now IN SCOPE (strategy raised to match shipped code — 2026-07-10)
- **Monetization / Premium** — LIVE: Stripe premium tier (paywall pe articole premium, `/subscribe`, `/account/subscription`, billing portal), Revenue Dashboard, Newsletter Ads, Affiliate Links (Faze 3+4, mai 2026). Follow-up cunoscut: endpoint-ul `/subscription/status` a fost făcut Bearer-aware abia 2026-07-10 (până atunci statusul Premium nu se încărca în frontend).
- **Referral system** — planificat ca parte din WOW Nivel 2 (share-to-unlock / referral cu payoff real); încă neconstruit (M5 în TODO).

## Key Goals
- [x] Phase A: User registration, onboarding, dashboard — COMPLETE
- [x] Phase B: News aggregation, AI processing, admin review, news pages — COMPLETE
- [x] Phase C: Email delivery system (scheduled digests, Brevo + wp_mail fallback) — COMPLETE
- [x] Phase D: Analytics, SEO canonical/JSON-LD, LCP image priority, WP transient caching, security H-04–H-08 — COMPLETE
- [x] Phase E: Juridic section, Telegram integration, social posting — COMPLETE

## Constraints
- **Technical**: WordPress backend (PHP 8.3 + MariaDB), Next.js frontend — ambele pe VPS2 (Vercel abandonat, politica VPS+PG-only 2026-06-10)
- **Budget**: AI processing pe groq free-tier (fallback-uri via ai-router-service :3100); OpenAI doar istoric
- **Email**: Brevo free tier (300 emails/day)
- **DNS**: teinformez.eu registered at Hostico, A record → VPS2

## Architecture (updated 2026-07-10)
- **Frontend**: Next.js standalone pe VPS2, PM2 `teinformez` :3002 (build pe VPS după git pull → rsync la `/var/www/teinformez-frontend/`)
- **Backend**: WordPress + `teinformez-core` plugin on VPS2 (`/var/www/teinformez`; plugin = symlink la `/var/www/teinformez-repo/backend/...`)
- **DB**: MariaDB on VPS2 (custom tables incl. stripe_subscriptions)
- **AI routing**: `ai-router-service` :3100 (groq-first; git-versionat local pe VPS din 2026-07-07)
- **API**: REST endpoints under `/wp-json/teinformez/v1/`
- **Deploy**: backend = `deploy.sh teinformez` (git pull + plugin build + PHP-FPM restart, health-check pe URL public); frontend = VPS-build recipe (vezi CLAUDE.md)

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
- **Nivel 1 — ELI12 (motivul de a exista)**: câmpuri AI noi `simple_explanation` + `why_it_matters` per articol; UI **show-first, articol complet vizibil** (revizuit 2026-07-07 seara, feedback user „prea multe ajutoare"). Pe pagina de știre, ordinea e: **(1) „Pe scurt"** — un singur bloc scurt care contopește vechiul „Rezumat" cu explicația plain-language (`simple_explanation`, fallback pe `summary`); **(2) „De ce contează"** (`why_it_matters`) — impactul concret; **(3) „Articolul complet"** — textul integral al știrii, afișat **din oficiu** (nu mai există expander „Citește articolul complet"). Principiu: esențialul întâi, apoi știrea reală — max 2 straturi de ajutor, niciodată să ascundă articolul. Toggle-ul „Mod simplu" a fost **eliminat** (devenise no-op după ce articolul se afișează mereu). *Istoric: opt-in (buton „💡 Explică pe scurt") până 2026-07-07 dimineața → show-first cu expander (WI-2) → show-first fără expander + Rezumat contopit (seara). **Off-ramp**: git history păstrează ambele variante anterioare.*
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
