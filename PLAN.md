# TeInformez.eu - Plan Tehnic Complet

## Viziune
Platformă de agregare știri personalizată, bazată pe AI, cu livrare multi-canal.
Arhitectură clonabilă pentru orice țară/limbă.

---

## Decizii Tehnice Confirmate

| Aspect | Decizie |
|--------|---------|
| CMS | WordPress (pe VPS2) |
| Email Provider | Brevo (configurat) |
| Surse știri | RSS + API-uri gratuite + Web scraping |
| AI Processing | OpenAI API (refinare/sinteză/traducere + imagini DALL-E) |
| Social Media | Email + postări publice (Meta API - later) |
| Limbi | RO/EN inițial, arhitectură multilingvă |
| GDPR | Formular consent obligatoriu |
| Hosting | VPS2 (72.62.155.74) + Vercel frontend |

---

## Arhitectură Sistem

```
┌─────────────────────────────────────────────────────────────────┐
│                    WORDPRESS FRONTEND                            │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │ Tema Custom │  │ Multilingv  │  │ User Dashboard          │  │
│  │ (Starter)   │  │ (Polylang)  │  │ - Preferințe categorii  │  │
│  │             │  │ RO/EN/...   │  │ - Frecvență livrare     │  │
│  │             │  │             │  │ - Canale notificare     │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              PLUGIN CUSTOM: TeInformez Core                      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ CONFIG (wp_options)                                       │   │
│  │ - DEFAULT_LANGUAGE: 'ro'                                  │   │
│  │ - AVAILABLE_LANGUAGES: ['ro', 'en', 'de', 'fr', ...]     │   │
│  │ - ADMIN_REVIEW_PERIOD: 7200 (seconds = 2h)               │   │
│  │ - OPENAI_API_KEY: '***'                                   │   │
│  │ - BREVO_API_KEY: '***'                                    │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌─────────────────┐  ┌─────────────────┐  ┌────────────────┐   │
│  │ User Manager    │  │ Category System │  │ Subscription   │   │
│  │ - Registration  │  │ - Hierarchical  │  │ Manager        │   │
│  │ - Preferences   │  │ - Tags/Topics   │  │ - Schedules    │   │
│  │ - GDPR Consent  │  │ - Per Language  │  │ - Channels     │   │
│  └─────────────────┘  └─────────────────┘  └────────────────┘   │
│                                                                  │
│  ┌─────────────────┐  ┌─────────────────┐  ┌────────────────┐   │
│  │ News Aggregator │  │ AI Processor    │  │ Delivery Queue │   │
│  │ - RSS Parser    │  │ - Summarize     │  │ - Email        │   │
│  │ - API Fetcher   │  │ - Translate     │  │ - Social Post  │   │
│  │ - Web Scraper   │  │ - Generate Img  │  │ - Scheduler    │   │
│  └─────────────────┘  └─────────────────┘  └────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ ADMIN REVIEW QUEUE                                        │   │
│  │ - Pending stories (status: draft/pending/approved)        │   │
│  │ - Edit before publish                                     │   │
│  │ - Bulk approve/reject                                     │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    EXTERNAL SERVICES                             │
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │ OpenAI API  │  │ Brevo       │  │ Social APIs             │  │
│  │ - GPT-4     │  │ - Email     │  │ - Facebook Graph API    │  │
│  │ - DALL-E    │  │   delivery  │  │ - Twitter/X API         │  │
│  │             │  │             │  │ - (Meta Business later) │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │ NEWS SOURCES                                             │    │
│  │ - RSS Feeds (unlimited, free)                            │    │
│  │ - NewsAPI.org (100 req/day free)                         │    │
│  │ - GNews.io (100 req/day free)                            │    │
│  │ - Custom Scrapers (per source)                           │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

---

## Schema Bază de Date (WordPress Custom Tables)

### wp_teinformez_users_preferences
```sql
CREATE TABLE wp_teinformez_users_preferences (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,                    -- wp_users.ID
    preferred_language VARCHAR(5) DEFAULT 'ro', -- limba pentru conținut
    delivery_channels JSON,                      -- ["email", "facebook"]
    delivery_schedule JSON,                      -- {"time": "14:00", "timezone": "Europe/Bucharest", "frequency": "daily"}
    gdpr_consent TINYINT DEFAULT 0,
    gdpr_consent_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID)
);
```

### wp_teinformez_subscriptions
```sql
CREATE TABLE wp_teinformez_subscriptions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    category_id BIGINT,                         -- wp_terms.term_id
    topic_keyword VARCHAR(255),                 -- ex: "Tesla", "iPhone 16"
    country_filter VARCHAR(50),                 -- ex: "Romania", "USA", "all"
    source_filter JSON,                         -- surse specifice
    is_active TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID)
);
```

### wp_teinformez_news_queue
```sql
CREATE TABLE wp_teinformez_news_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    original_url VARCHAR(500),
    original_title TEXT,
    original_content LONGTEXT,
    original_language VARCHAR(5),
    source_name VARCHAR(100),
    source_type ENUM('rss', 'api', 'scraper'),

    -- AI Processed
    processed_title TEXT,
    processed_summary TEXT,                     -- max 280 chars for social
    processed_content TEXT,
    target_language VARCHAR(5),
    ai_generated_image_url VARCHAR(500),
    youtube_embed VARCHAR(500),                 -- dacă există video

    -- Status
    status ENUM('fetched', 'processing', 'pending_review', 'approved', 'rejected', 'published') DEFAULT 'fetched',
    admin_notes TEXT,

    -- Categorization
    categories JSON,                            -- ["tech", "electric-cars"]
    tags JSON,                                  -- ["Tesla", "Model Y"]

    -- Timestamps
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    reviewed_at DATETIME,
    published_at DATETIME,

    INDEX idx_status (status),
    INDEX idx_categories (categories(100))
);
```

### wp_teinformez_delivery_log
```sql
CREATE TABLE wp_teinformez_delivery_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT,
    news_id BIGINT,
    channel ENUM('email', 'facebook_post', 'twitter_post', 'instagram_post'),
    status ENUM('pending', 'sent', 'failed', 'opened', 'clicked'),
    scheduled_for DATETIME,
    sent_at DATETIME,
    error_message TEXT,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID),
    FOREIGN KEY (news_id) REFERENCES wp_teinformez_news_queue(id)
);
```

---

## FAZA A: Sistem Înrolare Utilizatori

### A1. Plugin Base Structure
```
wp-content/plugins/teinformez-core/
├── teinformez-core.php              # Main plugin file
├── includes/
│   ├── class-activator.php          # Create tables on activation
│   ├── class-deactivator.php
│   ├── class-config.php             # Centralized config (LANGUAGE variable here!)
│   ├── class-user-manager.php
│   ├── class-subscription-manager.php
│   └── class-gdpr-handler.php
├── admin/
│   ├── class-admin-settings.php     # Admin panel settings
│   ├── class-news-review.php        # Review queue interface
│   └── views/
│       ├── settings-page.php
│       └── review-queue.php
├── public/
│   ├── class-registration-form.php
│   ├── class-user-dashboard.php
│   └── views/
│       ├── registration-form.php
│       ├── preferences-form.php
│       └── dashboard.php
├── assets/
│   ├── css/
│   └── js/
└── languages/
    ├── teinformez-ro_RO.po
    └── teinformez-en_US.po
