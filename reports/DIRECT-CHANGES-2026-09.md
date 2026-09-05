# Modificări directe — TeInformez — septembrie 2026

## 2026-09-05 · Slot CAS pe pagina Juridic

**Context**: auditul True E2E pe MarketingAutomation a deschis subiectul reclamelor CAS pe
site-urile consumatoare. Verificare directă: prima pagină (`BannerSlot`, după secțiunile 1/4/7) și
`/news` (`InFeedAd`, la fiecare al 5-lea card) aveau deja sloturi funcționale, alimentate prin
proxy-ul WP server-to-server. `/juridic` era singura suprafață publică fără.

**Livrat**: `frontend/src/app/juridic/JuridicList.tsx` — `InFeedAd` după fiecare al 3-lea răspuns,
niciodată ultimul. Commit-uri `175dd89` (pas 4) + `dacc707` (corectat la pas 3: pagina are 4
întrebări, iar cu pas 4 regula „niciodată ultimul" suprima slotul complet — verificat în browser,
zero reclame afișate).

**Cum s-a construit** (important pentru livrările viitoare): build **pe VPS2**, în
`/var/www/teinformez-repo/frontend`, NU local. `.env.local` de pe mașina de dezvoltare **nu are**
`NEXT_PUBLIC_CAS_ENABLED`, iar variabilele `NEXT_PUBLIC_*` se fixează la construcție — un build
local ar fi stins TOATE reclamele de pe site, tăcut. Backup al versiunii anterioare:
`/root/backups/teinformez-frontend.bak-2026-09-05`.

**Verificat live**: `/juridic` afișează un bloc „Sponsored" cu link către
`ma.techbiz.ae/api/cas/click/PDF-I8BT7?utm_source=teinformez`; cererea `placement=infeed&n=0` →
200. Prima pagină și `/news` neatinse (200, reclamele funcționează). Afișările se contorizează:
`AUTO-0CB6` 629 din teinformez, `PDF-I8BT7` 611.

**Notă**: CSP-ul site-ului permite scripturi doar de la `'self'` + Google Tag Manager, iar conexiuni
doar către GA/Sentry. Un embed clasic `<script src="https://ma.techbiz.ae/...">` ar fi fost blocat
tăcut de browser — de aceea calea corectă rămâne proxy-ul propriu, cum era deja construit.
