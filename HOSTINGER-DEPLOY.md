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
DB_NAME=u248320297_eastren
DB_USER=u248320297_eastren
DB_PASS=your_db_password_from_hpanel
SESSION_SECRET=use_a_long_random_string_here
```

On Hostinger the **database name and username are often the same** (`u248320297_eastren`). Use the password you set when creating the MySQL user in hPanel.

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