```

### A2. Funcționalități Înrolare

1. **Formular Înregistrare**
   - Email + Parolă
   - Nume (opțional)
   - Checkbox GDPR obligatoriu cu link spre politică
   - Opțional: Social login (Google/Facebook) - later

2. **Onboarding Wizard (post-registration)**
   - Step 1: Selectează limba conținutului
   - Step 2: Selectează categorii principale (multi-select)
   - Step 3: Adaugă topicuri specifice (input tags: "Tesla", "Apple")
   - Step 4: Selectează țări/piețe de interes
   - Step 5: Configurează frecvența și ora livrării
   - Step 6: Alege canalele de livrare

3. **User Dashboard**
   - Editare preferințe oricând
   - Istoric știri primite
   - Manage subscriptions (add/remove topics)
   - Account settings (change email, password, delete account)

### A3. Categorii Predefinite (expandabile)

```php
$default_categories = [
    'juridic' => [
        'label' => __('Juridic cu Alina', 'teinformez'),
        'subcategories' => ['Dreptul muncii', 'Dreptul familiei', 'Drept comercial', 'Drept penal']
    ],
    'actualitate' => [
        'label' => __('Actualitate', 'teinformez'),
        'subcategories' => ['Breaking', 'Social', 'Educație', 'Cultură', 'România']
    ],
    'politics' => [
        'label' => __('Politică', 'teinformez'),
        'subcategories' => ['România', 'UE', 'SUA', 'Internațional']
    ],
    'international' => [
        'label' => __('Internațional', 'teinformez'),
        'subcategories' => ['Europa', 'SUA', 'Orientul Mijlociu', 'Asia', 'Africa']
    ],
    'justitie' => [
        'label' => __('Justiție', 'teinformez'),
        'subcategories' => ['Instanțe', 'DNA', 'Legislație', 'Cazuri penale']
    ],
    'business' => [
        'label' => __('Business', 'teinformez'),
        'subcategories' => ['Startup-uri', 'Corporate', 'Antreprenoriat', 'Economie']
    ],
    'finance' => [
        'label' => __('Finanțe', 'teinformez'),
        'subcategories' => ['Crypto', 'Bursă', 'Bănci', 'Imobiliare']
    ],
    'tech' => [
        'label' => __('Tehnologie', 'teinformez'),
        'subcategories' => ['Smartphone', 'Laptop', 'AI', 'Software', 'Gadget-uri']
    ],
    'sanatate' => [
        'label' => __('Sănătate', 'teinformez'),
        'subcategories' => ['Medicină', 'Nutriție', 'Fitness', 'Sănătate mintală']
    ],
    'science' => [
        'label' => __('Știință', 'teinformez'),
        'subcategories' => ['Spațiu', 'Medicină', 'Mediu', 'Cercetare']
    ],
    'sports' => [
        'label' => __('Sport', 'teinformez'),
        'subcategories' => ['Fotbal', 'Tenis', 'F1', 'Baschet']
    ],
    'entertainment' => [
        'label' => __('Divertisment', 'teinformez'),
        'subcategories' => ['Filme', 'Muzică', 'Gaming', 'Celebrități']
    ],
    'auto' => [
        'label' => __('Auto', 'teinformez'),
        'subcategories' => ['Mașini electrice', 'Clasice', 'Motorsport', 'Recenzii']
    ],
    'lifestyle' => [
        'label' => __('Lifestyle', 'teinformez'),
        'subcategories' => ['Travel', 'Food', 'Fashion', 'Home', 'Parenting']
    ],
    'opinii' => [
        'label' => __('Opinii', 'teinformez'),
        'subcategories' => ['Editoriale', 'Analize', 'Comentarii', 'Interviuri']
    ]
];
```

---

## FAZA B: Agregare și Procesare Știri

### B1. News Aggregator Module

```php
// RSS Sources (free, unlimited)
$rss_sources = [
    'romania' => [
        'hotnews' => 'https://www.hotnews.ro/rss',
        'digi24' => 'https://www.digi24.ro/rss',
        'mediafax' => 'https://www.mediafax.ro/rss',
        'zf' => 'https://www.zf.ro/rss'
    ],
    'tech' => [
        'techcrunch' => 'https://techcrunch.com/feed/',
        'theverge' => 'https://www.theverge.com/rss/index.xml',
        'arstechnica' => 'https://feeds.arstechnica.com/arstechnica/technology-lab'
    ],
    'international' => [
        'bbc' => 'https://feeds.bbci.co.uk/news/rss.xml',
        'reuters' => 'https://www.reutersagency.com/feed/'
    ]
];
```

### B2. AI Processing Pipeline

```
┌─────────────────────────────────────────────────────────────┐
│ 1. FETCH                                                     │
│    - Cron job every 30 min                                   │
│    - Pull from RSS/API/Scrapers                              │
│    - Deduplicate (check original_url)                        │
│    - Store raw in news_queue (status: fetched)               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. PROCESS (OpenAI)                                          │
│    - Detect original language                                │
│    - Summarize to max 150 words                              │
│    - Create social snippet (max 280 chars)                   │
│    - Extract categories/tags automatically                   │
│    - Translate to target language                            │
│    - Generate image IF no video exists (DALL-E)              │
│    - Store processed (status: pending_review)                │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. REVIEW (Admin - within X hours)                           │
│    - Admin dashboard shows pending stories                   │
│    - Edit title/content if needed                            │
│    - Approve / Reject / Request regeneration                 │
│    - Auto-approve after X hours (configurable)               │
│    - Status changes to: approved                             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. PUBLISH                                                   │
│    - Create WordPress post (for website)                     │
│    - Status: published                                       │
│    - Add to delivery queue for subscribed users              │
└─────────────────────────────────────────────────────────────┘
```

### B3. OpenAI Prompts Templates

```php
// Summarize & Rewrite (avoid copyright)
$prompt_summarize = "
You are a news editor. Given the following article, create:
1. A new headline (catchy, max 80 characters)
2. A summary (max 150 words, completely rewritten - no copied phrases)
3. A social media snippet (max 280 characters, engaging)

