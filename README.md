# TeInformez.eu — AI-Powered News Platform

**Status**: Phase A+B+C+D Complete — Platform Live
**Version**: 1.2.0
**Last Updated**: 3 Martie 2026

Platforma de stiri personalizate bazata pe AI, cu livrare multi-canal.

---

## Architecture

```
Frontend (Next.js 14)          Backend (WordPress + PHP Plugin)
teinformez.eu (:3002)         teinformez.eu (PHP-FPM 8.3)
VPS2 (72.62.155.74)           VPS2 (72.62.155.74)
PM2 standalone                 Nginx + MariaDB 10.11
```

**Stack**: Next.js 14 + Tailwind 3 + Zustand | WordPress + teinformez-core PHP plugin
**DB**: MariaDB 10.11 (5 custom tables)
**Email**: Brevo API + wp_mail fallback
**AI**: OpenAI GPT-4 Turbo (summarize/translate/categorize) + DALL-E 3 (images)

---

## Status per Phase

| Phase | Status | Details |
|-------|--------|---------|
| A — User Registration | COMPLETE | Auth, onboarding, dashboard, GDPR |
| B — News Aggregation | COMPLETE | RSS feeds, AI processing, admin review, news pages |
| C — Delivery System | COMPLETE | Email digests, scheduling, timezone-aware delivery |
| D — Analytics & Launch | COMPLETE | View tracking, admin analytics, SEO, deployed |

---

## API Endpoints (25 total)

### Auth (7)
POST /auth/register, /auth/login, /auth/logout, /auth/refresh, /auth/forgot-password, /auth/reset-password
GET /auth/me

### User (9)
GET/PUT /user/preferences, GET/POST/PUT/DELETE /user/subscriptions, POST /user/subscriptions/bulk
POST /user/subscriptions/{id}/toggle, GET /user/stats
POST /user/change-password, /user/change-email
GET /user/deliveries, /user/export, DELETE /user/delete

### News (5)
GET /news, GET /news/{id}, GET /news/personalized
POST /news/{id}/view
GET /categories

### Admin (1)
GET /admin/analytics

---

## Deployment

```bash
# VPS2 deploy
ssh root@72.62.155.74
/var/www/deploy.sh teinformez    # git pull + PHP-FPM restart

# Local dev
cd frontend && npm run dev       # localhost:3002
```

**WP Admin**: https://teinformez.eu/wp-admin/ (user: teinformez)
**SSL**: Certbot, expires 2026-05-29

---

## Key Files

### Backend (PHP Plugin)
```
backend/wp-content/plugins/teinformez-core/
  teinformez-core.php            — Main plugin file
  api/class-auth-api.php         — 7 auth endpoints
  api/class-user-api.php         — 15 user endpoints
  api/class-news-api.php         — 5 news endpoints + analytics
  includes/class-news-fetcher.php    — RSS feed parser
  includes/class-ai-processor.php    — OpenAI integration
  includes/class-news-publisher.php  — Approval workflow
  includes/class-delivery-handler.php — Email digest scheduler
  includes/class-email-sender.php    — Brevo API client
  admin/class-admin.php              — WP admin pages
```

### Frontend (Next.js)
```
frontend/src/
  app/page.tsx                   — Landing page
  app/login/page.tsx             — Login
  app/register/page.tsx          — Registration + GDPR
  app/onboarding/page.tsx        — 4-step wizard
  app/dashboard/page.tsx         — Dashboard overview
  app/news/page.tsx              — News list + filters
  app/news/[id]/page.tsx         — News detail + OG + JSON-LD
  lib/api.ts                     — API client (25 methods)
  store/authStore.ts             — Auth state (Zustand)
```

---

## Roadmap

- [x] Phase A — User Registration
- [x] Phase B — News Aggregation + AI Processing
- [x] Phase C — Email Delivery System
- [x] Phase D — Analytics, Performance, Launch
- [ ] Phase E — Social media posting (future)
