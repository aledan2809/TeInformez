# TeInformez Security Audit — Gap Analysis

**Date:** 2026-04-16 (updated 2026-05-12)
**Plugin:** TeInformez Core v1.0.0
**Scope:** Static code analysis of `backend/wp-content/plugins/teinformez-core/`
**Mode:** AUDIT-ONLY → DIRECT FIX (H-01–H-08 fixed 2026-05-11; Sprint 1 fixed 2026-05-11; Sprint 2 fixed 2026-05-12; Sprint 3 fixed 2026-05-12; Sprint 4 fixed 2026-05-12; Sprint 5 fixed 2026-05-12; Sprint 5 review fixes `564a983` 2026-05-12)
**Target:** WordPress 6.0+ / PHP 8.0+

---

## Eliminated Gaps

| Gap | Status | Commit | Date |
|-----|--------|--------|------|
| C-01 | Eliminated | VPS deploy 2026-05-11 | 2026-05-11 |
| H-01 | Eliminated | VPS deploy 2026-05-11 | 2026-05-11 |
| H-02 | Eliminated | VPS deploy 2026-05-11 | 2026-05-11 |
| H-03 | Eliminated | VPS deploy 2026-05-11 | 2026-05-11 |
| H-04 | Eliminated | `is_admin()` + `is_authenticated→is_admin` on `/admin/analytics` | 2026-05-11 |
| H-05 | Eliminated | `is_admin()` on all 5 juridic admin endpoints | 2026-05-11 |
| H-06 | Eliminated | `is_admin()` on all 5 telegram endpoints | 2026-05-11 |
| H-07 | Eliminated | AES-256-CBC encrypt/decrypt_token() in class-telegram-api.php | 2026-05-11 |
| H-08 | Eliminated | `current_user_can('manage_options')` in juridic-queue.php + news-queue.php | 2026-05-11 |
| M-03 | Eliminated | `a6ed79d` — is_authenticated required + validate_auth_token() + user ownership check | 2026-05-11 |
| M-07 | Eliminated | `a6ed79d` — reset link removed from error_log in send_password_reset() | 2026-05-11 |
| M-08 | Eliminated | `a6ed79d` — field whitelist blocks GDPR column overwrite; Sprint 3 — null vs false semantic: empty-after-whitelist now returns null→400 instead of false→500 | 2026-05-12 |
| M-14 | Eliminated | Sprint 2 — `check_cookie_csrf()` defined + wired on all 12 write endpoints; review fix `7953260` — nonce read from body only (`get_body_params()`) + Bearer/cookie invariant doc | 2026-05-12 |
| M-15 | Eliminated | Sprint 2 — `count() > 50` cap + 400 error in bulk_add_subscriptions() | 2026-05-12 |
| M-16 | Eliminated | Sprint 3 — full double opt-in: change_email() stores pending_email/token/expires in user_meta + sends confirmation email; new GET confirm-email-change route validates hash_equals token + expiry + re-checks email availability before applying wp_update_user | 2026-05-12 |
| M-05 | Eliminated | Sprint 4 — `*.vercel.app` wildcard removed from ALLOWED_ORIGINS; only `teinformez.vercel.app` specific URL retained | 2026-05-12 |
| M-09 | Eliminated | Sprint 4 — `'reject_unsafe_urls' => true` added to both `wp_remote_get()` calls in fetch_rss() and fetch_page_html(); blocks private IPs, loopback, link-local (169.254.x.x) | 2026-05-12 |
| M-10 | Eliminated | Sprint 4 — `simplexml_load_string()` now uses `LIBXML_NONET` flag; blocks network access during XML entity resolution | 2026-05-12 |
| M-11 | Eliminated | Sprint 4 — AI-generated title/summary/content sanitized via `sanitize_text_field()` / `sanitize_textarea_field()` / `wp_kses_post()` in both class-ai-processor.php and class-chief-editor.php (6 call-sites) | 2026-05-12 |
| M-13 | Eliminated | Sprint 4 — HAVING clause in social-poster retry query wrapped in `$wpdb->prepare()` with `%d` placeholder | 2026-05-12 |
| M-17 | Eliminated | Sprint 4 — localhost:3000 and localhost:3002 removed from ALLOWED_ORIGINS const; added conditionally in get_allowed_origins() only when `wp_get_environment_type() !== 'production'` | 2026-05-12 |
| G3 | Eliminated | Sprint 5 — verified no fixed-bottom overlap issue; SoftRegistrationBanner.tsx is dead code (never imported); no pb-* needed | 2026-05-12 |
| G4 | Eliminated | `4cb0859` — aria-label added to SharedHeader nav + Sidebar nav; axe-core landmark-unique resolved | 2026-05-12 |
| M-01 | Eliminated | `ea02951` — register() returns HTTP 200 + null data for existing email; frontend null-guard on response?.user | 2026-05-12 |
| M-02 | Eliminated | `c27054b` — validate_password_strength() in REST_API base; register/reset_password/change_password all enforce full 5-rule policy | 2026-05-12 |
| M-04 | Eliminated | `0c4e0a8` — rate limit 60/min per IP on /analytics/track; check_rate_limit+clear_rate_limit promoted to REST_API base with configurable params | 2026-05-12 |
| M-06 | Eliminated | `1b73b9b` — get_client_ip() takes rightmost XFF entry only when REMOTE_ADDR is loopback/private; is_trusted_proxy() helper added | 2026-05-12 |
| M-12 | Eliminated | `fb15a12` — Config::validate_ai_router_url() guards both AI_Processor + Chief_Editor; http only for localhost, https for any external host | 2026-05-12 |
| Sprint 5 review fixes | Eliminated | `564a983` — string type hint; WP_REST_Response Retry-After; IPv6 brackets trim; dev comment removed from authStore | 2026-05-12 |

