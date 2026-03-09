# East Renfrewshire Council — Housing Digital Transformation Research Portal

Interview research portal for the Housing Digital Transformation Lead role at East Renfrewshire Council. Prepared by William Ellis.

## GitHub Pages

This repo is set up for **GitHub Pages**. The static site is at the project root:

- `index.html` — Home / dashboard
- `presentation.html` — 13-minute interview presentation
- `history.html` — ERC digital transformation timeline
- `reports.html`, `resources.html`, `sector.html`, etc.
- `assets/` — CSS and JS

### Enabling GitHub Pages

1. Push this repo to GitHub
2. Go to **Settings → Pages**
3. Under "Build and deployment", set **Source** to **Deploy from a branch**
4. Choose the **main** (or default) branch, folder **/ (root)**
5. Save — the site will be at `https://<username>.github.io/<repo-name>/`

### Rebuilding the static site

The HTML is generated from PHP. To rebuild after editing the PHP source:

```bash
cd website
php build-static.php
```

Commit the updated `*.html` and `assets/` at the project root.

**Media files** (video, infographics, PowerPoint, CV, mind map): place them in the project root or `website/assets/media/` before running the build. They will be copied to `assets/media/` in the output.

## Project structure

| Path | Description |
|------|-------------|
| `index.html`, `*.html` | Static site (GitHub Pages) |
| `assets/` | CSS and JS |
| `website/` | PHP source (header, footer, icons, pages) |
| `documents/` | Research notes, strategy extracts, CSV data |
| `CLAUDE.md` | Workspace guidance for AI tools |
