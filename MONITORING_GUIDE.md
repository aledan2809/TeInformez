# 📊 TeInformez - Monitoring & Maintenance Guide

**Data**: 26 Ianuarie 2026
**Scop**: Monitorizare 24/7 și mentenanță Phase B
**Target**: Primele 24-48 ore + Long-term

---

## 🎯 Quick Health Check (5 minute)

Rulează acest check zilnic pentru status rapid:

### ✅ Frontend Status

```bash
# Test homepage
curl -I https://teinformez.vercel.app
# Expected: HTTP/2 200

# Test news page
curl -I https://teinformez.vercel.app/news
# Expected: HTTP/2 200
```

### ✅ Backend API Status

```bash
# Test WordPress API
curl -s https://teinformez.eu/wp-json/teinformez/v1/news | jq '.success'
# Expected: true

# Count published news
curl -s https://teinformez.eu/wp-json/teinformez/v1/news | jq '.data.total'
# Expected: > 0 (după primele 24h)
```

### ✅ Database Health

```sql
-- Via phpMyAdmin sau MySQL client
SELECT status, COUNT(*) as count
FROM wp_teinformez_news_queue
GROUP BY status;
```

**Expected output** (după 24h):
```
status          | count
----------------|-------
fetched         | 10-30   (waiting for AI)
processing      | 0-5     (actively processing)
pending_review  | 5-20    (waiting approval)
approved        | 0-10    (waiting publish)
published       | 50-100  (live on frontend)
rejected        | 0-5     (rejected by admin)
```

**🚨 Red Flags**:
- `fetched > 100`: AI processing stuck
- `pending_review > 50`: Admin review backlog
- `published = 0` (după 24h): Publish workflow blocat

---

## 📈 Dashboard Metrics

### WordPress Admin Dashboard

**URL**: https://teinformez.eu/wp-admin/admin.php?page=teinformez-dashboard

**Key Metrics** (refresh zilnic):

| Metric | Target (După 24h) | Target (După 7 zile) |
|--------|-------------------|----------------------|
| **Total Items in Queue** | 100-200 | 500-1000 |
| **Pending Review** | 10-30 | 20-50 |
| **Published Today** | 20-50 | 30-80 |
| **Processing Errors** | 0-2 | 0-5 |
| **Last Fetch** | < 30 min ago | < 30 min ago |
| **Last AI Process** | < 30 min ago | < 30 min ago |

### Vercel Analytics

**URL**: https://vercel.com/alex-danciulescus-projects/teinformez/analytics

**Key Metrics**:
- **Page views /news**: Track growth
- **Unique visitors**: Engagement
- **Bounce rate**: Quality check (target: < 60%)
- **Avg session duration**: Engagement (target: > 2 min)

### OpenAI Usage

**URL**: https://platform.openai.com/usage

**Monitorizare Cost**:
- **Target**: < $10/lună (500 articole)
- **Alert**: Dacă > $15/lună, reduce frecvența fetch sau număr surse

**Calcul estimativ**:
```
Cost per articol: $0.04-0.08
Articole/zi: 30
Cost/lună: 30 × 30 × $0.06 = $54

⚠️ ATENȚIE: Dacă costul e prea mare, reduce la 10-15 articole/zi
```

---

## 🔍 Monitoring Checklist - Primele 24h

### Ora 1 (După deployment)

- [ ] **Backend plugin activ**: WordPress Admin → Plugins → "TeInformez Core" = Active
- [ ] **API responding**: `curl https://teinformez.eu/wp-json/teinformez/v1/news` returnează 200
- [ ] **CORS working**: Test din browser Console pe teinformez.vercel.app
- [ ] **Cron jobs scheduled**: Via WP Crontrol sau WP-CLI

### Ora 2-3

- [ ] **Primul fetch complet**: Tab "Fetched" în News Queue arată 20-50 items
- [ ] **AI processing started**: Tab "Processing" arată activitate
- [ ] **Items procesate**: Tab "Pending Review" arată minimum 5 items
- [ ] **Zero erori PHP**: Check `/wp-content/debug.log`

### Ora 4-6

