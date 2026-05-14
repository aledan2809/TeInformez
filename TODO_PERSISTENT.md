# TODO Persistent — TeInformez
> Items rămân până marcate DONE cu dată + commit.
> Last updated: 2026-05-14

---

## 🔧 Post-soft-launch — Pending

### Email infrastructure (pre-deliverability)

- [x] **EMAIL-01** — Brevo verify `teinformez.eu` (DKIM brevo1/brevo2 + brevo-code TXT) — DONE 2026-05-14 (Hostico DNS)
- [x] **EMAIL-02** — Hostico mailbox `noreply@teinformez.eu` — DONE 2026-05-14
- [x] **EMAIL-03** — WP `teinformez_from_email` flip → `noreply@teinformez.eu` — DONE 2026-05-14
- [x] **EMAIL-04** — Resend verify `techbiz.ae` (DKIM + SPF send.*) — DONE 2026-05-14
- [x] **EMAIL-05** — Fix `$wpdb` global missing în `build_digest_html` (MN-05 regression) — DONE 2026-05-14 (`b975c78`)
- [ ] **EMAIL-06** — SPF Hostico TXT: adaugă `include:spf.brevo.com` (acțiune user, ~5 min Hostico Zone Editor) — îmbunătățește deliverability pe Outlook/Yahoo/corporate (Gmail merge cu DKIM-only, dar SPF=fail e suspicios pentru alți providers)

### CAS — Carousel of Ads (depinde de MA dev)

- [x] **CAS-01** — Halt newsletter sends (kill switch via `teinformez_newsletter_paused` WP option + admin banner toggle pe Newsletter Ads page) — DONE 2026-05-14 (`80554dc`). Set ON until CAS live.
- [x] **CAS-02** — Remove 4PRO day-of-week rotation din email digest (păstrat doar sponsored campaign + slot empty fallback) — DONE 2026-05-14 (`80554dc`)
- [x] **CAS-03** — Hide InFeedAd 4PRO carousel pe `/news` (feature flag `NEXT_PUBLIC_CAS_ENABLED`, default off → slot empty) — DONE 2026-05-14 (`80554dc`)
- [ ] **CAS-04** — **Wire CAS integration with MA** — depinde de MA Launch Plan + CAS endpoint dev (separate session pe MA):
  - Newsletter side: fetch `GET ${MA_API}/api/cas/render?slot=newsletter&recipient=<email_hash>` în `build_digest_html`, inject HTML în `$promo_html` placeholder
  - InFeed side: fetch `GET ${MA_API}/api/cas/render?slot=infeed&visitor=<visitor_hash>` în InFeedAd component, render returned HTML
  - Env vars: `TEINFORMEZ_MA_API_URL` + `TEINFORMEZ_MA_API_KEY` (.env teinformez) + `NEXT_PUBLIC_CAS_ENABLED=true`
  - Resume newsletter sends after CAS endpoint live + tested
  - Estimat: 2-3h în TeInformez + dependență pe MA side

- [ ] **CAS-05** — Eventual: banner CAS pe homepage `/` (între secțiuni 2-3, opțional) — discuție când CAS e live + ai signal că ai inventory real de servit

### Analytics redesign (3 faze)

> **Scop:** simplifică dashboard-ul de la 25+ metrici GA-style la 3 secțiuni utile pentru owner non-marketeer. Răspunde la 3 întrebări: "cresc/scad?", "ce a funcționat?", "ce trebuie să fac?"
> **Decizie 2026-05-14:** GA4 tab păstrat dar mutat în pagină "Advanced" (toggle, nu pe homepage analytics). Single source of truth pentru daily check = noul dashboard simplu.

- [ ] **AN-01** — **Phase 1 — Headline + Trend grafice** (~3-4h):
  - 5 card-uri mari sus: Vizitatori unici săpt. (cu ↑↓ vs săpt. trecută), Useri înregistrați total (+ noi azi), Email subscribers activi (+ noi azi), Articole citite total (cu ↑↓), Top categorie audiență
  - 3 grafice SVG line charts (30 zile + comparison year-ago): Trafic zilnic, Înregistrări noi/zi, Newsletter signups/zi
  - Toate clickable → drill-down detail (păstrat din versiunea actuală)
  - Mutarea celorlalte 20+ metrici la `?view=advanced` (un toggle "Show advanced" pe pagina principală)
  - Files: rewrite `admin/views/analytics.php` + posibil split în `analytics-simple.php` + `analytics-advanced.php`

