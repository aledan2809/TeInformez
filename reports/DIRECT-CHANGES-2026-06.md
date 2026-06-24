# TeInformez — Direct Changes Ledger (2026-06)

> RESTRICT project (WordPress/PHP custom, manual deploy). Per Master CLASSIFICATION §2c, direct changes are logged here.

## 2026-06-24 — Retired insecure deploy scripts (introspection critical)

**Trigger**: Introspection Audit 2026-06-20 flagged `backend/deploy.php`, `backend/webhook.php`, `backend/deploy-download.php` as RCE-class — `shell_exec('git pull')` gated behind weak hardcoded secrets (`teinformez_deploy_2024_secret`, `teinformez_webhook_secret_2024_XyZ123`) tracked in git. User approved full remediation ("repară acum complet").

**Investigation (re-verify before fix, per L255/L250)**:
- Files NOT web-served — nginx for teinformez.eu executes PHP only on WordPress paths (index.php, wp-admin, wp-login, wp-cron/includes/content); the files lived in `/var/www/teinformez-repo/backend/` (outside any docroot). Public probes → 404 (apex + www).
- A GitHub webhook (id `592942547`) → `www.teinformez.eu/webhook.php` was **active but broken** (delivering to a 404) → auto-deploy non-functional; real deploy is `deploy.sh deploy_teinformez()` (cd teinformez-repo + git pull + build WP plugin).
- Conclusion: not live-exploitable in the current config, but a genuine RCE surface if ever restored + secrets leaked in history.

**Remediation (commit `7aa6929`, pushed)**:
1. `git rm` the 3 scripts + `.gitignore` to prevent re-tracking.
2. Deleted the dead GitHub webhook `592942547` (`gh api -X DELETE`; hooks now 0).
3. Removed server copies `/var/www/teinformez-repo/backend/{deploy,webhook,deploy-download}.php` (backup `/root/backups/teinformez-deploy-scripts-2026-06-24/`).
4. Synced server `teinformez-repo` to origin `7aa6929` (clean tree).

**Verification**: teinformez.eu + www → 200 (site unaffected — only non-served artifacts + a broken webhook touched). `deploy.sh deploy_teinformez()` intact (the working manual path).

**Residual (low)**: secrets remain in git *history* but now protect nothing (mechanism fully removed). Optional BFG/filter-repo scrub deferred — not urgent since no live system honors these secrets anymore.

**Scope discipline**: committed ONLY the 3 deletions + `.gitignore`; pre-existing uncommitted changes (NewsDetailClient.tsx, tsbuildinfo, TODO_PERSISTENT.md) left untouched.