Rules:
- DO NOT copy any sentences from the original
- Rewrite everything in your own words
- Keep facts accurate
- Make it engaging and easy to read
- If video source exists, mention 'Vezi video: [source]'

Article:
{ORIGINAL_CONTENT}

Output format (JSON):
{
  'headline': '...',
  'summary': '...',
  'social_snippet': '...',
  'suggested_tags': ['tag1', 'tag2']
}
";

// Translation
$prompt_translate = "
Translate the following news article to {TARGET_LANGUAGE}.
Adapt cultural references if needed for {TARGET_COUNTRY} audience.
Keep the same structure and meaning.

Article:
{CONTENT}
";

// Image Generation (DALL-E)
$prompt_image = "
Create a news article thumbnail image for this headline: '{HEADLINE}'
Style: Modern, clean, journalistic photography style
Avoid: Text, logos, faces of real people
";
```

---

## FAZA C: Sistem de Livrare

### C1. Email Delivery (Brevo)

```php
class Email_Delivery {

    public function send_personalized_digest($user_id) {
        $user_prefs = $this->get_user_preferences($user_id);
        $stories = $this->get_matching_stories($user_prefs);

        $template = $this->render_email_template([
            'user_name' => $user_prefs->name,
            'stories' => $stories,
            'language' => $user_prefs->preferred_language,
            'unsubscribe_link' => $this->get_unsubscribe_link($user_id)
        ]);

        return $this->brevo->send([
            'to' => $user_prefs->email,
            'subject' => $this->get_subject_line($user_prefs->preferred_language),
            'html' => $template
        ]);
    }