- [ ] **AN-02** — **Phase 2 — "Ce a funcționat" tables** (~2-3h):
  - Top 5 articole săpt. (cu views + click rate — există deja)
  - Top 5 surse de trafic: organic / facebook / email / RSS / direct — necesită source tracking îmbunătățit în `wp_teinformez_visitor_analytics` (coloană `referrer_source` derivată din `metadata` JSON)
  - Top 5 categorii ca audiență
  - Acțiune recomandată sub fiecare tabel (text scurt, contextual)

- [ ] **AN-03** — **Phase 3 — Revenue mini-dashboard** (~1h, post-CAS-04):
  - Card-uri: Impressii CAS săpt., Sponsorizate active azi, Click rate per slot CAS (newsletter vs infeed)
  - Hookup cu MA CAS data via `GET ${MA_API}/api/cas/metrics?source=teinformez&range=7d`

- [ ] **AN-04** — **GA4 tab → pagină separată "Advanced Analytics"** (parte din AN-01):
  - Mută Custom + Google Analytics tabs + Data Cross-check + cele 25 metrici la `/wp-admin/?page=teinformez-analytics-advanced`
  - Link cross-reference din dashboard nou către advanced

### Monitoring & ops (post-launch nice-to-have)

- [ ] **OP-SENTRY** — Adaugă `NEXT_PUBLIC_SENTRY_DSN` în `/var/www/teinformez-frontend/.env` pe VPS2 + `pm2 restart teinformez` — necesită user să-și facă cont Sentry (free tier 5k errors/lună suficient pentru soft launch). Error monitoring centralizat e gata wired (SL-07), așteaptă doar DSN.

---

---

## 🚀 Soft Launch & Monetization

> Sesiune dedicată. Toate itemele de mai jos sunt **neatastate** — de atacat în ordine faza cu faza.

---

### Faza 1 — Soft Launch Prep (înainte de anunțul public)

- [x] **SL-01** — SEO meta tags: `<title>` dinamic, `<meta description>`, OG tags + Twitter Cards pe toate paginile publice — DONE 2026-05-12 (`176ed16`)
- [x] **SL-02** — Sitemap XML: `/sitemap.xml` index + generateSitemaps pentru acoperire completă articole — DONE 2026-05-12 (`cf9b009` + `b457f63`)
- [x] **SL-03** — Robots.txt: disallow private routes + SITE_URL env — DONE 2026-05-13 (`0bac42f`)
- [x] **SL-04** — Canonical URLs + noindex metadata pe 5 pagini (newsletter, onboarding, news/saved, newsletter/confirm, reset-password) — DONE 2026-05-13 (`b9f7924`)
- [x] **SL-05** — GA4 SPA tracking: `PageViewTracker` cu usePathname+useSearchParams+Suspense — DONE 2026-05-13 (`d4fc41d`)
- [x] **SL-06** — Core Web Vitals → GA4 via `useReportWebVitals`; CLS×1000, non_interaction:true — DONE 2026-05-13 (`486c65e`)
- [x] **SL-07** — Error monitoring: `/api/errors/report` sliding window + spike alert via Brevo (gated `BREVO_API_KEY`+`ADMIN_ALERT_EMAIL`); error.tsx+global-error.tsx raportează local + Sentry — DONE 2026-05-13 (`05ae6b3`). Note: Sentry DSN neactivat (add `NEXT_PUBLIC_SENTRY_DSN` în .env.local VPS când disponibil)
- [x] **SL-08** — Homepage copy: headline "Știri din România, rezumate de AI. Zero zgomot." + 3 trust signals + CTA "Înregistrare gratuită →"; bottom CTA cu 3 benefit icons + "2 minute" urgency; diacritice fixate — DONE 2026-05-13 (`06fa2d3`)
- [x] **SL-09** — Email welcome sequence: email D+1 post-înregistrare dacă userul nu a citit nicio știre → "Ai ratat ieri: TOP 3 știri din categoriile tale" (Brevo automation trigger via webhook) — DONE 2026-05-13 (`ff58ffd`)
- [x] **SL-10** — Social sharing: OG image generată dinamic per articol (titlu + sursă + logo TeInformez) — endpoint `/api/og?title=&source=&category=` cu `next/og` (Node.js runtime pentru VPS) — DONE 2026-05-13 (`b8337da`, deployed + verified HTTP 200 image/png)

