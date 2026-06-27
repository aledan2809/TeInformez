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

---

## 2026-06-27 — News publishing dead 16 days + add 6-8h cadence throttle

**Trigger**: User reported news not posting regularly to teinformez.eu, wanting "o știre la 6-8h". Investigation found publishing had been **fully stopped since 2026-06-11 09:05** (16 days). Live diagnosis: queue had 9270 published / 0 in any pipeline state (`fetched`/`processing`/`pending_review`); the RSS fetcher hook `teinformez_fetch_news` was **not scheduled** in WP-cron at all.

**Root cause (the real one)**: The self-heal block that re-schedules vanished cron hooks lived **INSIDE** the `teinformez_fetch_news` handler (added 2026-05-22 after a prior 5-day outage). When `fetch_news` itself got unscheduled, the self-heal that could have restored it died with it — a healer that depended on the thing it healed. No fetch → no new items → nothing to process → nothing to publish → 16 days silent. (`teinformez_check_delivery_health` had also silently dropped.)

**Also discovered**: the actual auto-publisher is the **Chief Editor AI agent** (`Chief_Editor::review_and_publish`, fired on the `teinformez_article_pending_review` hook) — it published EVERY processed article immediately. The documented `auto_publish_expired`/`publish_approved` path was not the live publisher. Any cadence throttle had to gate the Chief Editor, not just the cron publish methods.

**Changes (propose-confirm-apply, RESTRICT; backups `*.bak-2026-06-27` on VPS)**:
1. `teinformez-core.php` — extracted `teinformez_ensure_crons()` (idempotent, covers `fetch_news` + 3 siblings) and call it from `plugins_loaded` (runs on every page load + cron tick) — the healer no longer depends on any single hook. Belt-and-suspenders calls kept in both cron handlers.
2. `class-news-publisher.php` — new cadence gate `auto_publish_allowed()` / `mark_auto_published()` (single source of truth, WP option `teinformez_last_publish_ts`, gap `PUBLISH_MIN_GAP=25200` ≈ 7h, tunable via Config `publish_min_gap`); new `publish_throttled()` (cron path publishes ≤1 story/window, freshest first); new `expire_unpublished_stale($days=2)` (ages out un-published rows → 'rejected' so the throttled intake can't grow the queue without bound). `publish_approved`/`auto_publish_expired` left intact for the **unthrottled manual admin** button.
3. `class-chief-editor.php` — gated the "always publish" block: when the ~7h window hasn't elapsed, the (already AI-reviewed) article is queued as `approved` instead of published; when elapsed it publishes + stamps the clock.
4. Cron handler `teinformez_process_news` now calls `publish_throttled()` instead of publishing everything; `teinformez_daily_cleanup` now calls `expire_unpublished_stale(2)` before `cleanup_old_items(30)`.

**Verification (live, wp-cli = fresh PHP, bypasses FPM opcache)**:
- `fetch_news` re-scheduled (+ `check_delivery_health`) — all 6 hooks present.
- `fetch_news` run → 496 fresh items fetched.
- `process_news` run with throttle active → 29 processed, **0 published**, all queued `approved`; `published` count unchanged.
- Clock set to 7.2h-ago → `publish_throttled()` → **exactly 1 published** (freshest), 2nd immediate call → `{"published":0,"throttled":true}`.
- `expire_unpublished_stale(9999)` → 0, no fatal.
- Live: `teinformez.eu` 200; news API shows 2026-06-27 stories (fresh after 16 days dark).
- `php -l` clean on all 3 files; local repo == VPS (diff empty).

**One-time artifact**: the first un-throttled `process_news` (during diagnosis, before the throttle was deployed) published ~29 fresh stories at once. Acceptable (real news, site was empty-feeling); cadence is throttled from here.

**Follow-up (flagged, not built)**: with ~hundreds fetched/day vs ~3-4 published/day, most processed items get AI-reviewed then expired — wasteful (groq free tier, ≈$0). Optional: lower the AI_Processor batch size to match the publish rate. Cadence is configurable via `publish_min_gap`.
