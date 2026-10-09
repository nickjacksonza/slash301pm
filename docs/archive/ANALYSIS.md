# SLASH301PM - Project Comprehensive Analysis

**Project Name:** Slash 301 PM  
**Repository:** https://github.com/nickjacksonza/slash301pm  
**Current Location:** /home/dev/nickjacksonza/slash301pm  
**Status:** Phase 2.5a Complete (32 bugs fixed), Phase 2.5b Queued  
**Last Updated:** February 18, 2026

---

## 1. PROJECT OVERVIEW

### What is Slash 301 PM?

Slash 301 PM is a **web-based project management tool specifically designed for small creative agencies** (5-20 people). It manages the full creative workflow from brief through delivery, including:

- Job and brief creation and tracking
- Asset management with auto-naming conventions
- Capacity planning and workload management
- Role-based permissions and access control
- Internal and client review workflows
- Team collaboration with a wiki system
- Operations dashboard with agency metrics

**Target Users:** Small creative agency teams where simplicity and speed matter more than enterprise features.

### Vision Statement

Enable small agencies to manage complex creative workflows efficiently without the overhead of enterprise software.

---

## 2. TECH STACK

### Frontend
- **React 18** (via CDN, not npm)
- **Babel** (in-browser JSX compilation)
- **Vanilla JavaScript** (ES5/6, no build system currently)
- **Custom CSS** (no CSS framework like Bootstrap or Tailwind)
- **localStorage** (data persistence for single-user)

### Storage & Data
- **localStorage** (5-10MB limit, single-user only)
- **JSON** data format
- **Schema versioning** (SCHEMA_VERSION = 1) with migration system

### Deployment
- **Static HTML/CSS/JS** - no server required
- **FTP deployed** with cache busting (v=2 → v=3)
- **CDN for React/React-DOM**

### Testing
- Manual testing via browser console
- Test helper functions (window.testPMWorkflow, window.testRoles)
- Automated workflow simulation functions

---

## 3. PROJECT STRUCTURE

```
/home/dev/nickjacksonza/slash301pm/
├── index.html                 # Entry point (6,588 lines when bundled)
├── styles.css                 # All styles (5,563 lines)
├── scripts.js                 # Bundled app (6,588 lines) - DEPLOYED VERSION
├── PLAN.md                    # Detailed project roadmap (750+ lines)
├── FUNCTIONS.md               # API reference documentation
├── README.md                  # Quick start guide
├── package.json               # Project metadata (minimal)
├── backup/                    # Outdated backup (to be deleted in Phase 1)
│   └── scripts.js            # Old version (no longer maintained)
├── src/                       # Modular source code (2,702 lines)
│   ├── app.js                # Main App component with state management
│   ├── constants.js          # Roles, permissions, statuses, asset configs
│   ├── data.js               # State reducer, storage, migrations
│   ├── utils.js              # Helper functions, asset naming, capacity
│   └── components/
│       ├── dashboard.js      # Dashboard landing page (NEW in Phase 2)
│       ├── work.js           # Unified Work view (NEW in Phase 2)
│       ├── capacity.js       # Capacity planning + calendar
│       ├── reviews.js        # Internal review workflow
│       ├── reviews-unified.js # Unified review component (NEW)
│       ├── operations.js     # Agency metrics dashboard
│       ├── wiki.js           # Wiki/knowledge base system
│       ├── panels.js         # Detail panel (side drawer)
│       ├── views.js          # Table and Kanban views
│       ├── modals-brief.js   # Create/edit brief modal
│       ├── modals-person.js  # Add person & user selector
│       ├── more-menu.js      # Navigation overflow menu (NEW)
│       ├── ui.js             # Base components (Badge, Avatar, etc.)
├── .git/                      # Git repository
└── .claude/                   # Claude Code settings
```