- [ ] **Primele știri aprobate**: Manual approve 3-5 items în "Pending Review"
- [ ] **Primele știri publicate**: API `/news` returnează array non-gol
- [ ] **Frontend /news functional**: https://teinformez.vercel.app/news afișează știri
- [ ] **Frontend /news/[id] functional**: Click pe știre → detalii complete

### Ora 12

- [ ] **Cron fetch rulează automat**: Verifică timestamp "Last Fetch" < 30 min
- [ ] **Cron process rulează automat**: Verifică "Last AI Process" < 30 min
- [ ] **Queue growth normal**: Total items > 50 (dacă fetch-uri reușite)
- [ ] **Published count crescut**: Minimum 10-20 știri publicate

### Ora 24

- [ ] **Auto-publish funcționează**: Items pending > 2h sunt auto-approved
- [ ] **Published > 50**: Site-ul are conținut decent
- [ ] **Zero erori critice**: PHP error log curat
- [ ] **OpenAI cost < $5**: Verifică usage OpenAI Platform
- [ ] **Frontend traffic**: Vercel Analytics arată vizite (dacă users invitați)

---

## 🚨 Alert Rules

### Critical Alerts (Acțiune IMEDIATĂ)

| Alert | Trigger | Acțiune |
|-------|---------|---------|
| **API Down** | Status 500 pe `/news` | Check PHP error log, restart web server |
| **Database error** | Cannot connect to DB | Contact Hostico support |
| **OpenAI quota exceeded** | 429 rate limit non-stop | Reduce fetch frequency sau upgrade plan |
| **Cron stopped** | Last fetch > 2 hours | Setup real server cron, disable WP Cron |
| **Zero published** | Published = 0 după 24h | Manual approve + publish, check workflow |

### Warning Alerts (Acțiune în 24h)

| Alert | Trigger | Acțiune |
|-------|---------|---------|
| **Pending backlog** | Pending review > 50 | Crește frecvență revizuire sau reduce fetch |
| **High OpenAI cost** | > $10 în prima săptămână | Reduce articole/zi de la 30 la 10-15 |
| **Low publish rate** | < 10 published/zi | Verifică auto-publish enabled, reduce review time |
| **High rejection rate** | > 30% rejected | Îmbunătățește surse RSS (add romanești) |
| **Slow AI processing** | > 2 min per item | Verifică OpenAI API latency |

---

## 📊 SQL Queries pentru Monitoring

### Daily Stats Query

```sql
-- Run zilnic pentru rezumat complet
SELECT
  DATE(fetched_at) as date,
  COUNT(CASE WHEN status = 'fetched' THEN 1 END) as fetched,
  COUNT(CASE WHEN status = 'pending_review' THEN 1 END) as pending,
  COUNT(CASE WHEN status = 'published' THEN 1 END) as published,
  COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
FROM wp_teinformez_news_queue
WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
GROUP BY DATE(fetched_at)
ORDER BY date DESC;
```

### Processing Performance

```sql
-- Cât durează procesarea AI?
SELECT
  AVG(TIMESTAMPDIFF(SECOND, fetched_at, processed_at)) as avg_process_time_sec,
  MIN(TIMESTAMPDIFF(SECOND, fetched_at, processed_at)) as min_time,
  MAX(TIMESTAMPDIFF(SECOND, fetched_at, processed_at)) as max_time
FROM wp_teinformez_news_queue
WHERE status IN ('pending_review', 'approved', 'published')
  AND processed_at IS NOT NULL;
```

**Expected**: 30-60 secunde average (depinde de OpenAI API)

### Top Sources

```sql
-- Care surse produc cele mai multe știri publicate?
SELECT
  source_name,
  COUNT(*) as published_count,
  AVG(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) * 100 as rejection_rate_pct
FROM wp_teinformez_news_queue
WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
GROUP BY source_name
ORDER BY published_count DESC
LIMIT 10;
```

**Acțiune**: Dezactivează surse cu rejection_rate > 50%

### Category Distribution

```sql
-- Ce categorii sunt cele mai populare?
SELECT
  JSON_UNQUOTE(JSON_EXTRACT(categories, CONCAT('$[', n, ']'))) as category,
  COUNT(*) as count
FROM wp_teinformez_news_queue
CROSS JOIN (
  SELECT 0 as n UNION SELECT 1 UNION SELECT 2
) as numbers
WHERE status = 'published'
  AND JSON_LENGTH(categories) > n
GROUP BY category
ORDER BY count DESC;
```

