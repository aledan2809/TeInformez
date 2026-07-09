# TeInformez — Direct Changes Ledger (2026-07)

> RESTRICT project (WordPress/PHP custom, manual deploy). Per Master CLASSIFICATION §2c, direct changes are logged here.

## 2026-07-07 — Proactive UX pivot: ELI12 show-first (WI-2) + proactive dashboard (WI-3)

**Trigger**: PA UX audit (mockup "acum vs propunere", 4 ecrane) — the app should be proactive and guide the reader step-by-step per role, not make them hunt through menus. User approved the proposal; delivered per-item in mesh regime (dev → /review → fix).

**Changes (frontend Next.js `teinformez-frontend`, commits on `master`)**:

1. **WI-3 — proactive dashboard** (`791a2a5`, already pushed before this session): `dashboard/page` card "Ce urmează pentru tine" (next-best-action) + completeness bar + stat explainers. tsc ✅, /review ✅.

2. **WI-2 — ELI12 show-first neutral** (`81eebc2`): `frontend/src/app/news/[id]/NewsDetailClient.tsx` — the plain-language "Pe scurt" + "De ce contează" card now renders **by default** when an article has `simple_explanation`/`why_it_matters`; the full article moves behind a "Citește articolul complet" expander. Neutral framing (essentials first, detail one click away). **Reverses the previously-documented opt-in model** — `STRATEGY.md` Nivel 1 updated opt-in → show-first + **off-ramp** note (revert WI-2 restores the opt-in button; implementation preserved in git history). Same commit also folds in two small pre-existing WIP hardenings on the same file (documented in the commit body, not lumped silently): `formatDate` try/catch + `isNaN` guard; `handleShare` early `if (!news) return`. tsc --noEmit ✅ (re-verified on real output this session), /review ✅ (prior session).

**Deploy**: frontend deploy = local `next build` → tar → `deploy.sh teinformez_frontend`. Both WI-2 + WI-3 go live together (WI-3 was pushed but not yet deployed). Verified on `https://teinformez.eu` — see verification below.

