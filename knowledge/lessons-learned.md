# Lessons Learned — TeInformez

> Capture incident root causes here. One entry per lesson: L## — YYYY-MM-DD — <short title>.
> Format: **Symptom / Root cause / Fix / Prevention**.

## L01 — 2026-05-13 — Citește dictionary.json înainte de orice abreviere

**Symptom**: Utilizatorul a scris `ST TeInformez` → Claude a interpretat ST ca "Status" și a afișat un raport de status în loc să producă un Session Transfer prompt.

**Root cause**: dictionary.json nu a fost citit la începutul sesiunii. `ST = Session Transfer` e definit explicit acolo.

**Fix**: Când apare orice abreviere necunoscută sau ambiguă, citește `Master/data/dictionary.json` ÎNAINTE de orice interpretare sau răspuns.

**Prevention**: Regula din CLAUDE.md §1 — "verifică mai întâi `data/dictionary.json`" — se aplică nu doar la primul mesaj, ci la orice comandă scurtă din sesiune.

---

## L02 — 2026-05-13 — Meniul de sesiune nu trebuie sărit la primul mesaj

**Symptom**: Primul mesaj al sesiunii `PA! - Teinformez` → Claude a trecut direct la project-switch fără să afișeze meniul de routing.

**Root cause**: `PA!` în română poate însemna "OK/hai" (=start sesiune) sau "la revedere" (=bye). Claude a ales branch-ul greșit și a continuat fără meniu.

**Fix**: CLAUDE.md §1 (updated 2026-05-13) specifică explicit: afișează meniul INDIFERENT de forma primului mesaj, chiar dacă pare salut, bye sau abreviere.

**Prevention**: Meniul de sesiune e obligatoriu la START — nu există excepții pentru mesaje scurte sau ambigue.

## L03 — 2026-06-04 — Numărul de log-uri de eroare ≠ provider/rezultat efectiv

**Symptom**: La diagnosticarea backlog-ului de procesare news, am concluzionat (greșit) că procesarea „s-a oprit" și că rulează „98.5% pe API metered (anthropic/openai)" pe baza a 31.446 log-uri „AI Router unavailable" vs 484 „via AI Router".

**Root cause**: Log-ul „AI Router unavailable" se emite când router-ul `:3100` e ocolit — dar NU spune ce provider preia efectiv. Lanțul real e router → anthropic → openai → **groq**. Anthropic+openai fiind fără credit, eșuează rapid, iar **groq (gratuit) face de fapt toată munca**. Numărarea log-urilor de eroare a măsurat bypass-ul router-ului, nu costul sau eșecul procesării.

**Fix**: Un test runtime IZOLAT (`wp eval $p->process_queue()`, fără concurență) a arătat `{"processed":30}` + „Published via groq" + 0 apeluri metered → a infirmat ipoteza. Diagnostic real: plafon de debit (10/30min=480/zi < ~700/zi fetch), nu oprire, nu cost. Fix = batch 10→30 + groq-first.

**Anti-pattern**: A afirma root-cause/cost din agregate de log-uri fără a trasa rezultatul real al unui apel. Aplică `feedback_research_before_proposing` + `feedback_honest_reporting_no_overstating` ÎNAINTE de a propune un fix bazat pe presupuneri din loguri.

**Cross-ref**: commits `fc7e72f` + `38445fb`; DEVELOPMENT_STATUS 2026-06-04.

## L04 — 2026-06-27 — Un self-heal nu trebuie să trăiască în interiorul lucrului pe care îl repară

**Symptom**: Publicarea știrilor a stat oprită 16 zile (din 2026-06-11). Coada avea doar `published` + `rejected`, zero `fetched`/`pending_review`. Hook-ul cron `teinformez_fetch_news` lipsea complet din WP-cron, deși celelalte hook-uri rulau.

**Root cause**: Blocul de self-heal (care reprogramează hook-urile dispărute) fusese pus **înăuntrul** handler-ului `teinformez_fetch_news` (după incidentul de 5 zile din 2026-05-22). Când `fetch_news` însuși s-a de-programat, self-heal-ul care l-ar fi reînviat a murit odată cu el. Paznicul depindea de exact ceea ce păzea → single point of failure care se auto-sabotează. (Și `check_delivery_health` dispăruse la fel.)

**Fix**: Extras `teinformez_ensure_crons()` (idempotent, acoperă fetch_news + frații) și apelat din `plugins_loaded` — rulează la fiecare încărcare de pagină + fiecare tick de cron, deci nu mai depinde de niciun hook care poate muri. (`teinformez-core.php`.)

**Prevention**: Logica de recuperare/watchdog trebuie ancorată într-un punct care rulează **independent** de componenta monitorizată (plugins_loaded / init / cron de sistem extern), niciodată în interiorul componentei pe care o monitorizează. Dacă „X se repară prin Y", iar Y rulează doar când X rulează → X nu se mai reface niciodată după ce cade.

