# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Workspace Purpose

This is a **document research workspace** — not a software codebase. It contains reference materials, research notes, and assets related to East Renfrewshire Council's (ERC) digital transformation strategy, used in the context of a job application (CV and cover letter also present).

## Key Documents

| File | Content |
|---|---|
| `documents/strategic-notes.md` | Master consolidation of ERC digital & housing transformation notes (Markdown) |
| `documents/east-ren-blueprint.txt` | Vision for customer experience and workforce productivity |
| `documents/east-ren-digi-transform-strattegy.txt` | How digital technology enables ERC's three-front strategy |
| `documents/east-ren-strattegies.txt` | Strategic context and programme details |
| `documents/east-ren-co-design.txt` | Co-design and citizen engagement approaches |
| `documents/digital-champion-framework.txt` | Digital Champions network, training, and DDaT skills |
| `documents/champions-nnetwork.txt` | Peer support network details |
| `documents/the-digi-champs.txt` | Additional Digital Champions content |
| `documents/transforming-through-co-design.txt` | Co-design methodology |
| `documents/user-centered-design-in-scotland.txt` | Scottish Approach to Service Design (SAtSD) |
| `documents/modernising-through-strategic-innovation.txt` | MAP programme and innovation |
| `documents/Citizen Engagement and Service Co-Design in East Renfrewshire.txt` | Citizen engagement impact |
| `documents/ERC_Digital_Strategy.pdf` / `.pptx` | Source strategy documents (if added) |

**Excluded:** `documents/progress/` — internal draft progress files; not used by the site.

## Core Strategy Themes

ERC's Digital Transformation Strategy (2021–2024) operates across three fronts:
1. **Customer Experience** — MyEastRen portal (mygov.scot SSO), Goss CXM platform, AI/chatbot exploration, SAtSD co-design
2. **Workforce Productivity** — Microsoft 365 embedding, 30+ Digital Champions, RPA/automation, hybrid working, DDaT skills
3. **Business Systems & Processes** — Legacy system replacement (Housing, SEEMIS, CareFirst), IoT sensors, Power BI dashboards, "Golden Record" data matching

Key programmes:
- **MAP (Modern, Ambitious Programme):** Six departmental change programmes
- **Housing Asset Management Plan (HAMP 2024–2026):** 3,141 units, EPC Band B by 2032, 171 new units by 2028
- **Digital Telecare:** Transitioning 3,000 residents from analogue before 2025 national switch-off

## Static Site

This repo also contains a **GitHub Pages interview research portal** — pure static HTML, no build step.

| Path | Role |
|---|---|
| `index.html` | Home / dashboard |
| `presentation.html` | 13-minute interview presentation |
| `history.html` | ERC digital transformation timeline |
| `reports.html`, `resources.html`, `sector.html`, `best-practice.html`, `digital-direction.html`, `scotland-policy.html` | Thematic content pages |
| `assets/css/style.css` | Single shared stylesheet (CSS custom properties for ERC design tokens) |
| `assets/js/main.js` | Shared JavaScript |
| `assets/media/` | Images, PDF, video |

**Local preview:**
```bash
python3 -m http.server 8000
# Open http://localhost:8000
```

**Navigation is duplicated across every `.html` file** — use find-and-replace when updating the nav or header.

**Design tokens** are defined as CSS custom properties in `style.css` (e.g. `--erc-green: #006A51`). Use these variables rather than hardcoding colours.

## Working with These Files

- `documents/strategic-notes.md` is the authoritative consolidated reference — start here for an overview
- Most `documents/*.txt` files are thematic deep-dives extracted from the same source material; expect overlapping content
- **`documents/progress/` is out of scope** — draft programme progress files (CSV, MOU, briefing docs). Do not read, integrate, or reference these when working on the portal.
- PDF/PPTX are the original source documents; prefer the `.txt`/`.md` files for text extraction tasks
- `documents/east-ren-digi-plans.txt` exists in the directory but is not yet listed in the key documents table above

## Design Context

### Users
East Renfrewshire Council staff working on the Housing Digital Transformation programme, using this as an internal working tool during the working day — not a public-facing site. Two distinct user types:
- **Confirmers** (Lisa, William) — power users who use the tool regularly/daily to maintain live governance group membership, resolve proposals, and set group status. Efficiency and low friction matter most to them.
- **Proposers/occasional contributors** — other staff who drop in less often to vote (Yes/Maybe/No) on people, leave reasons, or suggest membership changes. Clarity and confidence matter most to them; they shouldn't feel intimidated by a dense "system."

### Brand Personality
Calm & orderly. Feels like a well-run meeting, not a spreadsheet dump or an admin dashboard. Trustworthy, unhurried, low-cognitive-load — appropriate for council-branded internal software, not a consumer product chasing delight for its own sake.

### Aesthetic Direction
- ERC corporate identity is fixed and non-negotiable: primary green `#006A51` and the existing token palette in `assets/css/style.css` stay exactly as-is. This is council-branded software, not a blank creative canvas.
- Decluttering is achieved through layout, spacing, grouping, and progressive disclosure — not new colors, new fonts, or a visual re-brand.
- Light mode only; no indication dark mode is needed for this internal tool.
- Avoid generic AI-dashboard clichés (card-grid sameness, hero-metric layouts, glassmorphism) — but equally avoid over-designing a civic tool with unnecessary flair. Restraint over spectacle.

### Design Principles
1. **Progressive disclosure over always-on density.** Primary info per person (name, role, current vote/tally) stays visible by default; secondary detail (reason notes, tag editor, "also on" cross-listing, place-on-another-group, propose actions) should be reachable but not permanently rendered for every row at once — known pain point: "too much always-visible at once."
2. **Never remove capability.** Every existing feature (voting, shareable reasons, tags, proposals, confirm/propose permissions, group status, filters, exports, history) must remain fully reachable — decluttering re-organizes, it does not amputate.
3. **Serve both audiences without compromise.** Power users (confirmers) need speed and minimal clicks for frequent actions; occasional proposers need clarity and low intimidation. Prefer patterns that scale disclosure to intent rather than picking one audience over the other.
4. **Brand identity is fixed, not up for reinvention.** Any visual change must work within the existing ERC green/token palette already defined in `style.css`.
5. **Calm, orderly rhythm.** Favor generous grouping and intentional whitespace over uniform dense packing; avoid multiple competing always-on controls fighting for attention within a single row or card.