    public function schedule_delivery($user_id, $time, $timezone) {
        // Add to WP Cron with user-specific time
        wp_schedule_single_event(
            $this->convert_to_utc($time, $timezone),
            'teinformez_send_digest',
            [$user_id]
        );
    }
}
```

### C2. Social Media Posting

```php
class Social_Poster {

    // Facebook Page Post (Graph API)
    public function post_to_facebook($story_id) {
        $story = $this->get_story($story_id);

        $post_data = [
            'message' => $story->social_snippet . "\n\n" . $story->permalink,
            'link' => $story->permalink
        ];

        if ($story->ai_generated_image_url) {
            $post_data['picture'] = $story->ai_generated_image_url;
        }

        return $this->facebook_api->post('/me/feed', $post_data);
    }

    // Twitter/X Post
    public function post_to_twitter($story_id) {
        $story = $this->get_story($story_id);

        $tweet = $story->social_snippet;
        if (strlen($tweet) > 250) {
            $tweet = substr($tweet, 0, 247) . '...';
        }
        $tweet .= "\n" . $story->short_url;

        return $this->twitter_api->tweet($tweet);
    }
}
```

### C3. Scheduler (WP Cron Enhanced)

```php
// Custom intervals
add_filter('cron_schedules', function($schedules) {
    $schedules['every_30_min'] = [
        'interval' => 1800,
        'display' => 'Every 30 minutes'
    ];
    return $schedules;
});