**Bonus** (mascat de bug): publisher-ul real e agentul `Chief_Editor` (pe hook-ul `teinformez_article_pending_review`), nu `auto_publish_expired`/`publish_approved` din docs — orice throttle de cadență trebuie să gate-ze Chief Editor. Lecție secundară: verifică CINE publică efectiv în runtime, nu ce zice documentația.

## L05 — 2026-07-12 — UPDATE fără upsert = salvare silent no-op pentru rândurile create în afara signup-ului aplicației

**Symptom**: Verificarea live (walk Playwright logat ca reader) a picat pe „preferințele persistă după reload" — deși UI-ul arăta salvare reușită, iar `tsc` era verde. Canalul Telegram bifat + „Salvează" → succes vizual, dar după reload revenea la starea veche.

**Root cause**: `User_Manager::update_preferences()` folosea `$wpdb->update()` direct. Pentru userii FĂRĂ rând în `wp_teinformez_user_preferences` (creați via wp-cli / admin, NU prin signup-ul aplicației care apelează `create_default_preferences`), `UPDATE ... WHERE user_id=X` afectează 0 rânduri și întoarce `0` — pe care WordPress îl tratează ca „no rows changed", nu ca eroare. REST API-ul întorcea 200, frontend-ul afișa succes, baza rămânea goală. Contul de test (e2e-reader id 9) era exact un astfel de user → simptomul apărea doar pe conturile de test, nu pe userii reali (care au signup normal).

**Fix**: guard de upsert în `update_preferences` — `SELECT COUNT(*)` pe `user_id`; dacă 0, apelează `create_default_preferences($user_id)` înainte de `UPDATE`. Idempotent, aditiv. (`class-user-manager.php`, commit `5d57e22`.)

**Prevention**:
1. Orice `UPDATE ... WHERE key=X` pe un rând care „ar trebui să existe" dar poate fi creat pe căi paralele (admin, wp-cli, import, SSO-provisioning) = candidat de upsert. `$wpdb->update` cu 0 rânduri afectate NU e eroare — nu te baza pe return-ul lui ca semnal de succes.
2. **Lecția de proces (repetă memory `feedback-verify-against-user-goal-not-claims`)**: `tsc`/build verde + UI care zice „salvat" NU dovedesc persistența. Doar walk-ul pe output real (reload + citire DB directă) a prins-o. Verify = privește rezultatul real, nu claim-ul componentei.
3. Conturile de test create manual (wp-cli) diferă subtil de userii reali (lipsesc rânduri satelit din signup) → un test care trece pe user real poate pica pe cont de test și invers. Merită aliniate la fluxul real de signup.

**Cross-ref**: commit `5d57e22`; ledger `reports/DIRECT-CHANGES-2026-07.md` 2026-07-10 PM entry D; memory `feedback-verify-against-user-goal-not-claims`.

---

## L06 — 2026-07-12 — Rând platform-level într-un tabel cu FK pe user_id: NULL, nu sentinel 0

**Symptom**: La activarea FB posting, fiecare postare socială ieșea pe Facebook (Graph API 200 + post_id) dar `log_social_post` arunca `WordPress database error Cannot add or update a child row: a foreign key constraint fails (fk_delivery_log_user_id)`. Postările erau trimise dar NU se logau → fără audit trail, `retry_failed_posts` orb. Prins doar prin execuție reală (instanță de L05), nu de `tsc`/`php -l`.

**Root cause**: `wp_teinformez_delivery_log.user_id` = `BIGINT NOT NULL` + FK → `wp_users(ID) ON DELETE CASCADE`. Postările platform-level (fără user) erau logate cu sentinel `user_id = 0`, dar user 0 nu există în `wp_users` → FK respinge insert-ul. Nefuncțional dintotdeauna — a ieșit la prima rulare reală (posting-ul social n-a fost activat pe prod până acum).

**Fix**: `user_id 0 → null` în `log_social_post` (NULL e exceptat de la verificarea FK) + `ALTER TABLE ... MODIFY user_id BIGINT UNSIGNED NULL` pe prod (rânduri existente neatinse, FK păstrat) + CREATE TABLE din activator făcut nullable pt instalări noi. `/review`: safe — `retry_failed_posts` filtrează pe channel/status, nu user_id; niciun cititor pe `user_id=0`. (`class-social-poster.php` + `class-activator.php`, commit `145df20`.)

**Anti-pattern**: NU refolosi un id-sentinel fals (0/-1) pentru rânduri „fără owner" într-un tabel cu FK — FK-ul îl respinge. Coloană nullable + FK (NULL exempt) e semantica corectă. Aceeași greșeală ar apărea în orice tabel user-scoped reutilizat pentru rânduri platform-level.

