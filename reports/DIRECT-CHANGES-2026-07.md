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

**Follow-up (flagged, not built — surgical scope)**: after WI-2, `simpleModeStore.ts` `eli12Discovered` / `markEli12Discovered` + persisted `eli12` state are orphaned dead code (0 usages outside the store), plus a stale comment referencing the removed button. Tiny cleanup commit when convenient (kept out of the WI-2 commit to stay surgical).
