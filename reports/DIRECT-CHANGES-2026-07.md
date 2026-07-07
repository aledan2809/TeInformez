# TeInformez — Direct Changes Ledger (2026-07)

> RESTRICT project (WordPress/PHP custom, manual deploy). Per Master CLASSIFICATION §2c, direct changes are logged here.

## 2026-07-07 — Proactive UX pivot: ELI12 show-first (WI-2) + proactive dashboard (WI-3)

**Trigger**: PA UX audit (mockup "acum vs propunere", 4 ecrane) — the app should be proactive and guide the reader step-by-step per role, not make them hunt through menus. User approved the proposal; delivered per-item in mesh regime (dev → /review → fix).

**Changes (frontend Next.js `teinformez-frontend`, commits on `master`)**:

1. **WI-3 — proactive dashboard** (`791a2a5`, already pushed before this session): `dashboard/page` card "Ce urmează pentru tine" (next-best-action) + completeness bar + stat explainers. tsc ✅, /review ✅.

2. **WI-2 — ELI12 show-first neutral** (`81eebc2`): `frontend/src/app/news/[id]/NewsDetailClient.tsx` — the plain-language "Pe scurt" + "De ce contează" card now renders **by default** when an article has `simple_explanation`/`why_it_matters`; the full article moves behind a "Citește articolul complet" expander. Neutral framing (essentials first, detail one click away). **Reverses the previously-documented opt-in model** — `STRATEGY.md` Nivel 1 updated opt-in → show-first + **off-ramp** note (revert WI-2 restores the opt-in button; implementation preserved in git history). Same commit also folds in two small pre-existing WIP hardenings on the same file (documented in the commit body, not lumped silently): `formatDate` try/catch + `isNaN` guard; `handleShare` early `if (!news) return`. tsc --noEmit ✅ (re-verified on real output this session), /review ✅ (prior session).

**Deploy**: frontend deploy = local `next build` → tar → `deploy.sh teinformez_frontend`. Both WI-2 + WI-3 go live together (WI-3 was pushed but not yet deployed). Verified on `https://teinformez.eu` — see verification below.

