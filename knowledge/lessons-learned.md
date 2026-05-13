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