---

### Faza 2 — Growth (achiziție utilizatori post-launch)

- [x] **GR-01** — Newsletter public: landing page `/newsletter` cu formular simplu (email + categorii de interes) — DONE (pre-existing, confirmed 2026-05-13; `/newsletter` page + backend `/newsletter/subscribe` fully implemented)
- [x] **GR-02** — Referral tracking: `utm_source` / `utm_medium` persisted în localStorage la primul visit → trimis la înregistrare → admin analytics arată sursa fiecărui user nou — DONE (pre-existing, confirmed 2026-05-13; `utm.ts` + `register/page.tsx` captureUTM + backend saves to `user_meta`)
- [x] **GR-03** — Share widget pe articol: butoane "Share to WhatsApp / Telegram / LinkedIn" cu `utm_source=share&utm_medium=article` pre-fill — DONE (pre-existing, confirmed 2026-05-13; `ShareButtons` component în `NewsDetailClient.tsx` cu WhatsApp/Telegram/LinkedIn/Facebook + UTM tags)
- [x] **GR-04** — SEO articole: schema markup `NewsArticle` (JSON-LD) pe `/news/[id]` cu `datePublished`, `author`, `image`, `headline` — DONE (pre-existing, confirmed 2026-05-13; JSON-LD în `/news/[id]/page.tsx` lines 95-101)
- [x] **GR-05** — Push notifications web (PWA optional): `service-worker.js` + permission prompt la a 3-a vizită — notificare la breaking news în categoriile abonate — DONE 2026-05-13 (`4756012`; `public/sw.js` + `PushPrompt.tsx` + `pushNotifications.ts` + backend `/push/subscribe` 201 verified; TRWG-GW: 3 OK / 3 GATED / 3 EMPTY — baseline parity, no regressions)

---

### Faza 3 — Monetization

- [x] **MN-01** — **Feature matrix**: DECIZIE 2026-05-13 — platformă 100% Free, monetizare ads only. Premium tier DEFER până crește baza de useri. MN-02/MN-03 DEFER corespunzător.
- [~] **MN-02** — **Stripe subscriptions**: DEFER (fără tier Premium activ)
- [~] **MN-03** — **Paywall soft**: DEFER (fără tier Premium activ)
- [x] **MN-05** — **Newsletter sponsorizat**: DONE 2026-05-13 (`fef09fd`) — DB `wp_teinformez_newsletter_ads` + admin UI CRUD (sponsor_name, banner_html, campaign_start/end, status, impressions counter) + delivery handler inject (campanie activă azi → override promo intern, fallback rotație internă). TRWG-GW: toate checks OK.
- [x] **MN-06** — **Admin revenue dashboard**: DONE 2026-05-13 (`b2fcfe3`) — `/wp-admin/?page=teinformez-revenue` — grid cards (useri, newsletter subscribers, articole, campanii ads active, total impressii), tabel campanii cu status azi, status AdSense (configurat/neconfigurat + instrucțiuni), strategia de monetizare curentă documentată. TRWG-GW: toate checks OK.

---

### Faza 4 — Optimizări post-monetization

- [ ] **OP-01** — Churn prevention: email la 3 zile înainte de expirarea subscripției Premium ("Reînnoiește acum — ofertă 10% dacă reînnoiești azi")
- [ ] **OP-02** — Onboarding Premium: după upgrade, wizard scurt (1 pas) care activează Telegram push + setează toate categoriile → reduce time-to-value
- [x] **OP-03** — Affiliate links: admin poate taga categorii cu `affiliate_provider` (ex: Bancă X pentru categoria Finanțe) → articolele din acea categorie includ un link de tip "Deschide cont" în sidebar — separat de conținut editorial — DONE 2026-05-13 (`759c1e0`)

---

## 🎯 TRUE FULL E2E — multi-role business workflows

