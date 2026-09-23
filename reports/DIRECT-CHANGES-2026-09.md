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

---

## 2026-09-23 — știrile oprite din 08.09 + site-ul public căzut din 08.09 + trimiterea spre MA refuzată (confirmat de Alex)

**Diagnoză (doar citire, apoi confirmare)**: descărcarea mergea (839 de știri în 21–23.09), dar prelucrarea pica pe
toate căile: modelul Groq `llama-3.3-70b-versatile` retras (3.479 de erori „model does not exist”), Anthropic și
OpenAI fără credit, AIRouter-ul local de pe :3100 nepornit. Ultima știre prelucrată: 08.09 03:52. Ce nu se
prelucra expira la 2 zile (`expire_unpublished_stale`) — ~200–430/zi pierdute.

**1. Model** (`bf52720`): `openai/gpt-oss-120b` + `reasoning_effort: low` în `AI_Processor::call_groq` și
`Chief_Editor`. Probat înainte de livrare pe 3 știri reale din coadă, fără scrieri: 3/3, ~2,2 s, toate câmpurile.
După livrare: prima știre prelucrată → publicată de redactorul-șef, alta respinsă de el; nicio eroare nouă de model.

**2. Trimiterea spre MA** (`bf52720`): `user_id`/`email` mutate sub `payload` (receptorul MA are schemă strictă,
fiecare lot primea HTTP 400 și cursorul nu avansa). După livrare: evenimentul ajuns în MA la 10:05, cursor `users`=17.

**3. Site-ul public** (fără cod): procesul PM2 `teinformez` (Next standalone, :3002) dispăruse la repornirea
daemonului PM2 din 08.09 (a murit pe „ENOSPC”, disc plin) și nu era în `dump.pm2` → teinformez.eu dădea 502 de
15 zile. Repornit din build-ul existent (`PORT=3002 pm2 start server.js --name teinformez`) + `pm2 save`.
Verificat: `/`, `www`, o știre → 200.

Copie înainte: `VPS2:/root/backups/teinformez-class-ai-processor.php.bak-2026-09-23`. Revenire: `git revert bf52720`
pe `/var/www/teinformez-repo` (pluginul live e clona).

⚠️ **Rămas**: prelucrarea depinde acum de un singur furnizor gratuit (Groq). Anthropic/OpenAI fără credit, iar
AIRouter-ul local nu rulează — o nouă retragere de model oprește din nou știrile. Vezi cererea lui Alex din aceeași
zi: AIRouter să verifice zilnic modelele active (Master/AIRouter TODO).
