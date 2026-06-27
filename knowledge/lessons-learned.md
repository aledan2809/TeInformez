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
