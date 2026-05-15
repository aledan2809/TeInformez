# CAS-04 — MA endpoint READY (server side)

> **Status**: MA side READY pe `ma.techbiz.ae`.
> TeInformez side: nu mai e blocat. Implementarea wiring-ului (PHP + Next.js)
> poate continua per CAS-04-handoff-2026-05-15.md secțiunea "Post-MA-endpoint
> TeInformez wiring".
> **Updated**: 2026-05-15

---

## What landed pe MA

Trei endpoint-uri compatibile cu spec-ul din handoff-ul original:

### 1. `GET /api/cas/render`

Backwards-compatible cu CAS Faza 3 (`placement=NEWSLETTER_TOP` continuă să meargă).
**Plus**:

- `slot=<newsletter|infeed|sidebar|banner|push>` — alias util pentru consumeri externi (mapează la canonical placement intern).
- `source=<consumer-id>` — atribuire per consumer (e.g. `source=teinformez`). Folosit la (a) `utm_source` implicit pe click-through dacă nu e setat explicit, (b) breakdown `metrics.by_source.teinformez.impressions`.
- `recipient=<sha256-hash>` / `visitor=<sha256-hash>` — accepted pass-through pentru targeting viitor. Nu se stochează PII; sunt doar hash-uri opace.
- `X-API-Key` header — opțional. Devine **REQUIRED** când env `CAS_API_KEY` e setat pe MA (acum e setat). Fără header valid → `401`.
- **204 No Content** când nu există ad-uri eligibile pentru slot — consumerii pot detecta empty inventory fără parse de body.
- `format=html` (default) sau `format=json`.

**Exemplu curl** (test din local sau de pe VPS2):

```bash
curl -i -H "X-API-Key: b40abe66945cb8a0cbe8f7fbf8de27bcdf53bdc90fc5c42dadc7a282fe7dbdcd" \
  "https://ma.techbiz.ae/api/cas/render?slot=newsletter&source=teinformez&recipient=abc123def456"
```

Răspuns 200 când există ad eligible: `Content-Type: text/html`, corp HTML email-safe (table layout, inline styles, link click prin `/api/cas/click/<trackingCode>?source=teinformez`).

Răspuns 204 când slot e empty: corp gol, nici un metric bump.

### 2. `GET /api/cas/track` (NEW — tracking pixel)

```
GET /api/cas/track?event=impression&trackingCode=<code>&source=teinformez&recipient=<hash>
```

- Return: 1×1 transparent GIF (43 bytes), `Content-Type: image/gif`, `Cache-Control: no-store`.
- **Side effect**: bumpează `LaunchPlanAction.metrics.impressions` + `metrics.by_source.<source>.impressions` (+ `.<event>` breakdown).
- **Niciodată 404** — pixel-ul răspunde mereu 200 cu GIF, chiar dacă `trackingCode` invalid. Privacy + UX (clientul de email nu poate distinge tracked vs untracked).
- Alias `adid=<code>` acceptat pentru compat cu spec-ul TeInformez.

**Embed în newsletter**:

```html
<img src="https://ma.techbiz.ae/api/cas/track?event=impression&trackingCode={{TRACKING_CODE}}&source=teinformez&recipient={{HASHED_EMAIL}}"
     width="1" height="1" border="0" alt="" style="display:block">
```

### 3. `GET /api/cas/click/<trackingCode>` (extended)

CAS Faza 3 click endpoint extins cu `?source=<>`. Acum:

- Atribuire per consumer în `metrics.by_source.<source>.clicks` (în plus la top-level `metrics.clicks`).
- UTM-uri inbound forwarded la destination (already DONE in Faza 3).

**Format URL** (auto-generat de `/api/cas/render` în HTML-ul rezultat — TeInformez nu trebuie să construiască manual):

```
https://ma.techbiz.ae/api/cas/click/ABC123?source=teinformez&utm_source=teinformez&utm_medium=newsletter
```

---

## Env vars TeInformez (READY for VPS sync)

Sync-uite în `Master/credentials/teinformez.env`:

```env
TEINFORMEZ_MA_API_URL=https://ma.techbiz.ae
TEINFORMEZ_MA_API_KEY=b40abe66945cb8a0cbe8f7fbf8de27bcdf53bdc90fc5c42dadc7a282fe7dbdcd
TEINFORMEZ_CAS_SALT=ti-cas-salt-2026-05-15-rotate-quarterly
```

**Acțiune VPS2** (când TeInformez side începe wiring-ul):

```bash
ssh root@72.62.155.74 'cat >> /var/www/teinformez-repo/backend/wp-content/plugins/teinformez-core/.env' <<EOF
TEINFORMEZ_MA_API_URL=https://ma.techbiz.ae
TEINFORMEZ_MA_API_KEY=b40abe66945cb8a0cbe8f7fbf8de27bcdf53bdc90fc5c42dadc7a282fe7dbdcd
TEINFORMEZ_CAS_SALT=ti-cas-salt-2026-05-15-rotate-quarterly
EOF
```

**Pentru frontend** (`.env.local`):