**Evidence H-04–H-08**: E8 test (2026-05-11): unauthenticated GET /admin/analytics → HTTP 401; reader → HTTP 403; admin → HTTP 200.
**Note M-07**: Fully eliminated — `send_via_brevo()` and `send_newsletter_confirmation()` now log `*@domain.com` only (see commit below).

---

## New Gaps (TRUE E2E FULL 2026-05-11)

| Gap | Severity | Description | Status |
|-----|----------|-------------|--------|
| G-TI-NEW-001 | LOW | `/profile` and `/settings` frontend routes return 404 — not implemented | Eliminated — redirects added in `next.config.js` (see commit below) |
| G-TI-NEW-005 | MEDIUM | `dangerouslySetInnerHTML` without DOMPurify in 4 frontend files: `page.tsx:52,56`, `news/[id]/NewsDetailClient.tsx:404`, `juridic/page.tsx:79`, `juridic/[id]/JuridicDetailClient.tsx:105` — WordPress content rendered raw, no client-side sanitization | Open |
| G-TI-NEW-002 | INFO | Dashboard SSR delivers 112-byte loading skeleton — journey audit tooling sees EMPTY | Accepted — authenticated-only page; SSR skeleton is correct behaviour, not a content gap |
| G-TI-NEW-003 | INFO | Journey audit `/terms` GATED false positive — onboarding marker matches word in content | Accepted — `/terms` is publicly accessible; tooling pattern match on "Setările Contului" text inside page |
| G-TI-NEW-004 | INFO | Journey audit `/news` HAS_ERRORS false positive — Next.js RSC JSON payload | Accepted — RSC payload in Next.js App Router is expected, not an application error |

---

---

## Summary

| Severity       | Count |
|----------------|-------|
| Critical       | 1     |
| High           | 8     |
| Medium         | 17    |
| Low            | 12    |
| Informational  | 6     |
| **Total**      | **44** |

---

## Critical

### C-01: IDOR — `update_subscription()` has no user ownership check

- **Location:** `api/class-user-api.php:251-263`, `includes/class-subscription-manager.php:75-89`
- **Description:** `update_subscription()` updates a subscription by `id` alone without verifying `user_id`. Any authenticated user can modify any other user's subscription by iterating IDs. Combined with H-01 (no field whitelisting), an attacker can also set `user_id` to take full ownership.
- **Impact:** Full subscription takeover for any user.
- **Recommendation:** Add `$user_id` parameter and include `'user_id' => $user_id` in the WHERE clause of `$wpdb->update()`, matching the pattern in `delete_subscription()` and `toggle_subscription()`.