// Scheduled jobs
register_activation_hook(__FILE__, function() {
    // Fetch new content
    if (!wp_next_scheduled('teinformez_fetch_news')) {
        wp_schedule_event(time(), 'every_30_min', 'teinformez_fetch_news');
    }

    // Process with AI
    if (!wp_next_scheduled('teinformez_process_news')) {
        wp_schedule_event(time(), 'every_30_min', 'teinformez_process_news');
    }

    // Check delivery queue
    if (!wp_next_scheduled('teinformez_check_deliveries')) {
        wp_schedule_event(time(), 'every_30_min', 'teinformez_check_deliveries');
    }
});
```

---

## GDPR Compliance

### Formular Consent (obligatoriu la înregistrare)

```html
<div class="gdpr-consent">
    <label>
        <input type="checkbox" name="gdpr_consent" required>
        <span>
            Accept <a href="/politica-confidentialitate" target="_blank">Politica de Confidențialitate</a>
            și sunt de acord ca datele mele să fie procesate pentru a primi știri personalizate.
            Pot să mă dezabonez oricând.
        </span>
    </label>
</div>
```

### Pagini Obligatorii
- `/politica-confidentialitate` - Privacy Policy
- `/termeni-si-conditii` - Terms & Conditions
- `/gdpr-drepturi` - GDPR Rights (access, delete, export data)

### Funcționalități GDPR
- Export data (user can download all their data)
- Delete account (complete removal)
- Unsubscribe link in every email
- Consent tracking (date, IP, version of policy)

---

## Social Sharing (Viralitate)

### Share Buttons pe fiecare știre
```php
$share_links = [
    'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($permalink),
    'twitter' => 'https://twitter.com/intent/tweet?text=' . urlencode($title) . '&url=' . urlencode($permalink),
    'linkedin' => 'https://www.linkedin.com/shareArticle?mini=true&url=' . urlencode($permalink),
    'whatsapp' => 'https://wa.me/?text=' . urlencode($title . ' ' . $permalink),
    'telegram' => 'https://t.me/share/url?url=' . urlencode($permalink) . '&text=' . urlencode($title),
    'copy_link' => $permalink
];
```

### Referral System (opțional, pentru viralitate)
- Fiecare user primește un link unic de referral
- Bonus: dacă invită X prieteni, primește features premium
- Leaderboard cu top referrers

---

## Configurare Multilingvă (pentru clonare)

### Variabile de limbă în config
```php
// includes/class-config.php

class TeInformez_Config {

    // CHANGE THIS WHEN CLONING TO NEW COUNTRY
    const SITE_LANGUAGE = 'ro';  // Primary site language
    const SITE_COUNTRY = 'Romania';
    const SITE_TIMEZONE = 'Europe/Bucharest';

    // Available content languages
    const AVAILABLE_LANGUAGES = ['ro', 'en', 'de', 'fr', 'es', 'it'];

    // Translation service
    const TRANSLATION_PROVIDER = 'openai'; // or 'deepl', 'google'

