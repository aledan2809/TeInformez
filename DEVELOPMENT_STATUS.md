# Project Status — TeInformez
Last Updated: 2026-05-15 late-evening (G-TI-CAS-XSS-RISK closed)

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