---

## 🛠️ Maintenance Tasks

### Zilnic (5-10 min)

**Dimineața (9:00)**:
- [ ] Check WordPress Admin → TeInformez → Dashboard
- [ ] Review "Pending Review" tab: Aprobă 10-20 știri noi
- [ ] Verifică "Processing Errors" = 0
- [ ] Quick SQL query pentru stats

**Seara (18:00)**:
- [ ] Check OpenAI usage: https://platform.openai.com/usage
- [ ] Verifică Vercel Analytics: https://vercel.com/analytics
- [ ] Scan PHP error log pentru warnings

### Săptămânal (30 min)

**Luni dimineața**:
- [ ] Run full SQL stats pentru săptămâna trecută
- [ ] Identifică top 5 surse (published count)
- [ ] Identifică worst 3 surse (rejection rate > 40%)
- [ ] Dezactivează surse low-quality
- [ ] Verifică backup database (via cPanel)
- [ ] Review OpenAI cost total: Target < $10/săptămână

### Lunar (2 ore)

**Prima duminică a lunii**:
- [ ] **Database cleanup**: Run `teinformez_cleanup_old_items` manual
  ```sql
  -- Items > 30 zile rejected/published
  DELETE FROM wp_teinformez_news_queue
  WHERE (status = 'rejected' OR status = 'published')
    AND fetched_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
  ```
- [ ] **Performance review**: Analizează slow queries (via phpMyAdmin)
- [ ] **Cost optimization**: Dacă OpenAI > $40/lună, reduce la 15 articole/zi
- [ ] **Source optimization**: Adaugă surse noi românești
- [ ] **Full backup**: Database + plugin files via cPanel

---

## 🐛 Troubleshooting Scenarios

### Scenario 1: Cron Jobs nu mai rulează

**Simptome**:
- "Last Fetch" > 2 hours ago
- "Last AI Process" > 2 hours ago
- Queue stopped growing

**Diagnostic**:
```bash
# Via WP-CLI
wp cron event list | grep teinformez

# Via WP Crontrol plugin
Check if events are scheduled
```

**Fix 1: Re-schedule Events**
```php
// Via WordPress Admin → Tools → Available Hooks
// Find: teinformez_fetch_news
// Click "Run Now"
```

**Fix 2: Setup Real Server Cron**
```bash
# cPanel → Cron Jobs
*/30 * * * * wget -q -O - https://teinformez.eu/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

**Fix 3: Check wp-config.php**
```php
// Asigură-te că nu e disabled
// define('DISABLE_WP_CRON', true); ← Șterge sau comment out
```

### Scenario 2: OpenAI Rate Limit Persistent

**Simptome**:
- "Processing Errors" > 10
- Items rămân stuck în "fetched"
- Error log: "Rate limit exceeded"

**Diagnostic**:
```bash
# Check OpenAI tier
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer sk-proj-xxx" | jq '.data[0]'
```

**Fix 1: Reduce Batch Size**
```php
// În class-ai-processor.php, linia ~50
$items = $wpdb->get_results(
    "SELECT * FROM {$table} WHERE status = 'fetched' LIMIT 5" // Reduce de la 20 la 5
);
```

**Fix 2: Add Sleep Between Requests**
```php
// În class-ai-processor.php, în loop de procesare
foreach ($items as $item) {
    $this->process_item($item);
    sleep(3); // 3 secunde pauză între articole
}
```

**Fix 3: Upgrade OpenAI Plan**
- Tier 1 (Free): 3 RPM (requests per minute)
- Tier 2 ($5 spent): 60 RPM
- Tier 3 ($50 spent): 3500 RPM

### Scenario 3: CORS Errors Persistent

**Simptome**:
- Frontend /news arată loading forever
- Browser Console: "blocked by CORS policy"
- API works în curl, fails în browser

**Diagnostic**:
```javascript
// Browser Console pe teinformez.vercel.app
fetch('https://teinformez.eu/wp-json/teinformez/v1/news')
  .then(r => {
    console.log('Headers:', r.headers.get('Access-Control-Allow-Origin'));
    return r.json();
  })
