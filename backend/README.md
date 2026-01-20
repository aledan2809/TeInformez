# TeInformez WordPress Backend

WordPress headless backend plugin pentru platformă de știri personalizate.

## Instalare

### 1. Upload plugin

Copiază folderul `wp-content/plugins/teinformez-core` în instalarea ta WordPress pe Hostico.

```bash
# Via FTP/SFTP
cd /path/to/wordpress
cp -r teinformez-core wp-content/plugins/
```

### 2. Activare plugin

1. Loghează-te în WordPress Admin
2. Mergi la **Plugins** > **Installed Plugins**
3. Găsește **TeInformez Core** și click pe **Activate**

La activare, plugin-ul va:
- Crea 4 tabele custom în baza de date
- Seta opțiuni default
- Programa cron jobs pentru procesare

### 3. Configurare

Mergi la **TeInformez** > **Settings** în WordPress Admin și setează:

- **OpenAI API Key**: Obține de la [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
- **SendGrid API Key**: Obține de la [app.sendgrid.com/settings/api_keys](https://app.sendgrid.com/settings/api_keys)
- **Admin Review Period**: Timpul (în secunde) de review înainte de auto-publish (default: 7200 = 2h)
- **News Fetch Interval**: Cât de des să se preia știri (default: 1800 = 30 min)

### 4. Verificare

După activare, verifică că tabelele au fost create:

```sql
SHOW TABLES LIKE 'wp_teinformez_%';
```

Ar trebui să vezi:
- `wp_teinformez_user_preferences`
- `wp_teinformez_subscriptions`
- `wp_teinformez_news_queue`
- `wp_teinformez_delivery_log`

## API Endpoints

### Autentificare
- `POST /wp-json/teinformez/v1/auth/register` - Înregistrare user nou
- `POST /wp-json/teinformez/v1/auth/login` - Login
- `POST /wp-json/teinformez/v1/auth/logout` - Logout
- `GET /wp-json/teinformez/v1/auth/me` - User curent

### User Management
- `GET /wp-json/teinformez/v1/user/preferences` - Preferințe user
- `PUT /wp-json/teinformez/v1/user/preferences` - Update preferințe
- `GET /wp-json/teinformez/v1/user/subscriptions` - Abonamente
- `POST /wp-json/teinformez/v1/user/subscriptions` - Adaugă abonament
- `POST /wp-json/teinformez/v1/user/subscriptions/bulk` - Adaugă multiple abonamente
- `DELETE /wp-json/teinformez/v1/user/subscriptions/{id}` - Șterge abonament

### GDPR
- `GET /wp-json/teinformez/v1/user/export` - Export date user
- `DELETE /wp-json/teinformez/v1/user/delete` - Șterge cont

### Categorii
- `GET /wp-json/teinformez/v1/categories` - Lista categorii disponibile

## Clonare pe alt domeniu/țară

Pentru a clona site-ul pe alt domeniu (ex: TeInformez.de pentru Germania):

1. **Editează fișierul de configurare**: `includes/class-config.php`

```php
// Schimbă aceste valori:
const SITE_LANGUAGE = 'de';              // din 'ro' în 'de'
const SITE_COUNTRY = 'Germany';          // din 'Romania'
const SITE_TIMEZONE = 'Europe/Berlin';   // din 'Europe/Bucharest'
```

2. **Actualizează sursele de știri** (în viitorul Phase B):
   - Adaugă RSS feeds germane
   - Configurează API-uri pentru surse germane

3. **Traduce string-urile UI**:
   - Copiază `languages/teinformez-ro_RO.po` în `teinformez-de_DE.po`
   - Traduce toate string-urile
   - Compilează cu `msgfmt`

4. **Deploy** pe noul domeniu

## Securitate

### CORS
Plugin-ul permite request-uri doar din originile specificate în `Config::ALLOWED_ORIGINS`:
- `http://localhost:3000` (development)
- `https://teinformez.eu` (production)
- `https://*.vercel.app` (preview deployments)

Pentru a adăuga origini custom, editează `includes/class-config.php`.

### Autentificare
- Parolele sunt hash-ate cu WordPress `wp_hash_password()`
- Token-urile folosesc WordPress nonce system
- Session-urile sunt gestionate de WordPress

## Troubleshooting

### Plugin-ul nu se activează
- Verifică PHP version >= 8.0
- Verifică permisiunile folderului plugins
- Verifică error log-ul WordPress

### Cron jobs nu rulează
Verifică că WordPress cron funcționează:
```bash
wp cron event list
```

Sau forțează rularea manuală:
```bash
wp cron event run teinformez_fetch_news
```

### API returnează 404
- Verifică că permalink-urile sunt activate
- Flush rewrite rules: Settings > Permalinks > Save

## Development

Pentru development local cu WordPress:

```bash
# Folosește Local by Flywheel sau similar
# Sau Docker:
docker-compose up -d
```

Apoi copiază plugin-ul în `wp-content/plugins/`.

## Support

Pentru probleme, contactează: contact@teinformez.eu
