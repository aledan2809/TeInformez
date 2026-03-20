# Strategy — TeInformez
Last Updated: 2026-03-20

## Vision
AI-powered personalized news platform for the Romanian market. Aggregates news from RSS feeds, processes with OpenAI (translate, summarize, categorize), delivers personalized digests to users via email and social channels.

Cloneable architecture: configurable language/country/sources for replication to other markets.

## Scope

### In Scope
- News aggregation from RSS feeds (10+ sources configured)
- AI processing: translation, summarization, categorization (OpenAI GPT-4)
- User registration with GDPR consent + onboarding wizard
- Personalized news feed based on subscriptions
- Admin review queue (approve/reject/auto-approve)
- Email delivery via Brevo (daily/weekly digest)
- GDPR compliance (export, delete, consent tracking)
- WordPress backend (PHP plugin) + Next.js frontend (Vercel)

### Out of Scope (for now)
- Mobile app (future)
- Web scraping (only RSS for now)
- Paid subscriptions / monetization
- Referral system
- Multi-language frontend UI (backend supports it, UI is Romanian only)

## Key Goals
- [x] Phase A: User registration, onboarding, dashboard — COMPLETE
- [x] Phase B: News aggregation, AI processing, admin review, news pages — COMPLETE
- [x] Phase C: Email delivery system (scheduled digests, Brevo + wp_mail fallback) — COMPLETE
- [~] Phase D: Analytics done (view tracking, admin analytics, SEO), optimization pending — PARTIAL
- [x] Phase E: Juridic section, Telegram integration, social posting — COMPLETE

## Constraints
- **Technical**: WordPress backend (PHP 8.3 + MariaDB), Next.js frontend (Vercel)
- **Hosting**: VPS2 (72.62.155.74) for backend, Vercel for frontend
- **Budget**: OpenAI target ~$10-25/month (10-30 articles/day)
- **Email**: Brevo free tier (300 emails/day)
- **DNS**: teinformez.eu registered at Hostico, A record → VPS2

## Architecture
- **Frontend**: Next.js on Vercel (`teinformez.vercel.app`)
- **Backend**: WordPress + `teinformez-core` plugin on VPS2
- **DB**: MariaDB on VPS2 (9 custom tables)
- **API**: 53 REST endpoints under `/wp-json/teinformez/v1/`
- **Deploy**: `deploy.sh teinformez` on VPS2 (git pull + PHP-FPM restart)
