# CLAUDE.md — TeInformez

## Project Setup

### Stack
- **Frontend**: Next.js (TypeScript) on VPS (`teinformez.eu`)
- **Backend**: WordPress + `teinformez-core` PHP plugin on VPS
- **Database**: MariaDB 10.11 on VPS (5 custom tables prefixed `wp_teinformez_`)
- **AI**: OpenAI GPT-4 Turbo (summarization, translation, categorization)
- **Email**: Brevo API (fallback: wp_mail)

### Paths
- **Frontend**: `C:\Projects\TeInformez\frontend\`
- **Backend plugin**: `C:\Projects\TeInformez\backend\wp-content\plugins\teinformez-core\`
- **VPS WordPress**: `/var/www/teinformez/`
- **VPS Git repo**: `/var/www/teinformez-repo/`
- **VPS Frontend build**: `/var/www/teinformez-frontend/`

### Deployment
- **Frontend**: Push to master → `ssh root@72.62.155.74` → git pull + `npm run build` + `pm2 restart teinformez-frontend`
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
- Allowed origins: `teinformez.eu`, `localhost:3000`, `localhost:3002`
- Configured in Nginx server block on VPS

---

## Knowledge Base
- `knowledge/README.md` — Project overview and architecture


## Governance Reference
See: `Master/knowledge/MASTER_SYSTEM.md` §1-§5. This project follows Master governance; do not duplicate rules.
