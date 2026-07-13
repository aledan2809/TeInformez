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

---

## 2026-07-07 (noapte 3) — Audit findings items 1-3 livrate + verificate (item 4 amânat)

**Trigger**: user „fa 1-3 si lasam 4 pentru alta data (sa ramana in TODO)".

**Item 1 — Telegram admin-tool ascuns din contul reader** (`28b68c1`): sidebar-ul reader expunea „Telegram Workspace" (consolă bulk-messaging admin, backend `manage_options` → 403 pt readeri). Acum: nav-ul apare doar la `role=administrator`; pagina redirectează non-adminii la `/dashboard` + sare fetch-ul admin-gated (fără 403 în consolă). **Verificat live**: sidebar reader fără Telegram ✅, deep-link → redirect ✅, 0 erori 403 ✅. Fluxul reader real „primește știri pe Telegram" (backend + bot) → TODO follow-on.

**Item 2 — „Abonamentul meu" fără eroare falsă** (`cba889e`, frontend): orice răspuns non-OK de la `/subscription/status` arunca eroare → banner roșu + card „Plan Gratuit" simultan. Acum: eșec/gol → default curat „Plan Gratuit · ✓ Activ" (banner roșu rezervat doar erorilor reale de portal facturare). **Verificat live**: banner roșu dispărut ✅, „Plan Gratuit + Activ" ✅ (screenshot = machetă). **Root cause 401** (endpoint auth pe sesiune-WP nu pe Bearer) → TODO backend follow-on.

**Item 3 — „AI" scos din copy** (`6a05d7e`): „Digest AI de azi"→„Rezumatul zilei", newsletter/OG/meta news+newsletter curățate. Tehnic „AI" (topic știre, subcategoria Tehnologie, comentarii cod) neatins. **Verificat live**: „Digest AI" dispărut ✅, „Rezumatul zilei" prezent ✅.

**Item 4 (Juridic) — AMÂNAT** la cererea user, rămâne în TODO (decizie produs: expui public sau marchezi amânat).

**Follow-on-uri backend puse în TODO** (necesită sesiune dedicată): (a) reader Telegram connect-flow real (@aledan/telegram + bot); (b) `/subscription/status` auth Bearer-aware; (c) sincronizare STRATEGY.md (Premium live, hosting VPS, referral).

Deploy: build+rsync+restart, PM2 stabil 0 unstable, :3002 200. tsc EXIT=0.

---

## 2026-07-10 — Follow-on-uri A-D livrate + verificate (batch final mesh)

**Trigger**: user „Continua cu toate, in regim de instructiuni mesh".

**A (`b9530ca`) — `/subscription/status` Bearer-aware**: Stripe_API extinde REST_API, `require_auth` → `is_authenticated` (Bearer + sesiune, cu `wp_set_current_user`). **Verificat live cu token reader: 200** `{tier:free,status:none}` (era 401 pentru orice user logat prin frontend).

**B (`d53200e`) — STRATEGY.md sincronizat**: Monetizare/Premium + Referral → „now IN SCOPE (strategy raised to match shipped code)"; Constraints + Architecture rescrise pe realitate (VPS2/PM2 :3002, Vercel abandonat, ai-router :3100, rețete deploy reale).

**C (`f37ab3b`) — Juridic expus public**: root cause „Juridic invizibil" = `class-juridic-api.php` (rute publice GET, `__return_true`) NU era require-uit/instanțiat în loader → toate rutele 404 deși clasa exista din Phase E. Wired (2 linii) + pagină nouă `/juridic` (SSR revalidate 300, chips categorii, expand per item, răspunsuri text-rendered — fără HTML injection) + link în footer „Legal". **Verificat live**: API `success:true, total:4`, pagină 200, 4 expanders vizibili.

