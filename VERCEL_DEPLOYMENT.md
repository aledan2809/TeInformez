# 🚀 TeInformez - Vercel Deployment Guide

**Last Updated**: 26 Ianuarie 2026
**Status**: Ready for deployment

---

## ✅ Pre-Deployment Checklist

- [x] Code pushed to GitHub: `aledan2809/TeInformez`
- [x] Security fixes committed (commit c38425d)
- [x] Frontend build tested locally
- [x] Environment variables documented
- [ ] Vercel account ready
- [ ] Backend CORS configured (will update after deployment)

---

## 📋 Step-by-Step Deployment

### Step 1: Import Project to Vercel (2 min)

1. Go to: https://vercel.com/new
2. Click **"Import Git Repository"**
3. Select: `aledan2809/TeInformez`
4. **IMPORTANT**: Set **Root Directory** to: `frontend`
   - Click "Edit" next to Root Directory
   - Type: `frontend`
   - This tells Vercel where the Next.js app is

### Step 2: Configure Project Settings (1 min)

**Framework Preset**: Next.js (should auto-detect)

**Build Settings**:
- Build Command: `npm run build` (default)
- Output Directory: `.next` (default)
- Install Command: `npm install` (default)

**Node.js Version**: 18.x or 20.x (recommended)

### Step 3: Add Environment Variables (3 min)

Click **"Environment Variables"** and add these:

| Name | Value | Notes |
|------|-------|-------|
| `NEXT_PUBLIC_WP_API_URL` | `https://teinformez.eu/wp-json` | WordPress API URL |
| `NEXT_PUBLIC_SITE_URL` | `https://your-app.vercel.app` | Leave as placeholder for now |
| `OPENAI_API_KEY` | `your_actual_key_here` | Get from Master_API_Key folder |

**Where to add**:
- **Production**: Add to "Production" tab
- **Preview**: Add to "Preview" tab (same values)
- **Development**: Skip (uses local `.env.local`)

### Step 4: Deploy (2 min)

1. Click **"Deploy"** button
2. Wait ~2-3 minutes for build
3. Vercel will show: ✅ **Deployment successful**
4. Click **"Visit"** to see your live site
5. **COPY the URL** (e.g., `teinformez-xyz.vercel.app`)

---

## 🔧 Post-Deployment Configuration

### Step 5: Update Vercel Environment Variables (1 min)

Now that you have the real URL:

1. Go to: **Project Settings** → **Environment Variables**
2. Edit `NEXT_PUBLIC_SITE_URL`:
   - Change from placeholder to actual URL
   - Example: `https://teinformez-xyz.vercel.app`
3. Click **"Save"**
4. **Redeploy** to apply changes:
   - Go to **Deployments** tab
   - Click **"..."** on latest deployment
   - Click **"Redeploy"**

### Step 6: Update Backend CORS (CRITICAL - 2 min)

**Via FTP/cPanel** (since backend isn't deployed yet):

1. Edit file: `backend/wp-content/plugins/teinformez-core/includes/class-config.php`
2. Find line ~43: `const ALLOWED_ORIGINS`
3. Add your Vercel URL:

```php
const ALLOWED_ORIGINS = [
    'http://localhost:3000',
    'https://teinformez.eu',
    'https://teinformez-xyz.vercel.app',  // ← Your actual Vercel URL
    'https://*.vercel.app',               // For preview deployments
];
```

4. Save file
5. **Test**: Try to register from Vercel URL

**Alternative** (if backend is on Hostico already):
- Upload the modified file via FTP
- Or edit directly in cPanel File Manager

---

## 🧪 Testing Your Deployment

### Test Checklist:

Visit your Vercel URL and test:

- [ ] Homepage loads correctly
- [ ] Navigate to `/register`
- [ ] Try to create account (will fail if CORS not configured)
- [ ] After fixing CORS: Complete registration
- [ ] Test login at `/login`
- [ ] Check onboarding flow at `/onboarding`
- [ ] Verify dashboard at `/dashboard`

### Expected Issues:

**CORS Error** (before Step 6):
```
Access to XMLHttpRequest has been blocked by CORS policy
```
**Fix**: Complete Step 6 (update backend CORS)

**401 Unauthorized** (after CORS fix):
- Check that WordPress backend is accessible
- Verify `NEXT_PUBLIC_WP_API_URL` is correct
- Test: `curl https://teinformez.eu/wp-json/teinformez/v1/categories`

**Network Error**:
- Check WordPress is online
- Verify domain `teinformez.eu` is accessible
- Check backend plugin is activated

---

## 🎯 Vercel-Specific Features

### Automatic Deployments

Every push to `master` triggers a new deployment:
- **Production**: `https://your-app.vercel.app`
- **Preview**: `https://teinformez-git-branch-name.vercel.app`

### Preview Deployments

Every pull request gets a unique preview URL:
- Useful for testing before merging
- Automatic SSL certificates
- Full Next.js features

### Domain Setup (Optional)

To use custom domain (e.g., `app.teinformez.eu`):

1. Go to: **Project Settings** → **Domains**
2. Add domain: `app.teinformez.eu`
3. Follow DNS configuration instructions
4. Update `NEXT_PUBLIC_SITE_URL` to new domain
5. Update backend CORS to include new domain

---

## 🔍 Troubleshooting

### Build Fails on Vercel

**Error**: `Module not found` or `npm ERR!`

**Fix**:
```bash
# Test build locally first:
cd frontend
npm install
npm run build
```

If local build succeeds but Vercel fails:
- Check Node.js version in Vercel settings
- Clear build cache: Settings → General → Clear Build Cache

### CORS Issues Persist

**Symptom**: Still getting CORS errors after updating backend

**Fix**:
1. Verify exact URL in `ALLOWED_ORIGINS` (no trailing slash!)
2. Check wildcard pattern: `https://*.vercel.app`
3. Clear browser cache and cookies
4. Test in incognito/private window
5. Check browser console for exact origin being sent

### Environment Variables Not Working

**Symptom**: API calls go to wrong URL

**Fix**:
1. Verify variables start with `NEXT_PUBLIC_`
2. **Redeploy** after changing env vars (not auto-applied!)
3. Check in browser console: `process.env.NEXT_PUBLIC_WP_API_URL`

---

## 📊 Deployment URLs

After deployment, you'll have:

| Environment | URL | Purpose |
|-------------|-----|---------|
| **Production** | `https://teinformez-[hash].vercel.app` | Live site for users |
| **Preview** | `https://teinformez-git-[branch].vercel.app` | Test branches before merge |
| **Development** | `http://localhost:3000` | Local development |

---

## 🎉 Success Criteria

Your deployment is successful when:

✅ Vercel build completes without errors
✅ Homepage loads at Vercel URL
✅ Registration flow works end-to-end
✅ Login redirects to dashboard
✅ Onboarding saves preferences
✅ No CORS errors in browser console
✅ API calls reach WordPress backend

---

## 🔄 Next Steps After Deployment

1. **Test thoroughly** - Try all features
2. **Monitor errors** - Check Vercel logs
3. **Update CORS** - Add Vercel URL to backend
4. **Share with beta testers** - Get feedback
5. **Phase B Development** - Continue with news aggregation

---

## 📞 Support

**Vercel Issues**:
- Docs: https://vercel.com/docs
- Support: https://vercel.com/support

**Project Issues**:
- See: `PHASE_A_COMPLETE.md`
- Or: `TROUBLESHOOTING.md` (if exists)

---

**Status**: 🚀 Ready to Deploy!

Deploy now and update this file with your actual Vercel URL.

---

*Created: 26 Ian 2026*
*Author: Claude Code*
