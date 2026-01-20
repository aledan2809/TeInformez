# 🚀 TeInformez - Quick Start Guide

Ghid rapid pentru a pune proiectul în funcțiune.

---

## ⚡ TL;DR - Ce ai de făcut ACUM

1. **Instalează dependențele frontend** (5 minute)
2. **Deploy backend pe Hostico** (15 minute)
3. **Deploy frontend pe Vercel** (10 minute)
4. **Test** (5 minute)

**Total timp**: ~35 minute pentru live deployment

---

## 📁 Ce ai în folderul TeInformez/

```
TeInformez/
├── backend/                    ← WordPress plugin
│   └── wp-content/
│       └── plugins/
│           └── teinformez-core/   ← Upload asta pe Hostico!
│
├── frontend/                   ← Next.js app
│   ├── src/                   ← Cod React
│   ├── package.json           ← Dependențe
│   └── .env.local             ← Configurare API
│
├── PLAN.md                    ← Plan tehnic complet
├── DEPLOYMENT_GUIDE.md        ← Ghid deployment detaliat
├── SUMMARY.md                 ← Ce e gata, ce lipsește
└── QUICK_START.md             ← TU EȘTI AICI
```

---

## 🎯 Opțiunea 1: Test Local (Recomandat pentru început)

### Step 1: Instalare dependențe frontend

```bash
cd C:\Users\alex.danciulescu\TeInformez\frontend
npm install
```

⏱️ **Timp**: 3-5 minute (depinde de internet)

### Step 2: Configurare .env.local

Fișierul `frontend/.env.local` ar trebui să existe deja cu:

```env
NEXT_PUBLIC_WP_API_URL=http://localhost/wp-json
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```

Dacă folosești **XAMPP/WAMP** local, perfect. Dacă nu, schimbă cu URL-ul tău WordPress de test.

### Step 3: Rulare frontend

```bash
npm run dev
```

✅ Deschide browser: `http://localhost:3000`

Ar trebui să vezi homepage-ul TeInformez!

⚠️ **NOTE**: Fără backend WordPress activ, vei primi erori la register/login. Normal!

---

## 🌐 Opțiunea 2: Deploy Production (Hostico + Vercel)

### BACKEND: Upload pe Hostico

#### Via FTP/SFTP (FileZilla/WinSCP):

1. **Conectează-te la Hostico**:
   - Host: `ftp.teinformez.eu` (sau ce ți-au dat ei)
   - Username: (din Hostico control panel)
   - Password: (din Hostico control panel)

2. **Navighează la**:
   ```
   /public_html/wp-content/plugins/
   ```

3. **Upload folderul**:
   ```
   TeInformez/backend/wp-content/plugins/teinformez-core/
   ```
   → Drag & drop în FileZilla

4. **În WordPress Admin** (`teinformez.eu/wp-admin`):
   - Plugins → Installed Plugins
   - Găsește "TeInformez Core"
   - Click **Activate**

