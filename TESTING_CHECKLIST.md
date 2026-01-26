# ✅ TeInformez - Testing Checklist Phase B

**Data**: 26 Ianuarie 2026
**Status**: Pregătit pentru testare

---

## 🧪 Pre-Deployment Testing (Local)

### Frontend Build Verification

✅ **Status**: COMPLET
```bash
cd C:\Projects\TeInformez\frontend
npm run build
```

**Rezultat**:
- ✅ 16/16 pages compiled successfully
- ✅ Zero TypeScript errors
- ✅ Bundle sizes optimizate
- ✅ /news și /news/[id] generate corect

### Backend PHP Syntax

⏳ **Status**: Necesar verificare cu XAMPP/WAMP local

**Comenzi verificare**:
```bash
# Verificare sintaxă PHP (necesită PHP instalat local)
php -l backend/wp-content/plugins/teinformez-core/api/class-news-api.php
php -l backend/wp-content/plugins/teinformez-core/includes/class-news-publisher.php
php -l backend/wp-content/plugins/teinformez-core/includes/class-subscription-manager.php
```

**Alternative**: Deploy și verificare pe server.

### Git Status

✅ **Status**: COMPLET
```bash
cd C:\Projects\TeInformez
git status
```

**Rezultat**:
- ✅ All changes committed
- ✅ Pushed to GitHub (commits 206b57d, ee8b332)
- ✅ Branch master up to date

---

## 🚀 Deployment Testing (Hostico)

### 1. Backend Plugin Upload

**Locație sursă**: `C:\Projects\TeInformez\backend\wp-content\plugins\teinformez-core\`

**Destinație FTP**:
```
Host: ftp.teinformez.eu (sau hostico FTP)
Path: /public_html/wp-content/plugins/teinformez-core/
```

**Fișiere de uploadat** (toate din folder teinformez-core):
```
teinformez-core/
├── admin/
│   ├── views/
│   │   ├── news-queue.php
│   │   ├── settings-page.php
│   │   └── dashboard.php
│   └── class-admin.php
├── api/
│   ├── class-rest-api.php
│   ├── class-auth-api.php
│   ├── class-user-api.php
│   ├── class-subscription-api.php
│   ├── class-category-api.php
│   └── class-news-api.php          ← MODIFICAT (Phase B)
├── includes/
│   ├── class-config.php
│   ├── class-database.php
│   ├── class-activator.php
│   ├── class-user-manager.php
│   ├── class-subscription-manager.php
│   ├── class-news-fetcher.php
│   ├── class-ai-processor.php
│   ├── class-news-publisher.php
│   └── class-news-source-manager.php
├── teinformez-core.php
└── README.md
```

**Metoda**:
- FileZilla / WinSCP
- Upload întreg folder `teinformez-core/`
- Overwrite all files

**Timp estimat**: 5-10 minute (depinde de conexiune)

### 2. WordPress Plugin Activation

**URL**: https://teinformez.eu/wp-admin/plugins.php

**Pași**:
1. Login cu credențiale admin
2. Găsește "TeInformez Core" în listă
3. Click **Activate** (dacă nu este deja activ)
4. Verifică că nu apar erori PHP

**Verificare activare**:
- Mergi la WordPress Admin sidebar
- Ar trebui să vezi meniul "TeInformez" cu submeniuri:
  - Dashboard
  - News Queue
  - Settings

### 3. Configurare OpenAI API Key

**URL**: WordPress Admin → TeInformez → Settings

**Pași**:
1. Găsește câmpul "OpenAI API Key"
2. Introdu cheia din `Master_API_Key` folder
3. Click **Save Settings**

**Verificare**:
```bash
# Test OpenAI connectivity (după configurare)
curl -X POST https://teinformez.eu/wp-admin/admin-ajax.php \
  -d "action=teinformez_test_openai" \
  -d "nonce=..."
```

Sau direct din admin: ar trebui să vezi mesaj "OpenAI API key configured successfully"

### 4. Verificare Database Tables

**phpMyAdmin** sau **MySQL client**:

```sql
-- Verifică că tabelele există
SHOW TABLES LIKE 'wp_teinformez%';

