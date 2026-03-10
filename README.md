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

### Local preview

Open `index.html` in a browser, or use a simple server:

```bash
python3 -m http.server 8000
# Open http://localhost:8000
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
