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
| `documents/East Renfrewshire Digital Transformation Progress and Strategy - Table 1.csv` | Structured progress data |

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
- The `.csv` file contains structured progress/status data suitable for tables or analysis
- PDF/PPTX are the original source documents; prefer the `.txt`/`.md` files for text extraction tasks
- `documents/east-ren-digi-plans.txt` exists in the directory but is not yet listed in the key documents table above