**D (`1d1af29`) — Reader Telegram connect-flow (COD LIVE, INERT până la bot)**: `includes/class-telegram-reader.php` (bot GLOBAL de reader în options — separat deliberat de tool-ul admin per-user-meta; nonce single-use 15min → t.me deep-link; webhook DOAR chat privat, fail-closed; chat_id în user meta; sendMessage + digest compact HTML) + 4 rute REST (mint/status/unlink cu Bearer; webhook validează `X-Telegram-Bot-Api-Secret-Token`, secret gol/mismatch → 403) + livrare: canal `telegram` piggyback pe cadența email (email-path NEatins; eșecul TG nu afectează email-ul; `log_delivery` cu param channel) + frontend `/dashboard/telegram` role-aware (reader = connect UI 2 pași cu poll + unlink + stare graceful „indisponibil"; admin = workspace-ul vechi intact; sidebar-ul arată Telegram pentru toți din nou). **Verificat live**: mint fără auth → 401, webhook fără secret → 403, pagina reader = connect UI cu graceful unconfigured (screenshot), consola admin nu se scurge. **ACTIVARE (user, ~3 min)**: BotFather → 3 `wp option update` → `setWebhook` (rețeta în header-ul clasei + TODO). **V2 follow-up**: cadență telegram-only (dedupe email-keyed azi).

**Deploy**: `deploy.sh teinformez` (health 200/200 — check-ul reparat pe 7 iul) + frontend VPS-build; PM2 stabil 0 unstable; tsc EXIT=0; php -l curat pe toate 5 fișierele PHP atinse. TODO markers sincronizați (A/B/C = [x], D = [~] inert-până-la-bot).

---

## 2026-07-10 (PM) — Telegram v2: cadență telegram-only + canal în UI + upsert prefs

**Trigger**: user „da, fa-le pe toate" pe planul propus (A+B+C + retragere Facebook/Twitter). Regim mesh (dev → /review → fix), RESTRICT propose-confirm respectat.

**A (`5ac61b8`) — delivery-handler, cadență telegram-only**: gate-ul „doar email" acceptă acum și useri telegram-only, DOAR dacă botul reader e configurat + contul linkat (fail-closed → până la activarea botului comportamentul e identic v1, safe inert). Dedupe-ul ferestrei: `channel = 'email'` → `channel IN ('email','telegram')` — semantică asumată: digest ajuns pe ≥1 canal = done pe fereastră (succesul unui canal suprimă retry-ul celuilalt în aceeași fereastră; documentat, acceptat la /review). Emailul se trimite doar dacă e bifat; rezultatul digestului = succes pe oricare canal încercat.

**B (`5ac61b8`) — telegram-reader, auto-opt-in la bind**: conectarea reușită din bot bifează automat canalul `telegram` în preferințe (idempotent, nu atinge alte canale, no-op fără rând de preferințe); mesajul de confirmare din bot actualizat (nu mai cere bifă manuală).

**C (`5ac61b8`+`0910d18`) — ChannelSelector**: card Telegram adăugat (icon Send, descriere cu prerechizitul conectării); Facebook/Twitter retrase la „În curând" (nu livrau NIMIC pentru readeri — promisiune falsă în UI), dar rămân debifabile pentru userii care le aveau selectate istoric (ex. user 5 avea facebook); sumarul listează doar canale care livrează; hint condiționat la bifarea Telegram („devine activ după conectare" — finding /review, previne așteptarea de livrare fără cont linkat).

**D (`5d57e22`) — BUG PREEXISTENT găsit de walk-ul de verificare**: `User_Manager::update_preferences` face `UPDATE` fără upsert → pentru userii FĂRĂ rând în `wp_teinformez_user_preferences` (creați via wp-cli/admin, ex. e2e-reader id 9), salvarea din Setări era un no-op silențios (UI arăta succes, DB rămânea gol). Fix: guard care creează rândul de default înainte de update. Dovedit pe live: înainte = FAIL persistență după reload; după = PASS.

**Verificare (output real, nu tsc)**: php -l curat (3 fișiere PHP), tsc EXIT=0, deploy backend `deploy.sh teinformez` (health 200/200) + frontend VPS-build (exit 0, PM2 restart curat), **walk Playwright live logat ca reader: 9/9 PASS** (card vizibil/clickabil, FB/TW disabled+badge, hint, sumar, persistență după reload, revert). Screenshots în scratchpad sesiune. Cont test lăsat curat (`["email"]`).