---

## High

### H-01: Arbitrary column injection via unsanitized `$data` in `update_subscription()`

- **Location:** `includes/class-subscription-manager.php:75-89`
- **Description:** `$data` from `$request->get_json_params()` is passed directly to `$wpdb->update()`. Attacker can include arbitrary column names (`user_id`, `is_active`, `created_at`) to overwrite protected fields.
- **Recommendation:** Whitelist allowed update fields: `$allowed = ['category_slug', 'source_filter', 'frequency']; $data = array_intersect_key($data, array_flip($allowed));`

### H-02: No rate limiting on authentication endpoints

- **Location:** `api/class-auth-api.php:20-31, 55-58`
- **Description:** Login, registration, and password reset endpoints have no rate limiting or account lockout. Enables brute-force, credential stuffing, and email bombing via password reset.
- **Recommendation:** Implement rate limiting using WordPress transients (e.g., max 5 failed logins per IP per 15 min). Lock accounts after N failed attempts.

### H-03: Token non-revocability — no server-side invalidation

- **Location:** `api/class-auth-api.php:233-235, 281-294`
- **Description:** Auth tokens are stateless HMAC-signed with no server-side storage. `logout()` clears WordPress session cookie but cannot invalidate the Bearer token. Stolen tokens remain valid until expiry (24h). Password changes also don't invalidate tokens.
- **Recommendation:** Store tokens server-side with a revocation mechanism, or include per-user secret (hash of password hash) in the HMAC.

### H-04: Missing capability checks on admin analytics endpoint

- **Location:** `api/class-news-api.php:95-99`
- **Description:** `/admin/analytics` uses `is_authenticated` (login check only). Any subscriber-level user can access platform analytics including user counts, delivery stats, and top articles.
- **Recommendation:** Replace with callback checking `current_user_can('manage_options')`.

### H-05: Missing capability checks on Juridic CRUD endpoints

- **Location:** `api/class-juridic-api.php:43-82`
- **Description:** All admin juridic endpoints (create, update, delete, import, publish-social) only verify login, not admin role. Any authenticated user can create/modify/delete juridic entries and trigger social media publishing.
- **Recommendation:** Add `current_user_can('manage_options')` or `current_user_can('edit_posts')` check.

### H-06: Missing capability checks on all Telegram endpoints

- **Location:** `api/class-telegram-api.php:17-46`
- **Description:** All 5 Telegram endpoints only check login. Any subscriber can read/write bot tokens, discover groups, read messages, and send messages as the bot.
- **Recommendation:** Restrict all Telegram endpoints to `current_user_can('manage_options')`.

### H-07: Telegram bot token stored in plaintext

- **Location:** `api/class-telegram-api.php:81, 283-285`
- **Description:** Bot token is stored as plaintext in `user_meta`. Database compromise exposes full bot control.
- **Recommendation:** Encrypt before storing using a custom wrapper with `AUTH_KEY` or `wp_encrypt()`.

### H-08: Missing capability checks in admin form handlers

- **Location:** `admin/views/juridic-queue.php:8-71`, `admin/views/news-queue.php:16-57`
- **Description:** Both form handlers verify nonces but never check `current_user_can('manage_options')` before performing save/delete/publish operations. While menu registration requires the capability, a CSRF-like attack targeting the POST handler could bypass menu-level checks.
- **Recommendation:** Add `if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }` after nonce verification.

---

## Medium

### M-01: Email enumeration via registration endpoint

- **Location:** `api/class-auth-api.php:107-114`
- **Description:** Returns specific `email_exists` error (HTTP 409) allowing email enumeration. The forgot-password endpoint correctly returns a generic message.
- **Recommendation:** Return generic response or use a two-step verification flow.

### M-02: Inconsistent password validation

- **Location:** `api/class-auth-api.php:389-395` (reset), `api/class-user-api.php:364` (change)
- **Description:** Registration enforces uppercase, lowercase, digit, and special character requirements. Password reset and change only check `strlen >= 8`.
- **Recommendation:** Extract password validation into a shared method and use consistently.

### M-03: `set-secure-cookie` endpoint accepts unvalidated tokens

