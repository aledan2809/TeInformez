# Project Status — TeInformez
Last Updated: 2026-05-13

## Current State

### Production
- Live at `https://teinformez.eu` (VPS2 72.62.155.74)
- WordPress + teinformez-core plugin (PHP)
- Frontend: Next.js standalone on VPS2 (PM2 id 17, port 3002)
- Last deploy: `933d97c` (Faza 1 SL-01–SL-10 all complete)
- PHP-FPM + Apache, MariaDB 10.11

### Faza 1 — Soft Launch (ALL DONE 2026-05-13)

SL-01 through SL-10 complete. Key items this session:

**SL-09** ✅ D+1 re-engagement email (`ff58ffd`)
- `teinformez_welcome_d1_email` WP-Cron hook fires 24h post-register
- Skips if user clicked any article in past 48h (visitor_events table)
- Filters TOP 3 articles by subscribed categories; fallback fills remainder with latest
- `Email_Sender::send_d1_reengagement()` — branded HTML email with article cards

**SL-10** ✅ Dynamic OG images via `/api/og` (`b8337da`, deployed `933d97c`)
- `frontend/src/app/api/og/route.tsx` — ImageResponse (next/og, Node.js runtime)
- Params: `?title=&source=&category=` (no extra API fetch)
- 1200×630 dark gradient, adaptive font-size, TeInformez branding
- `/news/[id]` already wired to use `/api/og` as fallback when no image
- Verified: `curl https://teinformez.eu/api/og?title=Test&source=HotNews&category=actualitate` → HTTP 200 image/png

### Faza 2 — Growth Features (DONE 2026-05-12)

**GR-01** ✅ Newsletter public landing page + double opt-in
- `/newsletter` — email form + GDPR checkbox
- `/newsletter/confirm` — token confirmation (Suspense pattern)
- Uses pre-existing double opt-in endpoint in `class-news-api.php`
- Commits: `5217c72`, `f712dfe`

**GR-02** ✅ UTM referral tracking
- `frontend/src/lib/utm.ts` — captureUTM / getStoredUTM / clearUTM
- `register/page.tsx` — capture on mount, send at register, clear after
- `class-auth-api.php` — utm_source/medium/campaign saved to user_meta
- Commits: `9eac98e`, `9dc8e07`

**GR-03** ✅ Share widget UTM params
- Share buttons (WhatsApp/Telegram/LinkedIn/Facebook/X) append `utm_source=share&utm_medium=article`
- URL computed once via IIFE (not per-render)
- Commits: `9eac98e`, `9dc8e07`

**GR-04** ✅ JSON-LD NewsArticle schema complete
- `publisher.logo` → `/logo.svg` (280×60, stable static SVG)
- `dateModified` added
- Commits: `9eac98e`, `9dc8e07`

**Review fixes** ✅ (commit `0c9c759`, 2026-05-12)
- `newsletter/page.tsx`: captureUTM on mount + UTM passed to subscribe
- `api.ts`: `newsletterSubscribe(email, gdprConsent, utm?)` — explicit params
- `class-news-api.php`: utm_source/medium/campaign stored in INSERT/UPDATE
- `class-activator.php`: UTM columns added to `teinformez_newsletter` table
- DB migration applied on VPS: `ALTER TABLE wp_teinformez_newsletter ADD COLUMN utm_*`

### Security Sprint Progress
- **Sprint 1 DONE** (commit `a6ed79d`, deployed 2026-05-11)
  - M-08: field whitelist in update_preferences() — GDPR columns protected
  - M-07: reset link removed from error_log — token exposure closed
  - M-03: set-secure-cookie requires auth + HMAC validation + ownership check
- **H-01–H-08 DONE** (prior session, deployed 2026-05-11)

### Open Backlog
- M-07 partial: send_via_brevo() logs $to_email (low risk, no token)
- G-TI-NEW-001: /profile and /settings frontend 404 routes

## TODO — Sprints remaining

### Sprint 2 (next session)
- [ ] M-16: Email change without confirmation — account takeover risk
- [ ] M-14: CSRF nonce verification for cookie-authenticated REST requests
- [ ] M-15: Bulk subscriptions size cap (max 50 entries)

### Sprint 3
- [ ] M-11: Stored XSS via AI-generated content — sanitize title/summary/content
- [ ] M-09 + M-10: SSRF + XXE in news fetcher
- [ ] M-02: Unified password validation

### Sprint 4
- [ ] G-TI-NEW-001: /profile and /settings frontend 404 routes
- [ ] M-01: Email enumeration (generic register response)
- [ ] M-05: CORS wildcard fix (*.vercel.app → specific URLs)

## Recent Changes (2026-05-13)
- **Faza 1 COMPLETE**: SL-01–SL-10 all done and deployed
- SL-09: D+1 re-engagement hook wired + email sender added (`ff58ffd`)
- SL-10: `/api/og` deployed, HTTP 200 image/png confirmed live (`933d97c`)
- TRWG-GW: 2/49 baseline unchanged (pre-existing React hydration + GTM headless — not a regression)

## Recent Changes (2026-05-12)
- Faza 2 GR-01–GR-04 implemented, reviewed (PR #1–3 on GitHub), and deployed
- Review findings fixed: gtagEvent order, logo.svg, IIFE share URL, newsletter UTM capture
- DB migration: utm_source/medium/campaign columns added to wp_teinformez_newsletter
- PR branches: review/GR-01, review/GR-02-03, review/GR-04 on aledan2809/TeInformez

## Technical Notes
- `sanitize_text_field()` strips base64 chars — use `wp_unslash()` for token handling
- Deploy frontend: `ssh root@72.62.155.74 "cd /var/www/teinformez-repo && git pull origin master && cd frontend && npm run build && cp -r .next/static .next/standalone/.next/ && cp -r public/ .next/standalone/public/ && rsync -a --delete .next/standalone/ /var/www/teinformez-frontend/ && PORT=3002 pm2 restart teinformez"`
- Deploy backend: `ssh root@72.62.155.74 "/var/www/deploy.sh teinformez"`
- WP-CLI: `wp --path=/var/www/teinformez --allow-root <command>`
- DB: `mysql -u root teinformez_wp`
- Newsletter table: `wp_teinformez_newsletter` (columns: id, email, token, confirmed, confirmed_at, subscribed_at, unsubscribed_at, ip_address, utm_source, utm_medium, utm_campaign)