-- Ar trebui să returneze:
-- wp_teinformez_news_queue
-- wp_teinformez_news_sources
-- wp_teinformez_subscriptions
-- wp_teinformez_user_preferences
-- wp_teinformez_auth_tokens
```

**Verificare structură wp_teinformez_news_queue**:
```sql
DESCRIBE wp_teinformez_news_queue;

-- Ar trebui să conțină coloane:
-- id, source_name, original_url, original_title, original_content
-- processed_title, processed_summary, processed_content
-- categories, tags, ai_generated_image_url
-- status, fetched_at, processed_at, reviewed_at, published_at
```

### 5. Verificare Cron Jobs

**WP-CLI** (dacă este disponibil pe server):
```bash
wp cron event list | grep teinformez
```

**Ar trebui să afișeze**:
```
teinformez_fetch_news         2026-01-26 15:30:00   30 minutes
teinformez_process_news       2026-01-26 15:30:00   30 minutes
teinformez_cleanup_old_items  2026-01-27 03:00:00   daily
```

**Alternative** (fără WP-CLI):
- Instalează plugin "WP Crontrol"
- Verifică că job-urile sunt programate

### 6. Test API Endpoints

**Endpoint 1: Categories** (ar trebui să funcționeze deja):
```bash
curl https://teinformez.eu/wp-json/teinformez/v1/categories
```

**Expected response**:
```json
{
  "success": true,
  "data": {
    "categories": {
      "tech": "Tehnologie",
      "finance": "Finanțe",
      ...
    }
  }
}
```

**Endpoint 2: News** (NOU - Phase B):
```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news
```

**Expected response** (dacă nu sunt știri încă):
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

**Endpoint 3: Single News**:
```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news/1
```

**Expected response** (dacă nu există ID 1):
```json
{
  "code": "not_found",
  "message": "News item not found.",
  "data": {"status": 404}
}
```

**Endpoint 4: Personalized Feed** (necesită autentificare):
```bash
curl -H "Authorization: Bearer <token>" \
  https://teinformez.eu/wp-json/teinformez/v1/news/personalized
```

**Expected response** (fără token):
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {"status": 401}
}
```

---

## 📰 News Flow Testing

### Step 1: Manual Fetch News

**Locație**: WordPress Admin → TeInformez → News Queue

**Acțiune**: Click butonul **"Fetch News Now"**

**Verificări**:
- [ ] Browser nu arată erori JavaScript
- [ ] Mesaj success: "Fetching news from X sources..."
- [ ] După 10-20 secunde, refresh pagina
- [ ] Tab "Fetched" ar trebui să arate items (ex: 20-50 știri noi)

**SQL Verification**:
```sql
SELECT COUNT(*) as fetched_count
FROM wp_teinformez_news_queue
WHERE status = 'fetched';

-- Ar trebui să returneze > 0 (ex: 50)
```

**Troubleshooting**:
- Dacă 0 items: Verifică PHP error log
- Dacă eroare "Connection timeout": Verifică firewall server permite outbound HTTP
- Dacă eroare "SSL": Verifică curl/openssl pe server

### Step 2: Manual Process with AI

**Locație**: WordPress Admin → TeInformez → News Queue

**Acțiune**: Click butonul **"Process with AI"**

**Verificări**:
- [ ] Mesaj success: "Processing X items with AI..."
- [ ] Procesare durează ~10-30 secunde (depinde de câte items)
- [ ] După procesare, refresh pagina
- [ ] Tab "Pending Review" ar trebui să arate items procesate

**SQL Verification**:
```sql
SELECT id, original_title, processed_title, status
FROM wp_teinformez_news_queue
WHERE status = 'pending_review'
LIMIT 5;

-- Verifică că processed_title este în română
```

**Verificare calitate AI**:
```sql
SELECT
  original_title,
  processed_title,
  categories,
  tags
FROM wp_teinformez_news_queue
WHERE status = 'pending_review'
LIMIT 1;
```

**Așteptat**:
- `processed_title`: Tradus corect în română
- `categories`: JSON array, ex: `["tech","ai"]`
- `tags`: JSON array, ex: `["openai","gpt-4","artificial-intelligence"]`