```

**Fix 1: Verify ALLOWED_ORIGINS**
```php
// class-config.php linia 43
const ALLOWED_ORIGINS = [
    'http://localhost:3000',
    'https://teinformez.eu',
    'https://teinformez.vercel.app',     // ← ADD IF MISSING
    'https://*.vercel.app',              // ← ADD IF MISSING
];
```

**Fix 2: Check Wildcard Pattern Matcher**
```php
// class-config.php linia 60
public static function is_origin_allowed($origin) {
    // Funcția ar trebui să existe din Phase A security fixes
    // Dacă lipsește, re-upload class-config.php
}
```

**Fix 3: Manual CORS Header**

Dacă wildcard nu funcționează, forțează în `teinformez-core.php`:
```php
add_filter('rest_pre_serve_request', function($served, $result, $request) {
    $request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (strpos($request_origin, 'vercel.app') !== false) {
        header('Access-Control-Allow-Origin: ' . $request_origin);
    }
    return $served;
}, 10, 3);
```

### Scenario 4: MySQL Performance Issues

**Simptome**:
- API `/news` slow (> 3 secunde)
- WordPress Admin slow
- phpMyAdmin slow queries warning

**Diagnostic**:
```sql
-- Check table size
SELECT
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'teinformez_db'
  AND table_name LIKE 'wp_teinformez%';
```

**Fix 1: Add Indexes**
```sql
-- Index on status (used în toate queries)
ALTER TABLE wp_teinformez_news_queue
ADD INDEX idx_status (status);

-- Index on published_at (used în ordering)
ALTER TABLE wp_teinformez_news_queue
ADD INDEX idx_published_at (published_at);

-- Composite index for published news
ALTER TABLE wp_teinformez_news_queue
ADD INDEX idx_status_published (status, published_at);
```

**Fix 2: Cleanup Old Data**
```sql
-- Delete rejected > 7 zile (nu 30)
DELETE FROM wp_teinformez_news_queue
WHERE status = 'rejected'
  AND fetched_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Archive published > 30 zile în altă tabelă
CREATE TABLE wp_teinformez_news_archive LIKE wp_teinformez_news_queue;

INSERT INTO wp_teinformez_news_archive
SELECT * FROM wp_teinformez_news_queue
WHERE status = 'published'
  AND published_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

DELETE FROM wp_teinformez_news_queue
WHERE status = 'published'
  AND published_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

**Fix 3: Query Optimization**

În `class-news-publisher.php`, linia 68, optimizează:
```php
// OLD (slow pentru > 1000 items)
$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";

// NEW (faster cu early filtering)
$query = "SELECT * FROM {$table}
          WHERE status = 'published'  -- Filter FIRST
          AND {$where_clause}
          ORDER BY published_at DESC  -- Use indexed column
          LIMIT %d OFFSET %d";
```

---

## 📈 Performance Benchmarks

### API Response Times (Target)

| Endpoint | Target | Acceptabil | Problematic |
|----------|--------|------------|-------------|
| `/categories` | < 100ms | < 300ms | > 500ms |
| `/news` | < 500ms | < 1s | > 2s |
| `/news/{id}` | < 300ms | < 700ms | > 1s |
| `/news/personalized` | < 800ms | < 1.5s | > 3s |

**Măsurare**:
```bash
# Test response time
time curl -s https://teinformez.eu/wp-json/teinformez/v1/news > /dev/null
```

### Database Query Times (Target)

| Query Type | Target | Acceptabil | Problematic |
|------------|--------|------------|-------------|
| SELECT published (20 items) | < 50ms | < 150ms | > 300ms |
| SELECT single by ID | < 10ms | < 30ms | > 100ms |
| UPDATE status (1 item) | < 20ms | < 50ms | > 150ms |
| COUNT(*) GROUP BY | < 100ms | < 300ms | > 1s |

**Măsurare** (via phpMyAdmin):
- Enable query profiling
- Run query
- Check "Query took X seconds"

### Frontend Load Times (Target)

| Page | Target | Acceptabil | Problematic |
|------|--------|------------|-------------|
| `/news` | < 1s | < 2s | > 3s |
| `/news/[id]` | < 800ms | < 1.5s | > 2.5s |
| `/dashboard` | < 1.2s | < 2.5s | > 4s |