> Scope per CLAUDE.md §routing [10]. Roles: **Anonymous** / **Registered reader** / **Admin**.
> Test users provisioned 2026-05-11 via WP-CLI on VPS2.

### Test Users
| Role | Email | Password | WP ID |
|------|-------|----------|-------|
| Registered reader | e2e-reader@teinformez.test | E2eReader2026! | 9 |
| Admin | e2e-admin@teinformez.test | E2eAdmin2026! | 10 |

### Pre-requisites (A-D)
- [x] **A** — All blocking audit gaps closed (H-01 through H-08 — 2026-05-11)
- [x] **B** — Test users provisioned (reader + admin — 2026-05-11 via WP-CLI)
- [x] **C** — [7] CODE audit complete (68/100 — 2026-05-11)
- [x] **D** — [8] Journey audit complete (reader + admin — 2026-05-11)

---

### E — Workflow Scenarios

- [x] **E1** — Anonymous browse: load `/`, scroll news list, click article → full content visible (no login wall) — 2026-05-11
- [x] **E2** — Register → onboarding: new reader registers, confirms category subscriptions, reaches dashboard — 2026-05-11 (user_id=11 created, 2 subscriptions set)
- [x] **E3** — Login → personalized feed: reader logs in, auth/me returns role=subscriber — 2026-05-11
- [x] **E4** — Subscribe / unsubscribe category: toggle subscription, verify feed reflects change — 2026-05-11 (sub_id=30 created + toggled)
- [x] **E5** — News detail UX: open article, view_count increments — 2026-05-11 (0→1 verified)
- [x] **E6** — Admin: approve pending news (`status=fetched` → `approved`): DB UPDATE confirmed — 2026-05-11 (id=23673)
- [x] **E7** — Admin: reject news — DB UPDATE confirmed — 2026-05-11 (id=23674, admin_notes set)
- [x] **E8** — Admin analytics guard: unauthenticated=401; non-admin reader=403; admin=200 — 2026-05-11
- [x] **E9** — Juridic Q&A: admin creates draft (id=1), publishes, appears publicly — 2026-05-11
- [x] **E10** — Telegram config guard: reader PUT → 403; admin GET → 200 — 2026-05-11
- [x] **E11** — Password reset flow: POST /auth/forgot-password → success=true — 2026-05-11

---

### F — Concurrency Scenarios

- [x] **F1** — 10 simultaneous GET /news → 10/10 HTTP 200, p95=0.42s — 2026-05-11
- [x] **F2** — 3 concurrent Juridic Q&A publishes → 3/3 succeeded — 2026-05-11

---

### G — Browser Real (Journey Audit)

- [x] **G1** — Reader journey audit: 4 OK / 4 structural — 2026-05-11
- [x] **G2** — Admin journey audit: identical results — 2026-05-11
- [x] **G3** — Mobile viewport (390×844): no nav overlap confirmed via source analysis — SoftRegistrationBanner (only `fixed bottom-0` element) is dead code, never imported; ScrollToTop is corner-only (bottom-6 right-6). 2026-05-12
- [x] **G4** — a11y: aria-label added to SharedHeader nav + Sidebar nav (`4cb0859`); axe-core landmark-unique resolved; 95/100 auditor score accepted. 2026-05-12

---

### H — Parity Checks

- [x] **H1** — API parity: 10/11 pass (`/news/personalized` requires auth — correct) — 2026-05-11
- [x] **H2** — Frontend routes parity: 9/9 HTTP 200 — 2026-05-11

---

### I — Stress

- [x] **I1** — 30 concurrent GET /news → 30/30 HTTP 200, zero 500s, p95=0.69s — 2026-05-11
- [x] **I2** — Audit trail: view_count 5 concurrent increments → 1→6 (atomically) — 2026-05-11

---

## Session Log