**Deploy (canonical CLAUDE.md recipe, NOT the handoff's "local build → tar")**: `ssh VPS2 "cd /var/www/teinformez-repo && git pull origin master && cd frontend && npm run build && cp -r .next/static .next/standalone/.next/ && cp -r public/ .next/standalone/public/ && rsync -a --delete .next/standalone/ /var/www/teinformez-frontend/ && PORT=3002 pm2 restart teinformez"`. The real frontend deploy builds **on the VPS after git pull** (no `deploy.sh teinformez_frontend` exists) — this also sidesteps the "local dev corrupts .next" blocker entirely. Preflight: VPS disk 22G free (78%), `.env.local` present only at repo source (build-time NEXT_PUBLIC bake), none at rsync target → `--delete` safe.

**Verification (live)**:
- Repo HEAD VPS `4180a90` → `81eebc2` (git pull brought WI-3 `dashboard/page.tsx` + WI-2 `NewsDetailClient.tsx`); `npm run build` clean.
- PM2 `teinformez` (id 4) online, 0 unstable restarts, stable at 22s+ uptime; local `:3002` → 200; `https://teinformez.eu` → 200.
- Shipped client chunk `news/[id]/page-*.js`: opt-in string "Explică pe scurt" = **0 occurrences** (button truly gone); expander "Citește articolul complet" **present**; dashboard "Ce urmează pentru tine" **present** in `dashboard/page-*.js`.
- Pre-existing non-regression: `sharp` image-optimization warning in standalone mode (predates this deploy; page still 200).

**Follow-up (flagged, not built — surgical scope)**: after WI-2, `simpleModeStore.ts` `eli12Discovered` / `markEli12Discovered` + persisted `eli12` state are orphaned dead code (0 usages outside the store), plus a stale comment referencing the removed button. Tiny cleanup commit when convenient (kept out of the WI-2 commit to stay surgical). → **DONE same day, see batch below (`65dd221`).**

---

## 2026-07-07 (PM) — Mesh batch: ELI12 cleanup + WI-4 homepage + mobile nav + WI-5 Cabina de comandă (all LIVE)

**Trigger**: user approved "toate, în regim de instrucțiuni mesh" — the remaining UX-proposal items, sequential dev → /review → fix per item.

**Changes (commits on `master`, all pushed)**:

1. **`65dd221` chore(store)** — removed orphaned ELI12 opt-in state (`eli12Discovered`/`markEli12Discovered` + stale comment) from `simpleModeStore.ts`. Stale localStorage keys ignored by zustand persist on rehydrate. Verified absent from deployed chunks.

2. **`1d61897` feat(home) WI-4** — top banner reworked into a 3-step quick-start band („⚡ Pornește în 20 de secunde": cont → categorii → digest), single CTA path to `/register` („Începe acum →"); lead article cards render `simple_explanation` with a „📌 Pe scurt:" marker (line-clamp-2, summary fallback); homepage + metadata copy cleaned of 'AI' wording (active rule). /review: approve (empty-string fallback, single ArticleCard consumer, ol/li semantics noted).

3. **`72bc48e` fix(api)** — found during live verification: `/news/homepage` SELECT omitted the `simple_explanation` column → `format_news_item` emitted null on every card despite **100% ELI12 coverage** on last-14-days published items (verified in DB: 70/70). One column added to the SELECT. This was the handoff's anticipated „verifică dacă API întoarce simple_explanation" case — it half-did (key present, value never selected).

4. **`d3bf346` feat(dashboard)** — responsive mobile nav: sidebar (always-visible `w-64`, crushed phone content) now `hidden md:block`; mobile gets sticky top bar (logo + hamburger) + slide-over drawer reusing the same Sidebar (new `onNavigate` prop). Drawer closes on backdrop / X / nav click / route change. /review fix applied pre-commit: floating X with `calc(theme(...))` was fragile on <301px screens → moved inside the drawer container.

5. **`ba98ffd` feat(admin) WI-5** — „🎛️ Cabina de comandă" replaces the stub TeInformez dashboard: 4 health tiles (Publicare cu status-ul celor 4 cron-uri critice / Livrări 24h / Coadă / Venit) + severity-ordered „📌 De făcut acum" list. Detects the June-outage failure class: cron hook **missing OR overdue >30min** (wp-cron not ticking) → CRIT. `php -l` clean; all SQL prepared or constant; `esc_html`/`wp_kses_post` on output; `manage_options` + ABSPATH guards.

**Deploys**:
- Frontend: canonical VPS-build recipe (git pull `81eebc2→ba98ffd` then `→72bc48e`, build, static/public copy, rsync, pm2 restart). PM2 stable, 0 unstable; :3002 → 200.
- Backend: `/var/www/deploy.sh teinformez` ×2 (plugin symlink + PHP-FPM restart), HEAD `72bc48e`.

**Verification (live, real output)**:
- tsc --noEmit EXIT=0 (whole frontend, items 1+2+4).
- Homepage SSR (post-ISR revalidate): „Pornește în 20 de secunde" ✅, „Începe acum" ✅, **„Pe scurt:" ×5** (one per section lead) ✅, 'de AI' = 0 ✅.
- Homepage API: 5/5 section leads + hero now carry `simple_explanation` (was 0/5 + null before `72bc48e`; transient `teinformez_homepage_data` deleted post-deploy).
- Mobile nav: „Deschide meniul" marker present in deployed `app/dashboard/layout-*.js`; `eli12Discovered` absent from all chunks. (Full click-through is auth-gated — chunk + tsc evidence; visual walk deferred.)
- WI-5: rendered server-side via `wp eval` with a real admin (user 3): LEN=4754, title OK, 3 tile badges, „Totul funcționează" all-green — consistent with the healthy post-4180a90 state.

**Pre-existing quirk (flagged, NOT touched)**: `deploy.sh teinformez`'s own health check prints „WordPress: HTTP 404 / REST API: HTTP 404" — it probes `http://localhost/wp-json/` with a Host header, which no longer routes (public `https://teinformez.eu/wp-json/...` is 200). The check is broken, not the site. Candidate one-line fix in `deploy.sh` (use the public URL), needs its own confirm since deploy.sh serves multiple projects.