**Măsurare**: Vercel Analytics sau Chrome DevTools → Network tab

---

## 📊 Reporting Template

### Weekly Report Template

```markdown
# TeInformez Weekly Report - Week of [Date]

## 📈 Key Metrics

- **Total News Published**: 150 (+30 from last week)
- **Pending Review**: 12 (down from 25)
- **Publishing Rate**: 21.4 articles/day (up from 17.1)
- **Rejection Rate**: 8% (down from 15%)

## 💰 Cost Analysis

- **OpenAI Spend**: $8.40 ($0.056 per article)
- **Vercel Bandwidth**: Free tier (0.5 GB used)
- **Hostico Hosting**: $10/month (fixed)
- **Total Weekly Cost**: $8.40

## 🏆 Top Performing Sources

1. TechCrunch: 35 published, 5% rejection
2. The Verge: 28 published, 10% rejection
3. Wired: 22 published, 8% rejection

## ⚠️ Issues Encountered

- [Date] CORS error fixed by adding Vercel wildcard
- [Date] OpenAI rate limit → reduced batch to 10 items
- [Date] Cron stopped → setup real server cron

## 🎯 Next Week Goals

- [ ] Reduce OpenAI cost to < $7/week
- [ ] Add 2 Romanian news sources
- [ ] Achieve 25+ published/day
- [ ] Keep rejection rate < 10%
```

---

## 🔗 Monitoring Tools & Links

| Tool | URL | Purpose |
|------|-----|---------|
| **WordPress Dashboard** | https://teinformez.eu/wp-admin/admin.php?page=teinformez-dashboard | Queue stats |
| **News Queue** | https://teinformez.eu/wp-admin/admin.php?page=teinformez-news-queue | Manual actions |
| **phpMyAdmin** | Via cPanel → Databases | SQL queries |
| **WP Crontrol** | WordPress Admin → Tools → Cron Events | Cron monitoring |
| **Vercel Analytics** | https://vercel.com/analytics | Frontend metrics |
| **Vercel Logs** | https://vercel.com/logs | Errors & warnings |
| **OpenAI Usage** | https://platform.openai.com/usage | API costs |
| **OpenAI Billing** | https://platform.openai.com/account/billing | Set limits |

---

## 📞 Emergency Contacts

| Issue | Contact | Method |
|-------|---------|--------|
| **Hosting down** | Hostico Support | https://hostico.ro/contact |
| **Database issues** | Hostico Support | Ticket via cPanel |
| **OpenAI quota** | OpenAI Support | https://help.openai.com |
| **Vercel deployment** | Vercel Support | https://vercel.com/support |
| **Code bugs** | GitHub Issues | https://github.com/aledan2809/TeInformez/issues |

---

## ✅ Monthly Health Report Template

```sql
-- Run la sfârșitul fiecărei luni
SELECT
  'News Fetched' as metric,
  COUNT(*) as value
FROM wp_teinformez_news_queue
WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)

UNION ALL

SELECT
  'News Published' as metric,
  COUNT(*) as value
FROM wp_teinformez_news_queue
WHERE status = 'published'
  AND published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)

UNION ALL

SELECT
  'Rejection Rate %' as metric,
  ROUND(
    (SELECT COUNT(*) FROM wp_teinformez_news_queue
     WHERE status = 'rejected'
       AND fetched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) * 100.0 /
    (SELECT COUNT(*) FROM wp_teinformez_news_queue
     WHERE fetched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),
    2
  ) as value

UNION ALL

SELECT
  'Avg Process Time (sec)' as metric,
  ROUND(AVG(TIMESTAMPDIFF(SECOND, fetched_at, processed_at)), 0) as value
FROM wp_teinformez_news_queue
WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

**Target Results**:
```
Metric                    | Value
--------------------------|-------
News Fetched             | 800-1500
News Published           | 600-900
Rejection Rate %         | 8-15%
Avg Process Time (sec)   | 30-60
```

---

**Status**: 📊 MONITORING SETUP COMPLET
**Frecvență**: Zilnic (5-10 min), Săptămânal (30 min), Lunar (2h)

**Autor**: Claude Code
**Data**: 26 Ianuarie 2026
