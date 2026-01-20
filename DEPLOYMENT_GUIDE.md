# TeInformez.eu - Ghid Complet de Deployment

## 📋 Prerequisite

Înainte de a începe, asigură-te că ai:

- [ ] Cont WordPress pe Hostico (activ)
- [ ] Acces FTP/SFTP la WordPress
- [ ] Cont GitHub
- [ ] Cont Vercel (gratuit)
- [ ] OpenAI API Key (cu billing activ)
- [ ] SendGrid API Key (tier gratuit OK)

---

## 🚀 PASUL 1: Deploy WordPress Backend (Hostico)

### 1.1 Upload Plugin

**Opțiunea A: Via FTP/SFTP**
```bash
# Conectează-te la Hostico via FileZilla/WinSCP
# Navighează la: /public_html/wp-content/plugins/

# Upload folderul:
backend/wp-content/plugins/teinformez-core/
```

**Opțiunea B: Via ZIP**
```bash
# Creează ZIP local
cd backend/wp-content/plugins
zip -r teinformez-core.zip teinformez-core/

# Upload în WordPress Admin:
# Plugins > Add New > Upload Plugin > Choose File
```

### 1.2 Activare Plugin

1. Loghează-te în WordPress Admin: `https://teinformez.eu/wp-admin`
2. Mergi la **Plugins** > **Installed Plugins**
3. Găsește **TeInformez Core**
4. Click pe **Activate**

✅ **Verificare**: Ar trebui să vezi meniul "TeInformez" în sidebar-ul WordPress.

### 1.3 Configurare API Keys

1. Mergi la **TeInformez** > **Settings**
2. Introdu:
   - **OpenAI API Key**: Obține de la https://platform.openai.com/api-keys
   - **SendGrid API Key**: Obține de la https://app.sendgrid.com/settings/api_keys
3. Click **Save Settings**

### 1.4 Verificare Tabele Database

Conectează-te la phpMyAdmin și verifică că există:
- `wp_teinformez_user_preferences`
- `wp_teinformez_subscriptions`
- `wp_teinformez_news_queue`
- `wp_teinformez_delivery_log`

---

## 💻 PASUL 2: Deploy Frontend (GitHub + Vercel)

### 2.1 Push pe GitHub

```bash
cd /c/Users/alex.danciulescu/TeInformez

# Inițializare Git
git init

# Add remote (schimbă USERNAME cu al tău)
git remote add origin https://github.com/USERNAME/teinformez.git

# Commit toate fișierele
git add .
git commit -m "Initial commit: TeInformez headless WordPress + Next.js"

# Push
git push -u origin main
```

### 2.2 Deploy pe Vercel

1. **Conectează Vercel la GitHub**:
   - Mergi pe https://vercel.com
   - Click **Add New** > **Project**
   - Import repository-ul `teinformez`
   - Vercel detectează automat Next.js

2. **Configurare Root Directory**:
   - În Vercel, setează **Root Directory**: `frontend`

3. **Environment Variables**:
   Click **Environment Variables** și adaugă:

   ```
   NEXT_PUBLIC_WP_API_URL = https://teinformez.eu/wp-json
   NEXT_PUBLIC_SITE_URL = https://teinformez.vercel.app
   ```

4. **Deploy**:
   - Click **Deploy**
   - Așteaptă 2-3 minute
   - ✅ Frontend-ul tău e live pe `https://teinformez.vercel.app`

### 2.3 Configurare Custom Domain (Opțional)

**Dacă vrei să folosești `teinformez.eu` pentru frontend:**

1. În Vercel dashboard: **Settings** > **Domains**
2. Adaugă `teinformez.eu`
3. Configurează DNS (la provider-ul tău de domeniu):

```
Type: A
Name: @
Value: 76.76.21.21

Type: CNAME
Name: www
Value: cname.vercel-dns.com
```

4. Așteaptă propagare DNS (până la 24h, dar de obicei 1-2h)

**SAU păstrezi WordPress pe `teinformez.eu` și frontend pe subdomain:**
- Frontend: `app.teinformez.eu` (Vercel)
- Backend: `teinformez.eu` (Hostico WordPress)

---

## 🔧 PASUL 3: Testare Completă

### 3.1 Test Backend API

Testează că API-ul funcționează:

```bash
# Test ping
curl https://teinformez.eu/wp-json/teinformez/v1/categories

# Ar trebui să returneze JSON cu categoriile
```

### 3.2 Test Frontend

1. Deschide `https://teinformez.vercel.app` (sau domeniul tău)
2. Click pe **Înregistrare gratuită**
3. Completează formularul
4. Verifică că:
   - [ ] Te redirecționează către onboarding (sau dashboard)
   - [ ] Nu apar erori CORS
   - [ ] Token-ul e salvat (verifică Cookies în DevTools)