    // Get all translatable strings
    public static function get_strings() {
        return [
            'site_name' => __('TeInformez', 'teinformez'),
            'tagline' => __('Știri personalizate, livrate când vrei tu', 'teinformez'),
            'register_cta' => __('Înregistrează-te gratuit', 'teinformez'),
            // ... all UI strings
        ];
    }
}
```

### Clonare pe alt domeniu
1. Export baza de date
2. Schimbă `SITE_LANGUAGE`, `SITE_COUNTRY`, `SITE_TIMEZONE`
3. Traduce fișierele .po/.mo
4. Configurează sursele RSS/API pentru noua țară
5. Deploy

---

## TODO - Pași Implementare

### ✅ Sprint 1: Foundation (COMPLETED - 19 Ian 2026)
- [x] Creare structură plugin WordPress
- [x] Setup tabele custom în baza de date
- [x] Implementare sistem de configurare (language as variable)
- [x] Formular înregistrare cu GDPR
- [x] Pagină login/logout
- [x] REST API pentru auth și user management
- [x] Next.js frontend cu TypeScript
- [x] Homepage + Landing page
- [x] API client cu Axios
- [x] Auth store cu Zustand
- [x] Deployment guide complet

**Status**: ✅ Backend și Frontend base sunt gata pentru deployment!

### ✅ Sprint 2: User Preferences (COMPLETED - 21 Ian 2026)
- [x] Onboarding wizard (4 steps)
  - [x] Step 1: Selectare categorii
  - [x] Step 2: Adăugare topicuri specifice
  - [x] Step 3: Frecvență și timezone
  - [x] Step 4: Canale de livrare
  - [x] Final: Bulk save subscriptions
- [x] User dashboard
  - [x] Overview cu stats
  - [x] Manage subscriptions (CRUD + toggle)
  - [x] Edit preferințe (setări)
  - [x] Account settings (export data, delete account)
- [x] Categorii și topicuri selectabile
- [x] Setări frecvență și canale

### ✅ Sprint 3: News Aggregation (COMPLETED - 26 Ian 2026)
- [x] RSS Parser (10 surse preconfigurate)
- [ ] News API integration (NewsAPI, GNews) — SKIPPED (RSS sufficient)
- [ ] Web scraper base class — SKIPPED
- [x] Deduplication logic
- [x] Cron jobs pentru fetch (every 30 min)
- [x] Categorization automat cu AI

### ✅ Sprint 4: AI Processing (COMPLETED - 26 Ian 2026)
- [x] OpenAI integration complet
- [x] Summarization pipeline
- [x] Translation service
- [x] Image generation (DALL-E)
- [x] Admin review queue (UI + logic)
- [x] Auto-publish după review period

### ✅ Sprint 5: Delivery System (COMPLETED - Phase C)
- [x] Email delivery to subscribers (scheduled digests)
- [x] Email templates (HTML responsive newsletter)
- [x] Personalized digest generator
- [x] Delivery scheduler (timezone-aware, Brevo + wp_mail fallback)
- [x] Delivery logs și statistics
- [ ] Social media posting (Facebook, Twitter) — deferred

### ✅ Sprint 6 (partial): Polish (COMPLETED - 28 Feb 2026)
- [x] Share button (Web Share API)
- [x] Social share buttons (Facebook, Twitter, WhatsApp, Telegram, LinkedIn)
- [x] Change password / change email in settings
- [x] Stats page with real data
- [x] Legal pages (Privacy, Terms, GDPR rights)
- [x] VPS2 deployment (PHP-FPM + MariaDB + Nginx + SSL)
- [ ] SEO optimization
- [ ] Performance optimization
- [ ] Load testing
- [ ] Soft launch (beta users)

---

## 📊 Current Status (Martie 2026)

### ✅ Completat
1. **Backend WordPress Plugin** — 48 API endpoints, 5 DB tables
2. **Frontend Next.js** — 20 pages, auth flow, dashboard, news, juridic, GDPR
3. **Phase A** — User registration, onboarding, dashboard (100%)
4. **Phase B** — News aggregation, AI processing, admin review (100%)
5. **Phase C** — Email delivery system, timezone-aware scheduling (100%)
6. **Phase D (partial)** — Analytics + view tracking done, SEO done
7. **Phase E** — Juridic section, Telegram integration, social posting
8. **Deployment** — VPS2 (WordPress + Next.js standalone + SSL), both on same server

### 📅 Remaining
- Phase D completion: performance optimization, load testing
- Soft launch (beta users)

---

## 🎯 Next Immediate Steps

1. **Performance optimization** — load testing, caching
2. **SEO refinement** — meta tags, sitemap
3. **Soft launch** — beta users, monitoring
4. **Social media automation** — Facebook/Twitter auto-posting (deferred)

---

## ON HOLD (pentru mai târziu)
- [ ] Meta Business API (Instagram/Facebook Messaging)
- [ ] Monetizare (reclame targetate)
- [ ] Referral system
- [ ] Mobile app
- [ ] Push notifications

---

## Note Tehnice

### Dependențe WordPress
- PHP 8.0+
- WordPress 6.0+
- MySQL 8.0+ sau MariaDB 10.5+

### Plugin-uri recomandate
- **Polylang** - pentru multilingvism frontend
- **WP Mail SMTP** - pentru configurare email
- **Wordfence** - securitate

### API Keys necesare
- OpenAI API Key
- Brevo API Key
- Facebook App (pentru posting)
- Twitter Developer App (pentru posting)
- (Later) NewsAPI.org, GNews.io

---

*Document creat: Ianuarie 2026*
*Ultima actualizare: Martie 2026*
