# TODO Persistent — TeInformez
> Items rămân până marcate DONE cu dată + commit.
> Last updated: 2026-05-12

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

- [ ] **GR-01** — Newsletter public: landing page `/newsletter` cu formular simplu (email + categorii de interes) — distinct de contul de utilizator, targhet non-registered visitors
- [ ] **GR-02** — Referral tracking: `utm_source` / `utm_medium` persisted în localStorage la primul visit → trimis la înregistrare → admin analytics arată sursa fiecărui user nou
- [ ] **GR-03** — Share widget pe articol: butoane "Share to WhatsApp / Telegram / LinkedIn" cu `utm_source=share&utm_medium=article` pre-fill — cresc reach organic
- [ ] **GR-04** — SEO articole: schema markup `NewsArticle` (JSON-LD) pe `/news/[id]` cu `datePublished`, `author`, `image`, `headline` — Google News eligibility
- [ ] **GR-05** — Push notifications web (PWA optional): `service-worker.js` + permission prompt la a 3-a vizită — notificare la breaking news în categoriile abonate

---

### Faza 3 — Monetization

- [ ] **MN-01** — **Feature matrix Premium vs Free**: definit clar ce e gated (draft propus: Free = ultimele 24h știri, 3 categorii max; Premium = feed nelimitat, toate categoriile, export, Telegram push, statistici avansate) — decizie business înainte de orice cod
- [ ] **MN-02** — **Stripe subscriptions**: integrare `@aledan/stripe` — planuri Lunar (X RON) + Anual (Y RON cu discount); checkout flow din `/dashboard/upgrade`; webhook `customer.subscription.updated` → setează `role=premium` în WP user_meta
- [ ] **MN-03** — **Paywall soft**: articolele premium (gated) arată primele 3 paragrafe + blur + CTA "Continuă cu contul Premium" — implementat în `/news/[id]` pe baza `user.role`
- [ ] **MN-05** — **Newsletter sponsorizat**: template email cu slot "Partener" opțional (banner HTML 600×100px); admin UI în WordPress `/wp-admin` → `TeInformez → Newsletter Ads` — câmp sponsor_name + banner_html + campanie_start/end; injectat în newsletter-urile din intervalul campaniei
- [ ] **MN-06** — **Admin revenue dashboard**: pagină `/wp-admin/admin.php?page=teinformez-revenue` — abonați activi (Free/Premium), MRR, newsletter ads bookings active, conversion rate înregistrare→Premium

---

### Faza 4 — Optimizări post-monetization

- [ ] **OP-01** — Churn prevention: email la 3 zile înainte de expirarea subscripției Premium ("Reînnoiește acum — ofertă 10% dacă reînnoiești azi")
- [ ] **OP-02** — Onboarding Premium: după upgrade, wizard scurt (1 pas) care activează Telegram push + setează toate categoriile → reduce time-to-value
- [ ] **OP-03** — Affiliate links: admin poate taga categorii cu `affiliate_provider` (ex: Bancă X pentru categoria Finanțe) → articolele din acea categorie includ un link de tip "Deschide cont" în sidebar — separat de conținut editorial

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