```env
NEXT_PUBLIC_MA_API_URL=https://ma.techbiz.ae
NEXT_PUBLIC_CAS_ENABLED=true
```

**Nota despre cheia frontend**: spec-ul original cere o cheie separată `NEXT_PUBLIC_MA_CAS_KEY` (read-only, per-origin rate-limited). Deocamdată nu am implementat două chei separate — pentru InFeed widget pe `teinformez.eu`, opțiunile sunt:

1. **Lasă endpoint-ul public pe slot-ul WEBSITE_INFEED** — adică nu cere X-API-Key pe `slot=infeed` din browser. Trade-off: oricine poate apela; mitigation: CORS + rate-limit la nivel nginx pe `/api/cas/render` din origine teinformez.eu.
2. **Server-side fetch** — frontend Next.js face fetch printr-o route proxy `/api/cas/proxy?slot=infeed` care injectează `X-API-Key`-ul din env server-side. Recomandat pentru prod.

Strategia recomandată: începe cu opțiunea 2 (proxy), evită expunerea cheii în client bundle.

---

## Differences vs spec-ul original CAS-04-handoff

| Spec | Implemented |
|---|---|
| DB schema dedicată (cas_campaigns/impressions/clicks) | ❌ — reused existing `LaunchPlanAction` table (Campaign + channel='CAS'). Metrics live în `metrics` JSON. Funcțional echivalent, no schema change needed. |
| Admin UI `/admin/cas/*` | ✅ already shipped în CAS Faza 3 la `/cas` (dashboard list + create + metrics). New endpoint reuses același ad pool. |
| `GET /api/cas/render?slot=<>&recipient=<>` | ✅ DONE — accepts both `slot` (alias) and `placement` (canonical). Nou: `source`, `recipient`/`visitor`, optional `X-API-Key`. 204 când empty. |
| Tracking pixel `GET /api/cas/track` | ✅ DONE — 1×1 GIF, accepts `trackingCode` și `adid` alias. |
| Click redirect cu source attribution | ✅ DONE — existing endpoint extins cu `?source=<>` param + `metrics.by_source.<source>.clicks` breakdown. |
| 2-tier API key (server + client) | ⚠️ PARTIAL — single key acum (env `CAS_API_KEY` opt-in gate). Pentru frontend client-side, recomandat pattern proxy în loc de cheie publică. |

---

## Smoke test (post-deploy MA)

După redeploy MA pe VPS1 cu noul env `CAS_API_KEY`:

```bash
# 1. Auth required check (should 401)
curl -s -o /dev/null -w "%{http_code}\n" \
  "https://ma.techbiz.ae/api/cas/render?slot=newsletter&source=teinformez"
# Expected: 401

# 2. Auth pass (should 204 if no ads, 200 if ads exist)
curl -s -o /dev/null -w "%{http_code}\n" \
  -H "X-API-Key: b40abe66945cb8a0cbe8f7fbf8de27bcdf53bdc90fc5c42dadc7a282fe7dbdcd" \
  "https://ma.techbiz.ae/api/cas/render?slot=newsletter&source=teinformez"
# Expected: 204 (no active newsletter ads currently) sau 200 (when admin creates one)

# 3. Tracking pixel always 200 + GIF bytes
curl -s -o /tmp/pixel.gif -w "%{http_code} %{content_type}\n" \
  "https://ma.techbiz.ae/api/cas/track?event=impression&trackingCode=fake&source=teinformez"
# Expected: 200 image/gif (43 bytes pixel even though trackingCode invalid)
ls -l /tmp/pixel.gif  # 43 bytes
```

---

## Files changed pe MA (server-side)

- `src/lib/cas/rotation.ts` — added `WEBSITE_INFEED` placement + `SLOT_TO_PLACEMENT` map + `resolvePlacement()` helper.
- `src/app/api/cas/render/route.ts` — accepts `slot` / `source` / `recipient` / `visitor` / `X-API-Key`, 204 când empty, per-source metric breakdown.
- `src/app/api/cas/track/route.ts` — NEW. 1×1 GIF tracking pixel + metric bump.
- `src/app/api/cas/click/[trackingCode]/route.ts` — accepts `?source=<>` for per-source click breakdown.

Commit: see MA repo HEAD post 2026-05-15 evening.

Env updated pe Master/credentials/marketing-automation.env (CAS_API_KEY) și Master/credentials/teinformez.env (TEINFORMEZ_MA_API_KEY oglindit).

Deploy pe VPS1 = pașii standard MA (vezi handoff CAS Faza 3 deploy pattern).

---

## Next steps pe partea TeInformez (per CAS-04-handoff-2026-05-15.md)

1. Implementare `build_digest_html` PHP fetch cu `wp_remote_get` (~15min)
2. Implementare fetch în `InFeedAd.tsx` (~15min — via proxy route, nu direct)
3. Flip `NEXT_PUBLIC_CAS_ENABLED=true` în VPS .env.local + redeploy
4. Test newsletter send la 1-2 recipients, verificare ad + impression
5. Test InFeed render pe news page

Nu sunt blockers pe MA-side.
