# 🚀 TeInformez - Backend Deployment pe Hostico

**Data**: 26 Ianuarie 2026
**Target**: teinformez.eu (WordPress pe Hostico)
**Status**: Ghid pas-cu-pas deployment

---

## 📋 Prerequisite

### Informații Necesare

- [x] **FTP Credentials**: Username, Password, Host
- [x] **WordPress Admin**: URL, Username, Password
- [x] **OpenAI API Key**: Din folder Master_API_Key
- [ ] **Hostico cPanel Access** (opțional, pentru SSH/WP-CLI)

### Software Necesar

**Opțiune A: GUI FTP Client** (recomandat pentru Windows)
- [FileZilla](https://filezilla-project.org/download.php?type=client) - GRATUIT
- [WinSCP](https://winscp.net/eng/download.php) - GRATUIT

**Opțiune B: Command Line**
- Git Bash (deja instalat)
- `ftp` sau `sftp` command

---

## 🔐 Pas 0: Obține Credențiale FTP

### Via Hostico cPanel

1. Login la: **https://panel.hostico.ro** (sau linkul primit la înregistrare)
2. Găsește secțiunea **"FTP Accounts"** sau **"Conturi FTP"**
3. Notează:
   - **FTP Server**: `ftp.teinformez.eu` sau IP
   - **Username**: Probabil `teinformez@teinformez.eu` sau similar
   - **Password**: Parola setată la creare cont
   - **Port**: 21 (standard FTP) sau 22 (SFTP)

### Via Email Hostico

Caută în inbox email-ul de welcome de la Hostico cu subiect:
- "Bun venit la Hostico" sau
- "Detalii cont hosting"

Ar trebui să conțină:
```
FTP Host: ftp.teinformez.eu
FTP Username: xxxxx
FTP Password: xxxxx
```

---

## 📂 Pas 1: Pregătire Fișiere pentru Upload

### Ce să uploadezi

**DOAR** folder-ul plugin-ului, NU întreg backend-ul:

```
Sursă locală:
C:\Projects\TeInformez\backend\wp-content\plugins\teinformez-core\

Destinație server:
/public_html/wp-content/plugins/teinformez-core/
```

### Verificare Pre-Upload

**Check 1**: Folder-ul există local
```bash
ls -la "C:\Projects\TeInformez\backend\wp-content\plugins\teinformez-core\"
```

**Ar trebui să vezi**:
```
admin/
api/
includes/
teinformez-core.php
README.md
```

**Check 2**: Dimensiune folder
```bash
du -sh "C:\Projects\TeInformez\backend\wp-content\plugins\teinformez-core\"
```

**Estimare**: ~500 KB - 2 MB (depinde de număr fișiere)

---

## 🌐 Pas 2A: Upload via FileZilla (GUI)

### Instalare FileZilla

1. Download: https://filezilla-project.org/download.php?type=client
2. Instalează (Next, Next, Finish)
3. Deschide FileZilla

### Conectare la Server

**Site Manager** (Ctrl+S sau File → Site Manager):

1. Click **"New Site"**
2. Setări:
   ```
   Protocol: FTP - File Transfer Protocol
   Host: ftp.teinformez.eu  (sau IP-ul primit)
   Port: 21
   Encryption: Use explicit FTP over TLS if available
   Logon Type: Normal
   User: [username FTP]
   Password: [parola FTP]
   ```
3. Click **"Connect"**

### Verificare Conectare Success

După conectare, în panoul din dreapta (Remote site) ar trebui să vezi:
```
/public_html/
├── wp-admin/
├── wp-content/
│   ├── plugins/
│   ├── themes/
│   └── uploads/
├── wp-includes/
├── index.php
└── wp-config.php
```

### Navigare la Destinație

**Panel stânga** (Local site):
1. Navighează la: `C:\Projects\TeInformez\backend\wp-content\plugins\`
2. Găsește folder-ul `teinformez-core`

**Panel dreapta** (Remote site):
1. Navighează la: `/public_html/wp-content/plugins/`

### Upload Plugin

**Metodă Drag & Drop**:
1. Din panel stânga: Selectează folder-ul `teinformez-core`
2. Drag în panel dreapta la locația `/public_html/wp-content/plugins/`
3. Confirmă overwrite dacă folder-ul există deja

**Metodă Right-Click**:
1. Right-click pe `teinformez-core` (panel stânga)
2. Click **"Upload"**

### Verificare Upload Success

**În FileZilla**:
- Check "Transfer queue" tab jos: Ar trebui să fie gol (toate fișiere transferate)
- Check "Successful transfers" tab: Ar trebui să vezi ~50-100 fișiere

**În panel dreapta** (Remote site):
```
/public_html/wp-content/plugins/teinformez-core/
├── admin/           ✅
├── api/             ✅
├── includes/        ✅
├── teinformez-core.php  ✅
└── README.md        ✅
```

**Timp estimat**: 2-5 minute (depinde de conexiune internet)

---

## 🌐 Pas 2B: Upload via WinSCP (GUI Alternative)

### Instalare WinSCP

1. Download: https://winscp.net/eng/download.php
2. Instalează (Next, Next, Finish)
3. Deschide WinSCP

### Conectare

**Login Dialog**:
```
File protocol: FTP sau SFTP
Host name: ftp.teinformez.eu
Port number: 21 (FTP) sau 22 (SFTP)
User name: [username]
Password: [parola]
```

Click **"Login"**

### Upload

1. Panel stânga: Navighează la `C:\Projects\TeInformez\backend\wp-content\plugins\`
2. Panel dreapta: Navighează la `/public_html/wp-content/plugins/`
3. Drag folder `teinformez-core` din stânga în dreapta
4. Confirmă overwrite

---

## 🌐 Pas 2C: Upload via Command Line (Advanced)

### SFTP Command Line

```bash
# Conectare
sftp username@ftp.teinformez.eu

# Navighează la plugins folder
cd /public_html/wp-content/plugins/

# Upload întreg folder recursiv
put -r C:/Projects/TeInformez/backend/wp-content/plugins/teinformez-core

# Verificare
ls -la

# Ieșire
bye
```

### FTP Command Line (Windows)

```cmd
ftp ftp.teinformez.eu
# Enter username
# Enter password

cd /public_html/wp-content/plugins/
# Nu suportă upload folder recursiv - folosește FileZilla
```

---

## ✅ Pas 3: Verificare Upload Success

### Via FTP Client

**Check**: Există folder `/public_html/wp-content/plugins/teinformez-core/`

**Verifică fișiere cheie**:
```
teinformez-core/
├── teinformez-core.php  (main plugin file)
├── api/
│   └── class-news-api.php  (MODIFICAT - Phase B)
├── includes/
│   ├── class-news-fetcher.php
│   ├── class-ai-processor.php
│   └── class-news-publisher.php
└── admin/
    └── views/
        └── news-queue.php
```

### Via WordPress Admin

1. Login: **https://teinformez.eu/wp-admin**
2. Mergi la: **Plugins** → **Installed Plugins**
3. Caută în listă: **"TeInformez Core"**

**Dacă NU apare**:
- Problem: Upload incomplet sau permisiuni greșite
- Fix: Re-upload via FTP, verifică permisiuni folder (755)

**Dacă apare cu WARNING/ERROR**:
- Problem: Eroare sintaxă PHP în fișiere
- Fix: Verifică PHP error log, verifică versiune PHP (min 7.4)

**Dacă apare OK**:
- ✅ Status: Plugin detectat corect
- Continuă la Pas 4

---

## 🔌 Pas 4: Activare Plugin

### Via WordPress Admin

**Locație**: https://teinformez.eu/wp-admin/plugins.php

**Pași**:
1. Găsește în listă: **"TeInformez Core"**
2. Dacă status = "Inactive": Click **"Activate"**
3. Dacă status = "Active": ✅ Deja activat, continuă

### Verificare Activare Success

**Check 1: Meniu Admin Apărut**

În sidebar WordPress Admin ar trebui să vezi meniu nou:
```
📊 Dashboard
📰 Posts
📄 Pages
...
📰 TeInformez  ← NOU!
  ├── Dashboard
  ├── News Queue
  └── Settings
```

**Check 2: Database Tables Created**

Via phpMyAdmin sau MySQL client:
```sql
SHOW TABLES LIKE 'wp_teinformez%';
```

**Ar trebui să returneze 5 tabele**:
```
wp_teinformez_auth_tokens
wp_teinformez_news_queue         ← IMPORTANT pentru Phase B
wp_teinformez_news_sources
wp_teinformez_subscriptions
wp_teinformez_user_preferences
```

**Check 3: Nicio Eroare PHP**

- Top of page: Nu ar trebui să vezi PHP warnings/errors
- Dacă vezi erori: Notează mesajul și verifică PHP error log

### Troubleshooting Activation

**Problem: "Plugin could not be activated"**

**Cauză 1: Versiune PHP prea veche**
```
Soluție: Upgrade PHP la 7.4+ în cPanel:
cPanel → Software → Select PHP Version → PHP 7.4 sau 8.0
```

**Cauză 2: Eroare sintaxă PHP**
```
Verifică: /wp-content/debug.log sau PHP error log
Caută: "Parse error" sau "Fatal error"
```

**Cauză 3: Conflict cu alt plugin**
```
Soluție: Dezactivează temporar alte plugins, activează TeInformez, reactivează plugins
```

---

## 🔑 Pas 5: Configurare OpenAI API Key

### Găsește API Key Local

**Locație**: Folder `Master_API_Key` (undeva în documentele tale)

**Format**: `sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

**⚠️ IMPORTANT**: NU folosești key-ul din `.env.local` (acela a fost revocat pentru securitate)

### Setare în WordPress

**URL**: https://teinformez.eu/wp-admin/admin.php?page=teinformez-settings

**Pași**:
1. Login WordPress Admin
2. Sidebar: Click **TeInformez** → **Settings**
3. Găsește câmpul: **"OpenAI API Key"**
4. Introdu key-ul: `sk-proj-...`
5. Scroll jos: Click **"Save Settings"**
6. Ar trebui să vezi mesaj: ✅ "Settings saved successfully"

### Verificare Key Valid

**Test manual** (opțional):

```bash
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer sk-proj-xxxxx"
```

**Response așteptat**:
```json
{
  "data": [
    {"id": "gpt-4-turbo", ...},
    {"id": "gpt-3.5-turbo", ...}
  ]
}
```

**Dacă eroare "invalid_api_key"**: Key-ul este incorect sau revocat

---

## ⚙️ Pas 6: Verificare Cron Jobs

### Via WP-CLI (dacă disponibil)

```bash
ssh username@teinformez.eu
wp cron event list | grep teinformez
```

**Output așteptat**:
```
teinformez_fetch_news         2026-01-26 16:00:00   30 minutes
teinformez_process_news       2026-01-26 16:00:00   30 minutes
teinformez_cleanup_old_items  2026-01-27 03:00:00   daily
```

### Via Plugin "WP Crontrol" (recomandat)

**Instalare**:
1. WordPress Admin → Plugins → Add New
2. Search: "WP Crontrol"
3. Install + Activate

**Verificare**:
1. Sidebar: Tools → Cron Events
2. Caută în listă: `teinformez_fetch_news`, `teinformez_process_news`
3. Ar trebui să vezi:
   - Hook name: `teinformez_fetch_news`
   - Next run: Data și ora viitoare
   - Recurrence: "Every 30 minutes"

### Setup Real Cron (Opțional - Recomandat)

**Problem cu WordPress Cron**: Rulează doar când cineva vizitează site-ul

**Soluție**: Server cron real

**Via cPanel → Cron Jobs**:
1. cPanel → Advanced → Cron Jobs
2. Add New Cron Job:
   ```
   Frequency: */30 * * * * (Every 30 minutes)
   Command: wget -q -O - https://teinformez.eu/wp-cron.php?doing_wp_cron > /dev/null 2>&1
   ```
3. Save

**Dezactivează WordPress Cron** în `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

---

## 🧪 Pas 7: Test API Endpoints

### Test 1: Categories Endpoint

```bash
curl https://teinformez.eu/wp-json/teinformez/v1/categories
```

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "categories": {
      "tech": "Tehnologie",
      "finance": "Finanțe",
      "auto": "Auto",
      "entertainment": "Divertisment",
      "sports": "Sport",
      "science": "Știință",
      "politics": "Politică",
      "business": "Business"
    }
  }
}
```

**Dacă eroare 404**: REST API disabled sau permalink settings incorecte
**Fix**: Settings → Permalinks → Save Changes (re-flush rewrite rules)

### Test 2: News Endpoint (NOU - Phase B)

```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news
```

**Expected Response** (dacă nu sunt știri încă):
```json
{
  "success": true,
  "data": {
    "news": [],
    "total": 0,
    "page": 1,
    "per_page": 20,
    "total_pages": 0
  }
}
```

**Dacă eroare 500**: PHP error în `class-news-api.php`
**Fix**: Check PHP error log, verifică sintaxă

### Test 3: Single News Endpoint

```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news/1
```

**Expected Response** (dacă nu există ID 1):
```json
{
  "code": "not_found",
  "message": "News item not found.",
  "data": {"status": 404}
}
```

✅ **Asta e corect!** Endpoint-ul funcționează, doar nu există știri

### Test 4: Personalized Feed (necesită auth)

```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news/personalized
```

**Expected Response** (fără token):
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {"status": 401}
}
```

✅ **Perfect!** Autentificarea funcționează corect

---

## 📰 Pas 8: Test Manual News Fetch

### Via WordPress Admin

**URL**: https://teinformez.eu/wp-admin/admin.php?page=teinformez-news-queue

**Acțiuni**:
1. Click butonul **"Fetch News Now"** (top-right)
2. Ar trebui să vezi spinner + mesaj "Fetching..."
3. După 10-30 secunde: Page refresh automat
4. Tab "Fetched" ar trebui să arate 20-50 știri noi

### Verificare în Database

**Via phpMyAdmin**:
```sql
SELECT COUNT(*) as total, status
FROM wp_teinformez_news_queue
GROUP BY status;
```

**Expected Result**:
```
total | status
------|--------
50    | fetched
```

### Troubleshooting Fetch

**Problem: 0 items fetched**

**Diagnostic**:
```sql
-- Check dacă există surse active
SELECT * FROM wp_teinformez_news_sources WHERE is_active = 1;
```

**Dacă 0 results**:
```sql
-- Sursele nu au fost create la activare
-- Check PHP error log pentru erori la activation
```

**Cauze posibile**:
1. Firewall blochează outbound HTTP requests
2. cURL disabled pe server
3. SSL certificate issues

**Fix**:
1. Contact Hostico support pentru whitelist domenii RSS
2. Verifică `php.ini`: `allow_url_fopen = On`, `curl` enabled

---

## 🤖 Pas 9: Test AI Processing

### Via WordPress Admin

**Prerequisite**: Minimum 1 știre cu status='fetched' (din Pas 8)

**URL**: https://teinformez.eu/wp-admin/admin.php?page=teinformez-news-queue

**Acțiuni**:
1. Click butonul **"Process with AI"** (top-right)
2. Ar trebui să vezi spinner + mesaj "Processing..."
3. Procesare durează ~10-60 secunde (depinde de câte items)
4. Page refresh
5. Tab "Pending Review" ar trebui să arate items procesate

### Verificare Calitate AI

**Via phpMyAdmin**:
```sql
SELECT
  id,
  original_title,
  processed_title,
  categories,
  tags,
  status
FROM wp_teinformez_news_queue
WHERE status = 'pending_review'
LIMIT 3;
```

**Check**:
- `processed_title`: Tradus în română?
- `categories`: JSON array valabil? Ex: `["tech","ai"]`
- `tags`: JSON array valabil? Ex: `["openai","gpt-4"]`

### Troubleshooting AI Processing

**Problem: Items rămân "fetched", nu devin "pending_review"**

**Diagnostic PHP Error Log**:
```
Caută: "TeInformez ERROR: OpenAI API failed"
```

**Cauze posibile**:
1. **Invalid API key**: Re-verifică key în Settings
2. **Rate limit exceeded**: Așteaptă 1 minut și reîncearcă
3. **Insufficient credits**: Verifică billing pe https://platform.openai.com/account/billing
4. **Network timeout**: Crește timeout în `class-ai-processor.php`

**Fix pentru rate limit**:
```php
// În class-ai-processor.php, add sleep între requests
sleep(2); // 2 secunde pauză între articole
```

---

## ✅ Pas 10: Aprobare și Publicare

### Approve Manual

**URL**: https://teinformez.eu/wp-admin/admin.php?page=teinformez-news-queue

**Pași**:
1. Tab **"Pending Review"**
2. Click pe titlu unei știri → modal "View/Edit"
3. Revizuiește conținut
4. Click **"Approve & Save"**
5. Repetă pentru 3-5 știri

### Publish Manual

**Opțiune A**: Buton "Publish Approved"
1. Click butonul **"Publish Approved"** (top-right)
2. Toate știrile approved devin published instant

**Opțiune B**: Auto-publish (după 2 ore)
1. Așteaptă 2 ore de la procesare AI
2. Cron job va auto-aproba și publica

### Verificare Published

**SQL**:
```sql
SELECT id, processed_title, published_at
FROM wp_teinformez_news_queue
WHERE status = 'published'
LIMIT 5;
```

**API Test**:
```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news
```

**Should return**:
```json
{
  "success": true,
  "data": {
    "news": [
      {
        "id": 1,
        "title": "Titlu în română",
        ...
      }
    ],
    "total": 5
  }
}
```

---

## 🔐 Pas 11: Verificare CORS

### Test CORS din Browser

**Deschide**: https://teinformez.vercel.app

**Browser Console (F12)**:
```javascript
fetch('https://teinformez.eu/wp-json/teinformez/v1/news')
  .then(r => r.json())
  .then(data => console.log('✅ CORS OK:', data))
  .catch(err => console.error('❌ CORS Error:', err));
```

**Expected**: `✅ CORS OK: {success: true, ...}`

### Dacă CORS Error

**Error message**:
```
Access to fetch at 'https://teinformez.eu/...' from origin 'https://teinformez.vercel.app'
has been blocked by CORS policy: No 'Access-Control-Allow-Origin' header is present
```

**Fix în WordPress**:

**Fișier**: `backend/wp-content/plugins/teinformez-core/includes/class-config.php`

**Linia ~43**: Verifică `ALLOWED_ORIGINS`:
```php
const ALLOWED_ORIGINS = [
    'http://localhost:3000',
    'https://teinformez.eu',
    'https://teinformez.vercel.app',   // ← Adaugă dacă lipsește
    'https://*.vercel.app',            // ← Wildcard pentru previews
];
```

**După modificare**:
1. Re-upload `class-config.php` via FTP
2. Clear cache WordPress (dacă există plugin cache)
3. Test din nou

---

## 📊 Deployment Success Checklist

### Backend:
- [ ] Plugin uploadat via FTP la `/public_html/wp-content/plugins/teinformez-core/`
- [ ] Plugin activat în WordPress Admin
- [ ] Meniu "TeInformez" apare în sidebar
- [ ] 5 tabele database create (`wp_teinformez_*`)
- [ ] OpenAI API key configurat în Settings
- [ ] Cron jobs programate (via WP Crontrol sau WP-CLI)
- [ ] API `/categories` returnează 200 OK
- [ ] API `/news` returnează 200 OK (chiar dacă array gol)

### News Flow:
- [ ] "Fetch News Now" aduce 20-50 știri (status='fetched')
- [ ] "Process with AI" traduce știri (status='pending_review')
- [ ] Approve manual funcționează (status='approved')
- [ ] Publish manual funcționează (status='published')
- [ ] API `/news` returnează știrile publicate

### Integration:
- [ ] CORS funcționează (test din browser Vercel site)
- [ ] Frontend `/news` afișează știri (după publicare)
- [ ] Frontend `/news/[id]` afișează detalii știre
- [ ] Dashboard feed personalizat funcționează (cu auth)

---

## ⏱️ Timeline Estimat

| Pas | Acțiune | Timp | Cumulativ |
|-----|---------|------|-----------|
| 0 | Obține credențiale FTP | 5 min | 5 min |
| 1 | Pregătire fișiere | 2 min | 7 min |
| 2 | Upload via FileZilla | 5 min | 12 min |
| 3 | Verificare upload | 3 min | 15 min |
| 4 | Activare plugin | 3 min | 18 min |
| 5 | Configurare OpenAI key | 3 min | 21 min |
| 6 | Verificare cron jobs | 5 min | 26 min |
| 7 | Test API endpoints | 5 min | 31 min |
| 8 | Test manual fetch | 5 min | 36 min |
| 9 | Test AI processing | 10 min | 46 min |
| 10 | Approve + publish | 10 min | 56 min |
| 11 | Verificare CORS | 5 min | 61 min |
| **Buffer troubleshooting** | | 15 min | **76 min** |

**Total estimat**: ~75 minute (~1h 15min)

---

## 🆘 Support și Troubleshooting

### Probleme Comune

| Problem | Cauză | Soluție |
|---------|-------|---------|
| Plugin nu apare în listă | Upload incomplet | Re-upload via FTP |
| "Could not activate" | PHP < 7.4 | Upgrade PHP în cPanel |
| Database tables missing | Activation hook failed | Dezactivează + reactivează plugin |
| CORS error | Origin not whitelisted | Adaugă în `ALLOWED_ORIGINS` |
| OpenAI "Invalid API key" | Key incorect/revocat | Verifică key valid pe OpenAI platform |
| Fetch 0 items | Firewall/cURL disabled | Contact Hostico support |
| AI processing fails | Rate limit/credits | Verifică OpenAI billing |

### Link-uri Utile

| Resursă | URL |
|---------|-----|
| **Hostico cPanel** | https://panel.hostico.ro |
| **WordPress Admin** | https://teinformez.eu/wp-admin |
| **OpenAI Platform** | https://platform.openai.com |
| **FileZilla Download** | https://filezilla-project.org |
| **WP Crontrol Plugin** | https://wordpress.org/plugins/wp-crontrol/ |

---

**Status**: 📘 GHID COMPLET
**Next**: Urmează acest ghid pas-cu-pas pentru deployment

**Autor**: Claude Code
**Data**: 26 Ianuarie 2026
**Timp estimat total**: ~75 minute
