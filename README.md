# East Renfrewshire Council — Housing Digital Transformation Research Portal

Interview research portal for the Housing Digital Transformation Lead role at East Renfrewshire Council. Prepared by William Ellis.

## GitHub Pages

This repo is set up for **GitHub Pages**. The site is pure static HTML — no build step, no PHP.

- `index.html` — Home / dashboard
- `presentation.html` — 13-minute interview presentation
- `history.html` — ERC digital transformation timeline
- `reports.html`, `resources.html`, `sector.html`, etc.
- `assets/` — CSS, JS, and media (images, PDF, video, etc.)

### Enabling GitHub Pages

1. Push this repo to GitHub
2. Go to **Settings → Pages**
3. Under "Build and deployment", set **Source** to **Deploy from a branch**
4. Choose the **main** (or default) branch, folder **/ (root)**
5. Save — the site will be at `https://<username>.github.io/<repo-name>/`

## Local preview (with authentication)

This portal uses the **same PHP session authentication as the SOR system** (`sor-system`): username/password against the shared `users` table in MySQL, with optional Microsoft Entra SSO.

### Setup

1. Copy `.env.example` to `.env` and set `APP_URL`, `DB_*` to match your sor-system database (same `sor_management` DB and users).
2. Ensure the sor-system database is running and has users seeded (`sql/seed_users.sql` in sor-system).
3. Optional Entra SSO: `composer install` and copy `ENTRA_*` settings from sor-system `.env`; add `http://localhost:8080/auth/microsoft/callback.php` as a redirect URI in Entra.
4. Start the PHP server **with the router** (required for auth on static HTML pages):

```bash
cd "/Users/wellis/Desktop/Cursor/East Ren"
php -S localhost:8080 router.php
```

Open `http://localhost:8080` — you will be prompted to sign in. Use the same username/password as sor-system.

### GitHub Pages

GitHub Pages serves static files only — **authentication does not apply** on GitHub Pages. Use PHP hosting (local or Hostinger) for the protected portal.

### Hostinger (production with login)

See **[HOSTINGER-DEPLOY.md](HOSTINGER-DEPLOY.md)** for full steps. Summary:

1. Upload PHP auth files (`includes/`, `login.php`, `router.php`, `index.php`, `.htaccess`, etc.) — not just HTML.
2. Create `.env` on the server with `APP_URL=https://your-domain.hostingersite.com` and MySQL credentials.
3. Run `sql/auth-tables.sql` in phpMyAdmin and add a user (or use the same database as sor-system).
4. Clear Hostinger cache after deploy.

Without `.htaccess` + `router.php` + `.env`, the site stays public (as at [aqua-quetzal-992173.hostingersite.com](https://aqua-quetzal-992173.hostingersite.com/index.html)).

### Legacy static preview (no auth)

```bash
python3 -m http.server 8000
# Open http://localhost:8000 — no login required
```

### Editing

Edit the `.html` files directly. To change the header or navigation, update it in each page (or use find-and-replace across files).

**Media files:** add images, PDFs, video, etc. to `assets/media/`.

## Project structure

| Path | Description |
|------|-------------|
| `index.html`, `*.html` | Static site (GitHub Pages) |
| `assets/` | CSS, JS, media (images, PDF, video) |
| `documents/` | Research notes, strategy extracts, CSV data |
| `CLAUDE.md` | Workspace guidance for AI tools |
