# Context — TeInformez
Last Updated: 2026-02-28

## Current State
- **Phase A** (User Registration): COMPLETE — 14 API endpoints, onboarding wizard, dashboard
- **Phase B** (News Aggregation): COMPLETE — RSS fetcher, OpenAI processing, admin approval, news pages
- **Phase C** (Email/Social Delivery): NOT STARTED
- **Phase D** (Analytics/Launch): NOT STARTED
- **Frontend**: Deployed on VPS2 (72.62.155.74:3002) — Next.js standalone, PM2 `teinformez-frontend`
- **Backend**: Deployed on VPS2 (72.62.155.74) — WordPress + teinformez-core plugin via PHP-FPM
- **SSL**: Certbot, expires 2026-05-29
- **DNS**: teinformez.eu → 72.62.155.74 (changed from Hostico shared hosting)

## Session Log

### 2026-02-28 — VPS2 Deployment
- **Mode**: Autonomous
- **What was done**:
  - Installed PHP 8.3-FPM + MariaDB 10.11 on VPS2
  - Downloaded WordPress, created DB (`teinformez_wp`, user `teinformez`)
  - Configured Nginx + PHP-FPM for teinformez.eu
  - Cloned git repo, symlinked teinformez-core plugin
  - Installed WordPress via WP-CLI, activated plugin, enabled permalinks
  - DNS changed: teinformez.eu A record → 72.62.155.74
  - SSL via Certbot (teinformez.eu + www.teinformez.eu)
  - Updated deploy.sh: `deploy.sh teinformez` (git pull + PHP-FPM restart)
  - WP Admin credentials: user `teinformez`
  - Verified: CORS works, REST API responds, categories endpoint returns 8 categories
  - Updated infrastructure.md + MEMORY.md
- **Blockers**: OpenAI API key not yet configured in WP admin
- **Next**: Configure OpenAI key, trigger first news fetch, start Phase C
