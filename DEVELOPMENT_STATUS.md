# Project Status — TeInformez
Last Updated: 2026-07-12 (Telegram v2 telegram-only cadence + canal în UI + upsert prefs fix)

## Current State (Sesiunea 2026-07-12, mesh)

**Telegram v2 — cadență telegram-only + selector canale onest — LIVE pe teinformez.eu, verificat walk Playwright 9/9. Ledger: `reports/DIRECT-CHANGES-2026-07.md` (secțiunea 07-10 PM).**

- **`5ac61b8`** — `class-delivery-handler.php` + `class-telegram-reader.php`: userii telegram-only sunt acum livrabili (gate fail-closed: bot configurat + chat linkat → până la activarea botului, comportament identic v1); dedupe `channel IN ('email','telegram')`; email condiționat pe canal; rezultat digest = succes pe oricare canal; **bind-ul din bot bifează automat canalul telegram** (idempotent).
- **`0910d18`** — `ChannelSelector.tsx`: card Telegram (icon Send); Facebook/Twitter retrase la „În curând" (nu livrau nimic pt readeri) dar rămân debifabile pt cine le avea; sumar doar pe canale care livrează; hint condiționat „Telegram devine activ după conectare" (finding /review).
- **`5d57e22`** — **BUG PREEXISTENT găsit de walk-ul live** (nu de tsc): `User_Manager::update_preferences` făcea `UPDATE` fără upsert → salvarea din Setări era no-op silențios pentru userii fără rând de preferințe (creați via wp-cli/admin, ex. e2e-reader id 9). Fix: guard care creează rândul default înainte de update. Dovedit înainte/după pe live (FAIL→PASS persistență). → **L05**.
- **`1266c6c`** — TODO + ledger: Telegram v2 marcat `[~]` (rămâne test E2E real după activarea botului) + **M6b: analiză Instagram/CAS** (livrare știri IG = imposibil fără API broadcast; bani direcți ≈0; valoarea = funnel + CAS; pas 0 zero-cod = activare FB posting existent dar neconfigurat pe prod; build-prompt IG Graph API complet în TODO).

**Deploy**: backend `deploy.sh teinformez` (health 200/200) + frontend VPS-build (exit 0, PM2 restart curat). php -l curat pe 3 fișiere PHP, tsc EXIT=0.

## Lessons Learned (sesiunea 2026-07-12)
- **L05** — `UPDATE` fără upsert = salvare silent no-op pentru rândurile create în afara signup-ului aplicației (prins de walk live, nu de tsc). Detalii complete în `knowledge/lessons-learned.md`.

---

## Current State (Sesiunile 2026-07-07 → 2026-07-10, mesh)

