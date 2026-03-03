# Changelog — TeInformez

## [2026-03-03] — Phase E: Social Media Auto-Posting + Content Fixes

### Backend (PHP Plugin)
- New `Social_Poster` class — auto-posts to Facebook Page (Graph API v18.0) and Twitter/X (API v2 + OAuth 1.0a)
- Hooks into `teinformez_news_published` action — posts automatically when news is published
- Retry logic via cron for failed social posts (up to 3 attempts in 24h)
- Admin settings page: Social Media section with Facebook/Twitter API key inputs + enable toggle
- Social config constants added to Config class

### Frontend (Next.js)
- Removed all "AI" mentions from public-facing pages (user-visible text, meta descriptions, keywords)
- Changed "Rezumat AI" to "Rezumat" on news list and detail pages
- Fixed contact emails: `support@4pro.io` → `contact@teinformez.eu` (all pages)
- Fixed GDPR/DPO emails: `gdpr@4pro.io` → `gdpr@teinformez.eu`
- Updated JSON-LD schema email

### Deployment
- Backend + frontend deployed to VPS2
- PHP syntax: all files clean
- Build: 21 routes, all passing
- **Phase E-1 (platform-level posting): COMPLETE. Ready for API keys.**

## [2026-03-03] — Phase D: Analytics, SEO & Full Deployment

### Backend (PHP Plugin)
- Added `view_count` column to `news_queue` table for article view tracking
- Added `POST /news/{id}/view` endpoint — increments view counter
- Added `GET /admin/analytics` endpoint — aggregated platform stats:
  news pipeline, total views, top 10 articles, user growth, subscription
  breakdown, delivery stats
- Applied `ALTER TABLE` to add `view_count` column on live MariaDB

### Frontend (Next.js)
- Added `api.trackView()` call on news detail page load
- OpenAI API key configured in `.env.local` and seeded in Master DB
- Built standalone + deployed to VPS2 via PM2

### Deployment
- Backend: git pull + PHP-FPM restart on VPS2
- Frontend: standalone build → scp → `deploy.sh tei-front` → PM2 restart
- All endpoints verified live: Homepage 200, News API 200, View Track 200, Analytics 401 (auth), Categories 200
- Pipeline operational: 464 published articles, 40 pending review, 726 fetched
- API keys (OpenAI + Brevo) confirmed configured in WP options
- **Phase D: COMPLETE. Platform fully operational.**

## [2026-02-28] — VPS2 Deployment
- Installed PHP 8.3-FPM + MariaDB 10.11 on VPS2 (72.62.155.74)
- WordPress deployed with `teinformez-core` plugin symlinked from git repo
- Nginx + Certbot SSL for teinformez.eu
- Next.js standalone deployed via PM2 (port 3002)
- DNS changed from Hostico shared hosting to VPS2
- Updated `deploy.sh teinformez` script

## [2026-02-21] — Phase B: News Aggregation & AI Processing
- RSS feed fetcher: 10+ sources (HotNews, Digi24, TechCrunch, BBC, etc.)
- AI Processor: OpenAI GPT-4 Turbo integration (summarize, translate, categorize)
- DALL-E 3 image generation for articles without images
- Admin review queue (approve/reject/edit) with auto-publish after 2h
- News Publisher with approval workflow + cron-based auto-publishing
- News list page with category filters, search, pagination
- News detail page with AI summary, sharing buttons, related articles
- Personalized news feed based on user subscriptions
- Bookmarking / saved articles (client-side Zustand store)
- Open Graph meta tags + JSON-LD NewsArticle structured data
- Canonical URLs + Twitter cards

## [2026-02-15] — Phase A: User Registration & Onboarding
- User registration with GDPR consent + email/password validation
- JWT authentication (24h expiry, Bearer token, refresh)
- 4-step onboarding wizard (categories, topics, schedule, channels)
- User dashboard: overview, subscriptions, settings, stats, deliveries
- Subscription management (CRUD, toggle, bulk add)
- Account settings (change password, change email)
- Forgot/Reset password with email token
- GDPR compliance (data export, account deletion, consent tracking)
- Email delivery infrastructure (Brevo API + wp_mail fallback)
- Delivery handler with timezone-aware scheduling (5 frequencies)
- Responsive HTML email templates (digest, welcome, password reset)
- Delivery history page + stats

## [2026-02-15] — Governance Setup
- Added MASTER governance files (SESSION_BOOT, STRATEGY, CONTEXT, DECISIONS, GUARDRAILS, CHANGELOG)
