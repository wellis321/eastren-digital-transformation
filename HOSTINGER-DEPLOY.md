# Hostinger deployment — with login

The live site must run **PHP + MySQL**. Uploading HTML alone will **not** enforce login.

**Your site:** `https://aqua-quetzal-992173.hostingersite.com`

## 1. Upload these files/folders

Upload everything **except** `.env`, `documents/`, and `.git/`.

**Critical for auth (often missed on Hostinger):**

| File | Why |
|------|-----|
| `.htaccess` | Routes `.html` through `router.php` |
| `index.php` | Auth gate for `/` — **was 404 on live site** |
| `router.php` | Session check before serving pages |
| `login.php`, `logout.php`, `includes/`, `auth/` | Login flow |

After deploy: `https://yoursite.com/index.php` must **not** return 404.  
Then clear **hPanel → Cache Manager → Clear cache**.

## 2. Create MySQL database (hPanel)

1. **Databases → MySQL Databases** — create a database and user (note the `u123456_` prefix).
2. Open **phpMyAdmin** and run `sql/auth-tables.sql`.
   Optional tools: `sql/feedback-table.sql`, `sql/qrcode-table.sql` (also auto-created on first use).
3. Add at least one user. Generate a bcrypt hash locally:

```bash
php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
```

Then in phpMyAdmin:

```sql
INSERT INTO users (username, password_hash, auth_provider, app_role, is_active)
VALUES ('admin', 'PASTE_HASH_HERE', 'local', 'admin', 1);
```

**Tip:** If sor-system is already on Hostinger with users in the same database, point this site at that database — same logins will work.

## 3. Create `.env` on the server

In **File Manager**, create `.env` in the site root (same folder as `index.php`):

```
APP_ENV=production
APP_URL=https://aqua-quetzal-992173.hostingersite.com
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u248320297_sor_management
DB_USER=u248320297_sor_management
DB_PASS="your_password_here"
SESSION_SECRET=use_a_long_random_string_here
```

**Important:**
- No trailing slash on `APP_URL` (optional — code trims it, but avoid it).
- Wrap `DB_PASS` in **double quotes** if it contains `#`, `>`, or spaces.
- Set `APP_ENV=production` so session cookies work correctly on HTTPS.
- Use the **same database** as sor-system if you want the same usernames/passwords.
- After failed login attempts during setup, run `sql/clear-login-attempts.sql` in phpMyAdmin.

## 4. Clear cache

**Website → Cache Manager → Clear cache** in hPanel, then hard-refresh (Cmd+Shift+R).

## 5. Verify

| URL | Expected |
|-----|----------|
| `https://aqua-quetzal-992173.hostingersite.com/` | Redirect to `login.php` |
| `https://aqua-quetzal-992173.hostingersite.com/index.html` | Redirect to `login.php` |
| `https://aqua-quetzal-992173.hostingersite.com/login.php` | Sign-in form |
| After sign-in | Home page loads |

## Troubleshooting

**logout.php / login.php downloads instead of running**

This is a Hostinger/LiteSpeed issue. The old `.htaccess` used per-file `RewriteRule ^logout\.php$ - [L]` rules, which caused LiteSpeed to serve PHP files via the static file handler (download) instead of the PHP interpreter. The current `.htaccess` uses a single blanket condition (`RewriteCond %{REQUEST_URI} \.php$`) which avoids this. If you see a download: re-upload `.htaccess` and clear hPanel cache.

**Site skips login and goes straight to home page**

Two possible causes — fix both:
1. `.htaccess` not uploaded or not applied (see below). Without it, `.html` files bypass `router.php` and are served with no auth check.
2. User already has a session from the SOR system (both sites share the `sor_session` cookie on the same Hostinger host). This is intentional SSO, but can surprise users who expect separate logins.

**Still no login page**

- Confirm `router.php`, `login.php`, and `includes/` are uploaded.
- Confirm `.htaccess` is uploaded (File Manager may hide dotfiles — enable “Show hidden files”).
- Confirm PHP is enabled (Hostinger shared hosting includes PHP by default).

**“Database connection failed”**

- Check `DB_NAME`, `DB_USER`, `DB_PASS` in `.env` match hPanel exactly (including `u123456_` prefix).
- `DB_HOST` is usually `localhost` on Hostinger.

**Login works locally but not on Hostinger**

- `APP_URL` must match the live URL exactly (https, no trailing slash).
- Users table must exist on the **Hostinger** database, not only on your Mac.

## Microsoft Entra SSO (optional)

Same as sor-system: `composer install`, set `ENTRA_*` in `.env`, add redirect URI:

`https://aqua-quetzal-992173.hostingersite.com/auth/microsoft/callback.php`
