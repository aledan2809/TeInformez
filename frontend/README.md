# TeInformez Frontend

Next.js 14 frontend pentru TeInformez.eu - platformă de știri personalizate.

## Stack Tehnic

- **Framework**: Next.js 14 (App Router)
- **Language**: TypeScript
- **Styling**: TailwindCSS
- **State Management**: Zustand
- **Forms**: React Hook Form
- **HTTP Client**: Axios
- **Icons**: Lucide React

## Instalare & Rulare

### 1. Instalare dependențe

```bash
cd frontend
npm install
```

### 2. Configurare environment

Copiază `.env.example` în `.env.local` și configurează:

```bash
cp .env.example .env.local
```

Editează `.env.local`:

```env
# Pentru development local cu WordPress local
NEXT_PUBLIC_WP_API_URL=http://localhost/wp-json
NEXT_PUBLIC_SITE_URL=http://localhost:3000

# Pentru production (când WordPress e pe Hostico)
# NEXT_PUBLIC_WP_API_URL=https://teinformez.eu/wp-json
# NEXT_PUBLIC_SITE_URL=https://teinformez.eu
```

### 3. Rulare development server

```bash
npm run dev
```

Aplicația va fi disponibilă la: `http://localhost:3000`

### 4. Build pentru production

```bash
npm run build
npm start
```

## Deployment pe Vercel

### Setup inițial

1. **Push pe GitHub**:
```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/USERNAME/teinformez-frontend.git
git push -u origin main
```

2. **Conectează Vercel la GitHub**:
   - Mergi pe [vercel.com](https://vercel.com)
   - Click pe **New Project**
   - Import repository-ul tău de GitHub
   - Vercel va detecta automat că e Next.js

3. **Configurează Environment Variables în Vercel**:
   - În Vercel dashboard, mergi la **Settings** > **Environment Variables**
   - Adaugă:
     - `NEXT_PUBLIC_WP_API_URL` = `https://teinformez.eu/wp-json`
     - `NEXT_PUBLIC_SITE_URL` = `https://teinformez.eu` (sau domeniul Vercel)

4. **Deploy**:
   - Vercel va face auto-deploy la fiecare push pe `main`
   - Preview deployments pentru branches

### Custom Domain pe Vercel

1. În Vercel dashboard, mergi la **Settings** > **Domains**
2. Adaugă `teinformez.eu`
3. Configurează DNS:
   - Type: `A` Record
   - Name: `@`
   - Value: `76.76.21.21` (Vercel IP)

   - Type: `CNAME`
   - Name: `www`
   - Value: `cname.vercel-dns.com`

## Structură Proiect

```
frontend/
├── src/
│   ├── app/                    # Next.js App Router
│   │   ├── page.tsx           # Homepage
│   │   ├── layout.tsx         # Root layout
│   │   ├── globals.css        # Global styles
│   │   ├── register/          # Pagină înregistrare
│   │   ├── login/             # Pagină login
│   │   ├── dashboard/         # Dashboard user (TODO)
│   │   └── onboarding/        # Onboarding wizard (TODO)
│   │
│   ├── components/            # React components
│   │   ├── ui/               # UI components reutilizabile
│   │   └── ...
│   │
│   ├── lib/                   # Utilities
│   │   ├── api.ts            # API client (Axios)
│   │   └── utils.ts          # Helper functions
│   │
│   ├── store/                 # Zustand stores
│   │   └── authStore.ts      # Authentication state
│   │
│   ├── types/                 # TypeScript types
│   │   └── index.ts
│   │
│   └── hooks/                 # Custom React hooks
│
├── public/                    # Static assets
├── .env.example              # Environment variables example
├── .env.local                # Local environment (gitignored)
├── next.config.js            # Next.js config
├── tailwind.config.ts        # Tailwind config
└── package.json
```

## Features Implementate

### ✅ Phase A - User Registration & Auth

- [x] Homepage cu landing page
- [x] Pagină de înregistrare cu GDPR
- [x] Pagină de login
- [x] API client cu autentificare
- [x] Auth store (Zustand)
- [x] TypeScript types complete

### 🚧 În Lucru

- [ ] Onboarding wizard (selectare categorii, preferințe)
- [ ] Dashboard utilizator
- [ ] Gestionare abonamente
- [ ] Setări profil
- [ ] Export/delete data (GDPR)

### 📅 Coming Soon (Phase B & C)

- [ ] News feed personalizat
- [ ] Sharing functionality
- [ ] Delivery scheduling UI
- [ ] Admin review interface

## API Integration

Frontend-ul comunică cu WordPress backend prin REST API.

### Exemplu utilizare:

```typescript
import { api } from '@/lib/api';

// Register user
const { user, token } = await api.register({
  email: 'user@example.com',
  password: 'password123',
  gdpr_consent: true
});

// Login
const { user, token } = await api.login({
  email: 'user@example.com',
  password: 'password123'
});

// Get current user
const user = await api.getCurrentUser();

// Update preferences
await api.updatePreferences({
  preferred_language: 'en',
  delivery_schedule: {
    frequency: 'daily',
    time: '14:00',
    timezone: 'Europe/Bucharest'
  }
});
```

## Development Tips

### Hot Reload

Next.js oferă hot reload automat. Salvează fișierul și vezi schimbările instant.

### Type Checking

```bash
npm run type-check
```

### Linting

```bash
npm run lint
```

### Debugging

Folosește React DevTools și Network tab pentru a vedea request-urile API.

## Troubleshooting

### CORS Errors

Dacă primești erori CORS:
1. Verifică că backend-ul WordPress are CORS configurat corect
2. Verifică că `NEXT_PUBLIC_WP_API_URL` este corect în `.env.local`
3. Verifică că originea frontend-ului e în `Config::ALLOWED_ORIGINS` din backend

### API 401 Unauthorized

- Token-ul a expirat sau e invalid
- Șterge cookies și re-login
- Verifică că `withCredentials: true` e setat în Axios

### Build Errors

```bash
# Clear cache
rm -rf .next
npm run build
```

## Clonare pentru alt domeniu

Pentru a clona frontend-ul pentru alt domeniu (ex: TeInformez.de):

1. **Fork repository-ul**
2. **Schimbă `.env` variables**:
   ```env
   NEXT_PUBLIC_WP_API_URL=https://teinformez.de/wp-json
   NEXT_PUBLIC_SITE_URL=https://teinformez.de
   ```
3. **Traduci conținutul**:
   - Strings în componente (homepage, register, etc.)
   - Metadata în `layout.tsx`
4. **Deploy pe Vercel** cu noul domeniu

## Support

Pentru probleme: contact@teinformez.eu
