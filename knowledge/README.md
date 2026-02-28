# TeInformez — Project Overview

## Changelog
- [2026-02-28] v1.2: VPS2 deployment, change password/email, stats page, social share buttons
- [2026-01-26] v1.1: Phase B complete (news aggregation + AI processing)
- [2026-01-19] v1.0: Phase A complete (user registration + onboarding)

## Architecture

```
Frontend (Next.js, Vercel)          Backend (WordPress + PHP plugin, VPS2)
teinformez.vercel.app        →      teinformez.eu/wp-json/teinformez/v1/
                                     ↓
                              WordPress + teinformez-core plugin
                                     ↓
                              MariaDB 10.11 (5 custom tables)
                                     ↓
                              OpenAI GPT-4 (AI processing)
                              Brevo (email delivery)
```

## API Endpoints (25 total)

### Auth (7)
- POST /auth/register, /auth/login, /auth/logout, /auth/refresh
- GET /auth/me
- POST /auth/forgot-password, /auth/reset-password

### User (15)
- GET/PUT /user/preferences
- GET/POST /user/subscriptions, POST /user/subscriptions/bulk
- PUT/DELETE /user/subscriptions/{id}, POST /user/subscriptions/{id}/toggle
- GET /user/stats, /user/export
- DELETE /user/delete
- POST /user/change-password, /user/change-email
- GET /categories

### News (3)
- GET /news, /news/{id}, /news/personalized

## Database Tables
1. `wp_teinformez_user_preferences` — language, delivery channels/schedule, GDPR consent
2. `wp_teinformez_subscriptions` — category, topic, country filter, active flag
3. `wp_teinformez_news_queue` — full news pipeline (fetched → processing → review → published)
4. `wp_teinformez_delivery_log` — email/social delivery tracking
5. `wp_teinformez_news_sources` — RSS/API source management

## Frontend Pages (15)
- `/` — Homepage
- `/register`, `/login`, `/forgot-password`, `/reset-password` — Auth
- `/onboarding` — 4-step wizard
- `/dashboard` — Main dashboard with personalized feed
- `/dashboard/subscriptions` — Manage subscriptions
- `/dashboard/settings` — Account settings, change email/password, GDPR
- `/dashboard/stats` — Statistics
- `/news` — Public news list
- `/news/[id]` — News detail with social share buttons
- `/privacy`, `/terms`, `/gdpr` — Legal pages

## Deployment
- Frontend: Vercel auto-deploy on push to master
- Backend: `ssh root@72.62.155.74 "/var/www/deploy.sh teinformez"` (git pull + PHP-FPM restart)