**Val UX + audit + follow-on-uri — totul LIVE pe teinformez.eu, ledger complet în `reports/DIRECT-CHANGES-2026-07.md`:**
- WI-2/3/4/5 (ELI12 show-first → apoi articol-complet-vizibil + Rezumat contopit `1788767`; dashboard proactiv; homepage quick-start + „Pe scurt" pe lead-uri `1d61897` + fix API `72bc48e`; Cabina de comandă admin `ba98ffd`), mobile nav `d3bf346`.
- **P0 auth-bounce reparat** `3f198d5`: gate-urile client (dashboard layout + onboarding) așteaptă rehidratarea Zustand-persist → refresh/deep-link nu mai aruncă userii logați la /login (dovedit live înainte/după).
- GDPR: GA4 consent-gated + banner anonimi `5026f15` (walk 7/7); npm audit 25→5 vulns `65ebfc7` + 2 bug-uri lockfile reale fixate (`5a3bda0` integrity stale tgz, `2a333fa` placeholder null care crăpa npm 10).
- True E2E persona-audit (3 roluri × 36 pagini, 0 nav-fail/0 link rupt) → artifact propunere cu machete: claude.ai/code/artifact/0d05589e-e6a3-417b-8172-a390f11b8704; items 1-3 livrate (`28b68c1` Telegram admin-tool ascuns de readeri, `cba889e` abonament fără eroare falsă, `6a05d7e` zero „AI" în copy).
- Follow-on-uri A-D `b9530ca`/`d53200e`/`f37ab3b`/`1d1af29`: subscription/status Bearer-aware (401→200 verificat), STRATEGY sincronizată cu realitatea, **Juridic expus public** (`/juridic`, root cause: clasa API nu era instanțiată în loader), **Telegram reader connect-flow** (cod LIVE, INERT până creezi botul — rețeta în `class-telegram-reader.php`).
- Alte: deploy.sh health-check reparat (404 fals → 200/200), ai-router-service git-versionat pe VPS (`529faac`, push blocat pe creare repo = acțiune user).

**Pending user (3 acțiuni mici):** (1) creare repo GitHub `ai-router-service` + push; (2) activare bot Telegram (BotFather + 3 wp option + setWebhook, ~3 min); (3) nimic altceva blocant — CSP Report-Only + next@16 upgrade + cadență telegram-only = sesiuni viitoare.

## Lessons Learned (sesiunile 2026-07-07 → 2026-07-10)
- **L310** — Client-side auth gates trebuie să aștepte rehidratarea store-ului persistat (Zustand persist): gate-ul care redirectează pe `!isAuthenticated` la primul render aruncă TOȚI userii logați la /login la refresh/deep-link/tab-nou, deși cookie+localStorage sunt valide. Fix: `persist.onFinishHydration`/`hasHydrated` + spinner până la hidratare. (Full entry în Master `knowledge/lessons-learned.md`.)
- Pattern secundar (nu L separat): „feature construit dar invizibil" = clasă API cu rute publice care nu e nici require-uită nici instanțiată în loader (Juridic, 404 pe toate rutele deși codul exista de la Phase E) — la audituri, verifică loader-ul, nu doar existența codului.

## Current State (Sesiunea 2026-06-04 — news throughput + WOW/viral ELI12)

### Headline
Reparat blocajul de prospețime la procesarea știrilor (backlog ~3.3 zile / 1844 articole) + scris planul WOW/viral centrat pe **ELI12** („Explică-mi simplu") + livrat **M1 backend** (câmpuri AI `simple_explanation` + `why_it_matters`, live pe orice articol nou).

### Commits (5 commits, toate live origin/master)
| Hash | Scop |
|---|---|
| `fc7e72f` | fix(news): batch AI 10→30 (debit 480→1440/zi, golește backlog) |
| `38445fb` | perf(news): groq-first pe procesarea bulk (latență 58s→3s/item) |
| `d3f9309` | docs(strategy): plan WOW/viral centrat pe ELI12 (STRATEGY.md + TODO) |
| `8f80752` | fix(news): /review — cursă L-09 (stale-recovery 60→600s) + guard incomplete-output |
| `2d0e008` | feat(news): ELI12 backend — simple_explanation + why_it_matters (M1) |

### Diagnostic (corectat onest în sesiune)
Ipoteza inițială „procesare oprită / 98.5% cost metered" a fost **infirmată** de un test runtime izolat: de fapt era **plafon de debit** (10/30min=480/zi < ~700/zi fetch) iar procesarea rula **gratuit pe groq** (anthropic/openai morți → fallback groq). Lecție: numărul de log-uri „AI Router unavailable" (×31446) ≠ provider-ul folosit efectiv.

### Verificat live
Backlog scade (1786→~1340); batch-30 rulează în 91s (vs 29min); ELI12 generat în RO pe articole noi (id 33927); API `/news/{id}` expune `data.news.simple_explanation` + `why_it_matters`. PHP lint clean pe toate. Cost $0.

### Necomis lăsat intenționat
`DEVELOPMENT_STATUS.md` avea +137 linii din sesiunea 2026-05-28 #2 (conținut backup prior, păstrat mai jos). Restul untracked (`.tester*`, `reports/*`, `frontend/reports/`) = artefacte de test, negitate.

## Lessons Learned (sesiunea 2026-06-04)
- **L03** — Diagnostic runtime: numărul de log-uri de eroare ≠ rezultatul efectiv. Verifică providerul/calea reală cu un test izolat înainte de a afirma root-cause/cost. (detaliu complet în `knowledge/lessons-learned.md`)
- Aplicat patterns existente: `feedback_research_before_proposing`, `feedback_honest_reporting_no_overstating`, FILE MODIFICATION DISCIPLINE (surgical), L-09 atomic-claim concurrency.

## Current State (Sesiunea 2026-05-28 #2 — test-user flag + analytics filter end-to-end)

### Headline
Test/dev users (11 din 13 wp_users) marcați cu meta flag `teinformez_is_test_user` + excluși din TOATE suprafețele analytics (visitor_events, /admin/analytics REST, WP admin Visitor Analytics page cu 17 query-uri, MA emitter, news view_count, GA4 frontend dimension). 2 real users (mca + iph @gmail) intacți. Plus CAS creative copy fix pentru eat („14 zile" scos) + BlocX („primele luni" + „Locuri limitate" scoase) — direct UPDATE pe MA DB.

### Commits (4 commits, plus 1 MA DB write)
| Hash | Scop |
|---|---|
| `4709264` | feat(analytics): test-user flag + exclude — User_Helper class + WP admin UI (column/bulk/checkbox) + visitor_events ALTER TABLE + ma-emitter + news track_view + /admin/analytics filter + GA4 UserTypeTagger |
| `a0a771a` | fix(ga): optional-chain window.gtag (TS narrowing lost în closure UserTypeTagger) |
| `8a51e81` | fix(analytics): WP admin Visitor Analytics page — 17 queries patched (visitor_events + wp_users JOIN + newsletter) + User_Helper::sql_email_not_test() pentru tabele fără user_id FK |
| MA DB | UPDATE LaunchPlanAction creative pentru `cmpb69dul...` (eat: headline scos „14 zile") + `cmpn6ox95...` (BlocX: headline + body + ctaText freemium-honest) — fix CAS copy misleading |

### Mecanism test-user (single source of truth)
- **Meta key**: `teinformez_is_test_user='1'` pe wp_usermeta.
- **Helper class** `TeInformez\User_Helper` (`includes/class-user-helper.php`):
  - `is_test_user($user_id_or_email)` — verifică meta + fallback pe pattern email (@teinformez.test, @example.{com,org,net}).
  - `auto_flag_on_register($user_id)` — hook pe `user_register` auto-flagează pattern-emails.
  - `get_test_user_ids()` — cached list pt SQL NOT IN.
  - `sql_not_test($col)` — SQL fragment pentru WHERE pe tabele cu user_id FK.
  - `sql_email_not_test($col)` — composite filter pentru tabele fără user_id FK (newsletter): exclude (a) emails ale userilor flagged + (b) email pattern-match.
  - `set_test_flag($user_id, bool)` — set/unset.
- **WP admin UI** (hooks în teinformez-core.php): column „Test user" cu badge `✓ TEST`, bulk actions Mark/Unmark, checkbox în user-edit profile.
- **Backfill executat**: 11 users marcați via INSERT INTO wp_usermeta (3×ale@* + 1×ali@teinformez.eu + 6×@teinformez.test + 1×@example.com).

### Suprafețe analytics filtrate
- `wp_teinformez_visitor_events`: + column `is_test TINYINT` (idempotent ALTER), insert setează din User_Helper.
- `track_view` (news view_count): skip increment dacă user e test.
- `/admin/analytics` REST endpoint: user/sub/delivery aggregates excluse test users (sql_not_test).
- `ma-emitter` (3 emit funcții): user_registrations + newsletter_subscriptions per-row skip; article_events via `is_test=0` SQL.
- `WP admin /wp-admin/admin.php?page=teinformez-analytics` (`admin/views/analytics.php`): 17 queries patcheate cu 4 helper vars ($tu_events / $tu_events_e / $tu_user / $tu_email).
- `/user/preferences` returnează `is_test_user` field — frontend GA4 `UserTypeTagger` setează user_property `user_type ∈ {test, real}` pentru filtru în GA UI.

### Verificare live
- USERI ÎNREGISTRAȚI (raw 9 → filtered **3**) ✓
- /admin/analytics: user_preferences raw 10 → filtered 3 ✓
- visitor_events: column adăugat, 8942 events vechi cu is_test=0 (forward-only filter pentru istoric — toate 8942 anonymous, 0 user_id matchat la test users)
- WP admin: column „Test user" live cu 11 ✓ TEST badge ✓
- Frontend GA4 dimension: `UserTypeTagger` în bundle `layout-a9c4c91a9cfeedc6.js` ✓

### CAS creative copy fix
- eat headline: „Mănânci sănătos cu AI — gratuit 14 zile" → „Mănânci sănătos cu AI — gratuit"
- BlocX: headline + body + ctaText rescrise freemium-honest (scoase „primele luni", „lunile gratis", „Locuri limitate").

## Lessons Learned (sesiunea 2026-05-28 #2)
- **L186** — WP plugin analytics often has TWO consumer surfaces — REST API (frontend) + WP admin views/*.php (PHP-rendered). Patching the REST endpoint doesn't filter the admin page; each view file has its own SQL queries that need separate patches. Always `grep -rln` for the table/column across BOTH `/api/` AND `/admin/views/` before declaring an analytics filter "done". Incident: 2026-05-28 — patched `/admin/analytics` REST endpoint, user reported admin page still showed unfiltered counts; required separate patch on `admin/views/analytics.php` with 17 SQL queries.
- Reaplicat (nu novel): `feedback_research_before_proposing` (lay-of-the-land grep înainte de design), `feedback_no_half_measures` (toate 17 queries patcheate, nu doar 1-2), `feedback_honest_reporting` (forward-only vs retroactive caveat explicat clar).

### Recent Changes
- 2026-05-28 #2: test-user flagging end-to-end + WP admin analytics page fix + CAS copy freemium-honest.

---

## Current State (Sesiunea 2026-05-22→05-28 — incident recovery + CTA + CAS end-to-end + categories + RO video filter)

### Headline
News pipeline restaurat (down 5 zile), CAS funcțional live pe 3 sloturi homepage + /news + newsletter, cu 3 ad-uri distincte garantate per slot. Copy CAS curățat (eat „14 zile" + BlocX „primele luni" → „gratuit"). Plus UX (pulsating CTA), categorii (fix „sntate"), robustețe (cron self-heal, homepage SSR null-fallback, sharp restore), filtru RO YouTube videos.

### Commits this session (chronological, 14 commits)
| Hash | Scop |
|---|---|
| `f3606fe` | docs(audit): log 2026-05-22 news-pipeline incident |
| `ab4a9e5` | fix(cron): self-heal teinformez recurring cron + corecție notă |
| `720c4cc` | fix(categories): transliterare RO diacritics + alias-uri free-text (sntate→Sănătate) |
| `d26926e` | feat(home): pulsating signup CTA + glowing value-prop card |
| `54b1923` | fix(cas): send `slot` to MA + map to placements with inventory |
| `8a71fbe` | fix(home): client-side fallback when SSR homepage data null + declare sharp |
| `aa90a4b` | fix(cas): map homepage banner + newsletter to WEBSITE_INFEED |
| `08d6f9b` | fix(cas): proxy returns raw HTML, not WP_REST_Response (JSON-escaping fix) |
| `52c3581` | feat(cas): carousel — each InFeed slot shows a distinct campaign (`n` param) |
| `07ba49d` | feat(home,newsletter): 3 CAS slots homepage + RO-only YouTube videos |
| `253b682` | fix(cas): pass index 0/1/2 to each homepage BannerSlot |
| (MA DB, no commit) | Updated CAS creative pentru eat (`cmpb69dul...` — scos „14 zile") + BlocX (`cmpn6ox95...` — scos „primele luni / Locuri limitate") |

### Root causes diagnosticate + fix-uri permanente
- **News silent 5 zile (16-22 mai)**: `ai-router-service` (port 3100) căzut + nu sub PM2 → procesorul cădea pe OpenAI 429 → 3 cron hooks (`process_news` / `check_deliveries` / `check_delivery_health`) deprogramate. Fix: PM2 + skip backlog >48h + reschedule + **cron self-heal** din `fetch_news` (hook mereu viu).
- **Claude CLI default permanent** (`ai-router-service/server.js`): subscription-covered, fallback automat la free providers (Groq/Gemini/Mistral) cu cooldown 15min — directiva user „for good".
- **Categorii „sntate"**: WP `sanitize_key()` șterge diacriticele → „sănătate"→„sntate". Fix: transliterare RO (ă→a, ș→s, etc.) ÎNAINTE de sanitize_key + extindere `CATEGORY_ALIASES` cu free-text RO mapping.
- **Pagina blocată „Se încarcă..."**: ISR cache-uia pagini cu data=null când build-time fetch eșua. Fix: client-side fallback în `HomeClient` (re-fetch în browser dacă SSR vine gol).
- **Sharp wiped de rsync --delete**: pattern L41 — adăugat în package.json + restaurat via temp-dir install + copy post-rsync.
- **CAS proxy JSON-wrap**: `WP_REST_Response` JSON-encoda HTML-ul → DOMPurify primea string escapat → randare goală. Fix: `echo $body; exit` (bypass REST serializer).
- **CAS 3 sloturi identice**: același URL → browser dedup. Fix: index 0/1/2 → MA carousel mode (built-in `pickActiveAd({index})`) → 3 DISTINCT campanii garantate.
- **CAS placement mismatch**: TeInformez trimitea `placement=banner/infeed` dar MA rezolvă alias DOAR pe `slot=`. Fix: proxy trimite `slot=` + map pe `WEBSITE_INFEED` (singurul placement cu inventar activ).
- **YouTube videos non-RO**: `relevanceLanguage=ro` doar hint. Fix: + `regionCode=RO` + `maxResults=5` + post-filter `is_latin_script_text()` (respinge Cirilic/CJK/Arab/Hebraic/Devanagari/Thai/Hangul).
- **CAS copy misleading**: eat „14 zile" + BlocX „primele luni" pentru produse freemium. Fix: SQL update creative JSON → headline „gratuit" curat.

### Deploy state live
- `teinformez.eu` HTTP 200, articole + imagini optimizate (avif), CAS 3 sloturi cu ad-uri distincte rotind, newsletter (nepauzat 2026-05-22) cu CAS infeed + filtru video RO.
- VPS2 cron-uri toate active (7 hooks teinformez), self-heal armat.
- `ai-router-service` PM2 + saved, Claude CLI primar (rate-limit reset 25 mai).

## Lessons Learned (sesiunea 2026-05-22→05-28)

- **L###** (de codificat în `knowledge/lessons-learned.md` dacă nu există deja): **WP REST `WP_REST_Response` JSON-encodează BODY-ul indiferent de Content-Type header**. Pentru endpoint-uri proxy care trebuie să întoarcă raw HTML (CAS render, embed widgets), folosește `status_header()` + `header('Content-Type: ...')` + `echo $body; exit` ca să ocolești serializatorul REST. Setarea Content-Type pe `WP_REST_Response` NU schimbă serializarea — body-ul rămâne JSON-encoded → consumatorii care fac `resp.text()` + injectare HTML primesc string escapat (renderează garbage sau nimic). Pattern descoperit pe TeInformez CAS proxy 2026-05-25 (mascat anterior fiindcă MA întorcea 204).
- **L###**: **`relevanceLanguage` la YouTube Data API e HINT, nu filtru**. Pentru limbă strictă: + `regionCode=ROUR` + `maxResults>1` + post-filter pe script (regex Unicode block reject pe Cirilic/CJK/Arab/Hebraic/Devanagari/Thai/Hangul). Codificat în `is_latin_script_text()` helper, TeInformez/class-delivery-handler.php.
- Reaplicat (nu novel): L41 (rsync --delete pe shared deps wipes downstream — sharp incident), L92 (PM2 env caching — fresh start needed), L120 (session-end ritual must be atomic), L131 (placeholder-as-pseudo-issue anti-pattern — aplicat la diagnostic „sntate"), L83 (research before claiming — corecție onestă pe parse-fatal theory care era greșită, real cause fiind cron unschedule).

### Recent Changes
- 2026-05-28: ST atomic, sesiune închisă.
- 2026-05-28: CAS copy fix (eat „14 zile" + BlocX „primele luni" → freemium-honest) via SQL pe MA DB.
- 2026-05-28: 3 CAS slots homepage cu index 0/1/2 garantând ad-uri distincte (MA carousel mode).
- 2026-05-27: BannerSlot multi-slot (3× sloturi după secțiunile 2,5,8) + RO-only YouTube filter.
- 2026-05-25: CAS proxy raw HTML fix (JSON-wrap bug) + remap pe WEBSITE_INFEED.
- 2026-05-23: Sharp restore + HomeClient client-side fallback.
- 2026-05-22: News pipeline restore + cron self-heal + Claude CLI default permanent + categories diacritic fix + pulsating CTA.

---

## Current State (Sesiunea 2026-05-19 — homepage fix + MA_Emitter fixes + messaging cleanup)

### Commits this session

| Commit | Scope |
|---|---|
| `a609416` | fix(homepage): remove backtick wrapping around `%i` identifier in homepage SQL query — fixed `FROM \`%i\`` → `FROM %i` (WP 6.2+ `%i` auto-adds backticks; manual wrapping caused double-backtick MySQL error "Incorrect table name ''"). Also deleted stale `teinformez_homepage_data` transient post-deploy. |
| `3604a46` | fix(ma-emitter): ID-based cursor, email payload, drain loop, UTC-safe to_iso — 4 review findings fixed: (1) `WHERE id > %d` ID cursor replaces timestamp cursor (collision-free), (2) `user_id`+`email` added to TEINFORMEZ_USER_REGISTERED payload + `email` to TEINFORMEZ_NEWSLETTER_SUBSCRIBED, (3) `do{}while` drain loop per source capped at MAX_DRAIN_SECONDS=25, (4) `DateTime::createFromFormat` with explicit UTC timezone. |
| (frontend) | fix(messaging): removed all "Fără reclame" / "Zero reclame" copy sitewide — 6 frontend files: HomeClient.tsx, register/page.tsx, subscribe/SubscribeContent.tsx (monthly+yearly plans), newsletter/page.tsx, account/subscription/SubscriptionContent.tsx. Replaced with factual alternatives ("10 surse verificate", "Știri românești", "Conținut premium selectat"). |

### TODO markers updated this session

- Step 6 OP-02 → `[~]` DEFERRED 2026-08-01
- AN-02-followup → `[~]` reschedule 2026-05-22 (gate not met)

### Live verification (post-deploy 2026-05-19)

- `https://teinformez.eu/` → 200, articles load correctly (no more spinner)
- `3604a46` deployed to VPS2: `class-ma-emitter.php` updated, PHP-FPM reloaded
- MA_Emitter next WP cron tick (~5 min) will start emitting with fixed logic

## Lessons Learned (sesiunea 2026-05-19)

**L-TI-WP-IDENTIFIER** (2026-05-19): WordPress `$wpdb->prepare()` with `%i` identifier placeholder (WP 6.2+) auto-adds backtick escaping. Manually wrapping in SQL with `` `%i` `` causes double-backtick ` ``table_name`` ` which MySQL rejects with "Incorrect table name ''". Fix: use `FROM %i` (no manual backticks). Bad empty result was cached in transient — required `wp transient delete teinformez_homepage_data` after deploy to surface the fix. Always flush relevant transients after fixing a data-fetching bug.

---

## Current State (Sesiunea 2026-05-15 late-evening — CAS XSS mitigation)

G-TI-CAS-XSS-RISK closed via Direct propose-confirm-apply (TeInformez = RESTRICT,
not NO-TOUCH so no ledger ritual). Defense-in-depth: frontend centralized
DOMPurify in `useCasSlot.ts` hook (protects both InFeedAd CAS-04 + BannerSlot
CAS-05 consumers); backend new private `sanitize_promo_html()` kses helper in
`class-delivery-handler.php` applied at both `$promo_html` assignment sites
(sponsor banner + MA CAS fallback). DOMPurify config smoke 7/7 pass (script /
iframe / svg-onload / event-handlers / object / embed stripped). PHP -l clean
on VPS2. Live verified: teinformez.eu / + /news 200; CAS proxy 204 (no inventory,
correct); deployed code contains all new code paths (`sanitize_promo_html` x3,
`FORBID_TAGS` + `DOMPurify` in chunks).

### Commits this session

| Commit | Scope |
|---|---|
| `b533883` | security(cas-xss): DOMPurify on useCasSlot hook + wp_kses on $promo_html (G-TI-CAS-XSS-RISK). 2 files, +44/-3. Mirrors G-TI-NEW-005 precedent `b03f267`. |
| `16a61e3` | docs(audit): G-TI-CAS-XSS-RISK Eliminated in AUDIT_GAPS (top table + inline row) + TODO_PERSISTENT CAS-04 / CAS-05 follow-up references updated. 2 files, +4/-3. |

### Live verification

- `https://teinformez.eu/` → 200
- `https://teinformez.eu/news` → 200
- `https://teinformez.eu/wp-json/teinformez/v1/cas/render?placement=infeed&visitor=test-*` → 204 (No Content; expected with zero inventory)
- Backend file deployed: `grep -c sanitize_promo_html` → 3 (1 method def + 2 call sites) ✅
- Frontend chunks deployed: `FORBID_TAGS` in `chunks/app/news/page-*.js` + `DOMPurify` in `chunks/3142-*.js` ✅

### Cross-project follow-up

User requested Master cross-project items continued in new AIP2 session.
Eligible after honest scoreboard: **SSO E2E ecosystem verification** (PRIMARY,
audit-only) + **ML2 Wave 2 4PRO+AVE ecosystems** (SECONDARY, ABIP2 audit-only).
Excluded: TRWG-GW + api-tester + security-scanner (already done); AIRouter
Phase E (Direct only, NO-TOUCH consumers).

Full handoff at `Master/reports/handoffs/ST-2026-05-15-17.md`.

## Lessons Learned (sesiunea 2026-05-15 late-evening)

- **none** — pattern (DOMPurify on user-provided HTML + kses on server-injected
  promo HTML) already documented via G-TI-NEW-005 precedent `b03f267`.
  Reinforcement of `feedback_scope_discipline` (skip tsconfig.tsbuildinfo from
  commit) + `feedback_pre_commit_scope_verify` (declared expected +44/-3 before
  commit) — both worked as designed.

## Current State (Sesiunea 2026-05-15 late)

Backend log-noise cleanup: closed `G-TI-PHP-NEWS-API-WARNINGS` ledger entry. Single surgical fix on `format_news_item` deployed to VPS2; live-verified zero new warnings on 10× curl. `/review` post-commit found no real bugs; behavioral change `content: string|null → string` is safer for the strict-mode TS frontend (estimateReadingTime + DOMPurify handle `''` cleanly). TWG skipped — backend-only fix with no UI surface.

### Commits this session

| Commit | Scope |
|---|---|
| `13fcdcf` | fix(news-api): null-coalesce processed_content / original_content (G-TI-PHP-NEWS-API-WARNINGS) — 1 file, +3/-1. Root cause: homepage SELECT at [class-news-api.php:423-431](backend/wp-content/plugins/teinformez-core/api/class-news-api.php#L423-L431) explicitly omits these 2 content columns (payload-size optimization) → stdClass items passed to `format_news_item` triggered `Undefined property` warnings per row × ~20 rows per request. |
| `b44fdef` | docs: mark `G-TI-PHP-NEWS-API-WARNINGS [x]` in TODO_PERSISTENT with verification note. |

### Live verification (post-deploy VPS2)

- 10× curl `/wp-json/teinformez/v1/news?per_page=20` → `nginx.error.log` delta = **+0 lines**, `php8.3-fpm.log` delta = **+0 lines** (baseline pre-fix had hundreds of warnings per single fetch)
- Both `/var/www/teinformez-repo/` and `/var/www/teinformez/` paths show new code (`deploy.sh teinformez` PHP-FPM restart cleared opcache as expected)
- `/review` analysis: sibling unguarded reads (L795-806 on title/summary/source_name/etc.) don't emit warnings today because homepage SELECT includes them, but tracked as latent risk if a future narrower SELECT path is added → would be separate ~30min item, NOT in scope of this commit.

## Lessons Learned (sesiunea 2026-05-15 late)

- **none** — no novel non-trivial lessons. Applied existing patterns: scope discipline (feedback_scope_discipline; stuck to TODO ledger, didn't expand to sibling reads), null-coalesce idiom matching the same-file pattern at L784/L785/L807, line-delta verification ritual (10× curl + log delta count), `/review` with explicit TWG-skip rationale (no UI surface = no flow to walk).

---

## Current State

### Production
- Live at `https://teinformez.eu` (VPS2 72.62.155.74)
- WordPress + teinformez-core plugin (PHP) + Brevo API integration
- Frontend: Next.js standalone on VPS2 (PM2 id 17, port 3002)
- Last deploy: `82a0093` (TODO update); functional code: `80554dc` (pre-CAS cleanup)
- PHP-FPM + Apache, MariaDB 10.11
- Classification: **RESTRICT** per Master CLAUDE.md §2.3

### Faze 1-4 — All DONE (sept. 2026-05-13)
- Faza 1 — Soft Launch Prep: SL-01..SL-10 ✅
- Faza 2 — Growth: GR-01..GR-05 ✅
- Faza 3 — Monetization: MN-01 (Free + ads decision) ✅, MN-05 newsletter sponsorizat ✅, MN-06 revenue dashboard ✅; MN-02/03 DEFER
- Faza 4 — OP-03 affiliate links ✅; OP-01/02 DEFER (no Premium tier)

### Email Infrastructure — LIVE 2026-05-14
- **Brevo (transactional)**: `teinformez.eu` verified — DKIM brevo1/brevo2 CNAMEs + brevo-code TXT + DMARC live în Hostico DNS. Sender `TeInformez <noreply@teinformez.eu>` Verified.
- **Resend (lifecycle)**: `techbiz.ae` verified — DKIM + SPF MX `send.techbiz.ae` + SPF TXT `send.techbiz.ae`. MA receives webhooks from TeInformez, sends D+1 re-engagement via Resend.
- **Hostico mailbox**: `noreply@teinformez.eu` real mailbox (not alias), forward to `alexdanciulescu@gmail.com` for bounces.
- **WP option**: `teinformez_from_email = noreply@teinformez.eu`
- **Smoke test passed**: digest email arrived in Gmail Inbox, DKIM=pass (no SPF Brevo include yet, but DKIM alone was enough)

### CAS — Carousel of Ads (PAUSED state, waiting MA dev)
- Newsletter sends **HALTED** via `teinformez_newsletter_paused=1` WP option. Cron exits in 4ms with log `PAUSED via teinformez_newsletter_paused option`.
- Admin can toggle via Newsletter Ads page banner (`/wp-admin/?page=teinformez-newsletter-ads`).
- Internal 4PRO ecosystem rotation REMOVED from email digest + InFeedAd carousel pe `/news`. Slots empty until MA exposes CAS endpoint.
- Reserved env vars (not set yet): `NEXT_PUBLIC_CAS_ENABLED`, `TEINFORMEZ_MA_API_URL`, `TEINFORMEZ_MA_API_KEY`
- Resume sends after CAS-04 wiring (depends on MA dev session).

### Critical Bug Fixed This Session
**MN-05 regression** in `class-delivery-handler.php`: `build_digest_html` method used `$wpdb->prefix` + `$wpdb->get_row()` without declaring `global $wpdb;`. Caused PHP fatal error on every newsletter delivery cron run since 2026-05-13 deploy. **Zero newsletters were sent for ~24h before discovery** during today's smoke test. Fix: 1-line add `global $wpdb;` at start of method. Commit `b975c78`, deployed live, verified working.

## Recent Changes (this session — 2026-05-14)

| Commit | Scope |
|---|---|
| `b975c78` | fix(MN-05): add `global $wpdb` in build_digest_html — fixes silent cron crash |
| `80554dc` | feat: pre-CAS cleanup — halt newsletter sends + remove 4PRO promos (3 files) |
| `82a0093` | docs(TODO): add Post-soft-launch pending section |
| `0446212` | feat(AN-01): analytics simple/advanced split — 5 headline cards + 3 SVG trend charts (30d vs 30d-ago) on simple page; full 25+ metrics + GA4 tab + Cross-check + drill-downs preserved on `analytics-advanced.php` |
| `27e64d4` | fix(AN-01): expose advanced analytics submenu (remove_submenu_page broke WP capability check, advanced page returned 403 for admins) |
| `41f1a70` | fix(AN-01): chart today-inclusive + Card 5 percentage [0,100] math + i18n real em-dash. Adds `frontend/scripts/tg-walk-analytics.mjs` (assertion-driven Playwright walk). Verified by walk 14/14 PASS + Tester-Gateway audit run `2026-05-14T21-35-51-031Z-3tez` (zero AN-01 critical-flow failures; remaining P0 = pre-existing React hydration on Next.js public, tracked as G-TI-FRONTEND-REACT-HYDRATION) |
| `c0229f8` | feat(AN-02): traffic source attribution + bot filter + 'ce a funcționat' tables. Frontend captures `document.referrer` + UTM in sessionStorage, attaches to every event. Backend bot UA filter drops crawler events server-side. New `derive_source_bucket()` helper maps (referer, utm) → 16 coarse buckets. Display adds 3-table section to simple analytics page (top articles + sources + categories) with contextual recommendations. Forward-only data: pre-deploy events shown as "(neînregistrat)" with honest disclosure. |

## Active TODO (post-launch, see `TODO_PERSISTENT.md`)

### Pending USER actions (~10 min total)
- [ ] **EMAIL-06** — Add `include:spf.brevo.com` to existing SPF TXT in Hostico DNS Zone (5 min)
- [ ] **OP-SENTRY** — Create Sentry account + add `NEXT_PUBLIC_SENTRY_DSN` to `/var/www/teinformez-frontend/.env` (5 min)

### Deferred — depend on MA dev (separate session)
- [ ] **CAS-04** — Wire CAS integration: fetch from `${MA_API}/api/cas/render?slot=newsletter|infeed`, inject in email digest + InFeedAd. ~2-3h here, but needs MA endpoint live first.

### Deferred — independent (when capacity allows)
- [ ] **AN-01** — Analytics Phase 1: Headline cards (5 metrici cu ↑↓ trend) + 3 grafice SVG line charts 30d. Move 25 GA-style metrics + GA4 tab to `?view=advanced`. ~3-4h.
- [ ] **AN-02** — Analytics Phase 2: "Ce a funcționat" — top 5 articles, top 5 sources, top 5 categories. Needs improved referrer tracking. ~2-3h.
- [ ] **AN-03** — Analytics Phase 3 (post-CAS-04): Revenue mini-dashboard cu CAS impressions + sponsored active + slot click rate. ~1h.

### Future enhancements
- [ ] **CAS-05** — Eventual: banner CAS on homepage `/` (between sections 2-3). Discuție when CAS live + real inventory signal.

## Technical Notes

### Where things live
- Plugin: `/var/www/teinformez/wp-content/plugins/teinformez-core/`
- Frontend build: `/var/www/teinformez-frontend/` (rsync target from `.next/standalone/`)
- Frontend repo: `/var/www/teinformez-repo/frontend/`
- WP-CLI commands: `wp --path=/var/www/teinformez --allow-root <command>`
- Deploy backend: `ssh root@72.62.155.74 "/var/www/deploy.sh teinformez"`
- Deploy frontend: see CLAUDE.md "Deployment" section (rsync + pm2 restart teinformez)

### Newsletter delivery flow
- Cron `teinformez_check_deliveries` runs every 15 min via WP-Cron
- Entry: `class-delivery-handler.php::process_deliveries()` — FIRST checks `teinformez_newsletter_paused` option, early-return if true
- Per frequency (realtime/hourly/daily/weekly/monthly), fetches users due for delivery
- `send_digest()` → `build_digest_html()` builds HTML cu hero + articles + promo slot + footer → `Email_Sender::send()` via Brevo API
- Sponsored campaigns from `wp_teinformez_newsletter_ads` table override default empty slot
- Internal 4PRO rotation REMOVED — slot now reserved for CAS HTML when wired

### Admin views structure
- `/wp-admin/?page=teinformez-newsletter-ads` — campaign CRUD + pause toggle banner (NEW)
- `/wp-admin/?page=teinformez-affiliates` — affiliate links per category (OP-03)
- `/wp-admin/?page=teinformez-revenue` — revenue dashboard overview
- `/wp-admin/?page=teinformez-analytics` — analytics (TO BE REDESIGNED — see AN-01..04)
- `/wp-admin/?page=teinformez-news-queue` — news approve/reject workflow
- `/wp-admin/?page=teinformez-juridic` — Juridic Q&A drafts
- `/wp-admin/?page=teinformez-category-order` — homepage section ordering
- `/wp-admin/?page=teinformez-settings` — API keys (Brevo, OpenAI, GA4, social)

## Lessons Learned (această sesiune)

**L-TI-EMAIL-1** (2026-05-14): Brevo modern domain authentication uses selectors `brevo1` + `brevo2` for DKIM CNAMEs, not the legacy `mail` + `mail2` selectors documented in older guides. When verifying domain in Brevo, copy DNS records EXACT from the Brevo UI — don't assume selector names from external tutorials. Discovered when `dig CNAME mail._domainkey.teinformez.eu` returned empty despite Brevo showing "DKIM signature ✅" — correct query was `dig CNAME brevo1._domainkey.teinformez.eu`.

**L-TI-EMAIL-2** (2026-05-14): Brevo "Domain Authenticated" status only requires the `brevo-code` TXT (ownership verification). DKIM CNAMEs + SPF include + DMARC are SEPARATE — Brevo dashboard can show "Verified" with brevo-code alone, but emails will fail DKIM/SPF auth in receiving servers without the additional records. Always verify DNS independently with `dig` after Brevo says "authenticated."

**L-TI-MN05** (2026-05-14): When adding `$wpdb` queries to a PHP method that doesn't already use WordPress DB layer, MUST declare `global $wpdb;` at method start. Missing this caused MN-05 newsletter sponsored ads feature to crash entire delivery cron silently for 24h (cron returns Fatal Error, WP catches it, no user notification). Smoke testing emails after deploy of any feature touching delivery handler is essential — code review caught nothing because the method scope issue was non-obvious.

**L-TI-CAS-RESERVE** (2026-05-14): When integrating with shared infrastructure (CAS from MA in development), prefer empty/null placeholder + feature flag over filler content. Original internal 4PRO carousel "felt active" but was brand-inappropriate for TeInformez audience. Empty slots are honest — "we're saving this for ads" — vs forced cross-promotion. Pattern: feature flag (`NEXT_PUBLIC_CAS_ENABLED`) defaulting OFF + clean placeholder UI ("CAS slot") when enabled but not yet wired.

## Decision Log

| Date | Decision | Why |
|---|---|---|
| 2026-05-13 | MN-01: Platform 100% Free, monetize via ads only. Premium tier DEFER. | First need to grow user base; Premium without scale = wasted dev time |
| 2026-05-14 | Halt newsletter sends entirely until CAS wired (vs send with empty promo slot) | Avoid sending visually-incomplete emails that look unprofessional |
| 2026-05-14 | Analytics redesign: keep GA4 but move to "Advanced" page; main dashboard = 3 simple sections | Owner is non-marketeer; 25 metrici overwhelms vs 5 cu trend = actionable |
| 2026-05-14 | Consolidation Brevo+Resend → Brevo-only DEFERRED (not done now) | Resend works after fix; refactor adds 1h MA work that doesn't unblock launch |