- **Location:** `api/class-auth-api.php:455-500`
- **Description:** Public endpoint (`__return_true`) validates token format with regex but never calls `validate_auth_token()`. Attacker can set arbitrary cookie values. Regex also rejects valid base64 characters (`+`, `/`, `=`).
- **Recommendation:** Require authentication and validate token via `validate_auth_token()`.

### M-04: Analytics tracking endpoint abusable

- **Location:** `api/class-analytics-api.php:14-19`
- **Description:** `/analytics/track` is fully public with no rate limiting. Attacker can flood analytics table causing storage exhaustion or data skewing.
- **Recommendation:** Add per-IP rate limiting via transients.

### M-05: Overly permissive CORS wildcard `*.vercel.app`

- **Location:** `includes/class-config.php:48, 246-272`
- **Description:** Any Vercel deployment (e.g., `evil-phishing.vercel.app`) passes the CORS origin check, enabling cross-origin authenticated requests.
- **Recommendation:** Restrict to `teinformez-*.vercel.app` or list specific deployment URLs.

### M-06: IP spoofing via `X-Forwarded-For`

- **Location:** `includes/class-config.php:283-298`
- **Description:** `get_client_ip()` trusts `X-Forwarded-For` unconditionally. Used for GDPR consent evidence — audit trail can be forged. Also bypasses any IP-based rate limiting.
- **Recommendation:** Only trust from known proxy IPs or use Nginx-set trusted header. Parse only the rightmost entry.

### M-07: Password reset links logged to `error_log`

- **Location:** `includes/class-email-sender.php:43-46, 149-151`
- **Description:** Logs recipient email and explicit `Reset link: <url>` containing password reset token. Log exposure enables account takeover.
- **Recommendation:** Remove reset link logging. Redact emails in logs.

### M-08: Missing field whitelist in `update_preferences()`

- **Location:** `includes/class-user-manager.php:68-87`
- **Description:** `$data` passed directly to `$wpdb->update()` without key filtering. Attacker can overwrite `gdpr_consent`, `gdpr_consent_date`, `gdpr_ip_address` columns, forging GDPR consent records.
- **Recommendation:** Whitelist allowed keys: `['preferred_language', 'delivery_channels', 'delivery_schedule']`.

### M-09: SSRF via admin-controlled RSS source URLs

- **Location:** `includes/class-news-fetcher.php:98-102, 259-266, 510-516`
- **Description:** `wp_remote_get()` fetches URLs from RSS sources and article links without blocking private/reserved IP ranges. Compromised source config or attacker-controlled RSS links can target internal services (`169.254.169.254`, `127.0.0.1`).
- **Recommendation:** Validate URL scheme (HTTPS only) and block private IP ranges. Add `'reject_unsafe_urls' => true`.

### M-10: XXE risk in RSS XML parsing

- **Location:** `includes/class-news-fetcher.php:122-131`
- **Description:** `simplexml_load_string()` without `LIBXML_NONET` flag. On PHP < 8.0, no `libxml_disable_entity_loader(true)` call. Malicious RSS could trigger XXE for local file read or SSRF.
- **Recommendation:** Use `simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET)`. Add `libxml_disable_entity_loader(true)` for PHP < 8.0.

### M-11: Stored XSS via unsanitized AI-generated content

- **Location:** `includes/class-ai-processor.php:125-134`, `includes/class-chief-editor.php:98-137`
- **Description:** AI response fields (`title`, `summary`, `content`) stored verbatim without sanitization. Prompt injection in source articles could cause AI to return HTML/JS payloads.
- **Recommendation:** Apply `sanitize_text_field()` to title, `wp_kses_post()` to content, `sanitize_textarea_field()` to summary before storage.

### M-12: AI Router configurable to non-TLS endpoints

- **Location:** `includes/class-ai-processor.php:27`, `includes/class-chief-editor.php:26`
- **Description:** Default AI Router URL is `http://127.0.0.1:3100/api/ai/chat`. If changed to a remote host over HTTP, API keys and article content transmit in cleartext.
- **Recommendation:** Validate HTTPS if not localhost. Add admin UI warning.

### M-13: SQL constant concatenation without `prepare()`