**Cross-ref**: L05 (verify-real-output); commit `145df20`; ledger `reports/DIRECT-CHANGES-2026-07.md` 2026-07-12.

---

## L07 — 2026-07-12 — Igienă date de test: folosește ACELAȘI tabel ca produsul, nu unul „vecin"

**Symptom**: Am trimis un digest Telegram de test construit din `wp_teinformez_news_archive` → userul a raportat corect „o știre pusă de 4 ori". A părut un bug de conținut în producție.

**Root cause**: `news_archive` = tabel ISTORIC (ultima scriere acum ~o lună, ~53% titluri duplicate legacy). Fluxul REAL de digest (`get_news_for_user`) trage din `news_queue` (viu, publică la ~7h, 0 duplicate). Testul meu a lovit tabelul greșit → artefact de test, nu bug de prod.

**Fix / lecție**: când construiești manual un artefact de verificare (digest de test, seed, fixture), trage din EXACT sursa pe care o folosește calea reală de cod. Altfel produci alarme false care costă timp de investigație și subminează încrederea. Verifică întâi ce tabel/endpoint folosește codul real (`get_news_for_user` → `news_queue`), apoi oglindește-l în test.

**Cross-ref**: sesiunea 2026-07-12; ledger `reports/DIRECT-CHANGES-2026-07.md`.

---

## L08 — 2026-07-12 — Reuse-boundary: o piesă „reutilizabilă" nu se potrivește automat pe un canal nou

**Symptom**: CAS-pe-social (postează creative CAS din MA pe FB/IG) părea un simplu reuse al integrării CAS existente (deja live pe newsletter). Nu e.

**Root cause**: contractul CAS existent `GET {MA}/api/cas/render?slot=...` întoarce **HTML** (pentru embed în web/newsletter). O postare FB/IG are nevoie de câmpuri **separate**: `image_url` public (IG îl fetch-uiește server-side) + `caption` text + `link`. HTML-ul nu se poate folosi direct, iar parsarea lui = fragilă.

**Fix / lecție**: înainte de a presupune că un modul „reutilizabil" acoperă un caz nou, cercetează FORMA contractului (ce întoarce, în ce shape), nu doar existența lui. Când forma nu se potrivește, propune endpoint-ul/adaptorul lipsă (aici: `GET /api/cas/social` → JSON) în TODO-ul proiectului sursă — NU scrie cod speculativ împotriva unui contract inexistent (untestabil, „fabricated requirements").

**Cross-ref**: `class-cas-api.php` (contract HTML); build-prompt în `MarketingAutomation/TODO_PERSISTENT.md`; commit `dfea241`; memory `feedback-detailed-todo-reuse-prompt`.

---

## L09 — 2026-07-13 — Observă violările CSP Report-Only direct în browser, nu aștepta Sentry

**Symptom**: decizia „CSP Report-Only → enforce" era gate-uită pe „observare rapoarte în Sentry", dar aveam doar DSN-ul (fără `SENTRY_AUTH_TOKEN`) → nu puteam interoga Sentry API pentru violări. Aparent blocat până apar date longitudinale.

**Root cause / insight**: rapoartele CSP nu trăiesc DOAR în Sentry. Browser-ul emite evenimente DOM `securitypolicyviolation` pentru fiecare violare — **inclusiv în Report-Only** (`disposition: "report"`) — cu `effectiveDirective` + `blockedURI`. Un walk headless (Playwright, `addInitScript` cu listener pe `document` → array global citit după `networkidle`) peste paginile prod reale colectează exact ce s-ar bloca la enforce. E output real (nu tsc, nu presupuneri).

**Fix / metodă (reutilizabilă pe orice proiect cu CSP Report-Only)**: (1) walk publice + articole reale (id-uri din API) + **consimțit + autentificat** (cookie sesiune + `localStorage` consent='accepted' ca să se încarce efectiv GA/third-party — altfel originile din policy NU-s exercitate); confirmă că GA chiar a încărcat (`window.gtag`/`dataLayer`). (2) Static: scanează corpurile de conținut pentru `<iframe>`/`<script>`/embed + verifică cum se randează câmpuri media (ex: `youtube_url` = `<a href>` link, NU iframe → zero risc `frame-src`) + confirmă că nu există SDK third-party client-side (Stripe redirect-only = fără `stripe.js`). (3) După flip, **re-walk live sub enforce** (`disposition` acum „enforce") ca dovadă că nimic nu se blochează. Caveat onest: crawl point-in-time ≠ date multi-browser/longitudinale → păstrează `report-uri` activ și în enforce; rollback = 1 linie (redenumește header key înapoi).

**Cross-ref**: `frontend/next.config.js` (`cspPolicy()` + `report-uri`); commit `418cd30`; ledger `reports/DIRECT-CHANGES-2026-07.md` (secțiunea CSP enforce); memory `feedback-verify-against-user-goal-not-claims`.
