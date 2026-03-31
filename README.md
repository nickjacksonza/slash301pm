# Slash 301 PM

Project management tool for small creative agencies (5-20 people). Manages the full creative workflow from brief to delivery.

## Quick Start

Open `index.html` in a browser. No build step required.

## Features

- **Jobs & Briefs** - Create briefs, assign teams, track status
- **Asset Management** - Auto-naming convention, version tracking
- **Capacity Planning** - List and calendar views, workload tracking
- **Review Workflows** - Internal (CD/ECD) and client review stages
- **Wiki** - Client bibles, campaign logs, templates
- **Role-Based Permissions** - COO, Traffic, PM, Designer, etc.

## Architecture

```
Frontend-only React app
├── React 18 (via CDN)
├── Babel (in-browser JSX compilation)
├── localStorage (data persistence)
└── Custom CSS (no framework)
```

## File Structure

```
/slash301pm
├── index.html          # Entry point
├── styles.css          # All styles
├── src/
│   ├── constants.js    # Statuses, roles, permissions
│   ├── utils.js        # Utilities, asset naming
│   ├── data.js         # State management, storage
│   └── components/
│       ├── ui.js       # Base components
│       ├── views.js    # Table, Kanban
│       ├── panels.js   # Detail panels
│       ├── capacity.js # Capacity + calendar
│       ├── reviews.js  # Review workflows
│       ├── wiki.js     # Wiki system
│       └── ...
└── PLAN.md             # Detailed roadmap
```

## Current Status

**Phase:** Early Prototype (v3.1)

| Component | Status |
|-----------|--------|
| Core CRUD | Functional |
| Permissions | UI-only (no auth) |
| Storage | localStorage (5-10MB limit) |
| Multi-user | Not implemented |

## Roadmap

1. **Phase 1** - Cleanup (complete)
2. **Phase 2** - UX Polish (9→5 tabs, Dashboard)
3. **Phase 3** - Backend (Supabase, Auth, RLS)
4. **Phase 4** - Multi-user (real-time sync)

## Known Limitations

- Single-user only (localStorage)
- No authentication
- Permissions bypassable via DevTools
- ~500 brief limit before quota issues

See `PLAN.md` for detailed roadmap and architecture decisions.