5. **Configurare**:
   - TeInformez → Settings
   - Introdu:
     - **OpenAI API Key**: [Obține aici](https://platform.openai.com/api-keys)
     - **SendGrid API Key**: [Obține aici](https://app.sendgrid.com/settings/api_keys)
   - Save Settings

✅ **Verificare**: Deschide în browser:
```
https://teinformez.eu/wp-json/teinformez/v1/categories
```

Ar trebui să vezi JSON cu categorii!

---

### FRONTEND: Deploy pe Vercel

#### Step 1: Push pe GitHub

```bash
cd C:\Users\alex.danciulescu\TeInformez

# Inițializare Git (dacă nu e deja)
git init

# Add toate fișierele
git add .
git commit -m "TeInformez - Initial commit"

# Add remote (SCHIMBĂ username-ul!)
git remote add origin https://github.com/TAU_USERNAME/teinformez.git

# Push
git push -u origin main
```

⚠️ **Dacă nu ai repository**: Creează unul nou pe [github.com/new](https://github.com/new)

---

#### Step 2: Import în Vercel

1. **Mergi pe** [vercel.com](https://vercel.com)
2. **Login** cu GitHub
3. **Add New** → **Project**
4. **Import** repository-ul `teinformez`

**IMPORTANT** - Configurări Vercel:
- **Root Directory**: `frontend` ← OBLIGATORIU!
- **Framework Preset**: Next.js (auto-detectat)

5. **Environment Variables** (click "Add"):
   ```
   NEXT_PUBLIC_WP_API_URL = https://teinformez.eu/wp-json
   NEXT_PUBLIC_SITE_URL = https://teinformez.vercel.app
   ```

6. **Deploy** (click Deploy)

⏱️ **Timp build**: 2-3 minute

✅ **După deploy**: Vei primi URL de genul `https://teinformez.vercel.app`

---

#### Step 3: Fix CORS (Crucial!)

După ce frontend-ul e live pe Vercel, trebuie să permitem request-uri din Vercel spre WordPress.

**Pe Hostico** (via FTP sau File Manager în cPanel):

1. **Editează**: `wp-content/plugins/teinformez-core/includes/class-config.php`

2. **Găsește linia**:
```php
const ALLOWED_ORIGINS = [
    'http://localhost:3000',
    'https://teinformez.eu',
    'https://*.vercel.app',
];
```

3. **Adaugă URL-ul exact de la Vercel**:
```php
const ALLOWED_ORIGINS = [
    'http://localhost:3000',
    'https://teinformez.eu',
    'https://teinformez.vercel.app',  // ← ADD THIS
    'https://*.vercel.app',
];
```

4. **Salvează fișierul**

5. **În WordPress Admin**: Deactivează și reactivează plugin-ul TeInformez (pentru a reîncărca config)

---

### ✅ TEST Final

1. **Deschide**: `https://teinformez.vercel.app`
2. **Click**: "Înregistrare gratuită"
3. **Completează formularul**:
   - Email: `test@example.com`
   - Parolă: `password123`
   - ☑️ Accept GDPR
4. **Submit**

**Dacă funcționează**:
- ✅ Te redirecționează (chiar dacă pagina nu e gata încă)
- ✅ Nu apar erori în Console (F12)
- ✅ În WordPress Admin → Users, vezi user-ul nou creat

**Dacă NU funcționează**:
- ⚠️ Eroare CORS → Vezi secțiunea "Fix CORS" mai sus
- ⚠️ API 404 → Verifică că plugin-ul e activat
- ⚠️ Alte erori → Vezi `DEPLOYMENT_GUIDE.md` secțiunea "Troubleshooting"

---

## 🎨 Custom Domain (Opțional)

Dacă vrei `teinformez.eu` pentru frontend (nu doar pentru WordPress):

### Opțiunea A: Subdomain pentru frontend
- Frontend: `app.teinformez.eu` (Vercel)
- Backend: `teinformez.eu` sau `api.teinformez.eu` (Hostico)

**Setup**:
1. În Vercel: Settings → Domains → Add `app.teinformez.eu`
2. În DNS (la provider domeniu):
   - Type: `CNAME`
   - Name: `app`
   - Value: `cname.vercel-dns.com`

### Opțiunea B: Domeniul principal pentru frontend
- Frontend: `teinformez.eu` (Vercel)
- Backend: `api.teinformez.eu` (Hostico)

**Setup**: Mai complex, vezi `DEPLOYMENT_GUIDE.md`

---

## 📞 Ce faci dacă ceva nu merge?

1. **Check**: `DEPLOYMENT_GUIDE.md` → Secțiunea "Troubleshooting"
2. **Verifică Console**: F12 în browser → Console tab
3. **Verifică Network**: F12 → Network tab → Vezi request-urile API
4. **WordPress Debug**: Activează WP_DEBUG în `wp-config.php`

**Probleme comune**:
- **CORS Error** → Vezi "Fix CORS" mai sus
- **API 404** → Permalink-uri WordPress (Settings → Permalinks → Save)
- **Plugin nu se activează** → PHP version (trebuie >= 8.0)

---

## 🎯 Ce urmează după deployment?

După ce totul funcționează:

### Prioritate 1: Finalizare Phase A
1. **Onboarding Wizard** (3-4h)
   - User selectează categorii după register
   - Se salvează subscriptions

2. **User Dashboard** (2-3h)
   - User poate vedea și edita subscriptions
   - Account settings

→ Vezi `SUMMARY.md` pentru detalii

### Prioritate 2: Beta Testing
- Invită 10-20 prieteni să testeze
- Colectează feedback
- Fix bugs

### Prioritate 3: Phase B (Știri)
- Implementare news aggregation
- AI processing
- Delivery system

→ Vezi `PLAN.md` pentru roadmap complet

---

## 📚 Fișiere utile

| Fișier | Când îl folosești |
|--------|------------------|
| `QUICK_START.md` | Prima dată, setup rapid (TU EȘTI AICI) |
| `DEPLOYMENT_GUIDE.md` | Deployment pas cu pas detaliat |
| `SUMMARY.md` | Înțelegi ce e gata și ce lipsește |
| `PLAN.md` | Plan tehnic complet, arhitectură |
| `backend/README.md` | Detalii despre WordPress plugin |
| `frontend/README.md` | Detalii despre Next.js app |

---

## ✅ Checklist Rapid

Pentru deployment:
- [ ] Frontend dependencies installed (`npm install`)
- [ ] Backend uploaded pe Hostico
- [ ] Plugin activat în WordPress
- [ ] API keys configurate (OpenAI, SendGrid)
- [ ] Frontend pushed pe GitHub
- [ ] Frontend deployed pe Vercel
- [ ] Environment variables setate în Vercel
- [ ] CORS fixed (Vercel URL în WordPress)
- [ ] Test register flow funcționează

---

**Succes!** 🚀

Dacă ai întrebări sau probleme, verifică documentația sau contactează support.
