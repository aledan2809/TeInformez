# CLAUDE.md — TeInformez

## Project Setup

### Stack
- **Frontend**: Next.js (TypeScript) on Vercel (`teinformez.vercel.app`)
- **Backend**: WordPress + `teinformez-core` PHP plugin on VPS2
- **Database**: MariaDB 10.11 on VPS2 (5 custom tables prefixed `wp_teinformez_`)
- **AI**: OpenAI GPT-4 Turbo (summarization, translation, categorization)
- **Email**: Brevo API (fallback: wp_mail)

### Paths
- **Frontend**: `C:\Projects\TeInformez\frontend\`
- **Backend plugin**: `C:\Projects\TeInformez\backend\wp-content\plugins\teinformez-core\`
- **VPS2 WordPress**: `/var/www/teinformez/`
- **VPS2 Git repo**: `/var/www/teinformez-repo/`

### Deployment
- **Frontend**: Push to master → auto-deploys on Vercel
- **Backend**: `ssh root@72.62.155.74 "/var/www/deploy.sh teinformez"` (git pull + PHP-FPM restart)
- **WP Admin**: `https://teinformez.eu/wp-admin/` (user: `teinformez`)

### API Base
- Production: `https://teinformez.eu/wp-json/teinformez/v1/`
- 23 REST endpoints (auth, user, subscriptions, news, categories)

---

## Build & Test

```bash
# Frontend
cd frontend && npm install && npm run build

# Backend — no build step (PHP), deploy via deploy.sh
```

---

## Project-Specific Rules

### Frontend
- All pages in `frontend/src/app/` (Next.js App Router)
- API client in `frontend/src/lib/api.ts` (Axios, auto-attaches Bearer token)
- Auth store via Zustand (`frontend/src/lib/auth-store.ts`)
- UI language: Romanian

### Backend
- Plugin follows WordPress conventions (classes in `includes/`, API in `api/`, admin in `admin/`)
- All DB tables use `wp_teinformez_` prefix
- REST API namespace: `teinformez/v1`
- Cron jobs: fetch (30min), process (30min), deliveries (15min), cleanup (daily)

### CORS
- Allowed origins: `teinformez.vercel.app`, `teinformez.eu`, `localhost:3000`
- Configured in Nginx server block on VPS2

---

## Knowledge Base
- `knowledge/README.md` — Project overview and architecture