**Rămas**: test E2E real de livrare Telegram (mint→Start→bind→digest) — blocat pe activarea botului (acțiune user, rețeta în header `class-telegram-reader.php`).

---

## 2026-07-12 — Activare bot Telegram + activare FB posting + fix FK logging social + repo ai-router

**Trigger**: continuare sesiune, user „1,2 si 3" (din lista pending) + „a" (fix FB logging). Regim mesh, RESTRICT propose-confirm respectat.

**Item 1 — Telegram reader bot ACTIVAT + E2E real verificat**: bot @TeInformez_bot (token de la user, getMe valid). 3 `wp option` setate (`teinformez_tg_reader_{token,bot,webhook_secret}`, secret 48-hex generat) + `setWebhook` → OK, `getWebhookInfo` sănătos (0 pending, 0 error, IP VPS2). Config only — zero cod. **E2E real (nu doar API)**: mint link user 9 → user a apăsat Start → bind confirmat (`_teinformez_telegram_chat_id=8875047080`) + canal `telegram` auto-bifat în DB (`["email","telegram"]`) → digest telegram-only trimis pe calea reală (`build_digest`+`send_message`, SEND=OK) → **confirmat vizual de user în inbox** (screenshot: mesaj conectare + digest 5 titluri). Închide restul din v2.

**Item 2 — FB posting ACTIVAT (reuse creds din MA)**: refolosit token-ul FB al Paginii TeInformez.eu deja existent în `Master/credentials/marketing-automation.env` (`FB_PAGE_ID=1139788592553322`). Validat la Graph API: tip PAGE, is_valid, expires_at=0, scope `pages_manage_posts` (caveat: `data_access_expires_at` ~mid-aug 2026 → posibil refresh 90-zile). 3 `wp option` (`teinformez_{facebook_page_id,facebook_access_token,social_posting_enabled}`). Chei reale = prefixate `teinformez_` (Config::get prepends) — rețeta din handoff fără prefix ar fi fost citită NICIODATĂ (prins, corectat). **Verificat pe output real (variant test+șters)**: `post_on_publish` pe articol real → postare FB live (post_id) → copy curat, zero „AI" branding → **ștearsă imediat** (Graph DELETE success, confirmat gone).

**Item 2b — BUG FK real prins prin execuție (L05), fixat (`145df20`)**: `log_social_post` insera `user_id=0` pt postări platform-level, dar `wp_teinformez_delivery_log.user_id` = NOT NULL + FK→`wp_users(ID)`; user 0 nu există → **fiecare postare socială ieșea dar NU se loga** (fără audit, `retry_failed_posts` orb). Fix chirurgical: `class-social-poster.php` `user_id 0→null` (+ comentariu) + `class-activator.php` CREATE delivery_log `user_id NULL` + prod `ALTER ... MODIFY user_id BIGINT(20) UNSIGNED NULL` (FK păstrat, NULL exceptat; rânduri existente neatinse). `/review`: safe — `retry_failed_posts` filtrează pe channel/status, nu user_id; niciun cititor pe `user_id=0`. **Re-verificat real**: re-postare test → log_row inserat (`user_id=NULL, status=sent, post_id`) → FB post + log-row de test șterse. php -l curat, deploy `deploy.sh teinformez` (health 200/200).

**Item 3 — repo `ai-router-service`**: creat privat `aledan2809/ai-router-service` + push de pe VPS2 (`main`, commit `529faac`). SPOF :3100 acum are backup versionat GitHub.

**Corecție onestă**: „știre pusă de 4×" observată de user = artefact de la testul MEU (digest construit din `news_archive` = tabel istoric cu ~53% duplicate legacy). Fluxul LIVE (`news_queue`) e curat (89/89 titluri distincte, publică azi ~7h cadență) → digestul real al userilor NU are duplicate. Dups din `news_archive` = date legacy, prioritate mică.

---

## 2026-07-12 (cont.) — CSP Report-Only (rest GDPR)