**Deploy (canonical CLAUDE.md recipe, NOT the handoff's "local build → tar")**: `ssh VPS2 "cd /var/www/teinformez-repo && git pull origin master && cd frontend && npm run build && cp -r .next/static .next/standalone/.next/ && cp -r public/ .next/standalone/public/ && rsync -a --delete .next/standalone/ /var/www/teinformez-frontend/ && PORT=3002 pm2 restart teinformez"`. The real frontend deploy builds **on the VPS after git pull** (no `deploy.sh teinformez_frontend` exists) — this also sidesteps the "local dev corrupts .next" blocker entirely. Preflight: VPS disk 22G free (78%), `.env.local` present only at repo source (build-time NEXT_PUBLIC bake), none at rsync target → `--delete` safe.

**Verification (live)**:
- Repo HEAD VPS `4180a90` → `81eebc2` (git pull brought WI-3 `dashboard/page.tsx` + WI-2 `NewsDetailClient.tsx`); `npm run build` clean.
- PM2 `teinformez` (id 4) online, 0 unstable restarts, stable at 22s+ uptime; local `:3002` → 200; `https://teinformez.eu` → 200.
- Shipped client chunk `news/[id]/page-*.js`: opt-in string "Explică pe scurt" = **0 occurrences** (button truly gone); expander "Citește articolul complet" **present**; dashboard "Ce urmează pentru tine" **present** in `dashboard/page-*.js`.
- Pre-existing non-regression: `sharp` image-optimization warning in standalone mode (predates this deploy; page still 200).

**Follow-up (flagged, not built — surgical scope)**: after WI-2, `simpleModeStore.ts` `eli12Discovered` / `markEli12Discovered` + persisted `eli12` state are orphaned dead code (0 usages outside the store), plus a stale comment referencing the removed button. Tiny cleanup commit when convenient (kept out of the WI-2 commit to stay surgical). → **DONE same day, see batch below (`65dd221`).**

---

## 2026-07-07 (PM) — Mesh batch: ELI12 cleanup + WI-4 homepage + mobile nav + WI-5 Cabina de comandă (all LIVE)

**Trigger**: user approved "toate, în regim de instrucțiuni mesh" — the remaining UX-proposal items, sequential dev → /review → fix per item.

**Changes (commits on `master`, all pushed)**:

1. **`65dd221` chore(store)** — removed orphaned ELI12 opt-in state (`eli12Discovered`/`markEli12Discovered` + stale comment) from `simpleModeStore.ts`. Stale localStorage keys ignored by zustand persist on rehydrate. Verified absent from deployed chunks.

2. **`1d61897` feat(home) WI-4** — top banner reworked into a 3-step quick-start band („⚡ Pornește în 20 de secunde": cont → categorii → digest), single CTA path to `/register` („Începe acum →"); lead article cards render `simple_explanation` with a „📌 Pe scurt:" marker (line-clamp-2, summary fallback); homepage + metadata copy cleaned of 'AI' wording (active rule). /review: approve (empty-string fallback, single ArticleCard consumer, ol/li semantics noted).

3. **`72bc48e` fix(api)** — found during live verification: `/news/homepage` SELECT omitted the `simple_explanation` column → `format_news_item` emitted null on every card despite **100% ELI12 coverage** on last-14-days published items (verified in DB: 70/70). One column added to the SELECT. This was the handoff's anticipated „verifică dacă API întoarce simple_explanation" case — it half-did (key present, value never selected).

4. **`d3bf346` feat(dashboard)** — responsive mobile nav: sidebar (always-visible `w-64`, crushed phone content) now `hidden md:block`; mobile gets sticky top bar (logo + hamburger) + slide-over drawer reusing the same Sidebar (new `onNavigate` prop). Drawer closes on backdrop / X / nav click / route change. /review fix applied pre-commit: floating X with `calc(theme(...))` was fragile on <301px screens → moved inside the drawer container.

5. **`ba98ffd` feat(admin) WI-5** — „🎛️ Cabina de comandă" replaces the stub TeInformez dashboard: 4 health tiles (Publicare cu status-ul celor 4 cron-uri critice / Livrări 24h / Coadă / Venit) + severity-ordered „📌 De făcut acum" list. Detects the June-outage failure class: cron hook **missing OR overdue >30min** (wp-cron not ticking) → CRIT. `php -l` clean; all SQL prepared or constant; `esc_html`/`wp_kses_post` on output; `manage_options` + ABSPATH guards.

**Deploys**:
- Frontend: canonical VPS-build recipe (git pull `81eebc2→ba98ffd` then `→72bc48e`, build, static/public copy, rsync, pm2 restart). PM2 stable, 0 unstable; :3002 → 200.
- Backend: `/var/www/deploy.sh teinformez` ×2 (plugin symlink + PHP-FPM restart), HEAD `72bc48e`.

**Verification (live, real output)**:
- tsc --noEmit EXIT=0 (whole frontend, items 1+2+4).
- Homepage SSR (post-ISR revalidate): „Pornește în 20 de secunde" ✅, „Începe acum" ✅, **„Pe scurt:" ×5** (one per section lead) ✅, 'de AI' = 0 ✅.
- Homepage API: 5/5 section leads + hero now carry `simple_explanation` (was 0/5 + null before `72bc48e`; transient `teinformez_homepage_data` deleted post-deploy).
- Mobile nav: „Deschide meniul" marker present in deployed `app/dashboard/layout-*.js`; `eli12Discovered` absent from all chunks. (Full click-through is auth-gated — chunk + tsc evidence; visual walk deferred.)
- WI-5: rendered server-side via `wp eval` with a real admin (user 3): LEN=4754, title OK, 3 tile badges, „Totul funcționează" all-green — consistent with the healthy post-4180a90 state.

**Pre-existing quirk (flagged, NOT touched)**: `deploy.sh teinformez`'s own health check prints „WordPress: HTTP 404 / REST API: HTTP 404" — it probes `http://localhost/wp-json/` with a Host header, which no longer routes (public `https://teinformez.eu/wp-json/...` is 200). The check is broken, not the site. Candidate one-line fix in `deploy.sh` (use the public URL), needs its own confirm since deploy.sh serves multiple projects.

---

## 2026-07-07 (seara) — Mesh batch 2: deploy.sh health-check + mobile-nav verify + npm audit + GDPR gate + SPOF + TODO sync

**Trigger**: user "fa-le acum pe toate in regim de instructiuni mesh" — remaining flags + introspection items verificate ca ne-stale.

**1. deploy.sh teinformez health-check (VPS shared script, 2 linii)**: proba `http://localhost/wp-json/` cu Host header nu mai rutează (vhost servește wp-json doar pe 443) → 404 fals permanent. Patched la URL-ul public https (backup `deploy.sh.bak-2026-07-07-healthcheck`); run real → **200/200**. Template canonic re-sincronizat în Master (`645fdf4`).

**2. Mobile nav — verificare vizuală REALĂ (TWG-style pe `d3bf346`)**: Playwright iPhone 13 pe live, login reader e2e: **7/7 PASS** (hamburger vizibil, sidebar ascuns, drawer se deschide, link navighează + drawer se închide, X închide). Primele 2 run-uri au picat pe bug de locator în SCRIPTUL de test (matcha nav-ul desktop ascuns din DOM) — nu în UI; screenshots confirmă și WI-3 live pe mobil.

**3. npm audit fix (`65ebfc7`)**: **25 vulns → 5** (rămase: next/postcss chain, cer `next@16` breaking — deliberat NEforțat). Incident pe drum: 2 procese `npm audit fix` concurente au corupt node_modules local (erori tsc fantomă `@sentry/nextjs`/`react-hook-form`) → kill ambele + install+fix+tsc serializat → TSC_EXIT=0.

**4. Două bug-uri REALE de lockfile găsite+fixate la deploy** (npm ci pe VPS pica):
   - `5a3bda0` — integrity stale pt `vendor/stripe-module.tgz` (npm 11 local a înregistrat hash din cache vechi; toate 3 copiile — local/HEAD/VPS — identice) → EINTEGRITY. Patched la sha512 real.
   - `2a333fa` — 3 intrări stale dintr-un stripe-module.tgz VECHI (placeholder gol `@projects/AIRouter {}` = null-target care crapă reify-ul npm 10 „Cannot destructure property 'package'", link nested spre el, dep `ai-router: file:../AIRouter` deși tgz-ul curent îl are doar ca peer `*`). Rezoluția funcțională păstrată: link `node_modules/ai-router → ../../AIRouter` (sibling, identic pe VPS). Clasa L93, varianta lockfile-level. Post-fix: **npm ci EXIT=0 + BUILD_EXIT=0** pe VPS (gated pe exit code, fără pipe-mask).

**5. GDPR consent-gate LIVE (`5026f15`)**: GA4 se încarcă DOAR după accept explicit; banner NOU pt vizitatori anonimi (gap real: TIConsentGate arăta bannere doar userilor logați); accept-ul COOKIES din fluxul Legal deschide gate-ul; consimțământul server-side existent se oglindește local (nu re-întreabă). + h1 sr-only pe homepage. **Verificare live Playwright context proaspăt: 7/7 PASS** — 0 request-uri GA pre-consent (înainte gtag era preloaded în head pt toți), Accept→GA (persistă la reload), **Refuz→zero GA vreodată**. Rămas: CSP Report-Only (sesiune dedicată).

**6. ai-router-service SPOF**: `git init` + commit `529faac` pe VPS (48 fișiere, `.env*`+`*.bak-*` gitignored, verificat 0 secrete tracked). **Rămas (acțiune user)**: crearea repo-ului GitHub privat a fost blocată de policy la creare autonomă → user creează `aledan2809/ai-router-service` (privat), apoi push-ul de pe VPS merge direct (VPS2 e authed).

**7. TODO_PERSISTENT (`3f8a475`)**: 3 blocuri introspection duplicate consolidate în 1, markeri sincronizați (deploy.php CRITIC era rezolvat din 2026-06-24 dar figura `[ ]`).

**Deploy final**: pull `ba98ffd→2a333fa`, npm ci curat, build, rsync, PM2 stabil 0 unstable, :3002→200, public 200, „Pe scurt:"×5 pe SSR (nota: `grep -c` numără linii nu apariții — fals-alarmă intermediară), gtag 0 refs în HTML-ul pre-consent.

**Decizie de produs rămasă (întrebare pt Alex)**: „Juridic invizibil" — secțiunea juridică din plugin e construită complet dar fără rută publică în Next.js. O expunem public?

---

## 2026-07-07 (noapte) — URGENT: contopire Rezumat→Pe scurt + articol complet vizibil (feedback user)

**Trigger**: user, mid-audit — „știrile conțin Rezumat, Pe scurt și De ce contează, dar mai puțin știrea în sine. Prea multe ajutoare. Comprimă Rezumat cu Pe scurt și adaugă știrea în format complet." Confirmat vizual în walk-ul PA: pagina de știre ducea cu 3 carduri de ajutor (Rezumat mov + Pe scurt verde + De ce contează), iar articolul real era ascuns în spatele expander-ului „Citește articolul complet".

**Change (`1788767`, `NewsDetailClient.tsx` + `SharedHeader.tsx`, −116/+21)**:
- **Cardul „Rezumat" (mov) eliminat**; „Pe scurt" devine singurul lead scurt = `simple_explanation` cu fallback pe `summary` (cap-ul artificial de 3 fraze scos — `simple_explanation` e deja ~3 fraze). „De ce contează" (`why_it_matters`) păstrat ca bloc distinct de impact.
- **Articolul complet afișat din oficiu** sub un divider „ARTICOLUL COMPLET" — expander-ul + state-ul `contentExpanded` eliminate. Paywall-ul premium neatins; `DOMPurify.sanitize(content)` neatins.
- **Toggle „Mod simplu" eliminat** (header + pagina de știre) — după show-first + articol-mereu-vizibil nu mai gata nimic → control mort (mai rău decât să nu existe). `SimpleModeToggle.tsx` + `simpleModeStore.ts` șterse (0 referințe rămase).

**Verificare (live, screenshot real pe hero 44573, content 1375c)**: Rezumat ABSENT · „Pe scurt" prezent (1 bloc) · „De ce contează" prezent · **„ARTICOLUL COMPLET" cu textul integral vizibil** · expander ABSENT · „Mod simplu" ABSENT. tsc EXIT=0, build EXIT=0, PM2 stabil 0 unstable, :3002+public 200. (Assert-ul automat „Articolul complet" a dat fals-negativ — clasa CSS `uppercase` face `innerText` să întoarcă majuscule; screenshot-ul = dovada.)

**STRATEGY.md Nivel 1 actualizat**: descrie noua ordine (Pe scurt → De ce contează → Articolul complet vizibil), eliminarea expander-ului + a toggle-ului „Mod simplu"; off-ramp = git history.

---

## 2026-07-07 (noapte 2) — True E2E audit (3 roluri) + P0 auth-bounce fix + propunere cu machete

**Trigger**: user — audit complet toate rolurile/meniurile cu True E2E + toate tools, conformanță strategie↔realitate, apoi propunere cu machete „acum vs propunere", din unghiul „vreau să vând aplicația".

**Walk (Playwright, live, 3 conturi × toate meniurile)**: 36 pagini, **0 nav-fails, 0 linkuri interne rupte** (28 testate). Anon: home/news/login/register/forgot/subscribe/newsletter/gdpr/privacy/terms/news-detail. Reader: dashboard/saved/subscriptions/deliveries/telegram/stats/settings/account-subscription/onboarding. Admin: cabina(WI-5) + 9 pagini plugin — toate 200 cu h1 corect.

**P0 găsit + reparat + verificat (`3f198d5`)**: `dashboard/layout.tsx` + `onboarding/page.tsx` — gate-ul de auth redirecta pe render-ul inițial (isAuthenticated=false pre-rehydration) → **orice reader care dădea refresh / bookmark / tab-nou / deep-link pe /dashboard/* (sau /onboarding) era aruncat la /login**, deși localStorage + cookie-ul `teinformez_token` erau prezente. Dovedit live (reload + deep-link tab-nou → ambele bounce). Fix: gate pe `useAuthStore.persist.onFinishHydration/hasHydrated` (spinner până la hidratare, redirect doar după ce hidratarea confirmă logout real). **Verificat post-deploy: reload /dashboard → RĂMÂNE, deep-link /dashboard/settings tab-nou → RĂMÂNE.** tsc 0, build 0, PM2 stabil.

**Findings deschise (în propunere, NEschimbate — user a cerut propunere înainte)**:
1. **`/dashboard/telegram` = consolă de bot admin expusă la cititor** cu „Administrator access required" (403). Ar trebui flux reader „conectează Telegram ca să primești știri" (reuse @aledan/telegram). Impact mare.
2. **`/account/subscription`** arată banner roșu „Nu s-a putut încărca" + card „Plan Gratuit" simultan (401); lipsa abonamentului = stare normală, nu eroare.
3. **„AI" în copy vizibil**: „Digest AI de azi" (dashboard), „sintetizate de AI" (newsletter), „rezumate de AI" (OG card), meta news/newsletter.
4. **Juridic invizibil**: modul complet în backend, 0 rută publică în frontend — decizie produs.

**Conformanță strategie↔realitate**: Premium „out-of-scope" în STRATEGY dar LIVE (paywall+Stripe+upgrade) → cod înaintea strategiei; hosting „Vercel" în STRATEGY dar pe VPS2; Telegram — flux greșit livrat; Juridic „COMPLETE" dar invizibil; Referral out→in nesincronizat.

**Livrabil**: artifact `claude.ai/code/artifact/0d05589e-e6a3-417b-8172-a390f11b8704` (audit + machete acum-vs-propunere pt Telegram-reader + account/subscription + tabel conformanță + ordine recomandată). NU s-a atins codul findings-urilor deschise — așteaptă decizia user pe ordine/machete.
