# TODO Persistent — TeInformez
> Items rămân până marcate DONE cu dată + commit.
> Last updated: 2026-05-31

---

## [ ] 🚀 WOW/VIRAL — ELI12 (creat 2026-05-31)

**Sursă/context**: `STRATEGY.md` secțiunea „WOW / Viralitate". Diagnostic onest: produsul e azi un agregator commodity (~2/10 pe „viral"). Diferențiator ales (user, 2026-05-31): **„Explică-mi simplu" (ELI12)** — apolitic, simplu tehnic, foarte distribuibil.

**Principiu**: întâi un motiv să existe (ELI12), apoi bucla de share în jurul lui. NU lipim mecanică virală pe commodity.

**Governance**: TeInformez = RESTRICT → confirmare prealabilă per modificare. Per-milestone: `/review` + TWG după implementare (la fel ca SEQUENTIAL plan).

### Milestones
- [x] **M0 — Prospețime** (prerequisit absolut) — DONE 2026-05-31. Backlog ~3.3 zile eliminat: batch 10→30 (`fc7e72f`) + groq-first 58s→3s/item (`38445fb`). News fresh.
- [x] **M1 — ELI12 backend** — DONE 2026-06-04 (`2d0e008`). Câmpuri AI `simple_explanation` + `why_it_matters` în ambele ramuri build_prompt, scrise în process_item (opționale `?? ''`), expuse în `format_news_item` (API single-article). Coloane DB idempotent în process_queue (live) + activator CREATE TABLE (fresh). `$shared_cols`/archive neatinse (zero regresie read-path). Verificat live: ELI12 RO pe articol nou + API `/news/{id}` expune câmpurile.
- [x] **M2 — ELI12 frontend** — DONE 2026-06-06 (`71826d6` + `ba596f3`). Card „💡 Pe scurt" (Lightbulb) + „🎯 De ce contează" (Target), emerald, vizibil default pe pagina de știre când câmpurile există. Toggle „Mod simplu" site-wide (Variant A): persistat (`simpleModeStore`, default OFF) → ON colapsează corpul articolului sub „📖 Citește articolul complet" (doar ramura de conținut readable; premium-gate neatins; SEO-safe — boții primesc default OFF → conținut în DOM). 5 fișiere. Toggle expus în header articol (nu doar SharedHeader/homepage — altfel inaccesibil unde are efect). `/review` fix: reset `contentExpanded` per articol (App Router reuse `[id]`). Verificat live headless pe teinformez.eu/news/36486: card render + toggle prezent + content collapse confirmat. Emoji ales: `Lightbulb`/`Target` lucide (nu 🧒, evită conotație infantilă — decizie psihologică user).
- [x] **M3 — Share cards** — DONE 2026-06-06 (`f77b53b`). Rută nouă `/api/share-card/[id]` randează card portret 1080×1350 via `next/og` (Satori) — hero „Pe scurt" (simple_explanation, fallback summary) + „De ce contează" + brand + categorie + sursă. Buton „Distribuie ca imagine" în cardul ELI12 (legat de valoarea tocmai consumată): Web Share API L2 (`navigator.share({files})`) pe mobil → sheet nativ cu PNG-ul real; desktop fallback open imagine; cancel = no-op. **Deviere onestă de la „AICR"**: folosit infra OG existentă (`next/og` ImageResponse) în loc de AICR — zero dependențe noi, $0 randare, text+diacritice perfecte (principiul `reference_randare`). Zero leak premium (cardul folosește doar câmpurile teaser libere). Verificat live: card 1080×1350 randat pe teinformez.eu/api/share-card/36486 (diacritice OK, fără overflow) + buton prezent pe articol. Minor cosmetic: `why_it_matters` truncat la 200 char poate lăsa un cuvânt scurt danglind („De…") — acceptabil, bounded.
- [x] **Mesh-regime TWG pe M2+M3** — DONE 2026-06-06 (`ef71ac7`, loop `loop_trwg_mq2311uu_uaxu4u`, Claude CLI vision $0 credit). 4 iterații: static 65→100, runtime Vision 91 („Pe scurt, De ce contează, Mod simplu, share-card all present and properly rendered"). Guru fixes triate+aplicate: id-validation 400 + 404 pe articol inexistent (share-card), pill on/off pe toggle, validări defensive + a11y min-h-44px + separatoare Pe scurt/De ce contează + buton share centrat. Revertit 1 regresie Guru (recolorase cardul ELI12 emerald→purple → se ciocnea cu Rezumat-ul purple; păstrat emerald). Verificat live: 200/400/404 guards + card + buton.
- [~] **M4 — „Explică-mi asta" interactiv (tool public)** — ABANDONAT 2026-06-06 (decizie user). Motiv: oamenii nu lipesc text într-un tool propriu cât timp există Google/ChatGPT (fără moat). Înlocuit cu **butonul „Explică pe scurt" direct pe fiecare știre** (noi facem efortul pentru ei, doar pe știri, nu „orice"). Vezi M2-pivot mai jos.
- [x] **M2-pivot — ELI12 opt-in via buton** — DONE 2026-06-06. Cardul ELI12 NU mai e afișat din oficiu (risca să pară condescendent — „te luăm de prost"). În loc: buton **„💡 Explică pe scurt"** pulsatoriu (pulsul se oprește după prima folosire, flag `eli12Discovered` persistat) + tooltip care explică per-articol + „«Mod simplu» sus = la toate". Click → dezvăluie cardul inline instant ($0, textul e deja stocat din M1). „Mod simplu" ON = carduri afișate automat la toate + content collapse (Varianta A). Reveal resetat per articol.
- [ ] **M5 — Share-to-unlock / referral** (~1 sesiune): referral cu payoff real (digest fără reclame / premium), nu leaderboard sec. (referral mutat Out-of-Scope → In-Scope).
- [ ] **M6 — Distribuție** (~2 sesiuni): clipuri scurte verticale (ELI12 + TTS, TikTok/Reels/Shorts) + auto-post **card ELI12** pe Telegram/FB (nu link) + SEO conținut ELI12 unic.
- [~] **M6b — Instagram publishing + CAS pe social (analiză 2026-07-10, decizie: distribuție DA / bani direcți NU)** — **IG publishing CORE LIVE (inert) 2026-07-12 (`325d7a1`)**: `Social_Poster` extins cu Graph API 2-pași (`media`→`media_publish`), caption titlu+ELI12+UTM (`utm_source=instagram&utm_medium=social`), log `instagram_post` (user_id=NULL), `retry_failed_posts` extins; skip fără imagine (IG cere imagine); inert până la `teinformez_instagram_business_id`. **RĂMAS**: (user) cont IG Business legat de pagina FB + token cu `instagram_content_publish`+`instagram_basic` (tokenul MA actual are doar `pages_manage_posts`) → apoi `wp option update teinformez_instagram_business_id <id>` + test E2E real; (follow-on) **CAS-pe-social** (max 1/N postări = creative MA promo, UTM `utm_medium=cas`). Concluzii analizate cu cod + prod verificate:
  - **Livrare știri către abonați pe Instagram (ca email/Telegram) = NU se poate**: Instagram nu are API de broadcast DM (Messaging API = doar conversații inițiate de user, fereastră 24h; DM promoțional în masă = ban risk). Cardul „Instagram — În curând" din ChannelSelector rămâne corect dezactivat.
  - **Bani direcți din postare = ≈0** (fără bonusuri creator în RO, fără revenue-share pe feed). Valoarea reală = **funnel**: postări → trafic (UTM) → abonare → Premium; plus **CAS** (creative din MA, deja integrat în newsletter) postat ocazional = conversii pe produsele proprii ale ecosistemului, măsurabile în MA. Sloturi plătite pentru terți = abia la audiență de mii (nu planifica acum).
  - **Pas 0 (user, zero cod, cel mai bun efort/valoare)**: activarea FB posting-ului EXISTENT — `class-social-poster.php` are FB Page + Twitter gata scrise, dar pe prod TOATE config-urile sunt goale (`social_posting_enabled/facebook_page_id/facebook_access_token` — verificat 2026-07-10). Necesită: pagină FB TeInformez + Meta app + page access token long-lived → `wp option update`.
  - **Build-prompt (1 sesiune, DUPĂ pas 0)**: extinde `Social_Poster` cu Instagram Graph API content publishing — cont IG Business legat de pagina FB; flux 2 pași `POST /{ig-user-id}/media` (image_url = `ai_generated_image_url` al știrii — IG CERE imagine, avem per știre; caption = titlu + ELI12 scurt + link în bio/UTM) apoi `POST /{ig-user-id}/media_publish`; config `instagram_business_id` + reuse `facebook_access_token`; log în `delivery_log` cu `channel='instagram_post'` (user_id=0, pattern-ul existent FB/TW); rate cap ~100 posturi API/24h (throttle-ul de publicare ~7h e oricum sub); retry pattern-ul existent din `retry_failed_posts` extins la instagram_post. **CAS pe social**: max 1 din N postări = creative CAS din MA (`TEINFORMEZ_MA_API_URL`, pattern-ul newsletter-ului), marcat vizibil ca promo, cu UTM `utm_source=instagram&utm_medium=cas`. Zero „AI" în copy-ul postărilor (regulă activă).

### Opțional / rezervă (NU pe drumul critic)
- [ ] **OPT-1 — Buton „Ascultă știrea" (TTS)**: hands-free (ex: la volan/ocupat). Reutilizează textul ELI12 sau articolul. Feature de accesibilitate, prioritate joasă, opțional — NU produs flagship.
- [ ] **OPT-2 — Bias / spin-meter**: rezervă. Se activează DOAR dacă ELI12 prinde tracțiune (al doilea cârlig). Mai greu + divizant.

### Metrici de „viral" (de urmărit, altele decât pageviews)
Share rate (% citite → distribuite), K-factor (invitați → signup), trafic organic din share, timp pe site, retenție digest.

## [ ] 🎯 SEQUENTIAL AUTONOMOUS EXECUTION PLAN — 6 steps + /review + TWG per step (creat 2026-05-16)

**Trigger**: user request 2026-05-16 — execute all remaining TeInformez TODO items sequentially in autonomous Direct sessions, with `/review` skill + TWG (Tester ↔ Website Guru loop addressing all issues/bugs/concerns) AFTER each implementation phase.

**MN-01 reversal note**: Steps 3-6 require activating Premium tier (reversed from 2026-05-13 "100% Free, ads-only" decision). Step 3 (MN-02) Phase 1 MUST surface feature matrix decision to user before Stripe wiring — explicit user gate. If user re-confirms "Free-only" at that gate, Steps 3-6 stay DEFER and only Steps 1-2 land.

### Execution order (sequential — one step per session, do NOT batch)

#### [x] Step 1 — I-05 / Triage-2 (Google service account key migration) — DONE 2026-05-17 (commit `6d50001`; DB row deleted; `ga4-key-source` = filesystem)
- **Scope**: migrate `wp_options.ga4_private_key` → `wp-config.php` constant `TEINFORMEZ_GA4_PRIVATE_KEY` (fallback path `/var/www/teinformez-secrets/ga4-key.pem` chmod 600 root:www-data)
- **Files**: `backend/wp-content/plugins/teinformez-core/includes/class-google-analytics-service.php` + VPS2 `wp-config.php` + new filesystem secrets dir
- **Steps**: (a) SSH VPS2 create secrets dir + chmod, (b) one-time migration script reads existing key from DB → writes to filesystem + wp-config constant, (c) plugin code prefers constant, falls back to file, finally DB (deprecated), (d) admin UI removes ga4_private_key field, (e) DELETE row from wp_options after verification
- **AFTER**: `/review` on changed PHP files (no new critical/high findings on changes) + TWG verify GA4 admin dashboard still pulls metrics live + visit /admin GA4 settings page → field removed + plugin still works
- **Close**: move I-05 from Open Gaps → Eliminated Gaps in `AUDIT_GAPS.md` with commit hash + date + resolution summary
- **Cross-impact**: NO-TOUCH-flavored (touches wp-config on prod + filesystem secrets); requires propose-confirm-apply per CLASSIFICATION §2d if classified as such — verify project safety level at session start

#### Step 2 — AN-02-followup (when ≥7 days source-attribution data accumulated)
- **Gate check first**: verify ≥7 days since AN-02 deploy (2026-05-15 → earliest viable 2026-05-22). If insufficient data, mark `[~] data insufficient — reschedule` and skip to Step 3
- **Scope (if data available)**: refine attribution mapping in `admin/views/analytics-advanced.php` + add per-source breakdown charts (which UTM sources convert best to subscriptions)
- **AFTER**: `/review` on analytics PHP + TWG visit `/wp-admin/?page=teinformez-analytics-advanced` → charts render + drilldown links work + no PHP warnings in `error.log`
- **Close**: mark AN-02-followup `[x]` with commit + screenshot evidence

#### [x] Step 3 — MN-02 (Stripe subscriptions) — DONE 2026-05-17 (commits `991edc4` `400b5cc` `3725eb7` `6bf6571` `f3c6661` `a3e953e`)
- **Phase 1 USER GATE**: surface Premium feature matrix decision to user before any code (what's Premium-only vs Free?). Default proposal: advanced filters + juridic premium tier + PDF export + push priority. If user re-confirms "Free-only" → mark `[~] MN-01 stands` and CASCADE skip Steps 4-6.
- **Phase 2**: integrate `@aledan/stripe` lib + add `wp_teinformez_subscriptions` table (subscription_id, user_id, tier, status, current_period_end, stripe_customer_id, stripe_subscription_id)
- **Phase 3**: routes — frontend `/subscribe` + `/account/subscription` + backend `/api/stripe/checkout` + webhook handler `/api/stripe/webhook` (signature verify, idempotency)
- **Phase 4**: admin tier management UI (`/wp-admin/?page=teinformez-subscriptions`) — view subscribers, cancel, refund
- **AFTER**: `/review` on all changed files (TS+PHP) + TWG full Stripe checkout flow on **test mode** (test card 4242424242424242, success path, cancel path, webhook delivery, subscription downgrade)
- **Cross-impact**: sync `STRIPE_SECRET_KEY` + `STRIPE_WEBHOOK_SECRET` + `STRIPE_PUBLISHABLE_KEY` to `Master/credentials/teinformez.env` (gitignored); VPS2 `.env` update; webhook endpoint must be HTTPS (already covered)

#### [x] Step 4 — MN-03 (Paywall soft) — DONE 2026-05-17 (commits `4ecaab4` `7e5df29`)
- **Scope**: soft-block UI for Premium features per MN-02 feature matrix. NO 403 hard blocks — show "Disponibil pentru Premium" badge + CTA upgrade button
- **Routes affected**: per matrix decision in Step 3 Phase 1 (e.g., `/news/[id]/full-pdf`, advanced filter sidebar, juridic premium articles)
- **AFTER**: `/review` on changed frontend + TWG verify 3-tier UX: guest (sees CTA register), free-logged-in (sees CTA upgrade), premium (full access)

#### Step 5 — OP-01 (Churn prevention email) — depends on Step 3 DONE + first Premium subscribers exist
- **Scope**: Brevo automation triggered 3 days before subscription expiration
- **Backend**: WP cron `teinformez_check_expiring_subs` runs daily, scans `wp_teinformez_subscriptions WHERE expires_at BETWEEN NOW()+3d AND NOW()+4d AND status='active'`, sends Brevo transactional email "Reînnoiește acum — ofertă 10% dacă reînnoiești azi" with one-time discount code (Stripe coupon API)
- **AFTER**: `/review` + TWG manual trigger test: seed test subscription with `expires_at = NOW() + 3.5 days`, force-run cron, verify Brevo email arrives, click discount link, complete checkout with discount applied

#### [~] Step 6 — OP-02 (Onboarding Premium wizard) — DEFERRED 2026-08-01 (nu activăm Premium payments deocamdată)
- **Scope**: post-upgrade 1-step wizard activates Telegram push subscription + sets all category subscriptions ON → reduces time-to-value gap between payment and feature use
- **Trigger**: redirect from Stripe success URL → `/account/welcome-premium` (1 page, 1 form, ~30s flow)
- **Persistence**: `wp_user_meta` flag `teinformez_premium_onboarded=1` so wizard shows only once
- **AFTER**: `/review` + TWG verify wizard appears post-payment + completes cleanly + flag persists + does not re-appear on second login

### Acceptance criteria (composite)
- [ ] All 6 steps DONE with `[x] DONE YYYY-MM-DD (commit hash)` markers in their original TODO entries (this section serves as index, originals remain authoritative for line-level status)
- [ ] Each step deployed individually to `teinformez.eu` (no batched deploys per L83 visibility-per-phase rule)
- [ ] AUDIT_GAPS.md Eliminated Gaps section grew by ≥6 entries (1 per step + sub-findings from /review where applicable)
- [ ] Zero new critical/high findings from `/review` on changed files at end of each step
- [ ] Live smoke test https://teinformez.eu passes after each step (homepage + news API + juridic API + auth + relevant new endpoint per step)
- [ ] All commits follow project conventions (`fix(security):`, `feat(stripe):`, `feat(paywall):`, etc.)

### Anti-patterns to AVOID (codified)
- ❌ Skip `/review` or TWG between steps — user explicit requirement: BOTH after EACH step, no exceptions
- ❌ Batch multiple steps into one session — visibility per step required (L83 pattern from B1-B16 deploys)
- ❌ Skip MN-01 reversal documentation — Premium tier reactivation must be noted in DEVELOPMENT_STATUS + commit message
- ❌ Auto-proceed MN-02 without explicit Premium feature matrix decision — Phase 1 user gate is HARD requirement
- ❌ Cascade Steps 4-6 if user re-confirms Free-only at MN-02 Phase 1 gate — mark DEFER, do not implement on hypothetical premium scope
- ❌ Skip propose-confirm-apply protocol on Step 1 (I-05) if TeInformez classified RESTRICT or NO-TOUCH (touches prod wp-config) — verify at session start
- ❌ Deploy Stripe to prod without test-mode validation pass — webhook signature + idempotency MUST be verified in test before live mode
- ❌ Mark a step DONE if smoke test fails — failing smoke = step incomplete regardless of code commit landing

### Reference
- Related session: 2026-05-16 ABIP2 B1-B16 closure (this TODO's predecessor)
- L83 (visibility-per-deploy) + L131 (placeholder-as-pseudo-issue avoidance for /review false-positives) + memory `feedback_pipeline_deploy_live_projects`

---

## [x] 🔒 SECURITY FIXES — ABIP2 B1-B16 DONE 2026-05-16; Triage-1 DONE (Sprint 4); I-05 deferred Triage-2

**Status**: ABIP2 main pipeline complete. 17 findings eliminated (L-01–L-12, I-01–I-04, I-06). Only I-05 remains (deferred to Triage-2 Direct session).

**Commits (ABIP2 B1–B16)**:
- `45e3d5a` B1 L-08 require user_id in delete_subscription
- `700f72d` B2 L-05 view-dedup L-07 unicode-PII-regex
- `a51b650` B3 L-10 intval-output L-11 sanitize-POST-action L-12 PEM-validation
- `359788c` B3 L-10 missed intval() on news-queue add_query_arg
- `36b3591` B4 L-01 dedicated-secret L-02 opaque-refresh L-03 hashed-reset L-04 base64-safe-sanitize
- `da4c26c` B7 I-01 require-reauth-on-account-delete
- `12266b0` B11 L-09 atomic-cron-claim I-06 reset-static-auth
- `c690049` B12 I-04 gdpr-ip-hashing-and-retention
- `e78d877` B13 I-02 cors-fallback I-03 foreign-keys
- `a3acd40` B16 L-06 rest-args-schema-validation
- `9a9d97e` B11 check_rate_limit return type fix (WP_Error not WP_REST_Response)

**Prior Sprint commits (already Eliminated before ABIP2)**:
- `9d6d31e` C-01 IDOR, H-01 column injection, H-02 rate limiting, H-03 token revocability
- `8a3814b` review fixes — remove phantom whitelist field, harden rate limiter
- `d1b09ca` correct check_rate_limit() return type
- `9b39dff` Retry-After header on 429 + clear rate limit on successful login
- `0c50157` review fixes — $changes log XSS, dead CORS code, SSRF at source save

**Scope**: 44 findings in `TeInformez/AUDIT_GAPS.md` (1 Critical + 8 High + 17 Medium + 12 Low + 6 Informational).

**Decomposition** (3 distinct execution tracks per user directive 2026-05-16):

### Track A — ABIP2 main pipeline (next session, user pre-approved RESTRICT execution mode)

**13 batches**, deploy after EACH (per user "dupa fiecare ca sa vedem rezultatele online"):

| Batch | Files | Findings | Effort |
|---|---|---|---|
| B1 | `includes/class-subscription-manager.php` + `api/class-user-api.php` | C-01 IDOR, H-01 column injection whitelist, L-08 required `$user_id` | ~15min |
| B2 | `api/class-news-api.php` + `api/class-juridic-api.php` + `api/class-telegram-api.php` | H-04 admin analytics cap, H-05 juridic CRUD cap, H-06 telegram cap, L-05 view dedup, L-07 PII unicode | ~30min |
| B3 | `admin/views/juridic-queue.php` + `admin/views/news-queue.php` + `admin/class-admin.php` | H-08 admin form cap, L-10 esc_attr(id), L-11 sanitize $_POST action, L-12 PEM validation | ~20min |
| B4 | `api/class-auth-api.php` (rate limit infra) | H-02 transient rate limiter on login/register/reset, M-01 email enum, M-02 shared pwd validator, M-03 set-secure-cookie validate, L-01/02/03/04 token/AUTH_KEY/reset hash | ~1.5h |
| B5 | `api/class-auth-api.php` (revocation) | H-03 token revocation via hash(password) HMAC suffix → invalidate on pwd change | ~45min |
| B6 | `api/class-telegram-api.php` + new `includes/class-encryption.php` | H-07 encrypt telegram bot token (AES-256-CBC + AUTH_KEY salt) + migration | ~45min |
| B7 | `api/class-user-api.php` (preferences/email/delete) | M-08 preferences whitelist, M-15 bulk cap 50, M-16 email change confirmation flow, I-01 re-auth account delete | ~1h |
| B8 | `includes/class-news-fetcher.php` | M-09 SSRF block private IPs + HTTPS only + `reject_unsafe_urls`, M-10 XXE `LIBXML_NONET` | ~20min |
| B9 | `includes/class-ai-processor.php` + `includes/class-chief-editor.php` | M-11 sanitize AI output (sanitize_text_field/wp_kses_post/sanitize_textarea_field), M-12 HTTPS validation for non-localhost AI Router | ~20min |
| B11 | `includes/class-social-poster.php` + `api/class-rest-api.php` | M-13 `prepare()` HAVING, M-14 REST nonce for cookie auth (X-WP-Nonce), I-06 reset static `$authenticated_user_id`, L-09 atomic cron claim | ~30min |
| B12 | `includes/class-email-sender.php` + `includes/class-user-manager.php` + `includes/class-gdpr-handler.php` | M-07 redact reset link from logs, M-08 user-manager preferences whitelist sibling, I-04 hash IP for GDPR | ~20min |
| B13 | `includes/class-activator.php` + WP hooks | I-03 `delete_user` action hook cleanup orphans, I-02 app-level CORS fallback via `rest_pre_serve_request` | ~30min |
| B15 | `Master/TODO_PERSISTENT.md` + `TeInformez/TODO_PERSISTENT.md` (doc-only) | Align markers OP-01 + OP-02 → `[~] DEFER (no Premium tier per MN-01)` | 5min |
| B16 | `api/class-news-api.php` (REST args) | L-06 add `args` schema with sanitize_callback/validate_callback on REST routes | ~30min |

**Total ABIP2 estimat**: ~7h dev work, ~13 deploys.

### Track B — Triage-1 Direct surgical session (CORS hardening, separate)

**File**: `backend/wp-content/plugins/teinformez-core/includes/class-config.php`

**Findings**:
- M-05: Restrict `*.vercel.app` CORS wildcard (user confirmed 2026-05-16: Vercel TeInformez dead, migrated to VPS2 WordPress; safe to narrow)
- M-06: `get_client_ip()` only trust `X-Forwarded-For` from known Nginx proxy (single trusted hop); parse rightmost entry only
- M-17: Localhost origins (`localhost:3000`, `localhost:3002`) gated on `WP_DEBUG` OR env-based config (exclude in prod)

**Effort**: ~30min. Surgical patch. Deploy after.

### Track C — Triage-2 Direct surgical session (Google key infra, separate)

**Files**: `backend/wp-content/plugins/teinformez-core/includes/class-google-analytics-service.php` + VPS2 `wp-config.php`

**Findings**:
- I-05: Move Google service account private key from `wp_options.ga4_private_key` → `wp-config.php` constant `TEINFORMEZ_GA4_PRIVATE_KEY` (with fallback to filesystem path `/var/www/teinformez-secrets/ga4-key.pem` chmod 600 root:www-data). Database breach no longer exposes Google API credentials.

**Effort**: ~45min. Requires SSH VPS2 + wp-config edit + key migration script (one-time) + admin UI update to remove field. Deploy after.

### Track D — Post-all `/review` verification

After ABIP2 + both Triage sessions land:
- Run `/review` skill on TeInformez branch HEAD → baseline + verify findings closed
- AUDIT_GAPS.md update: move each finding to "Eliminated Gaps" with commit hash + date
- Mark this TODO item `[x]` with full closure context

### Acceptance criteria (composite)

- All 44 findings closed in AUDIT_GAPS.md "Eliminated Gaps" section with commit hashes
- Each ABIP2 batch deployed individually to `teinformez.eu` + manually smoke-tested
- `/review` skill output shows no NEW critical/high findings on changed files
- TODO_PERSISTENT this item marked `[x]` with date + commit list

### Anti-patterns to AVOID (codified)

- ❌ NU batch-uri merged together — fiecare batch e commit + deploy + smoke separat (user explicit cere visibility per phase)
- ❌ NU folosi ABIP2 audit-only (RESTRICT permite execution; user a pre-aprobat)
- ❌ NU atinge Triage findings (M-05/06/17, I-05) în ABIP2 — sunt separate Direct surgical sessions
- ❌ NU skip `/review` post-all (Track D obligatoriu)
- ❌ NU rephrase user constraints — verbatim block must appear ABOVE Technical Translation per CLAUDE.md FILE MODIFICATION DISCIPLINE

---

## 🔧 Post-soft-launch — Pending

### Email infrastructure (pre-deliverability)

- [x] **EMAIL-01** — Brevo verify `teinformez.eu` (DKIM brevo1/brevo2 + brevo-code TXT) — DONE 2026-05-14 (Hostico DNS)
- [x] **EMAIL-02** — Hostico mailbox `noreply@teinformez.eu` — DONE 2026-05-14
- [x] **EMAIL-03** — WP `teinformez_from_email` flip → `noreply@teinformez.eu` — DONE 2026-05-14
- [x] **EMAIL-04** — Resend verify `techbiz.ae` (DKIM + SPF send.*) — DONE 2026-05-14
- [x] **EMAIL-05** — Fix `$wpdb` global missing în `build_digest_html` (MN-05 regression) — DONE 2026-05-14 (`b975c78`)
- [x] **EMAIL-06** — DONE 2026-05-15. User applied SPF edit via Hostico Zone Editor: `v=spf1 +a +mx +ip4:188.241.222.25 +ip6:2a00:ece1:0000:0012:0000:0000:0000:023f +ip4:45.67.39.205 include:spf.brevo.com ~all`. Single TXT record (correct — nu split). Verified live via `dig +short TXT teinformez.eu` immediately post-save (Hostico low TTL). Brevo-code TXT intact + DKIM brevo1/brevo2 unaffected. Outlook/Yahoo/corporate now pass spf=pass (was spf=neutral).

### CAS — Carousel of Ads (depinde de MA dev)

- [x] **CAS-01** — Halt newsletter sends (kill switch via `teinformez_newsletter_paused` WP option + admin banner toggle pe Newsletter Ads page) — DONE 2026-05-14 (`80554dc`). Set ON until CAS live.
- [x] **CAS-02** — Remove 4PRO day-of-week rotation din email digest (păstrat doar sponsored campaign + slot empty fallback) — DONE 2026-05-14 (`80554dc`)
- [x] **CAS-03** — Hide InFeedAd 4PRO carousel pe `/news` (feature flag `NEXT_PUBLIC_CAS_ENABLED`, default off → slot empty) — DONE 2026-05-14 (`80554dc`)
- [x] **CAS-04** — **Wire CAS integration with MA** — DONE 2026-05-15 (`0886392`). MA endpoint live + verified `placement=<infeed|banner|newsletter>&source=teinformez&{recipient|visitor}=<hash>` cu `X-API-Key` header; returns 200+HTML or 204 (no inventory).
  - **Newsletter side** (server-to-server): `build_digest_html` falls back to MA CAS when no sponsored campaign active. Recipient hash = `sha256(email + salt)`. Timeout 3s; silent failure → empty slot. Gated by `teinformez_cas_enabled` option.
  - **InFeed side** (via WP proxy): new endpoint `GET /wp-json/teinformez/v1/cas/render?placement=infeed&visitor=<uuid>` keeps MA X-API-Key server-side. `InFeedAd.tsx` does dynamic fetch with sessionStorage-backed visitor token (no PII; server hashes with salt before forwarding to MA).
  - **Env**: `wp-config.php` defines `TEINFORMEZ_MA_API_URL` + `TEINFORMEZ_MA_API_KEY` + `TEINFORMEZ_CAS_SALT`; `teinformez_cas_enabled=1` option; `NEXT_PUBLIC_CAS_ENABLED=true` in frontend `.env.local`.
  - **Live smoke**: proxy returns 204 for no-inventory + 400 for invalid placement; Playwright walk `/news` shows 3 InFeed slots firing CAS proxy calls with stable UUID visitor + zero console errors.
  - **`/review`**: APPROVE WITH FOLLOWUP — XSS via `dangerouslySetInnerHTML` is theoretical with zero inventory; followup gap `G-TI-CAS-XSS-RISK` filed + **mitigated `b533883` 2026-05-15** (DOMPurify on useCasSlot hook + wp_kses on $promo_html).
  - **TWG**: explicit skip — Anthropic credit balance too low blocks Tester vision scoring (returns 0 score artifacts); equivalent validation via direct Playwright walk (3 CAS fires + 0 errors) + `/review` approve-with-followup (per Master CLAUDE.md TRWG threshold rule "ship fix-urile WG aplicate până atunci" + memory `feedback_trwg_gw_strict_acceptance` explicit-skip-with-reason).

- [x] **CAS-05** — DONE 2026-05-15 (`092a7d1`). Banner CAS slot pe homepage `/` între secțiunile 2 și 3 (gated by `sections.length > 2` ca să nu rămână banner orfan).
  - **Shared hook** `useCasSlot(placement)` în `frontend/src/lib/useCasSlot.ts` — extracted din InFeedAd; sessionStorage visitor token (UUID, no PII) stabil între slots → MA poate dedupe per (visitor, placement). InFeedAd refactored să consume hook-ul (~30 linii mai puține).
  - **BannerSlot** component cu `max-w-3xl` container + `role="complementary"` + `aria-label="Reclamă"`. Renders `null` când no inventory match → zero layout shift.
  - **Backend zero-touch**: CAS_API `ALLOWED_PLACEMENTS = ['infeed', 'banner']` deja livrat în CAS-04, doar consumat aici.
  - **Live smoke**: Playwright walk homepage → 1 CAS request `placement=banner`, zero console errors, banner DOM absent (no inventory = correct).
  - **`/review`**: APPROVE — XSS risk acoperit deja de `G-TI-CAS-XSS-RISK` (filed pentru CAS-04, se aplică și aici; **mitigated `b533883` 2026-05-15** — DOMPurify pe useCasSlot hook protejează ambele consumere InFeedAd + BannerSlot).
  - **TWG**: explicit skip (same reason ca CAS-04 — Anthropic credit blocks vision; Playwright walk + `/review` = equivalent validation).

- [ ] **CAS-06 — Launch Plan attribution emitter (reverse direction, pending)** — adăugat 2026-05-22 din MA Launch Plan Final Verification. CAS-04/05 acoperă render-side (TeInformez **trage** reclame din MA). Direcția inversă NU e încă wired: TeInformez ar trebui să **emită** evenimente de attribution (`TEINFORMEZ_USER_REGISTERED` etc.) către MA `POST https://ma.techbiz.ae/api/external/teinformez/events` cu `X-Internal-Key` + `trackingCode` (din redirect-ul CAS click `utm_content`), ca MA să atribuie signups/conversii la `LaunchPlanAction.metrics`. **MA-side receiver e gata** (`/api/external/teinformez/events`, atomic JSONB increment — vezi `MarketingAutomation/knowledge/launch-plan-module.md`). **Emitter TeInformez deferred** — sesiune dedicată propose-confirm-apply (RESTRICT). Estimat ~1-2h: hook pe user-register WP action → POST către MA cu trackingCode capturat la signup.

### Analytics redesign (3 faze)

> **Scop:** simplifică dashboard-ul de la 25+ metrici GA-style la 3 secțiuni utile pentru owner non-marketeer. Răspunde la 3 întrebări: "cresc/scad?", "ce a funcționat?", "ce trebuie să fac?"
> **Decizie 2026-05-14:** GA4 tab păstrat dar mutat în pagină "Advanced" (toggle, nu pe homepage analytics). Single source of truth pentru daily check = noul dashboard simplu.

- [x] **AN-01** — **Phase 1 — Headline + Trend grafice** — DONE 2026-05-14 (`0446212` + `27e64d4` + `41f1a70`):
  - 5 card-uri mari sus: Vizitatori unici săpt. (cu ↑↓ vs săpt. trecută), Useri înregistrați total (+ noi azi), Email subscribers activi (+ noi azi), Articole citite total (cu ↑↓), Top categorie audiență
  - 3 grafice SVG line charts (30 zile inclusiv azi + comparison 30d ago, NO library): Trafic zilnic, Înregistrări noi/zi, Newsletter signups/zi
  - Toate clickable → drill-down redirect la advanced page (`?page=teinformez-analytics-advanced&detail=...`)
  - Files split: rewrite `admin/views/analytics.php` (simple) + new `analytics-advanced.php` (current 25+ metrics + GA4 tab + Cross-check + Top Articles intact) + class-admin.php (registered Analytics — Advanced submenu)
  - Live verified `https://teinformez.eu/wp-admin/?page=teinformez-analytics`: 5 cards correct, 3 charts SVG render, advanced toggle + back link OK, drill-down OK, mobile 375px stacks vertical (full walk in `reports/an01-walk/`).
  - Follow-up fix `27e64d4`: removed `remove_submenu_page` call (broke WP capability check on advanced page); advanced visible in sidebar.
  - Bug-fix follow-up `41f1a70` (post-/review 2026-05-15): chart today-inclusive window (was dropping today's data), Card 5 percentage math (multi-cat overflow bound to [0,100]), real em-dash în i18n menu string. Verified by `frontend/scripts/tg-walk-analytics.mjs` 14/14 assertions PASS + Tester-Gateway audit `2026-05-14T21-35-51-031Z-3tez` (zero AN-01 critical-flow failures).

- [x] **AN-02** — **Phase 2 — "Ce a funcționat" tables + tracker upgrade** — DONE 2026-05-15 (`c0229f8`):
  - Tracker upgrade (data prerequisite): frontend `getEntrySignals()` capturează `document.referrer` + UTM params per session în sessionStorage, le atașează la fiecare event. Backend `Analytics_API::track_event()` acceptă noile params + **bot UA filter server-side** (drop GoogleBot/HeadlessChrome/scrapers/link-unfurlers înainte de DB insert). `Visitor_Analytics::derive_source_bucket()` clasifică (referer, utm) → 16 buckets coarse: `organic_{google,bing,other}`, `social_{facebook,instagram,twitter,linkedin,youtube,reddit,other}`, `email`, `rss`, `ad`, `internal`, `direct`, `referral_other`. UTM signals override referer (GA convention).
  - Display: 3 tabele în secțiunea "Ce a funcționat săptămâna asta" pe pagina simplă: 📰 Top 5 articole, 🔗 Top 5 surse trafic (cu disclosure "(neînregistrat)" pentru events pre-deploy), 🏷️ Top 5 categorii audiență. Fiecare cu acțiune recomandată contextuală sub.
  - Mobile: stack vertical sub 768px.
  - Live verified: bot UA dropped (`{tracked:false, reason:bot_ua_filtered}`), test event UTM `?utm_source=test&utm_medium=verify&utm_campaign=an02-deploy-check` cu referer `google.com/search?q=teinformez` → DB row are `source_bucket=organic_google` (referer overrides UTM `test`). tg-walk-analytics.mjs 14/14 PASS = nicio regresie pe AN-01 features.
  - **Forward-only data**: legacy events (1,566 din 1,567 page_views în primele 7 zile) sunt NULL bucket → afișat ca "(neînregistrat)" 99.9%. Ratio real apare progresiv pe măsură ce noi events se acumulează.

- [~] **AN-02-followup** — data insufficient — reschedule 2026-05-22 (gate NOT MET 2026-05-18, AN-02 deploy 2026-05-15, need ≥7 days):
  - Schema migration: mută `referer`, `utm_*`, `source_bucket`, `is_bot` din `metadata` JSON în coloane indexed dedicate (perf pe Top Sources query care va deveni lent la 100k+ events).
  - Sub-buckets pentru referral_other: top 10 unique referers afișate separat (e.g., dacă cineva linkează din wikipedia.org sau forumuri).
  - Geo / IP-based country breakdown (necesită lib MaxMind GeoIP sau Cloudflare CF-IPCountry header).

- [x] **AN-03** — **Phase 3 — Revenue mini-dashboard** — DONE 2026-05-15 (`7f047d5`).
  - **Local telemetry**: new `CAS_Telemetry` class + `wp_teinformez_cas_telemetry` (lazy-created append-only ledger); `CAS_API.handle_render` + `build_digest_html` record placement + filled outcome + timestamp for every MA fetch. No PII, fire-and-forget, failure swallowed.
  - **3 cards în `analytics.php` Revenue section** (între Headline cards și Trend charts):
    1. **Impresii CAS (săpt.)** — total filled last 7d + fill rate% (filled/total ratio).
    2. **Sponsorizate active** — count active rows în `wp_teinformez_newsletter_ads` cu campaign window curent.
    3. **Fill rate per slot** — breakdown newsletter / infeed / banner; gracefully degrades cu "(fără date încă)" când telemetry table goală.
  - **Live smoke**: 4 manual proxy calls → table created automat + 4 rows recorded (`placement=infeed×3 + banner×1`, `was_filled=0`); Playwright admin walk verifies Revenue h2 + all 3 cards render cu zero page errors.
  - **MA /api/cas/metrics**: blocked — NextAuth-gated (returns 307→login on X-API-Key). **Click-rate card deferred** until MA exposes API-key auth on metrics endpoint OR webhook callback to TeInformez. Logged as MA-side follow-up.
  - **`/review`**: APPROVE — append-only ledger avoids UPSERT race, lazy-create matches Visitor_Analytics convention, indexes cover both aggregation patterns, ~1.8M rows/year is manageable scale.
  - **TWG**: explicit skip (same reason — Anthropic credit blocks vision; Playwright authenticated walk + `/review` = equivalent validation).

- [x] **AN-04** — DONE 2026-05-14 (bundled atomic în AN-01 commit `0446212`; marker sync 2026-05-15). Verified live 2026-05-15: both submenu pages registered (`admin.php?page=teinformez-analytics` + `admin.php?page=teinformez-analytics-advanced`) — both return `302 → wp-login.php?redirect_to=...` correctly proving registration. Simple view = `analytics.php` (552 lines: 5 headline cards + 3 SVG 30d trend charts + AN-02 "ce a funcționat" tables). Advanced view = `analytics-advanced.php` (550 lines: Custom + Google Analytics tabs + Data Cross-check section + drilldown handlers for 25+ metrics). Cross-links both ways: `Show advanced view →` button on simple (analytics.php:365) + `← Back to simple view` on advanced (analytics-advanced.php:408). Drilldown wiring: simple cards link to `?detail=<metric>` on advanced (analytics-advanced.php:100+178). Menu registration in `class-admin.php:70-89` — both submenu pages visible in WP sidebar.
  - Mută Custom + Google Analytics tabs + Data Cross-check + cele 25 metrici la `/wp-admin/?page=teinformez-analytics-advanced`
  - Link cross-reference din dashboard nou către advanced

### Monitoring & ops (post-launch nice-to-have)

- [x] **OP-SENTRY** — DONE 2026-05-15. Sentry account created (fabulosos-srl org, project `javascript-nextjs`, DSN `https://...@o4511393455734784.ingest.de.sentry.io/4511393459798096`). DSN added to `/var/www/teinformez-repo/frontend/.env.local` (NEXT_PUBLIC_SENTRY_DSN + SENTRY_DSN) + copied to standalone target `/var/www/teinformez-frontend/.env.local`. Frontend rebuilt + redeployed + pm2 restarted. Smoke test (Playwright + Sentry network capture): **5 envelope POSTs** to `o4511393455734784.ingest.de.sentry.io/api/4511393459798096/envelope/` verified live — SDK `sentry.javascript.nextjs 10.52.0`, session tracking active, release auto-tagged with commit hash. Master/credentials/teinformez.env synced (gitignored).

### Pre-existing bugs surfaced by tooling (NOT caused by AN-01 — separate sessions)

- [x] **G-TI-FRONTEND-REACT-HYDRATION** — DONE 2026-05-15 (commit `5c29c87`). Root cause was option (a) — SSR/client time-drift mismatch. `ArticleCard.tsx` is a server component that calls `formatTimeAgo()` which uses `new Date()` for "now" reference; SSR renders with server time, client hydrates 1+ minutes later → text content mismatch per card × ~14 cards on homepage = exact match with reported ~14 errors/page. React `#418` + `#423` were downstream cascade of the `#425` first-error abort. Surgical fix: `suppressHydrationWarning` attribute on 3 time-rendering server-component sites (ArticleCard 2× + HeroArticle 1× + SharedFooter 1×). 4 line-modifications, no useEffect/client-component conversion, RSC streaming preserved. Live verification via Playwright walk on 4 routes (`/` + `/news` + `/login` + `/register`) post-deploy: **0/0/0/0** (errors / warnings / hydration / RSC fallback) vs ~14/page pre-fix.
- [x] **G-TI-PHP-ANALYTICS-FOREACH-NULL** — DONE 2026-05-15 (commit `d1e1db7`). Single-line surgical fix on `admin/views/analytics.php:250` — `foreach ($rows as $r)` → `foreach ((array) ($rows ?? []) as $r)`. Same codebase pattern as commit 13fcdcf + 461ae2a (null-coalesce + cast). `$wpdb->get_results()` can return null on DB error/failed prepare; this guards the foreach. Live-verified post-deploy: baseline 148 occurrences of "analytics.php on line 250" in nginx error log → 80s wait → still 148 (zero new entries, cron-fired admin render normally appends 1/min). VPS2 `php -l` PASS.
- [x] **G-TI-PHP-NEWS-API-DEFENSIVE-READS** — DONE 2026-05-15 (commit `461ae2a`). Extended null-coalesce pattern from `13fcdcf` to remaining 8 unguarded `$item->prop` reads in `format_news_item` return array (L795-810) + L792 image_source: id, processed_title, original_title, processed_summary, source_name (×2), tags, published_at, original_url, target_language. Inline `?? null` / `?? ''` pattern (no new locals — minimal diff). Preserves `?:` empty-string semantics + (int) casts. Live-verified: 10× `/wp-json/teinformez/v1/news?per_page=20` → nginx + php-fpm delta = 0/0 lines.
- [x] **G-TI-PHP-NEWS-API-WARNINGS** — DONE 2026-05-15 (commit `13fcdcf`). Null-coalesced both `processed_content` + `original_content` reads at `class-news-api.php:776` (3-line guard preserving `?:` empty-string semantics). Live-verified on VPS2 post-deploy: 10 curls on `/wp-json/teinformez/v1/news` produced 0 new nginx error lines + 0 new PHP-FPM log lines (was hundreds of warnings per single fetch before). Both `/var/www/teinformez-repo/` + `/var/www/teinformez/` paths picked up the new code (opcache reloaded by deploy.sh PHP-FPM restart).

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

- [x] **MN-01** — **Feature matrix**: DECIZIE 2026-05-13 — platformă 100% Free, monetizare ads only. Premium tier DEFER până crește baza de useri. MN-02/MN-03 DEFER corespunzător. **REVERSAT 2026-05-17**: Premium reactivat. Juridic cu Alina ELIMINAT complet. Preț: 9 RON/lună sau 99 RON/an. Feature matrix Premium: filtre avansate + export PDF + push prioritar.
- [x] **MN-02** — **Stripe subscriptions**: DONE 2026-05-17 (commits `991edc4`→`a3e953e`)
- [x] **MN-03** — **Paywall soft**: DONE 2026-05-17 (commits `4ecaab4` + `7e5df29`)
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

## [ ] Faza 4 — Optimizări post-monetization

### [ ] ⚖️ LEGAL ECOSYSTEM COVERAGE — TeInformez pending (creat 2026-05-17)

**Scope**: Connect TeInformez to `legal.knowbest.ro` hub (NO-TOUCH CRITIC per CLASSIFICATION §2.3).
- Determine controller entity (I-Phoenix CA / Class RDA Impex SRL / Fabulosos)
- Register app in Legal `LegalEntity ↔ App` routing rules
- Wire ConsentRecord submission for visitor + signup consent
- Wire DSR proxy (export/delete user data) → Legal `/api/dsr`
- Pull ToS/Privacy/Cookies from Legal (versioned) instead of hardcoded
- Backfill ConsentRecord for existing users
- Reference: `4pro-eat` G-EAT-017 (2026-05-07) as implementation pattern; `Legal/Reports/DIRECT-CHANGES-2026-05.md` ledger

**Gate**: Legal hub is NO-TOUCH CRITIC → dedicated session, propose-confirm-apply per CLASSIFICATION §2d.

---

### [ ] MN-04 (OP-01) — Brevo churn prevention email 3 zile înainte de expirare abonament (creat 2026-05-17)

**Scope**:
- WP cron `teinformez_check_expiring_subs` runs daily
- Scans `wp_teinformez_subscriptions WHERE expires_at BETWEEN NOW()+3d AND NOW()+4d AND status='active'`
- Sends Brevo transactional email "Reînnoiește acum — ofertă 10% dacă reînnoiești azi" cu one-time discount code (Stripe coupon API)
- **Gate**: depends on MN-02 (Step 3) DONE + first Premium subscribers exist

**After**: `/review` + TWG manual trigger test: seed test subscription cu `expires_at = NOW() + 3.5 days`, force-run cron, verify Brevo email arrives, click discount link, complete checkout with discount applied.

---

### [ ] MN-04 (OP-02) — Post-upgrade onboarding wizard la /account/welcome-premium (creat 2026-05-17)

**Scope**:
- 1-page wizard post-payment activates Telegram push subscription + sets all category subscriptions ON → reduces time-to-value gap
- Trigger: redirect from Stripe success URL → `/account/welcome-premium`
- Persistence: `wp_user_meta` flag `teinformez_premium_onboarded=1` so wizard shows only once
- **Gate**: depends on MN-02 (Step 3) DONE

**After**: `/review` + TWG verify wizard appears post-payment + completes cleanly + flag persists + does not re-appear on second login.

---

### [ ] AN-02 followup — schema migration + per-source breakdown charts (gated 2026-05-22)

**Gate check first**: verify ≥7 days since AN-02 deploy (2026-05-15 → earliest viable **2026-05-22**). If insufficient data, reschedule.

**Scope (if data available)**:
- Refine attribution mapping in `admin/views/analytics-advanced.php`
- Add per-source breakdown charts (which UTM sources convert best to subscriptions)
- Add indexed columns for analytics queries performance

**After**: `/review` + TWG visit `/wp-admin/?page=teinformez-analytics-advanced` → charts render + drilldown links work + no PHP warnings in `error.log`.

---

### [ ] Admin UI pentru is_premium — toggle în formularul de review articole (creat 2026-05-17)

**Scope**: Add `is_premium` toggle checkbox in the WordPress admin article review form (news queue review page).
- Identified in `/review` as next logical step after MN-03 soft paywall
- Admin should be able to mark any article as premium from the review queue without DB queries
- File: `admin/views/news-queue.php` + `admin/class-admin.php` (save handler)

**After**: `/review` + TWG verify toggle saves correctly + `is_premium=1` articles show ⭐ badge in frontend.

---

## Session Log

| Date | Entry |
|------|-------|
| 2026-05-11 | Phase A+B complete; [7] CODE audit 68/100; TRUE FULL E2E scope defined |
| 2026-05-11 | TRUE FULL E2E 92% complete — E1-E11 ✅, F1-F2 ✅, G1-G2 ✅, H1-H2 ✅, I1-I2 ✅; G3+G4 deferred. Report: `Reports/TRUE-E2E-FULL-2026-05-11.md` |
| 2026-05-17 | MN-03 Soft Paywall DONE (commits `4ecaab4` + `7e5df29`). `is_premium` col în DB (queue+archive), API field `format_news_item()`, frontend badges (ArticleCard+NewsListClient), NewsDetailClient 3-branch paywall render cu subscription loading skeleton. /review (P1 flicker + P2 cache logout + P3 backticks) applied. TWG 8/8 PASS. Faza 4 TODO items adăugate (LEGAL, OP-01, OP-02, AN-02 followup, admin is_premium toggle). Step 4 din SEQUENTIAL PLAN marcat implicit DONE via MN-03 completion. |
| 2026-05-12 | Security hardening complete — Sprint 1-5 + review fixes all eliminated. G3+G4 done. M-07 fully closed. Soft Launch & Monetization scope added (SL-01–SL-10, GR-01–GR-05, MN-01–MN-06, OP-01–OP-03). |
| 2026-05-13 | Faza 1 Soft Launch COMPLETE — SL-01 through SL-10 all done. SL-04 canonical/noindex, SL-05 GA4 SPA tracking, SL-06 Core Web Vitals, SL-07 error monitoring, SL-08 homepage copy, SL-09 D+1 re-engagement email with TOP 3 articles, SL-10 /api/og dynamic OG images (HTTP 200 image/png verified). TRWG-GW baseline: 2/49 pre-existing console errors (React hydration #425 + GTM headless), no regressions from SL work. |
| 2026-05-13 | **Faza 2 COMPLETĂ** (GR-01–GR-04 pre-existente; GR-05 push notifications PWA livrat commit `4756012`, TRWG-GW 3OK/3GATED/3EMPTY, zero regresii). **MN-04 eliminat** complet. **Fixes livrate** (commit `9382f00`): (1) dedup articole dubluri — Layer A title similarity ≥75%/12h + Layer B image URL uniqueness/24h; (2) UI Biziday-style — source sus, titlu bold, imagini înjumătățite în list view, `InFeedAd` component (carusel intern 4 proiecte + AdSense fallback via `NEXT_PUBLIC_ADSENSE_CLIENT`+`NEXT_PUBLIC_ADSENSE_SLOT` env vars); (3) email promo bloc rotativ zilnic în digest (minim 1 indiferent de nr. articole); (4) `$thumbnail_budget` 4→1 în delivery handler. **NEXT**: Faza 3 — MN-01 (feature matrix decision) → MN-02 (Stripe) → MN-03 (paywall) → MN-05 (newsletter sponsorizat) → MN-06 (revenue dashboard). |
| 2026-05-13 | Faza 2 Growth COMPLETE — Task 1 (duplicate dedup: title similarity 75%/12h + image uniqueness/24h in class-news-fetcher.php `9382f00`), Task 2 (MN-04 removed), Task 3 (Biziday-style UI: ArticleCard rewrite + InFeedAd carousel + feed injection + email promo block `9382f00`), Task 4 (thumbnail_budget 4→1 `9382f00`). GR-01/02/03/04 confirmed pre-existing. GR-05 implemented: sw.js + PushPrompt (3-visit threshold) + /push/subscribe endpoint (`4756012`). TRWG-GW post-GR-05: 3 OK / 3 GATED / 3 EMPTY — baseline parity. All Faza 2 items DONE. |
| 2026-05-13 | Faze 3+4 COMPLETE — MN-01 (decizie Free + ads only, Premium DEFER), MN-05 newsletter sponsorizat `fef09fd`, MN-06 revenue dashboard `b2fcfe3`, OP-03 affiliate links `759c1e0`. OP-01/02 DEFER (fără Premium tier). |
| 2026-05-14 | **Email infrastructure LIVE** — Brevo `teinformez.eu` verified (DKIM brevo1/brevo2 + brevo-code TXT + DMARC live în Hostico DNS), Resend `techbiz.ae` verified (DKIM + SPF send.MX + send.TXT), Hostico mailbox `noreply@teinformez.eu` created, WP `teinformez_from_email` flip. Smoke test: digest email arrived in Gmail Inbox cu DKIM=pass. **Critical bug fixed**: `class-delivery-handler.php` lipsea `global $wpdb;` în `build_digest_html` (MN-05 regression, commit `b975c78`) — newsletter delivery cron crash-uia silent de la deploy MN-05 până azi. **Pre-CAS cleanup** (commit `80554dc`): halt newsletter sends via `teinformez_newsletter_paused` WP option (kill switch + admin banner toggle), removed 4PRO day-of-week rotation din email digest, hidden InFeedAd 4PRO carousel pe `/news` (feature flag `NEXT_PUBLIC_CAS_ENABLED`). Slot-uri reserved pentru CAS integration (depinde de MA dev). Analytics redesign: propunere documentată (3 faze AN-01/02/03 + decision GA4→Advanced), implementation deferred. EMAIL-06 (SPF Brevo include) + OP-SENTRY pending user action. |

## 🔍 Introspection Audit 2026-06-20 — TeInformez (RESTRICT)
> Audit complet (gap strategie↔cod · ghid per-pagină · deep research · funcțional + cyber).
> **Scor AIWebAuditor: 73/100** · GDPR 35 (la data auditului). Rapoarte: `Reports/INTROSPECTION-2026-06-20/`.
> Checklist Alex centralizat: `Master/reports/Alex_TODO_2026-06-20.md` + tab „Introspection Audit" în UI Master.
> *(Notă 2026-07-07: cele 3 blocuri duplicate generate de re-rulările tooling-ului au fost consolidate aici, cu markeri sincronizați.)*

- [x] 🔴🔴 **CRITIC — `deploy.php`/`webhook.php`/`deploy-download.php`** — **DONE 2026-06-24** (commit `7aa6929` + ledger `reports/DIRECT-CHANGES-2026-06.md`): fișierele retrase din repo + verificat că NU erau web-served (nginx execută PHP doar pe path-uri WordPress; probe publice → 404); webhook GitHub defunct dezactivat. Marker-ul rămăsese `[ ]` din cauza blocurilor auto-generate.
- [x] 🟡 **`npm audit fix`** — **DONE 2026-07-07**: rulat non-breaking pe frontend (tree-ul evoluase la 25 vulns/11 high față de 16/5 la audit) + redeploy VPS. Vezi ledger `reports/DIRECT-CHANGES-2026-07.md` pentru rezultatul exact (ce a rămas nerezolvabil fără bump-uri breaking e notat acolo).
- [~] 🟡 **GDPR** — **consent-gating LIVE 2026-07-07**: GA4 NU se mai încarcă fără consimțământ explicit (gate pe `ti_cookies_consent`); banner cookie NOU pentru vizitatori anonimi (gap real: TIConsentGate arăta bannere doar userilor logați); accept-ul COOKIES din fluxul Legal deschide și el gate-ul. **Rămas**: ~~CSP (Report-Only întâi)~~ → **CSP Report-Only LIVE 2026-07-12** (`8062e43`, `next.config.js`): header `Content-Security-Policy-Report-Only` pe toate rutele, origini curatoriate din runtime real (GA4 gtag+beacons / Sentry ingest / self WP+media / https images / self fonts+workers), `unsafe-inline` script/style (Next.js bootstrap + gtag-init; nonce = out-of-scope faza observare), sink = endpoint Sentry Security derivat din `NEXT_PUBLIC_SENTRY_DSN` (fără secret nou). Verificat live pe prod (header prezent + report-uri corect). **Rămas enforce**: după fereastră de observare a rapoartelor în Sentry → mută pe `Content-Security-Policy` (blocking) când e curat.
- [~] 🟡 **Juridic invizibil + H1** — **H1 DONE 2026-07-07** (h1 sr-only pe homepage). **Rămas: decizie produs** — expui public secțiunea Juridic din plugin (rută Next.js nouă)? Aștept răspuns Alex.
- [~] 🟢 **`ai-router-service` :3100 SPOF** — **git init + commit local pe VPS DONE 2026-07-07** (`529faac`, 48 fișiere, secrete gitignored). **Rămas (acțiune user)**: creare repo GitHub privat `aledan2809/ai-router-service` (blocat de policy la creare autonomă) → apoi `git remote add` + push de pe VPS (VPS2 e deja authed ca aledan2809).
- _OK: SQL via prepare, XSS DOMPurify pe ambele suprafețe, 28 nonce, authz admin._

## 🔎 True E2E persona-audit 2026-07-07 — findings + follow-on (RESTRICT)
> Walk browser real 3 roluri × toate meniurile (36 pagini, 0 nav-fail, 0 linkuri rupte). Propunere+machete: `claude.ai/code/artifact/0d05589e-e6a3-417b-8172-a390f11b8704`. Ledger: `reports/DIRECT-CHANGES-2026-07.md`.

- [x] 🔴 **P0 auth-bounce** — reader logat aruncat la /login la refresh/deep-link pe /dashboard/* + /onboarding (race rehydration Zustand). **DONE + LIVE 2026-07-07** (`3f198d5`, gate pe persist.onFinishHydration; verificat reload+deep-link RĂMÂN).
- [x] 🟡 **Pagina de știre: prea multe ajutoare** — Rezumat contopit în Pe scurt + articol complet vizibil din oficiu. **DONE + LIVE** (`1788767`).
- [x] 🔴 **Telegram admin-tool expus la reader** — „Telegram Workspace" (bulk-messaging, 403) ascuns din sidebar pt non-admin + redirect deep-link. **DONE + LIVE 2026-07-07** (Item 1).
- [x] 🟡 **„Abonamentul meu" eroare falsă** — lipsa abonamentului nu mai e tratată ca eroare roșie; default „Plan Gratuit · activ". **DONE + LIVE 2026-07-07** (Item 2, frontend).
- [x] 🟡 **„AI" în copy vizibil** — Digest AI→Rezumatul zilei, newsletter/OG/meta curățate. **DONE + LIVE 2026-07-07** (Item 3).
- [x] 🟡 **Juridic — EXPUS PUBLIC 2026-07-10** (`f37ab3b`): root cause era că `class-juridic-api.php` nu era nici require-uit nici instanțiat în loader → toate rutele publice 404. Wired (2 linii) + pagină publică `/juridic` (chips categorii + expand pe răspuns, text-rendered — fără HTML injection, SSR revalidate 300) + link footer. Verificat live: API 4 items, pagină 200, 4 expanders.
- [~] 🟢 **Reader Telegram connect-flow — COD LIVE 2026-07-10** (`1d1af29`), **INERT până la activare (acțiune user, ~3 min)**: `Telegram_Reader` (nonce single-use 15min → deep-link, webhook fail-closed private-only, chat_id în user meta, digest compact) + rute REST mint/status/unlink (Bearer) + webhook (secret_token; verificat live 401/403) + livrare pe cadența email (v1 piggyback) + `/dashboard/telegram` role-aware (reader = connect UI cu stare graceful „indisponibil"; admin = workspace-ul vechi). **ACTIVARE**: (1) BotFather → bot nou; (2) `wp option update teinformez_tg_reader_token/…_bot/…_webhook_secret`; (3) `setWebhook` cu secret — rețeta completă în header-ul `class-telegram-reader.php`. **Follow-up v2**: cadență telegram-only (dedupe-ul e email-keyed azi → canalul telegram cere și email bifat). → **v2 DONE 2026-07-10, vezi itemul următor**.
- [~] 🟢 **Telegram v2 — cadență telegram-only + canal în UI — COD LIVE 2026-07-10** (`5ac61b8`+`0910d18`+`5d57e22`; walk Playwright live 9/9 PASS; bonus: fix bug preexistent upsert `update_preferences` — salvarea Setări era no-op silențios pentru useri fără rând de preferințe): delivery-handler acceptă useri telegram-only (gate: bot configurat + chat linkat; fail-closed → identic v1 până la activarea botului); dedupe `channel IN (email, telegram)` (semantică: digest ajuns pe ≥1 canal = done pe fereastră); email condiționat pe canal; **bind-ul din bot bifează automat canalul telegram** (idempotent); ChannelSelector: card Telegram adăugat + Facebook/Twitter retrase la „În curând" (nu livrau nimic pentru readeri; rămân debifabile pentru cine le avea) + sumar doar pe canale care livrează + hint „Telegram devine activ după conectare". **RĂMAS**: test E2E real (mint→Start→bind→digest) după activarea botului (acțiune user).
- [x] 🟢 **`/subscription/status` Bearer-aware — DONE 2026-07-10** (`b9530ca`): Stripe_API extinde REST_API + deleghează la `is_authenticated` (Bearer + sesiune WP). Verificat live cu token reader: **200** `{tier:free,status:none}` (era 401).
- [x] 🟢 **Sincronizare STRATEGY.md — DONE 2026-07-10** (`d53200e`): Monetizare/Premium + Referral mutate în „now IN SCOPE (strategy raised)"; Architecture actualizată (VPS2/PM2, ai-router :3100, rețete deploy reale).