### 3.3 Test în WordPress Admin

1. Loghează-te în WordPress Admin
2. Mergi la **Users** > **All Users**
3. Verifică că noul user e creat

---

## 🐛 Troubleshooting

### Problema: CORS Error

**Simptom**: `Access to XMLHttpRequest has been blocked by CORS policy`

**Soluție**:
1. În WordPress, editează `backend/wp-content/plugins/teinformez-core/includes/class-config.php`
2. Adaugă domeniul Vercel în `ALLOWED_ORIGINS`:
```php
const ALLOWED_ORIGINS = [
    'http://localhost:3000',
    'https://teinformez.eu',
    'https://teinformez.vercel.app',  // ADD THIS
    'https://*.vercel.app',
];
```
3. Re-upload plugin-ul
4. Reactivează plugin-ul

### Problema: API Returns 404

**Soluție**:
1. În WordPress Admin: **Settings** > **Permalinks**
2. Selectează orice opțiune (ex: "Post name")
3. Click **Save Changes**
4. Retry API call

### Problema: Frontend nu se conectează la Backend

**Verifică**:
```bash
# În frontend/.env.local
NEXT_PUBLIC_WP_API_URL=https://teinformez.eu/wp-json  # CORECT
# NU: http://localhost/wp-json (dacă e production)
```

### Problema: Plugin nu se activează

**Verifică**:
- PHP version în Hostico (trebuie >= 8.0)
- Permissions pe folder (755)
- WordPress version (trebuie >= 6.0)

---

## 📊 Next Steps - Ce urmează

### ✅ IMPLEMENTAT (Phase A - User Registration)

- [x] Backend: WordPress plugin complet
- [x] Backend: REST API pentru auth și user management
- [x] Backend: Tabele database
- [x] Backend: GDPR compliance
- [x] Frontend: Next.js cu TypeScript
- [x] Frontend: Homepage + Landing
- [x] Frontend: Register page
- [x] Frontend: Login page
- [x] Frontend: API client + Auth store

### 🚧 TO DO (Onboarding - continuare Phase A)

1. **Onboarding Wizard** (frontend):
   - Step 1: Selectare categorii
   - Step 2: Adăugare topicuri specifice (tags)
   - Step 3: Selectare frecvență livrare
   - Step 4: Selectare canale (email, social)
   - Final: Save subscriptions via API

2. **User Dashboard** (frontend):
   - Afișare preferințe curente
   - Edit subscriptions
   - Stats (câte subscriptions, delivery history)
   - Account settings (change password, delete account)

**Estimare timp**: 4-6 ore pentru onboarding + dashboard

### 📅 Phase B - News Aggregation (viitor)

- [ ] RSS Parser
- [ ] News API integration
- [ ] Web scraper
- [ ] OpenAI processing (summarize, translate, generate images)
- [ ] Admin review queue
- [ ] Auto-publish logic

**Estimare timp**: 2-3 săptămâni

### 📅 Phase C - Delivery System (viitor)

- [ ] SendGrid email templates
- [ ] Personalized digest generator
- [ ] Scheduler (WP Cron enhanced)
- [ ] Facebook/Twitter posting
- [ ] Delivery logs

**Estimare timp**: 2 săptămâni

---

## 🎯 Checklist Final Deployment

Înainte de a considera deployment-ul complet:

**Backend:**
- [ ] Plugin activat în WordPress
- [ ] API Keys configurate (OpenAI, SendGrid)
- [ ] Tabele create în database
- [ ] Cron jobs programate
- [ ] Test API cu Postman/curl

**Frontend:**
- [ ] Push pe GitHub
- [ ] Deploy pe Vercel reușit
- [ ] Environment variables setate
- [ ] Custom domain configurat (dacă e cazul)
- [ ] Test înregistrare user

**Integration:**
- [ ] CORS configurat corect
- [ ] Frontend comunică cu backend
- [ ] Auth flow funcționează (register, login, logout)
- [ ] Token-urile se salvează corect

---

## 📞 Support

Pentru probleme tehnice:
- Email: contact@teinformez.eu
- GitHub Issues: https://github.com/USERNAME/teinformez/issues

---

## 📝 Notes

- **Backup**: Întotdeauna ia backup la database înainte de update-uri
- **Security**: Nu comite niciodată `.env` files cu API keys
- **Updates**: Monitorizează Vercel deployments pentru erori
- **Costs**:
  - Vercel: Gratuit pentru hobby projects
  - OpenAI: ~$0.002 per request (aprox $10-20/lună pentru 5000-10000 știri)
  - SendGrid: 100 emails/zi gratuit

**Data deployment**: Ianuarie 2026
**Versiune**: 1.0.0