**Troubleshooting**:
- Dacă eroare OpenAI: Verifică API key valid
- Dacă "Rate limit": Așteaptă 1 minut și reîncearcă
- Dacă "Insufficient credits": Adaugă credite în OpenAI account

### Step 3: Approve News Items

**Locație**: WordPress Admin → TeInformez → News Queue → Tab "Pending Review"

**Pași pentru fiecare știre**:
1. Click pe titlu pentru a deschide modal "View/Edit"
2. Revizuiește:
   - Titlu tradus corect?
   - Sumar relevant (150 cuvinte)?
   - Conținut complet?
   - Categorii potrivite?
3. Dacă OK: Click **"Approve & Save"**
4. Dacă nu: Editează manual sau click **"Reject"**

**Target**: Aprobă minimum 3-5 știri pentru testare

**SQL Verification**:
```sql
SELECT COUNT(*) as approved_count
FROM wp_teinformez_news_queue
WHERE status = 'approved';

-- Ar trebui să returneze >= 3
```

### Step 4: Publish Approved Items

**Opțiune A: Manual Publish**

**Locație**: WordPress Admin → TeInformez → News Queue

**Acțiune**: Click **"Publish Approved"**

**Verificări**:
- [ ] Mesaj success: "Published X items"
- [ ] Refresh pagina
- [ ] Tab "Published" ar trebui să arate items publicate

**Opțiune B: Auto-Publish** (după 2 ore)

**Așteptare**: 2 ore de la procesare AI

**Verificare**:
```sql
SELECT id, processed_title, published_at
FROM wp_teinformez_news_queue
WHERE status = 'published'
LIMIT 5;
```

**SQL Verification**:
```sql
SELECT COUNT(*) as published_count
FROM wp_teinformez_news_queue
WHERE status = 'published';

-- Ar trebui să returneze >= 3
```

### Step 5: Verify API Returns Published News

**Test după publicare**:
```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news
```

**Expected response**:
```json
{
  "success": true,
  "data": {
    "news": [
      {
        "id": 1,
        "title": "Titlu tradus în română",
        "summary": "Sumar scurt...",
        "content": "<p>Conținut HTML...</p>",
        "image": "https://...",
        "source": "TechCrunch",
        "categories": ["tech", "ai"],
        "tags": ["openai", "gpt-4"],
        "published_at": "2026-01-26 14:30:00",
        "original_url": "https://techcrunch.com/...",
        "language": "ro"
      },
      ...
    ],
    "total": 5,
    "page": 1,
    "per_page": 20,
    "total_pages": 1
  }
}
```

**Verificări**:
- [ ] Array `news` nu este gol
- [ ] Fiecare item are toate câmpurile
- [ ] `title` este în română
- [ ] `categories` este array, nu string
- [ ] `published_at` este dată validă

---

## 🎨 Frontend Testing

### Step 1: Vercel Auto-Deploy

**Verificare**:
1. Mergi la: https://vercel.com/alex-danciulescus-projects/teinformez/deployments
2. Ar trebui să vezi deployment pentru commit `206b57d` sau `ee8b332`
3. Status: ✅ Ready

**Dacă nu s-a deploiat automat**:
```bash
cd C:\Projects\TeInformez\frontend
vercel --prod
```

### Step 2: Test News List Page

**URL**: https://teinformez.vercel.app/news

**Verificări vizuale**:
- [ ] Pagina se încarcă fără erori
- [ ] Header cu titlu "Știri" și logo
- [ ] Dacă există știri: Grid cu cards (1/2/3 coloane responsive)
- [ ] Fiecare card afișează: imagine, titlu, sumar, dată, sursă
- [ ] Categorii afișate ca badges colorate
- [ ] Dacă nu există știri: Mesaj "Nu sunt știri disponibile"

**Verificări tehnice** (Browser DevTools):
```javascript
// Console
console.log('Check for errors');

// Network tab
// Verifică request la: /wp-json/teinformez/v1/news
// Status: 200 OK
// Response: JSON cu news array
```