- **Location:** `includes/class-social-poster.php:242-251`
- **Description:** `Config::SOCIAL_MAX_RETRY` concatenated directly into SQL HAVING clause. Safe while constant, fragile if ever sourced from options.
- **Recommendation:** Use `$wpdb->prepare("... HAVING attempt_count < %d", Config::SOCIAL_MAX_RETRY)`.

### M-14: CSRF on REST endpoints — no nonce verification for cookie auth

- **Location:** `api/class-rest-api.php:27-53`
- **Description:** No REST endpoints verify WordPress nonces. Cookie-authenticated sessions are vulnerable to CSRF on state-changing requests (POST/PUT/DELETE).
- **Recommendation:** Require `X-WP-Nonce` header for cookie-based auth. Bearer token auth is not affected.

### M-15: No size limit on bulk subscription endpoint

- **Location:** `api/class-user-api.php:228-246`
- **Description:** `bulk_add_subscriptions()` accepts an unbounded array of subscriptions with no size limit. An authenticated user can submit thousands of entries in a single request, causing database write exhaustion and potential DoS.
- **Recommendation:** Cap array size (e.g., `if (count($params['subscriptions']) > 50) { return error; }`).

### M-16: Email change without confirmation — immediate account takeover risk

- **Location:** `api/class-user-api.php:380-416`
- **Description:** `change_email()` immediately updates `user_email` and `user_login` after password verification, with no confirmation email sent to the new address. If the new email is a typo or an attacker's email, the user loses access permanently. Combined with email enumeration (M-01), this aids targeted takeover.
- **Recommendation:** Send verification link to new email. Only apply the change after confirmation. Keep old email active until confirmed.

### M-17: Localhost origins in production CORS whitelist

- **Location:** `includes/class-config.php:42-49`
- **Description:** `ALLOWED_ORIGINS` includes `http://localhost:3000` and `http://localhost:3002` as hardcoded constants. In production, these allow any local process on the server (or browser extensions redirecting through localhost) to make authenticated cross-origin requests.
- **Recommendation:** Move origins to environment-based configuration. Exclude localhost in production builds.

---

## Low

### L-01: Token relies solely on site-wide `AUTH_KEY`

- **Location:** `api/class-auth-api.php:290, 320`
- **Description:** Key rotation invalidates all sessions. Key compromise enables forging tokens for any user.
- **Recommendation:** Use a dedicated secret or add per-user component.

### L-02: No separate refresh token mechanism

- **Location:** `api/class-auth-api.php:260-276`
- **Description:** `/auth/refresh` reuses the same access token format. No additional security over longer-lived access tokens.
- **Recommendation:** Implement opaque, server-side stored refresh tokens.

### L-03: Password reset token stored in plaintext in user meta

- **Location:** `api/class-auth-api.php:360-361`
- **Description:** Database compromise exposes unexpired reset tokens.
- **Recommendation:** Store `hash('sha256', $reset_token)` and compare hashes during validation.

### L-04: `sanitize_text_field()` may corrupt base64 tokens

- **Location:** `api/class-auth-api.php:386, 470`
- **Description:** Strips tags, encodes special chars. Base64 tokens contain `+`, `/`, `=` which can be corrupted.
- **Recommendation:** Use regex validation instead: `preg_replace('/[^A-Za-z0-9+\/=|]/', '', $token)`.

### L-05: View count manipulation — no rate limiting

- **Location:** `api/class-news-api.php:439-450`, `api/class-juridic-api.php:158-160`
- **Description:** Public view tracking endpoints with no deduplication or rate limiting. Juridic `get_single()` also double-counts by auto-incrementing on GET.
- **Recommendation:** Deduplicate via cookie/session. Remove auto-increment from `get_single()`.

### L-06: Missing REST route `args` schema validation

- **Location:** `api/class-news-api.php:39-92` (all routes)
- **Description:** No route-level `args` with `sanitize_callback`/`validate_callback`. Relies on manual validation in callbacks.
- **Recommendation:** Add `args` definitions for defense-in-depth and auto-documentation.

### L-07: Weak PII anonymization in juridic questions

