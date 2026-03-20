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
                              MariaDB 10.11 (9 custom tables)
                                     ↓
                              OpenAI GPT-4 (AI processing)
                              Brevo (email delivery)
```

## API Endpoints (53 total)

### Auth (7)
- POST /auth/register, /auth/login, /auth/logout, /auth/refresh
- GET /auth/me
- POST /auth/forgot-password, /auth/reset-password

### User (20)
- GET/PUT /user/preferences
- GET/POST /user/subscriptions, POST /user/subscriptions/bulk
- PUT/DELETE /user/subscriptions/{id}, POST /user/subscriptions/{id}/toggle
- GET /user/stats, /user/export, /user/deliveries
- DELETE /user/delete
- POST /user/change-password, /user/change-email
- GET /categories
- POST /user/reading-history, GET /user/reading-history
- GET /user/bookmarks, POST /user/bookmarks, DELETE /user/bookmarks/{id}

### News (5)
- GET /news, /news/{id}, /news/personalized, /news/homepage
- POST /news/{id}/view

### Newsletter (3)
- POST /newsletter/subscribe, /newsletter/confirm, /newsletter/unsubscribe

### Telegram (4)
- GET/PUT /telegram/config
- POST /telegram/groups/discover, /telegram/messages/read, /telegram/messages/send

### Analytics (1)
- POST /analytics/track

### Settings (2)
- GET/PUT /settings/category-order

### Admin (2)
- GET /admin/analytics, /admin/delivery-health

### Juridic (9)
- GET/POST /juridic
- GET/PUT/DELETE /juridic/{id}
- GET /juridic/categories, /juridic/columns
- POST /juridic/{id}/view, /juridic/import/facebook, /juridic/{id}/publish-social

## Database Tables (9 custom)
1. `wp_teinformez_user_preferences` — language, delivery channels/schedule, GDPR consent (IP + policy version)
2. `wp_teinformez_subscriptions` — category, topic, country filter, active flag
3. `wp_teinformez_news_queue` — full news pipeline (fetched → processing → review → published)
4. `wp_teinformez_delivery_log` — email/social delivery tracking
5. `wp_teinformez_newsletter` — double opt-in newsletter subscribers (token, confirmed, IP)
6. `wp_teinformez_juridic_qa` — juridic Q&A items
7. `wp_teinformez_news_archive` — archived old news
8. `wp_teinformez_reading_history` — user reading history + time spent (synced)
9. `wp_teinformez_bookmarks` — user bookmarks (synced with frontend)

## Frontend Pages (20)
- `/` — Homepage
- `/register`, `/login`, `/forgot-password`, `/reset-password` — Auth
- `/onboarding` — 6-step wizard (language, categories, topics, countries, schedule, channels)
- `/dashboard` — Main dashboard with personalized feed
- `/dashboard/subscriptions` — Manage subscriptions
- `/dashboard/settings` — Account settings, change email/password, GDPR
- `/dashboard/stats` — Statistics
- `/dashboard/deliveries` — Delivery history
- `/dashboard/telegram` — Telegram integration
- `/news` — Public news list
- `/news/[id]` — News detail with social share buttons
- `/news/saved` — Saved/bookmarked news
- `/juridic` — Juridic cu Alina section
- `/juridic/[id]` — Juridic article detail
- `/privacy`, `/terms`, `/gdpr` — Legal pages

## Deployment
- Frontend: Vercel auto-deploy on push to master
- Backend: `ssh root@72.62.155.74 "/var/www/deploy.sh teinformez"` (git pull + PHP-FPM restart)