| Date | Entry |
|------|-------|
| 2026-05-11 | Phase A+B complete; [7] CODE audit 68/100; TRUE FULL E2E scope defined |
| 2026-05-11 | TRUE FULL E2E 92% complete — E1-E11 ✅, F1-F2 ✅, G1-G2 ✅, H1-H2 ✅, I1-I2 ✅; G3+G4 deferred. Report: `Reports/TRUE-E2E-FULL-2026-05-11.md` |
| 2026-05-12 | Security hardening complete — Sprint 1-5 + review fixes all eliminated. G3+G4 done. M-07 fully closed. Soft Launch & Monetization scope added (SL-01–SL-10, GR-01–GR-05, MN-01–MN-06, OP-01–OP-03). |
| 2026-05-13 | Faza 1 Soft Launch COMPLETE — SL-01 through SL-10 all done. SL-04 canonical/noindex, SL-05 GA4 SPA tracking, SL-06 Core Web Vitals, SL-07 error monitoring, SL-08 homepage copy, SL-09 D+1 re-engagement email with TOP 3 articles, SL-10 /api/og dynamic OG images (HTTP 200 image/png verified). TRWG-GW baseline: 2/49 pre-existing console errors (React hydration #425 + GTM headless), no regressions from SL work. |
| 2026-05-13 | **Faza 2 COMPLETĂ** (GR-01–GR-04 pre-existente; GR-05 push notifications PWA livrat commit `4756012`, TRWG-GW 3OK/3GATED/3EMPTY, zero regresii). **MN-04 eliminat** complet. **Fixes livrate** (commit `9382f00`): (1) dedup articole dubluri — Layer A title similarity ≥75%/12h + Layer B image URL uniqueness/24h; (2) UI Biziday-style — source sus, titlu bold, imagini înjumătățite în list view, `InFeedAd` component (carusel intern 4 proiecte + AdSense fallback via `NEXT_PUBLIC_ADSENSE_CLIENT`+`NEXT_PUBLIC_ADSENSE_SLOT` env vars); (3) email promo bloc rotativ zilnic în digest (minim 1 indiferent de nr. articole); (4) `$thumbnail_budget` 4→1 în delivery handler. **NEXT**: Faza 3 — MN-01 (feature matrix decision) → MN-02 (Stripe) → MN-03 (paywall) → MN-05 (newsletter sponsorizat) → MN-06 (revenue dashboard). |
| 2026-05-13 | Faza 2 Growth COMPLETE — Task 1 (duplicate dedup: title similarity 75%/12h + image uniqueness/24h in class-news-fetcher.php `9382f00`), Task 2 (MN-04 removed), Task 3 (Biziday-style UI: ArticleCard rewrite + InFeedAd carousel + feed injection + email promo block `9382f00`), Task 4 (thumbnail_budget 4→1 `9382f00`). GR-01/02/03/04 confirmed pre-existing. GR-05 implemented: sw.js + PushPrompt (3-visit threshold) + /push/subscribe endpoint (`4756012`). TRWG-GW post-GR-05: 3 OK / 3 GATED / 3 EMPTY — baseline parity. All Faza 2 items DONE. |
| 2026-05-13 | Faze 3+4 COMPLETE — MN-01 (decizie Free + ads only, Premium DEFER), MN-05 newsletter sponsorizat `fef09fd`, MN-06 revenue dashboard `b2fcfe3`, OP-03 affiliate links `759c1e0`. OP-01/02 DEFER (fără Premium tier). |
| 2026-05-14 | **Email infrastructure LIVE** — Brevo `teinformez.eu` verified (DKIM brevo1/brevo2 + brevo-code TXT + DMARC live în Hostico DNS), Resend `techbiz.ae` verified (DKIM + SPF send.MX + send.TXT), Hostico mailbox `noreply@teinformez.eu` created, WP `teinformez_from_email` flip. Smoke test: digest email arrived in Gmail Inbox cu DKIM=pass. **Critical bug fixed**: `class-delivery-handler.php` lipsea `global $wpdb;` în `build_digest_html` (MN-05 regression, commit `b975c78`) — newsletter delivery cron crash-uia silent de la deploy MN-05 până azi. **Pre-CAS cleanup** (commit `80554dc`): halt newsletter sends via `teinformez_newsletter_paused` WP option (kill switch + admin banner toggle), removed 4PRO day-of-week rotation din email digest, hidden InFeedAd 4PRO carousel pe `/news` (feature flag `NEXT_PUBLIC_CAS_ENABLED`). Slot-uri reserved pentru CAS integration (depinde de MA dev). Analytics redesign: propunere documentată (3 faze AN-01/02/03 + decision GA4→Advanced), implementation deferred. EMAIL-06 (SPF Brevo include) + OP-SENTRY pending user action. |
