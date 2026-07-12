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
