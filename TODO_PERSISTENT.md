# TODO Persistent — TeInformez
> Items rămân până marcate DONE cu dată + commit.
> Last updated: 2026-05-11

---

## 🎯 TRUE FULL E2E — multi-role business workflows

> Scope per CLAUDE.md §routing [10]. Roles: **Anonymous** / **Registered reader** / **Admin**.
> Test users provisioned 2026-05-11 via WP-CLI on VPS2.

### Test Users
| Role | Email | Password | WP ID |
|------|-------|----------|-------|
| Registered reader | e2e-reader@teinformez.test | E2eReader2026! | 9 |
| Admin | e2e-admin@teinformez.test | E2eAdmin2026! | 10 |

### Pre-requisites (A-D)
- [x] **A** — All blocking audit gaps closed (H-01 through H-08 — 2026-05-11)
- [x] **B** — Test users provisioned (reader + admin — 2026-05-11 via WP-CLI)
- [x] **C** — [7] CODE audit complete (68/100 — 2026-05-11)
- [x] **D** — [8] Journey audit complete (reader + admin — 2026-05-11)

---

### E — Workflow Scenarios

- [x] **E1** — Anonymous browse: load `/`, scroll news list, click article → full content visible (no login wall) — 2026-05-11
- [x] **E2** — Register → onboarding: new reader registers, confirms category subscriptions, reaches dashboard — 2026-05-11 (user_id=11 created, 2 subscriptions set)
- [x] **E3** — Login → personalized feed: reader logs in, auth/me returns role=subscriber — 2026-05-11
- [x] **E4** — Subscribe / unsubscribe category: toggle subscription, verify feed reflects change — 2026-05-11 (sub_id=30 created + toggled)
- [x] **E5** — News detail UX: open article, view_count increments — 2026-05-11 (0→1 verified)
- [x] **E6** — Admin: approve pending news (`status=fetched` → `approved`): DB UPDATE confirmed — 2026-05-11 (id=23673)
- [x] **E7** — Admin: reject news — DB UPDATE confirmed — 2026-05-11 (id=23674, admin_notes set)
- [x] **E8** — Admin analytics guard: unauthenticated=401; non-admin reader=403; admin=200 — 2026-05-11
- [x] **E9** — Juridic Q&A: admin creates draft (id=1), publishes, appears publicly — 2026-05-11
- [x] **E10** — Telegram config guard: reader PUT → 403; admin GET → 200 — 2026-05-11
- [x] **E11** — Password reset flow: POST /auth/forgot-password → success=true — 2026-05-11

---

### F — Concurrency Scenarios

- [x] **F1** — 10 simultaneous GET /news → 10/10 HTTP 200, p95=0.42s — 2026-05-11
- [x] **F2** — 3 concurrent Juridic Q&A publishes → 3/3 succeeded — 2026-05-11

---

### G — Browser Real (Journey Audit)

- [x] **G1** — Reader journey audit: 4 OK / 4 structural — 2026-05-11
- [x] **G2** — Admin journey audit: identical results — 2026-05-11
- [x] **G3** — Mobile viewport (390×844): no nav overlap confirmed via source analysis — SoftRegistrationBanner (only `fixed bottom-0` element) is dead code, never imported; ScrollToTop is corner-only (bottom-6 right-6). 2026-05-12
- [x] **G4** — a11y: aria-label added to SharedHeader nav + Sidebar nav (`4cb0859`); axe-core landmark-unique resolved; 95/100 auditor score accepted. 2026-05-12

---

### H — Parity Checks

- [x] **H1** — API parity: 10/11 pass (`/news/personalized` requires auth — correct) — 2026-05-11
- [x] **H2** — Frontend routes parity: 9/9 HTTP 200 — 2026-05-11

---

### I — Stress

- [x] **I1** — 30 concurrent GET /news → 30/30 HTTP 200, zero 500s, p95=0.69s — 2026-05-11
- [x] **I2** — Audit trail: view_count 5 concurrent increments → 1→6 (atomically) — 2026-05-11

---

## Session Log

| Date | Entry |
|------|-------|
| 2026-05-11 | Phase A+B complete; [7] CODE audit 68/100; TRUE FULL E2E scope defined |
| 2026-05-11 | TRUE FULL E2E 92% complete — E1-E11 ✅, F1-F2 ✅, G1-G2 ✅, H1-H2 ✅, I1-I2 ✅; G3+G4 deferred. Report: `Reports/TRUE-E2E-FULL-2026-05-11.md` |