- **Location:** `api/class-juridic-api.php:450-463`
- **Description:** Regex misses Romanian diacritics (e.g., "Ionut Stefanescu"), single names, CNP (personal ID numbers), and addresses.
- **Recommendation:** Use unicode-aware patterns. Add CNP/address detection.

### L-08: Optional `$user_id` in `delete_subscription()` defaults to null

- **Location:** `includes/class-subscription-manager.php:94-105`
- **Description:** If any caller omits `$user_id`, ownership check is skipped.
- **Recommendation:** Make `$user_id` required (no default value).

### L-09: Race condition in cron job item claiming

- **Location:** `includes/class-ai-processor.php:44-46`, `includes/class-news-publisher.php:202-204`
- **Description:** SELECT then UPDATE pattern allows concurrent cron workers to process same items, causing duplicate AI API calls and doubled costs.
- **Recommendation:** Use atomic `UPDATE ... WHERE status='fetched' LIMIT 10` then SELECT claimed items.

### L-10: Unescaped integer IDs in admin view output

- **Location:** `admin/views/juridic-queue.php:211,225,230,238`, `admin/views/news-queue.php:137,329`
- **Description:** `$item->id` echoed without `esc_attr()`/`intval()` in hidden fields and display.
- **Recommendation:** Use `intval($item->id)` or `esc_attr($item->id)`.

### L-11: Unsanitized `$_POST['action']` in switch statement

- **Location:** `admin/views/news-queue.php:24`
- **Description:** `$_POST['action']` used directly in switch without `sanitize_text_field()`.
- **Recommendation:** Sanitize before use.

### L-12: `ga4_private_key` saved without format validation

- **Location:** `admin/class-admin.php:212-215`
- **Description:** Only `trim()` and `wp_unslash()` applied. No PEM format validation.
- **Recommendation:** Validate PEM format (`-----BEGIN...-----END`) or use `sanitize_textarea_field()`.

---

## Informational

### I-01: No re-authentication for account deletion

- **Location:** `api/class-user-api.php:325-343`
- **Description:** `/user/delete` permanently deletes account without password confirmation. Stolen token enables irreversible deletion.
- **Recommendation:** Require current password as confirmation.

### I-02: No application-level CORS enforcement

- **Location:** All API files
- **Description:** CORS configured only at Nginx level. No defense-in-depth via `rest_pre_serve_request` filter.
- **Recommendation:** Add application-level CORS headers as fallback.

### I-03: No foreign key constraints on database tables

- **Location:** `includes/class-activator.php:21-264`
- **Description:** No FK constraints. WordPress user deletion outside GDPR flow leaves orphaned PII records.
- **Recommendation:** Hook into `delete_user` action to trigger cleanup.

### I-04: GDPR consent IP stored in plaintext

- **Location:** `includes/class-gdpr-handler.php:30`
- **Description:** IP addresses are PII under GDPR. No retention policy or hashing.
- **Recommendation:** Hash IP or set retention policy for purging.

### I-05: Google service account private key in `wp_options`

- **Location:** `includes/class-google-analytics-service.php:101-141`
- **Description:** Database breach exposes Google API credentials.
- **Recommendation:** Store on filesystem with restricted permissions or use `wp-config.php` constants.

### I-06: Static `$authenticated_user_id` property in REST API base

- **Location:** `api/class-rest-api.php:14, 47`
- **Description:** Static property shared across instances. In persistent PHP workers, could theoretically leak auth state between requests.
- **Recommendation:** Reset to `null` at start of `is_authenticated()`.

---

## Positive Observations

- All files include `defined('ABSPATH')` direct access prevention.
- Admin forms use `wp_nonce_field()` / `wp_verify_nonce()` for CSRF protection.
- Most database queries use `$wpdb->prepare()` with parameterized placeholders.
- `class-visitor-analytics.php` has excellent input validation (whitelisted event types, sanitization, length limits).
- `class-news-publisher.php` uses `sanitize_text_field()` / `wp_kses_post()` for manual content edits.
- `class-delivery-handler.php` uses `esc_html()` / `esc_url()` in email HTML generation.
- No hardcoded API keys — all credentials loaded from Config/options.
- No command injection patterns found.
- No unsafe `eval()`, `extract()`, or `unserialize()` usage detected.
- Google Analytics JWT implementation uses proper RS256 signing.