**`8062e43` — `frontend/next.config.js`**: header `Content-Security-Policy-Report-Only` pe `/:path*`. Origini curatoriate din runtime REAL (citite din cod, nu ghicite): script `self`+`unsafe-inline`+googletagmanager; connect `self`+GA(`www`+`*.google-analytics.com`+`*.analytics.google.com`+googletagmanager)+Sentry-ingest; img `self data: blob: https:` (remotePatterns=`https://**`); font `self` (next/font self-hosted); worker/manifest/frame `self`; `frame-ancestors 'self'` (aliniat X-Frame-Options); YouTube = doar link-uri, fără iframe. `unsafe-inline` pe script/style = necesar (Next.js bootstrap + gtag-init inline); migrarea pe nonce = out-of-scope pt faza de observare. **Sink = Sentry**: helper `sentryDsnParts()` derivă host-ul ingest (connect-src) + endpoint-ul `/security/?sentry_key=` (report-uri) din `NEXT_PUBLIC_SENTRY_DSN` existent — zero secret/host hardcodat; fără DSN → header fără report-uri (graceful). `node --check` OK + logica testată (cu/fără DSN). **Report-Only = zero blocare** (safe pe prod). Deploy frontend (build pe VPS, exit 0, 53 pagini, pm2 restart curat). **Verificat live**: `curl -sI teinformez.eu` → 200 + header complet + report-uri corect spre Sentry. **Rămas enforce**: după observare rapoarte în Sentry → `Content-Security-Policy` blocking când e curat.

---

## 2026-07-12 (cont.) — M6b Instagram publishing core (inert)

**`325d7a1` — `class-social-poster.php` (+87 linii)**: extins `Social_Poster` cu Instagram Business content publishing (Graph API), oglindind pattern-ul FB/Twitter. Flux 2-pași: `POST /{ig-business-id}/media` (image_url=`ai_generated_image_url` + caption) → `POST /{ig-business-id}/media_publish` (creation_id). IG cere imagine (fără post text-only) → skip când știrea n-are imagine. Caption = titlu + summary(200) + link UTM (`utm_source=instagram&utm_medium=social`) + hashtag-uri categorii; **zero „AI"** în copy. Log `channel='instagram_post'` (ENUM îl avea deja; `user_id=NULL` per fix-ul FK). `retry_failed_posts` extins la `instagram_post`. Config: `teinformez_instagram_business_id` + reuse `facebook_access_token` (token-ul TREBUIE să aibă `instagram_content_publish`+`instagram_basic` + pagina legată de contul IG Business). **Inert** până se setează `instagram_business_id` (gol → toate blocurile IG sar; FB/Twitter neatinse). `php -l` curat, deploy `deploy.sh teinformez` (200/200). **Verificat pe prod**: clasa se încarcă din fișierul activ, `post_to_instagram` există, property IG='' (inert), option='' — zero efect până la configurare. **Review** (lens /code-review): additiv+inert=safe; riscuri documentate (dublă-postare pe blip răspuns = paritate FB; image_url trebuie public https). **RĂMAS**: user leagă cont IG Business + token cu scope IG → test E2E; CAS-pe-social = follow-on.

---

## 2026-07-12 (cont.) — CAS-pe-social: research → BLOCAT pe MA (fără cod speculativ)

