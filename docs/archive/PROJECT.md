# Slash 301 PM

**Stack:** React 18 (CDN, in-browser Babel), vanilla CSS, localStorage, PHP/SQLite (backend, partially)  
**Live:** https://projects.slash301.com/slash301pm/  
**Local:** Open `index.html` directly in browser (no build step)  
**Backend:** PHP API in `api/` for SQLite — deploy via SFTP; Node.js shim available for local testing

## What it is
Airtable-inspired agency project management tool for small creative agencies (5-20 people). Full workflow: briefs → jobs → asset management → capacity planning → review workflows → client delivery. Role-based permissions (COO, Traffic, PM, Designer, etc.).

## Structure
```
slash301pm/
├── index.html          — entry point
├── styles.css          — all styles
├── scripts.js          — top-level bootstrap
├── src/
│   ├── constants.js    — statuses, roles, permissions
│   ├── utils.js        — asset naming, utilities
│   ├── data.js         — state management, localStorage
│   └── components/     — 10+ JS component files (dashboard, capacity, reviews, modals, etc.)
├── api/                — PHP REST API
└── data/               — SQLite db or seed data
```

## State
Heavily documented (README, PLAN, ANALYSIS, CODEBASE_ANALYSIS, FUNCTIONS, FILE_STRUCTURE). Most detailed project in the portfolio. Actively developed — has CLAUDE.md for per-project AI guidance. In-browser Babel means no build tooling but slow initial load.

## What needs work / next directions
- Migrate from in-browser Babel to a build step (Vite) for production performance
- PHP backend: confirm SQLite persistence is wired up vs localStorage
- Review workflows and role permissions — verify end-to-end
- Deploy checklist: PHP + SQLite file permissions on shared hosting