**Test interacțiune**:
- [ ] Click pe card → redirect la /news/[id]
- [ ] Click pe link "Dashboard" → redirect la /dashboard
- [ ] Pagination (dacă > 20 știri): butoane Anterior/Următoarea

### Step 3: Test Single News Page

**URL**: https://teinformez.vercel.app/news/1 (sau alt ID valid)

**Verificări vizuale**:
- [ ] Pagina se încarcă fără erori
- [ ] Titlu mare (text-4xl)
- [ ] Imagine full-width (dacă există)
- [ ] Sumar destacat (bg gri cu border stânga)
- [ ] Conținut HTML renderizat corect
- [ ] Metadata: dată, sursă
- [ ] Categorii și tag-uri complete
- [ ] Buton "Înapoi la știri"
- [ ] Link "Sursă originală" (target="_blank")
- [ ] Buton "Distribuie" (doar pe mobile cu Web Share API)

**Verificări tehnice**:
```javascript
// Network tab
// Request la: /wp-json/teinformez/v1/news/1
// Status: 200 OK
// Response: JSON cu news object
```

**Test interacțiune**:
- [ ] Click "Înapoi la știri" → redirect la /news
- [ ] Click "Sursă originală" → deschide tab nou cu articol original
- [ ] Click "Distribuie" (mobile) → deschide share sheet

**Test 404**:
- URL: https://teinformez.vercel.app/news/999999
- Ar trebui să afișeze: "Știre negăsită"

### Step 4: Test Dashboard Personalized Feed

**Prerequisite**: Utilizator autentificat

**URL**: https://teinformez.vercel.app/dashboard

**Verificări**:
- [ ] Secțiune "Știrile tale personalizate" vizibilă
- [ ] Grid cu top 6 știri (sau mai puține)
- [ ] Fiecare card: imagine, titlu, sumar, sursă
- [ ] Click pe card → redirect la /news/[id]
- [ ] Link "Vezi toate →" → redirect la /news

**Dacă user nu are abonamente**:
- [ ] Afișează mesaj: "Nu sunt știri personalizate disponibile"
- [ ] Sugestie: "Adaugă mai multe abonamente..."

**Verificări tehnice**:
```javascript
// Network tab
// Request la: /wp-json/teinformez/v1/news/personalized
// Headers: Authorization: Bearer <token>
// Status: 200 OK
// Response: JSON cu news array filtrat după categorii subscrise
```

### Step 5: Test CORS

**Verificare în Browser Console**:
```javascript
fetch('https://teinformez.eu/wp-json/teinformez/v1/news')
  .then(r => r.json())
  .then(data => console.log('CORS OK:', data))
  .catch(err => console.error('CORS Error:', err));
```

**Așteptat**: "CORS OK: {success: true, data: {...}}"

**Dacă eroare CORS**:
```
Access to fetch at 'https://teinformez.eu/...' from origin 'https://teinformez.vercel.app'
has been blocked by CORS policy
```

**Fix**: Verifică în `class-config.php` → `ALLOWED_ORIGINS`:
```php
const ALLOWED_ORIGINS = [
    'http://localhost:3000',
    'https://teinformez.eu',
    'https://teinformez.vercel.app',
    'https://*.vercel.app',  // Wildcard pentru preview deployments
];
```

---

## 🐛 Troubleshooting

### Problem: API Returns Empty Array

**Simptome**: `/news` endpoint returnează `"news": []`

**Cauze posibile**:
1. Nicio știre publicată încă
2. Filtru incorect (ex: status != 'published')

**Diagnostic**:
```sql
SELECT status, COUNT(*) as count
FROM wp_teinformez_news_queue
GROUP BY status;
```

**Soluții**:
1. Aprobă și publică manual 3-5 știri din admin
2. Verifică SQL query în `class-news-api.php` linia 50

### Problem: Frontend Shows Loading Forever

**Simptome**: Spinner de loading infinit pe /news

**Cauze posibile**:
1. API request failed (CORS, 500, timeout)
2. Frontend error în catch block

**Diagnostic**:
- Browser DevTools → Network tab
- Verifică request la `/wp-json/teinformez/v1/news`
- Status code? Response body?