Cerut de user după M6b IG core. Research în cod (`class-cas-api.php` + `class-delivery-handler.php`): contractul CAS existent `GET {MA}/api/cas/render?slot=<infeed|banner>&source=teinformez` (X-API-Key) întoarce **HTML** (embed web/newsletter). Postările FB/IG au nevoie de câmpuri **separate** (`image_url` public + `caption` + `link`) — IG cere image_url public. Parsare HTML = respinsă (fragil). **Concluzie**: CAS-pe-social NU e implementabil pe partea TeInformez fără un endpoint MA structurat nou. **Zero cod speculativ scris** (no fabricated requirements / untestabil). Livrat în schimb: **build-prompt detaliat self-contained** în `MarketingAutomation/TODO_PERSISTENT.md` (endpoint `GET /api/cas/social` → JSON `{filled,image_url,caption,link,campaign_id}`, reuse inventar `/render`, telemetrie `placement=social`, zero „AI" în copy) + consumatorul TeInformez descris (contor 1/N + fetch + post FB/IG promo, UTM `utm_medium=cas`). M6b TODO actualizat cu blocker-ul.

---

## 2026-07-12 (cont.) — CSP flip Report-Only → ENFORCE (observare completă pe output real)

**`418cd30` — `frontend/next.config.js`**: header comutat `Content-Security-Policy-Report-Only` → `Content-Security-Policy` (enforce). Policy **byte-identică** (rename funcție `cspReportOnly`→`cspPolicy` + comentariu; `report-uri` păstrat → vizibilitate continuă în enforce). **Observare NU prin Sentry** (am doar DSN, fără `SENTRY_AUTH_TOKEN` pt API) ci **direct la sursă**: walk Playwright headless pe prod ascultând evenimente `securitypolicyviolation` (Report-Only le emite cu `disposition:report`). **29 page-loads reale, 0 violări**: 12 publice (/, news, login, register, newsletter, subscribe, privacy, terms, gdpr, juridic, onboarding, forgot-password) + 6 articole reale (id-uri live din API) + 11 consimțit+autentificat (reader id 9, cookie `teinformez_token` + `localStorage ti_cookies_consent=accepted` → **GA4 chiar a încărcat & beacon-uit**, `[GA✓]` pe fiecare → originile GA exercitate real, nu doar prezente). Static: 30 corpuri articol → 0 `<iframe>`/`<script>`/`<embed>`; `youtube_url`=`<a href>` (nu iframe → zero risc `frame-src`); zero SDK third-party client-side (Stripe = server-side redirect `window.location.href`, fără `stripe.js`). **Policy = superset al runtime-ului real** → enforce nu blochează nimic legitim. `/review` (low): îmbunătățire de securitate, `unsafe-inline` neschimbat (nonce deferat), `form-action 'self'` nu rupe Stripe redirect, 0 findings. Deploy frontend (build pe VPS exit 0, pm2 restart curat, uptime fresh). **Verificat LIVE post-deploy**: `curl -sI` → header e acum `content-security-policy` (nu report-only) + report-uri păstrat; home+article 200; **re-walk live sub enforce = 0 violări, GA încă încarcă pe toate 11 paginile** (disposition acum enforce → resursele chiar s-ar bloca, dar nimic nu se blochează). **Caveat onest**: observare point-in-time Chromium (29 pagini), nu date longitudinale Sentry multi-browser/multi-articol → report-uri rămâne activ ca plasă; rollback = 1 linie (rename header key înapoi). **RESTRICT**: propose-confirm respectat (user „da").

---

## 2026-07-12 (cont.) — Telegram digest programat: verificat E2E + bug enum prins prin execuție (L05)

**Item „Telegram digest programat" = de fapt VERIFICARE, nu build** — sistemul exista integral: cron `teinformez_check_deliveries`@15min (`teinformez-core.php:168` schedule + `:198` add_action → `Delivery_Handler::process_deliveries`), `DISABLE_WP_CRON=1` + **cron de sistem real** (`*/5 wp cron event run --due-now` + curl wp-cron) → fiabil, zero gap. `send_digest` are calea Telegram completă (`is_configured`→`chat_id`→`build_digest`→`send_message`→`log_delivery`). Netestat fiindcă reader-ul n-avea subs active.

**Verificare pe output real (izolată, zero atingere useri reali)**: user 5 e `realtime`=always-due (email+facebook) → NU am rulat cron-ul global; am izolat pe reader 9 via **reflection** pe `send_digest` (+`get_users_due_for_delivery` read-only). Provizionat reader 9: sub `Sport` activă (2 articole în 24h) + `delivery_channels=["telegram"]` + schedule `daily time=now`. Rezultat: `DUE_DAILY_NOW:[9]` (scheduler selectează corect DOAR user 9), `USER9_SELECTED_BY_SCHEDULER:YES`, `send_digest`→`send_message`=**true** (Telegram acceptă mesajul pt chat 8875047080). Newsletter nu-i paused (`teinformez_newsletter_paused=0`).

**Bug real prins prin execuție (`2ed5586`, L05)**: `wp_teinformez_delivery_log.channel` era `ENUM('email','facebook_post','twitter_post','instagram_post')` — **fără `'telegram'`**. `log_delivery(...,'telegram')` → MySQL non-strict respinge → stochează `''`. **Efect**: (1) livrări telegram invizibile ca „telegram" în analytics/revenue-dashboard (grupate pe channel); (2) **dedupe-ul v2 `get_users_due_for_delivery` `channel IN ('email','telegram')` nu matchează rândurile `''`** → useri telegram-only re-procesați inutil la fiecare cron (dedupe-ul per-news îi salvează de duplicat real, dar fragil). **Livrarea în sine funcționa** (`send_message=true`) — bug pur de logging. **Fix**: `'telegram'` adăugat la enum în `class-activator.php:103` (schema-of-record) + `ALTER TABLE ... MODIFY channel ENUM(...,'telegram') DEFAULT 'email'` pe prod (additiv, non-destructiv). **Validat pe prod post-ALTER**: write `channel='telegram'` stochează `'telegram'` (înainte `''`). `/review` (1 linie additivă): valoare = exact ce inserează codul, rânduri existente neafectate, default păstrat → 0 findings. Deploy `deploy.sh teinformez` (WordPress+REST 200). **Date test curățate (L07)**: rând test șters + reader 9 restaurat la starea originală (sub goală/inactivă, prefs `["email","telegram"]` daily 14:00). **RESTRICT**: propose-confirm respectat (user „da"). **User-side**: confirmare vizuală digest în Telegram reader (chat 8875047080).

---

## 2026-07-13 — Dedup `news_archive` + prevenție recurență (`ab2970d`)

**Context**: `wp_teinformez_news_archive` = store permanent (mută published>30d din `news_queue` via `cleanup_old_items`). Avea **47.693 rânduri / 27.166 URL-uri distincte = 20.527 redundante (43%)**, până la 5 copii/`original_url`. **Root cause**: `cleanup_old_items(30)` (cron zilnic `teinformez_daily_cleanup`, next ~07:07) re-arhivează cu `INSERT...SELECT` **fără guard de duplicat** → un URL re-fetch-uit+republicat+aged-out se re-arhivează. **Blast-radius verificat**: 0 bookmarks pe tot site-ul (`wp_teinformez_bookmarks` gol) → zero orfani; feed public /news = `archive:false` (`NewsListClient.tsx:189`, queue-only) → dups NU-s vizibile userilor (doar la `?archive=1` explicit, neapelat de frontend). MariaDB 10.11, InnoDB/Dynamic/utf8mb4 → unique full-length pe VARCHAR(500) fezabil (2000B < 3072B).

**Aplicat pe prod (RESTRICT, propose-confirm, user „aplica")**: (1) **Backup** `mysqldump` → `VPS2:/root/backups/teinformez-news_archive-pre-dedup-2026-07-13.sql` (266 MB, gated pe size înainte de DELETE). (2) **Dedup** `DELETE a ... INNER JOIN (SELECT original_url, MIN(id) keep_id ... GROUP BY original_url) k ON ... a.id<>keep_id` → 20.527 rânduri șterse, păstrat MIN(id)/URL (cea mai veche = cea mai probabil indexată). After: **27.166 rânduri, 0 redundante**. (3) `ALTER ... ADD UNIQUE KEY uq_archive_url (original_url)` + `DROP INDEX original_url` (prefix(191) redundant → unique-ul îl acoperă). Index-uri finale: PRIMARY, uq_archive_url, status, published_at, archived_at.

**Cod (prevenție recurență, `ab2970d`)**: `class-news-publisher.php` `cleanup_old_items`: `INSERT` → **`INSERT IGNORE`** (URL deja în arhivă = skip silențios; rândul din queue tot se șterge → zero pierdere de articol). `class-activator.php:219` schema-of-record: `KEY original_url(191)` → `UNIQUE KEY uq_archive_url(original_url)`. Activator = `CREATE TABLE IF NOT EXISTS` (nu dbDelta) → nu atinge instalări existente; prod aliniat manual. `/review`: INSERT IGNORE + DELETE-necondiționat = corect (no-loss); edge documentat (URL re-publicat cu conținut nou păstrează copia veche = acceptabil pt dedup). 0 findings. Deploy `deploy.sh teinformez` (WordPress+REST 200). **Rezultat**: arhivă curată (−43%) + nu mai crește cu dups. **Rollback**: restore din dump-ul de 266MB.

---

## 2026-07-13 (cont.) — De-risc React 19: bump `lucide-react` 0.321→0.469 (`2f5cbbe`)

**Context**: pas mic din pregătirea upgrade-ului viitor Next 15 / React 19 (item TODO „next@16"), rămânând pe **Next 14 + React 18** (decizie user: de-risc întâi, saltul 15/19 = sesiune dedicată). **Corecție de plan descoperită la recon**: Next 15 App Router are **React 19 cerință dură arhitecturală** (nu doar peer-recomandare; React 18 rămâne doar pe Pages Router — surse: wisp.blog, vercel/next.js issues) → pașii „14→15 ținând React 18" + „React 18→19" din TODO **nu se pot separa**, se contopesc.

**Recon (verificat, nu presupus)**: suprafața de cod 14→15 = **~zero** — app-ul e forward-compat: `news/[id]/page.tsx` deja `params: Promise<{id}>`+`await params`; restul paginilor client (`useSearchParams`); route handlers `new URL(req.url).searchParams`; **TOATE** fetch-urile server-render au deja `next:{revalidate:N}` explicit (schimbarea de caching din 15 nu atinge). `next.config.js` curat (fără `experimental` redenumite; Sentry v10 suportă 15/16). Deci riscul real la salt = **compat React 19 al dep-urilor**.

**Audit dep-uri React 19 (peer, pe pachete reale)**: framer-motion 11.18.2 (`^18||^19`), zustand 4.5.7 (`>=16.8`), next-themes 0.4.6, react-hook-form instalat 7.71.1 (`^19`) = **deja React-19-ready → neatinse**. Singurul fără `^19` = **lucide-react 0.321** (`^16||^17||^18`).

**Aplicat (RESTRICT, propose-confirm, user „da drumul la toate")**:
- `frontend/package.json`: `lucide-react ^0.321.0 → ^0.469.0` + `react-hook-form ^7.50.0 → ^7.71.1` (aliniere floor la versiunea deja instalată, cosmetic).
- **Alegerea 0.469.0 = deliberată**: lucide a scos brand icons (Facebook/Twitter/Instagram) la 0.475.0. Verificat pe pachetele reale (install izolat în scratch + `require`): 0.474.0 rupe + scoate brand icons; **0.469.0 = ultima versiune cu React-19 în peer (`^19.0.0` stabil) ȘI toate 58 iconițele folosite prezente** (inclusiv Facebook/Twitter/Instagram din `ChannelSelector.tsx`) → **zero schimbare UI**.

**Verificare**: `tsc --noEmit` exit 0. **Build PE VPS** (recipe frontend + `npm install` adăugat — recipe-ul standard nu-l avea → altfel VPS build-uia cu lucide vechi) exit 0, toate rutele compilate inclusiv `/onboarding` (webpack rezolvă `import {Facebook,Twitter,Instagram}` → ar fi picat dacă lipseau). Deploy: cp static+public → rsync → pm2 restart curat (uptime fresh). **Post-deploy LIVE**: /, /onboarding, /register, /reset-password = 200; logo lucide (Newspaper) randează în browser real; CSP header tot enforce (neatins). Brand-icons verificate la nivel build (SVG pure — build resolve = render garantat); `ChannelSelector` e auth-gated → n-am forțat login pe user test (evit mutare stare onboarding). `/review` (low): pur bump de versiune, 0 findings.

**Impact**: zero vizibil. Scade cu 1 necunoscută saltul viitor React 19 (lucide nu mai dă peer-mismatch). **Rollback**: `git revert 2f5cbbe` + `npm install` + rebuild. **Notă deploy**: bump-urile de dep pe frontend cer `npm install` în recipe (nu e default).