### Key File Stats
- **Total Source Code:** 2,702 lines (src/)
- **Bundled Script:** 6,588 lines (scripts.js)
- **CSS:** 5,563 lines
- **Documentation:** 750+ lines (PLAN.md)

---

## 4. CURRENT STATE & STATUS

### Implementation Status (Phase 2.5a: BUG FIXES - COMPLETE)

| Component | Status | Quality | Notes |
|-----------|--------|---------|-------|
| Projects | Functional | Prototype | CRUD, table + kanban views |
| Jobs/Briefs | Functional | Prototype | Full workflow, assignments |
| Assets | Functional | Prototype | Auto-naming, versioning |
| People | Functional | Prototype | Role-based, permissions |
| Wiki | Functional | Prototype | WYSIWYG editor, XSS fixed |
| Capacity | Functional | Prototype | List + calendar views |
| Operations | Functional | Prototype | Agency metrics |
| Reviews (Internal) | Functional | Prototype | CD/ECD workflow |
| Reviews (Client) | Functional | Prototype | Social mockups, approval |
| Dashboard | Functional | Prototype | Role-based landing (Phase 2) |

### Recent Changes (Phase 2.5a: 26 bugs fixed, 6 deferred)

**Bug Fixes Completed (February 18, 2026):**
1. Internal Review queue bug (#1) - Fixed task status checking
2. Infinite render loop (#2) - Fixed useEffect dependency
3. Null reference crashes (#3) - Added guards
4-6. Cascade delete bugs - Fixed project/job/person deletions
7. Permission logic error - Fixed asset visibility
8. Race condition in job numbers - Fixed project lookup
9. BriefModal not resetting - Fixed full field reset
10. Rejection not persisted - Added dispatch
11. Storage errors silent - Fixed error notification
12. Asset name parser - Fixed size string parsing
13. XSS in Wiki - Added DOMPurify
15. Wiki author hardcoding - Fixed createdBy
16-17. Dropdown close behavior - Added click-outside handlers
18. Calendar weekend display - Fixed week navigation
19. WorkTab badge count - Applied permission filtering
20. WYSIWYG template selection - Fixed dependency
21. Recent Activity dates - Fixed to show most recent 5
25. Random ID generation - Added crypto.randomUUID
26. Email validation - Added to forms
31. Search debounce - Added 200ms debounce

**Deferred Bugs (6):**
- #14: Client-side permission bypass warning (Phase 3 - server-side RLS)
- #22: Detail panel type detection (low impact)
- #24: Build/deploy documentation
- #28-30: Full form validation (partial: email validation added)
- #32: Race condition in rapid dispatches (Phase 2.5b)

---

## 5. DATA MODEL & ARCHITECTURE

### State Structure

```javascript
{
  _schemaVersion: 1,
  
  projects: [{
    id: string,
    name: string,
    client: string,
    description: string,
    status: string,
    jobCount: number,
    createdAt: ISO8601,
    clientColors: { primary, secondary }
  }],
  
  jobs: [{
    id: string,
    jobNumber: string (auto: "CAMPAIGN-001"),
    name: string,
    description: string,
    projectId: string,
    status: string,
    assignments: { PM, Traffic, Designer, Copywriter, CD, ECD, Client: personId },
    dueDate: YYYY-MM-DD,
    briefedBy: personId,
    briefedAt: ISO8601,
    internalApprovedBy: personId,
    internalRejected: boolean,
    createdAt: ISO8601,
    order: number
  }],
  
  assets: [{
    id: string,
    name: string (auto-generated per convention),
    jobId: string,
    type: string,
    size: string,
    version: number,
    assignedTo: personId,
    status: string,
    url: string,
    createdAt: ISO8601,
    order: number
  }],
  
  people: [{
    id: string,
    name: string,
    email: string,
    role: string (COO|PM|Traffic|ECD|CD|Copywriter|Designer|QA|Client|Producer),
    avatar: string (initials or URL),
    createdAt: ISO8601
  }],
  
  tasks: [{
    id: string,
    templateId: string (copy|media|internal_review|client_review|qa),
    jobId: string,
    status: string,
    completed: boolean,
    assignedTo: personId,
    content: string,
    fileUrl: string,
    completedAt: ISO8601,
    order: number
  }],
  
  wiki_pages: [{
    id: string,
    name: string,
    content: string (HTML, XSS-sanitized),
    type: string (client|campaign|award|report|general),
    createdBy: personId,
    createdAt: ISO8601,
    updatedAt: ISO8601,
    parent_id: string (for tree structure)
  }]
}
```

### Permission System

**Role Hierarchy:**
1. **COO** - Superadmin, view all, edit all, delete any
2. **Traffic/PM** - Admin, manage jobs/assets, assign roles
3. **Designer/Copywriter/CD/ECD** - User, view/edit assigned items only
4. **Client** - View-only, can review jobs and provide feedback
5. **QA/Producer** - User-level access

**Permission Checks:**
- View: canUserViewItem(user, item, itemType, data)
- Edit: canUserEditItem(user, item, itemType, editType)
- Create: canUserCreate(user, itemType)
- Delete: canUserDelete(user, item, itemType)

**Critical Limitation:** All permissions are UI-level only (no server-side enforcement). Anyone with DevTools access can bypass. **Phase 3 will add server-side RLS.**

---

## 6. KEY FEATURES & WORKFLOWS

### Asset Naming Convention

**Pattern:** `{JobNumber}-{Client}-{CampaignShortName}-{AssetType}{Sequence}-{Version}-{YYYYMMDD}_{Size}`

**Example:** `SUMM-001-Acme-Summer-Hero1-v1-20260121_1x1`

**Asset Types:** Hero1, Hero2, Tactical1-4, Organic1-2, Competition, Comp-Winners, Wrapup

**Sizes:** 1x1, 4x5, 16x9, 9x16

### Core Workflow Example (Social Campaign Brief to Delivery)

```
DAY 1: Brief Creation
  └─ AE creates brief → Status: "Briefed"
  └─ Traffic assigns team → Status: "Assigned"

DAY 2-3: Creative Work
  └─ Designer uploads assets (auto-named)
  └─ Copywriter writes copy
  └─ Status: "In Progress"

DAY 3: Designer Self-Check
  └─ Senior designer reviews assets
  └─ Status: "Ready for CD Review"

DAY 4: CD Internal Review
  └─ CD reviews all assets
  └─ Can approve → "Ready for Client"
  └─ Or reject with feedback → "Changes Requested"

DAY 5: Client Review
  └─ Client reviews via portal
  └─ Can approve → "Approved"
  └─ Or reject with feedback → Task returns to CD
```

### Capacity Planning

- **Daily capacity:** 7 hours
- **Weekly capacity:** 35 hours
- **Calculation:** Assets × 0.25 hours per asset
- **Views:** List view, calendar view
- **Features:** Workload %, email scheduling, overflow detection

### Review Workflows

**Internal (CD/ECD):**
- Jobs with completed Copy + Media tasks appear in review queue
- CD can approve → moves to Client Review
- CD can reject with feedback → tasks returned to designer

**Client:**
- Jobs approved internally appear in client review
- Client can approve → job complete
- Client can reject with feedback → assigned to CD

---

## 7. KNOWN LIMITATIONS & OPEN ISSUES

### Critical Issues (Block Production Use)
- **No authentication** - Anyone can claim any role
- **Client-side permissions only** - Bypassable via DevTools
- **Single-user only** - localStorage is per-browser
- **XSS vulnerability** - FIXED in Phase 2.5a (added DOMPurify)

### High-Priority Issues (Affect Scaling)
- **Storage quota limit** - ~500 briefs before localStorage fills (5-10MB limit)
- **Browser Babel compilation** - 500ms+ added to page load
- **No real-time sync** - Multiple tabs can overwrite each other
- **No cascade deletes** - FIXED in Phase 2.5a
- **Monolithic bundle** - 6,588-line scripts.js (hard to maintain)

### Medium-Priority Issues (UX/Performance)
- **No pagination** - Renders entire dataset
- **Synchronous localStorage** - Blocks UI on large saves
- **No input validation** - Invalid data states possible
- **Global scope pollution** - Risk of naming collisions
- **No performance optimization** - No React.memo or useMemo

### Deferred Bugs (For Future Phases)
- #14: Prototype warning banner (Phase 3)
- #22: Detail panel type detection (Phase 2.5b)
- #24: Build/deploy docs (Phase 3)
- #28-30: Full form validation (Phase 3)
- #32: Race condition in rapid dispatches (Phase 2.5b)

---

## 8. TESTING & QA

### Manual Testing Approach

Test helpers available in browser console:
- `window.testPMWorkflow()` - Basic PM workflow test
- `window.testPMWorkflow.full()` - Full workflow (brief → client review)
- `window.testRoles.runAll()` - Multi-role workflow test
- `window.testRoles.pm.run()` - PM only
- `window.testRoles.cd.approve(jobNumber)` - CD approval
- `window.testRoles.client.approve(jobNumber)` - Client approval

### URL-Based Auto-Testing
- `?runtest=true` - Auto-run PM workflow test
- `?roletest=true` - Full multi-role test
- `?roletest=reject` - Rejection flow test
- `?roletest=pm|cd|client` - Specific role tests

### Test Coverage (Phase 2.5a)
- All 6 sprints of regression tests completed
- 26 bugs verified as fixed
- 6 bugs deferred with justification
- Automated banner notifications for test results

### Future: Automated Testing (Phase 3+)
- Unit tests with Jest
- Component tests with React Testing Library
- E2E tests with Playwright

---

## 9. ROADMAP & DEPLOYMENT

### Phase Sequencing

**Phase 1: Cleanup** ✅ COMPLETE
- Multi-agent review completed
- Code duplication identified
- QuotaExceededError handling added
- Schema versioning implemented

**Phase 2: UX Polish** ✅ COMPLETE
- 9 tabs → 5 tabs consolidation (Dashboard, Work, Capacity, Reviews, More)
- Dashboard with role-based landing
- Brief form with collapsible sections
- Capacity calendar view
- Asset naming automation

**Phase 2.5a: Bug Fixes** ✅ COMPLETE (February 18, 2026)
- 32 bugs identified (Opus 4 analysis)
- 26 bugs fixed and deployed
- 6 deferred with phase assignments
- FTP deployed with cache bust (v=2 → v=3)

**Phase 2.5b: Data Abstraction** ⏳ QUEUED
- Create DataService interface
- Wrap localStorage in DataService
- Prepare for backend swap
- Address rapid dispatch race conditions

**Phase 3: Backend Foundation** ⏳ PLANNED
- Set up Supabase project
- PostgreSQL schema with relationships
- Supabase Auth (email/password)
- Row-level security (RLS) policies
- Vite build tooling
- ES modules migration
- Security headers

**Phase 4: Multi-User** ⏳ PLANNED
- Replace localStorage with Supabase
- Filtered real-time subscriptions
- Field-level conflict resolution
- Email scheduling with rate limiting
- Client portal with scoped tokens

**Phase 5: Scale Features** ⏳ PLANNED
- Performance optimization
- Analytics and reporting
- Slack/email/calendar integrations
- Mobile UX improvements

### Deployment Current State

- **Hosted:** Static file hosting (FTP)
- **URL:** Accessible via slash301pm domain
- **Cache Busting:** Version parameter in URLs (index.html?v=3, scripts.js?v=3)
- **No Build Step:** Currently runs Babel in browser
- **CDN:** React/React-DOM loaded from unpkg.com

### Build System

**Current:** None (Babel in-browser)  
**Gap:** Scripts.js is bundle, src/ is modular, no formal build process  
**Phase 3:** Add Vite for tree-shaking, code splitting, minification

---

## 10. GIT REPOSITORY

### Repository Info
- **Remote:** https://github.com/nickjacksonza/slash301pm
- **Local Path:** /home/dev/nickjacksonza/slash301pm
- **Branch:** main
- **Commits:** 1 initial commit + untracked Phase 2 work

### Git Status (Current)
**Untracked (new Phase 2 files):**
- PLAN.md
- README.md
- FUNCTIONS.md
- src/components/dashboard.js
- src/components/work.js
- src/components/more-menu.js
- src/components/reviews-unified.js

**Modified (Phase 2.5a bug fixes):**
- 16 files changed
- ~10,894 insertions, ~6,970 deletions
- All bug fixes applied to both scripts.js and src/ files

### Deployment Directory
- **Location:** /home/dev/nickjacksonza/slash301pm-deploy
- **Purpose:** Prepared for FTP deployment
- **Contents:** dist/ (minified assets), src/, package.json

---

## 11. DOCUMENTATION & REFERENCES

### Documentation Files

| File | Lines | Purpose |
|------|-------|---------|
| PLAN.md | 750+ | Comprehensive project roadmap, architecture, decisions log |
| README.md | 75 | Quick start guide |
| FUNCTIONS.md | 185 | API reference for all functions |
| CLAUDE.md* | TBD | Claude Code settings (if present) |

*Not fully examined

### Key Decision Log (From PLAN.md)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Target agency size | 5-20 people | Simpler needs, faster to ship |
| Phase order | Current | Validate before scaling infrastructure |
| Navigation | 9 → 5 tabs | Reduce cognitive load |
| Mobile | Responsive, desktop-optimized | Works mobile but optimized for desktop |
| Bug fixes | Phase 2.5a before 2.5 | 3 critical bugs block progress |
| Bug priority | Critical → Data → Security → UX | Crashes first, then corruption |

---

## 12. SUMMARY & INSIGHTS

### Strengths
✅ Clear product vision (small agencies, simple workflows)  
✅ Well-documented (PLAN.md is comprehensive)  
✅ Phase-based roadmap with clear sequencing  
✅ Phase 2.5a: Systematic bug analysis + fixes deployed  
✅ Test helpers for multi-role workflows  
✅ Role-based permissions system (UI-level)  
✅ Asset naming convention (professional, validated)  
✅ Modular source code (src/) + bundled version  

### Weaknesses / Risks
⚠️ No server-side authentication (Phase 3 blocker)  
⚠️ localStorage limit (~500 briefs) (Phase 3 migration needed)  
⚠️ Single-user only (no real-time sync)  
⚠️ Browser Babel compilation (500ms+ perf hit)  
⚠️ No automated tests (manual testing only)  
⚠️ Monolithic scripts.js (6,588 lines)  
⚠️ Build system gap (Phase 3 TODO)  
⚠️ 6 bugs deferred but tracked  

### Next Steps (In Priority Order)
1. Phase 2.5b: Data Abstraction layer (DataService)
2. Phase 3: Backend (Supabase + Auth + RLS)
3. Phase 4: Multi-user (real-time sync)
4. Phase 5: Scale features

### Overall Assessment

**Status:** Early Prototype (v3.1) - Functional but single-user  
**Readiness for MVP:** ~60% (needs backend before any multi-user use)  
**Code Quality:** Good (modular, well-documented, recent bug fixes)  
**Architecture:** Sound (layered, clear separation of concerns)  
**Production Blockers:** Authentication, RLS, real-time sync

---

**Last Comprehensive Analysis:** February 18, 2026  
**Analyzer:** Claude Code  
**Repository:** github.com/nickjacksonza/slash301pm
