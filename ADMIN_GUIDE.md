# 📚 TeInformez - Ghid Administrator

**Versiune**: Phase B - News Aggregation
**Data actualizare**: 26 Ianuarie 2026
**Autor**: Claude Code

---

## 📖 Cuprins

1. [Configurare Inițială](#configurare-inițială)
2. [Managementul Surselor de Știri](#managementul-surselor-de-știri)
3. [Fluxul de Aprobare](#fluxul-de-aprobare)
4. [Cron Jobs și Automatizare](#cron-jobs-și-automatizare)
5. [Acțiuni Manuale](#acțiuni-manuale)
6. [Monitorizare și Statistici](#monitorizare-și-statistici)
7. [Depanare](#depanare)

---

## 🚀 Configurare Inițială

### 1. Activare Plugin

După instalarea backend-ului WordPress:

1. Mergi la **WordPress Admin → Plugins**
2. Caută **TeInformez Core**
3. Click pe **Activate**
4. Verifică că tabelele au fost create:
   - `wp_teinformez_news_queue`
   - `wp_teinformez_subscriptions`
   - `wp_teinformez_user_preferences`

### 2. Configurare API Keys

**Locație**: WordPress Admin → TeInformez → Settings

#### OpenAI API Key (OBLIGATORIU)

- **Utilizare**: Procesare AI (traducere, sumarizare, categorii)
- **Cost**: ~$0.02-0.05 per articol (GPT-4 Turbo)
- **Obținere cheie**: [platform.openai.com/api-keys](https://platform.openai.com/api-keys)

**Setare**:
```
OpenAI API Key: sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

#### NewsAPI.org Key (OPȚIONAL)

- **Utilizare**: Sursă alternativă de știri (API-based)
- **Plan gratuit**: 100 cereri/zi
- **Obținere cheie**: [newsapi.org/register](https://newsapi.org/register)

**Setare**:
```
NewsAPI API Key: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### 3. Verificare Cron Jobs

După activare, verifică că următoarele cron jobs sunt programate:

| Job | Frecvență | Scop |
|-----|-----------|------|
| `teinformez_fetch_news` | 30 minute | Descarcă știri de la surse RSS |
| `teinformez_process_news` | 30 minute | Procesează știri cu OpenAI |
| `teinformez_cleanup_old_items` | Zilnic | Șterge știri vechi (>30 zile) |

**Verificare în terminal**:
```bash
wp cron event list | grep teinformez
```

---

## 📰 Managementul Surselor de Știri

### Surse Preconfigurate

Phase B vine cu **10 surse RSS** preconfigurate:

| Sursă | Categorie | Limbă | URL |
|-------|-----------|-------|-----|
| TechCrunch | tech | EN | https://techcrunch.com/feed/ |
| The Verge | tech | EN | https://www.theverge.com/rss/index.xml |
| Wired | tech | EN | https://www.wired.com/feed/rss |
| Reuters Tech | tech | EN | https://www.reutersagency.com/feed/?taxonomy=best-topics&post_type=best |
| Engadget | tech | EN | https://www.engadget.com/rss.xml |
| Bloomberg Tech | finance | EN | https://www.bloomberg.com/technology/feed |
| Financial Times | finance | EN | https://www.ft.com/?format=rss |
| Ars Technica | science | EN | https://feeds.arstechnica.com/arstechnica/index |
| The Guardian Tech | tech | EN | https://www.theguardian.com/technology/rss |
| MIT Tech Review | tech | EN | https://www.technologyreview.com/feed/ |

### Adăugare Sursă Nouă (Manual - Temporar)

Până la interfața admin pentru surse (viitoare funcționalitate), poți adăuga surse manual:

**Fișier**: `backend/wp-content/plugins/teinformez-core/includes/class-news-fetcher.php`

**Locație**: Linia ~37, metoda `get_default_sources()`

**Exemplu**:
```php
[
    'name' => 'ZDNet',
    'url' => 'https://www.zdnet.com/news/rss.xml',
    'type' => 'rss',
    'category' => 'tech',
    'language' => 'en',
    'is_active' => true
],
```

**Reactivează plugin-ul** după modificare.

---

## ✅ Fluxul de Aprobare

### Statusuri Știri

Știrile trec prin următoarele statusuri:

```
fetched → processing → pending_review → approved → published
                                      ↘ rejected
```

| Status | Descriere | Acțiune |
|--------|-----------|---------|
| **fetched** | Descărcat din RSS, dar nu procesat | Așteaptă procesare AI |
| **processing** | În curs de procesare cu OpenAI | Automat |
| **pending_review** | Procesat, așteaptă aprobare admin | **TU REVEZI** |
| **approved** | Aprobat de admin | Așteaptă publicare |
| **rejected** | Respins de admin | Nu va fi publicat |
| **published** | Live pe frontend | Vizibil utilizatorilor |

### Revizia Știrilor

**Locație**: WordPress Admin → TeInformez → News Queue

#### Pași de Revizuire

1. **Filtrare**: Click pe tab **"Pending Review"**
2. **Vizualizare**: Click pe **"View/Edit"** pentru fiecare știre
3. **Verificare**:
   - Titlu tradus corect?
   - Sumar relevant (max 150 cuvinte)?
   - Conținut complet tradus?
   - Categorii corecte?
   - Imagine generată adecvată?

4. **Decizie**:
   - **Approve & Save**: Marchează ca aprobat (va fi publicat)
   - **Reject**: Respinge știrea (nu va fi publicată)
   - **Edit & Save**: Modifică manual conținutul înainte de aprobare

#### Auto-Aprobare

**Setare**: WordPress Admin → TeInformez → Settings → **Admin Review Period**

**Default**: 7200 secunde (2 ore)

**Comportament**: Știrile care au status `pending_review` mai mult de 2 ore sunt **auto-aprobate și publicate**.

**Dezactivare**: Setează la `0` pentru a dezactiva auto-aprobarea.

---

## ⏰ Cron Jobs și Automatizare

### 1. Fetch News (30 minute)

**Job**: `teinformez_fetch_news`

**Ce face**:
- Citește toate sursele RSS active
- Descarcă ultimele 20 articole per sursă
- Salvează în baza de date cu status `fetched`
- Verifică dacă URL-ul există deja (evită duplicate)

**Rulare manuală**:
```bash
wp cron event run teinformez_fetch_news
```

**Sau din Admin**: TeInformez → News Queue → **"Fetch News Now"**

### 2. Process News (30 minute)

**Job**: `teinformez_process_news`

**Ce face**:
- Selectează toate știrile cu status `fetched`
- Procesează cu OpenAI GPT-4 Turbo:
  - Traduce în română
  - Generează sumar (150 cuvinte)
  - Extrage categorii (max 3)
  - Extrage tag-uri (max 5)
  - Opțional: Generează imagine cu DALL-E
- Actualizează status la `pending_review`

**Rulare manuală**:
```bash
wp cron event run teinformez_process_news
```

**Sau din Admin**: TeInformez → News Queue → **"Process with AI"**

**⚠️ Atenție**: Procesarea consumă API tokens OpenAI. Monitorizează costurile!

### 3. Cleanup Old Items (Zilnic)

**Job**: `teinformez_cleanup_old_items`

**Ce face**:
- Șterge știri `rejected` mai vechi de 30 zile
- Șterge știri `published` mai vechi de 30 zile

**Rulare manuală**:
```bash
wp cron event run teinformez_cleanup_old_items
```

---

## 🛠️ Acțiuni Manuale

### Dashboard Admin

**Locație**: WordPress Admin → TeInformez → News Queue

#### Butoane Disponibile

| Buton | Acțiune | Când să folosești |
|-------|---------|-------------------|
| **Fetch News Now** | Descarcă imediat de la toate sursele | Când vrei știri fresh instant |
| **Process with AI** | Procesează toate știrile `fetched` | După un fetch manual |
| **Publish Approved** | Publică toate știrile `approved` | Când vrei să publici imediat |
| **View/Edit** | Vizualizează/editează știre | Pentru revizuire |
| **Approve** | Aprobă știre | Când conținutul e OK |
| **Reject** | Respinge știre | Când conținutul e irelevant |

#### Filtrare Coadă

Folosește tab-urile pentru filtrare rapidă:

- **All**: Toate știrile
- **Fetched**: Descărcate, neprocessate
- **Processing**: În procesare (afișează progres)
- **Pending Review**: **ATENȚIE NECESARĂ**
- **Approved**: Aprobate, așteaptă publicare
- **Rejected**: Respinse
- **Published**: Live pe site

---

## 📊 Monitorizare și Statistici

### Dashboard Statistici

**Locație**: WordPress Admin → TeInformez → Dashboard

**Metrici afișate**:

| Metric | Semnificație |
|--------|--------------|
| **Total Items** | Total știri în coadă |
| **Pending Review** | Câte așteaptă aprobare (**IMPORTANT**) |
| **Published Today** | Câte au fost publicate astăzi |
| **Processing Errors** | Câte au avut erori la procesare |
| **Last Fetch** | Când a fost ultimul fetch RSS |
| **Last Process** | Când a fost ultima procesare AI |

### Verificare Sănătate

**Indicatori de probleme**:

- ⚠️ **Pending Review > 50**: Revizuire necesară urgent!
- ⚠️ **Processing Errors > 10**: Verifică OpenAI API key
- ⚠️ **Last Fetch > 1h**: Cron job nu rulează
- ⚠️ **Published Today = 0**: Flux blocat

---

## 🐛 Depanare

### Problemă: Cron jobs nu rulează

**Simptome**: Ultimul fetch > 1 oră

**Diagnostic**:
```bash
wp cron test
wp cron event list
```

**Soluții**:
1. **Server cron real** (recomandat):
   ```cron
   */30 * * * * wget -q -O - https://teinformez.eu/wp-cron.php?doing_wp_cron > /dev/null 2>&1
   ```

2. **Rulare manuală**:
   - Click **"Fetch News Now"** în admin
   - Click **"Process with AI"**

3. **Verificare `wp-config.php`**:
   ```php
   define('DISABLE_WP_CRON', false);
   ```

### Problemă: OpenAI API Errors

**Simptome**: Processing Errors > 0

**Diagnostic**:
- Verifică **WordPress Admin → Tools → Error Log**
- Caută: `TeInformez ERROR: OpenAI API failed`

**Soluții**:

1. **API Key Invalid**:
   - Verifică key în Settings
   - Testează pe [platform.openai.com](https://platform.openai.com)

2. **Rate Limit**:
   - Reduce frecvența procesării (ex: 1h în loc de 30min)
   - Upgrade la plan OpenAI mai mare

3. **Credite insuficiente**:
   - Verifică billing: [platform.openai.com/account/billing](https://platform.openai.com/account/billing)

### Problemă: Știri nu apar pe frontend

**Simptome**: `/news` arată pagina goală

**Diagnostic**:
1. Verifică status în admin: Sunt știri `published`?
2. Testează API direct:
   ```bash
   curl https://teinformez.eu/wp-json/teinformez/v1/news
   ```

**Soluții**:

1. **Nicio știre publicată**:
   - Aprobă manual câteva știri din **Pending Review**
   - Click **"Publish Approved"**

2. **CORS Error**:
   - Verifică `class-config.php` → `ALLOWED_ORIGINS`
   - Adaugă domeniul Vercel

3. **API Error 500**:
   - Verifică error log WordPress
   - Verifică că tabelele există în DB

### Problemă: Duplicate news items

**Simptome**: Aceeași știre apare de 2-3 ori

**Cauză**: Verificarea URL-ului nu funcționează

**Diagnostic**:
```sql
SELECT original_url, COUNT(*) as count
FROM wp_teinformez_news_queue
GROUP BY original_url
HAVING count > 1;
```

**Soluție**:
```sql
-- Păstrează doar prima apariție
DELETE t1 FROM wp_teinformez_news_queue t1
INNER JOIN wp_teinformez_news_queue t2
WHERE t1.id > t2.id AND t1.original_url = t2.original_url;
```

---

## 📈 Best Practices

### 1. Revizuire Zilnică

- **Dimineața**: Verifică **Pending Review** (15 min)
- **Seara**: Verifică **Processing Errors** (5 min)

### 2. Monitorizare Costuri OpenAI

- **Target**: Max $10/lună pentru 500 articole
- **Verificare**: [platform.openai.com/usage](https://platform.openai.com/usage)
- **Alertă**: Setează billing limit în OpenAI

### 3. Optimizare Surse

- **Dezactivează** sursele cu conținut de proastă calitate
- **Adaugă** surse românești pentru mai puține traduceri
- **Monitorizează** ce surse generează cei mai mulți rejections

### 4. Backup Regulat

**Lunar**:
```bash
wp db export teinformez_backup_$(date +%Y%m%d).sql
```

---

## 🔗 Resurse Utile

| Resursă | Link |
|---------|------|
| **OpenAI Platform** | https://platform.openai.com |
| **OpenAI Pricing** | https://openai.com/api/pricing |
| **NewsAPI Docs** | https://newsapi.org/docs |
| **WordPress Cron** | https://developer.wordpress.org/plugins/cron/ |
| **WP-CLI Cron** | https://developer.wordpress.org/cli/commands/cron/ |

---

## 📞 Suport

**Probleme tehnice**:
- Verifică mai întâi acest ghid
- Verifică error logs WordPress
- Verifică error logs OpenAI

**Contact dezvoltator**:
- Vezi `PHASE_B_COMPLETE.md` pentru detalii implementare

---

**Versiune document**: 1.0
**Data**: 26 Ianuarie 2026
**Autor**: Claude Code (Anthropic)

🎉 **Succes cu TeInformez!**