**Soluții**:
1. Dacă CORS error: Fix în backend `ALLOWED_ORIGINS`
2. Dacă 500 error: Verifică PHP error log pe server
3. Dacă timeout: Crește timeout în `api.ts` (linia 40)

### Problem: News Detail Shows 404

**Simptome**: Click pe știre → "Știre negăsită"

**Cauze posibile**:
1. ID invalid
2. Știre nu are status='published'
3. API endpoint returneză 404

**Diagnostic**:
```bash
curl https://teinformez.eu/wp-json/teinformez/v1/news/1
```

```sql
SELECT id, status FROM wp_teinformez_news_queue WHERE id = 1;
```

**Soluții**:
1. Verifică că știrea are status='published'
2. Verifică în `class-news-api.php` linia 105 (filter by published)

### Problem: Personalized Feed Empty

**Simptome**: Dashboard afișează "Nu sunt știri personalizate disponibile"

**Cauze posibile**:
1. User nu are subscriptions
2. Nicio știre match-uiește categoriile subscrise
3. API authentication failed

**Diagnostic**:
```bash
# Verifică cu token valid
curl -H "Authorization: Bearer <token>" \
  https://teinformez.eu/wp-json/teinformez/v1/news/personalized
```

```sql
SELECT category_slug FROM wp_teinformez_subscriptions WHERE user_id = 1;
```

**Soluții**:
1. Adaugă subscriptions pentru user în `/dashboard/subscriptions`
2. Verifică că există știri publicate în categoriile subscrise
3. Verifică token valid în cookies

---

## ✅ Success Criteria

Phase B testing este considerat success când:

### Backend (API):
- [ ] ✅ API `/news` returnează array de știri publicate
- [ ] ✅ API `/news/1` returnează detalii știre individuală
- [ ] ✅ API `/news/personalized` necesită autentificare (401 fără token)
- [ ] ✅ API `/news/personalized` filtrează după subscriptions user

### Frontend:
- [ ] ✅ Pagina `/news` afișează listă știri cu paginare
- [ ] ✅ Pagina `/news/[id]` afișează detalii complete știre
- [ ] ✅ Dashboard afișează top 6 știri personalizate
- [ ] ✅ Click pe știre redirect corect la detalii
- [ ] ✅ Zero erori JavaScript în console
- [ ] ✅ CORS funcționează corect (Vercel ↔ WordPress)

### News Flow:
- [ ] ✅ Fetch manual din admin aduce 20-50 știri (status='fetched')
- [ ] ✅ Process AI traduce și categorisează corect (status='pending_review')
- [ ] ✅ Approve manual marchează știri ca aprobate (status='approved')
- [ ] ✅ Publish manual sau auto-publish face știrile live (status='published')
- [ ] ✅ Știrile publicate apar pe frontend în < 1 minut

### Integration:
- [ ] ✅ End-to-end: RSS → AI → Approve → Publish → Frontend (< 5 minute manual)
- [ ] ✅ Cron jobs programate corect (fetch + process la 30 min)
- [ ] ✅ OpenAI API funcționează (traducere + categorii corecte)

---

## 📊 Testing Timeline

| Etapă | Timp estimat | Prioritate |
|-------|--------------|------------|
| **Backend upload (FTP)** | 10 min | HIGH |
| **Plugin activation** | 2 min | HIGH |
| **API key configuration** | 3 min | HIGH |
| **Database verification** | 5 min | MEDIUM |
| **Manual fetch test** | 5 min | HIGH |
| **Manual AI process test** | 10 min | HIGH |
| **Approve 3-5 items** | 10 min | HIGH |
| **Publish + verify API** | 5 min | HIGH |
| **Frontend /news test** | 10 min | HIGH |
| **Frontend /news/[id] test** | 5 min | HIGH |
| **Dashboard feed test** | 5 min | MEDIUM |
| **CORS verification** | 3 min | HIGH |
| **Troubleshooting (buffer)** | 20 min | - |
| **TOTAL** | **~90 min** | - |

---

**Status**: 📋 CHECKLIST PREGĂTIT
**Next**: Deploy backend pe Hostico și urmărește acest checklist

**Autor**: Claude Code
**Data**: 26 Ianuarie 2026
