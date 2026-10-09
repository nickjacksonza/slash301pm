# SLASH301PM - Complete File Structure & Code Reference

## Repository Structure

```
/home/dev/nickjacksonza/slash301pm/
│
├── 📄 index.html (15 lines)
│   └─ Entry point with React CDN, Babel, DOMPurify
│   └─ Cache busting: ?v=3
│   └─ Loads: React 18, React-DOM, scripts.js
│
├── 📦 scripts.js (6,588 lines)
│   └─ BUNDLED & DEPLOYED VERSION
│   └─ Contains all source code concatenated
│   └─ Updated v=2 → v=3 on February 18, 2026
│   └─ 10,894 insertions for Phase 2.5a fixes
│
├── 🎨 styles.css (5,563 lines)
│   └─ All application styles (no CSS framework)
│   └─ Traffic light colors for statuses
│   └─ Responsive layout
│   └─ Updated with new Dashboard/Work views
│
├── 📖 PLAN.md (750+ lines)
│   └─ Comprehensive project roadmap
│   └─ Decisions log (13 key decisions)
│   └─ Example workflow (Acme Corp social campaign)
│   └─ 32-bug analysis (26 fixed, 6 deferred)
│   └─ Phase sequencing (1-5)
│   └─ Multi-user architecture proposal
│   └─ Risk register
│   └─ Testing/QA checklist
│
├── 📖 README.md (75 lines)
│   └─ Quick start (open index.html)
│   └─ Features list
│   └─ Architecture diagram
│   └─ File structure
│   └─ Current status (Phase 2.5a)
│   └─ Roadmap
│   └─ Known limitations
│
├── 📖 FUNCTIONS.md (185 lines)
│   └─ API reference for all functions
│   └─ Permissions API
│   └─ Data/Storage functions
│   └─ Utilities (asset naming, capacity)
│   └─ Components list
│   └─ Constants reference
│
├── 📄 package.json (8 lines)
│   └─ Minimal: devDependencies only
│   └─ @babel/cli, @babel/core, @babel/preset-react
│   └─ No runtime dependencies
│
├── 📁 src/ (2,702 lines total)
│   │
│   ├── 📄 app.js (~410 lines)
│   │   └─ Main App component
│   │   └─ Global state management (useReducer)
│   │   └─ Tab navigation (Dashboard, Work, Capacity, Reviews, More)
│   │   └─ CurrentUser state (persisted to localStorage)
│   │   └─ Handles import/export JSON
│   │   └─ StorageWarning for quota errors
│   │   └─ useEffect hooks for initialization
│   │
│   ├── 📄 constants.js (~363 lines)
│   │   └─ Task statuses (Backlog, To Do, In Progress, Waiting, Done)
│   │   └─ Job statuses (Inbox, Today, This Week, On Hold, In Review, etc.)
│   │   └─ Status colors (traffic light: red/orange/green)
│   │   └─ Roles (COO, PM, Traffic, ECD, CD, Copywriter, Designer, QA, Client, Producer)
│   │   └─ Role permissions (ROLE_PERMISSIONS object)
│   │   └─ Permission functions (canUserViewItem, canUserEditItem, canUserCreate, canUserDelete)
│   │   └─ Asset templates, types, sizes
│   │   └─ Wiki templates and types
│   │   └─ Task templates (Copy, Media, Internal Review, Client Review, QA)
│   │   └─ Capacity constants (7hr/day, 35hr/week)
│   │
│   ├── 📄 data.js (~780 lines)
│   │   └─ Schema version & migrations system
│   │   └─ createInitialData() - Mock data with 9 people, sample projects
│   │   └─ loadFromStorage() - Loads data from localStorage
│   │   └─ saveToStorage(data) - Saves to localStorage with QuotaExceededError handling
│   │   └─ getLastStorageError() / clearLastStorageError()
│   │   └─ dataReducer() - Redux-style reducer
│   │   └─ Reducer actions (ADD_PROJECT, UPDATE_JOB, DELETE_ASSET, etc.)
│   │   └─ Cascade delete logic for projects/jobs/people
│   │   └─ All state mutations
│   │
│   ├── 📄 utils.js (~124 lines)
│   │   └─ generateId() - Uses crypto.randomUUID with fallback
│   │   └─ generateJobNumber(projectCode, jobCount) - "CAMPAIGN-001"
│   │   └─ getProjectCode(projectName) - Extracts 4-char code
│   │   └─ formatDate(date) - Formats as "Jan 15"
│   │   └─ generateAssetName(jobNumber, client, campaignName, assetType, version, size)
│   │   └─ validateAssetName(name) - Checks naming convention
│   │   └─ parseAssetName(name) - Parses name into components
│   │   └─ calculateJobHours(job, assets) - assets × 0.25
│   │   └─ getPersonJobs(personId, jobs)
│   │   └─ categorizeJobsByCapacity(jobs, assets)
│   │   └─ getNextWeekdayAt9am()
│   │   └─ generateCapacityEmailContent(person, categorizedJobs, data)
│   │
│   └── 📁 components/ (13 component files)
│       │
│       ├── 📄 ui.js (~185 lines)
│       │   └─ StatusBadge - Dropdown status selector
│       │   └─ PersonAvatar - Avatar with initials
│       │   └─ PersonAvatarGroup - Multiple avatars with overflow
│       │   └─ StorageWarning - LocalStorage quota banner
│       │
│       ├── 📄 app.js [DUPLICATE - in src/ not components/]
│       │
│       ├── 📄 dashboard.js (~8,700 lines)
│       │   └─ NEW in Phase 2 - Role-based landing page
│       │   └─ DashboardMyWork section (assigned jobs)
│       │   └─ DashboardJobCard (compact job display)
│       │   └─ DashboardQuickActions (New Brief, Check Capacity)
│       │   └─ DashboardRecentActivity (5 most recent jobs)
│       │   └─ Role-specific content (COO, Traffic, Designers, Clients)
│       │
│       ├── 📄 work.js (~18,500 lines)
│       │   └─ NEW in Phase 2 - Unified Work view
│       │   └─ Merges Projects, Jobs, Assets tabs
│       │   └─ Table view with sorting/filtering
│       │   └─ Kanban view with drag-drop
│       │   └─ Detail panel for selected items
│       │   └─ "New Brief" CTA
│       │   └─ Permission-based item filtering
│       │   └─ Badge counts (my items, filtered by role)
│       │
│       ├── 📄 capacity.js (~21,977 lines)
│       │   └─ CapacityTab main component
│       │   └─ List view with person carousel
│       │   └─ CapacityCalendar - Weekly view (Mon-Fri)
│       │   └─ CapacityBar - Hours progress bar
│       │   └─ CapacitySection - Today/This Week/Overflow groups
│       │   └─ PersonSelector - Person carousel navigation
│       │   └─ Email scheduling (mailto: functionality)
│       │   └─ Sorting/filtering (Workload, Role, Alphabetical)
│       │   └─ Fixed: Weekend calendar navigation (Bug #18)
│       │
│       ├── 📄 reviews.js (~23,050 lines)
│       │   └─ JobReviewTab - Internal review workflow
│       │   └─ ClientReviewTab - Client approval workflow
│       │   └─ Shows items pending user's approval
│       │   └─ Asset preview with mockups
│       │   └─ Comment/feedback system
│       │   └─ Approve/Reject buttons
│       │   └─ Fixed: Task status checking (Bug #1)
│       │   └─ Fixed: Rejection persistence (Bug #10)
│       │
│       ├── 📄 reviews-unified.js (~3,690 lines)
│       │   └─ NEW in Phase 2 - Unified review tab
│       │   └─ Combines internal & client reviews
│       │   └─ Tab-within-tab navigation
│       │   └─ Reduces cognitive load from separate tabs
│       │
│       ├── 📄 operations.js (~17,558 lines)
│       │   └─ OperationsDashboard - Agency metrics
│       │   └─ Job completion rates
│       │   └─ Capacity overview
│       │   └─ Team utilization
│       │   └─ Status distribution charts
│       │   └─ Permission-based access (COO, PM, Traffic, ECD, CD, Producer)
│       │
│       ├── 📄 wiki.js (~25,911 lines)
│       │   └─ WikiTab - Knowledge base system
│       │   └─ WikiPageModal - Create/edit page
│       │   └─ WysiwygEditor - Rich text editor
│       │   └─ Tree structure (parent_id)
│       │   └─ Templates (Client Bible, Campaign Log, Award, Report)
│       │   └─ Fixed: XSS vulnerability (Bug #13) - Added DOMPurify
│       │   └─ Fixed: Author hardcoding (Bug #15)
│       │   └─ Fixed: Template selection (Bug #20)
│       │   └─ Permission-based delete/edit
│       │
│       ├── 📄 panels.js (~22,046 lines)
│       │   └─ DetailPanel - Side drawer for item details
│       │   └─ Generic panel (adapts to item type)
│       │   └─ Edit/delete/status change
│       │   └─ Collapsible sections
│       │   └─ Handles Projects, Jobs, Assets, People
│       │
│       ├── 📄 views.js (~18,582 lines)
│       │   └─ TableView - Sortable table display
│       │   └─ KanbanBoard - Drag-drop kanban
│       │   └─ KanbanColumn - Status column
│       │   └─ KanbanCard - Draggable item card
│       │   └─ Status colors
│       │   └─ Fixed: Status editing (Bug #19 badge counts)
│       │
│       ├── 📄 modals-brief.js (~16,560 lines)
│       │   └─ BriefModal - Create/edit brief form
│       │   └─ Collapsible form sections
│       │   └─ Campaign selection
│       │   └─ Date validation (dueDate > briefDate)
│       │   └─ Team assignment (PM, Designer, Copywriter, CD, ECD, Traffic)
│       │   └─ Auto-generated job number
│       │   └─ Attachment fields (PDF, server link)
│       │   └─ Fixed: Form not resetting (Bug #9)
│       │   └─ Fixed: Race condition in job numbers (Bug #8)
│       │   └─ Fixed: Asset name parsing (Bug #12)
│       │
│       ├── 📄 modals-person.js (~5,468 lines)
│       │   └─ AddPersonModal - Add team member
│       │   └─ UserSelector - Switch current user
│       │   └─ Email validation
│       │   └─ Role assignment
│       │   └─ Fixed: Dropdown click-outside close (Bug #17)
│       │   └─ Fixed: Email validation (Bug #26)
│       │
│       └── 📄 more-menu.js (~2,892 lines)
│           └─ NEW in Phase 2 - Navigation overflow menu
│           └─ Dropdown menu (above main content, z-index fixed)
│           └─ Links to: People, Wiki, Operations, Settings
│           └─ Hidden from users without permissions
│           └─ Fixed: z-index CSS (post-sprint fix)
│
├── 📁 backup/ (DEPRECATED)
│   └─ scripts.js (198,182 bytes)
│   │   └─ OLD bundled version (from January 20)
│   │   └─ No longer maintained
│   │   └─ To be deleted per Phase 1
│   └─ styles.css (75,248 bytes)
│   └─ index.html
│
├── 📁 .git/
│   └─ Repository history
│   └─ Remote: https://github.com/nickjacksonza/slash301pm
│   └─ 1 commit (initial)
│   └─ 16 modified files (Phase 2.5a)
│   └─ 7 untracked files (Phase 2 docs)
│
└── 📁 .claude/
    └─ Claude Code settings
```

---

## Side-by-Side: Code Organization

### Current (Confusing)
```
scripts.js (DEPLOYED - 6,588 lines, monolithic)
    ├─ All code concatenated
    ├─ Updated from Phase 2.5a fixes
    └─ Hard to maintain

src/ (SOURCE - 2,702 lines, modular)
    ├─ Split across 17 files
    ├─ Better organized
    ├─ But NOT deployed directly
    └─ Gap: How to build?

backup/ (DEPRECATED)
    └─ Old version (no longer used)
    └─ Should be deleted
```

### Desired (Phase 3)
```
src/ (SOURCE - modular)
├─ ES modules (import/export)
├─ Tree-shaking ready
└─ Build system processes this

dist/ (BUILT OUTPUT)
└─ Minified bundle
└─ Cache-busted
└─ Deployed to production
```

---

## Key Code Locations by Feature

| Feature | File | Lines | Key Functions |
|---------|------|-------|----------------|
| **State Management** | src/data.js | 780 | dataReducer, loadFromStorage, saveToStorage |
| **Permissions** | src/constants.js | 363 | getUserPermissions, canUserViewItem, canUserEditItem |
| **Asset Naming** | src/utils.js | 124 | generateAssetName, parseAssetName, validateAssetName |
| **Capacity** | src/utils.js | 124 | calculateJobHours, categorizeJobsByCapacity |
| **Dashboard** | src/components/dashboard.js | 8,700 | Dashboard, DashboardMyWork, DashboardRecentActivity |
| **Work Tab** | src/components/work.js | 18,500 | WorkTab, TableView, KanbanBoard |
| **Reviews** | src/components/reviews.js | 23,050 | JobReviewTab, ClientReviewTab |
| **Capacity** | src/components/capacity.js | 21,977 | CapacityTab, CapacityCalendar |
| **Wiki** | src/components/wiki.js | 25,911 | WikiTab, WysiwygEditor |
| **Modals** | src/components/modals-brief.js | 16,560 | BriefModal |
| **UI Components** | src/components/ui.js | 185 | StatusBadge, PersonAvatar |

---

## Bug Fix Locations (Phase 2.5a)

| Bug | File(s) | Issue | Fix |
|-----|---------|-------|-----|
| #1 | reviews.js, app.js | Task status check | `completed` → `status === 'Done'` |
| #2 | app.js | Infinite render loop | Add useEffect dependency array `[]` |
| #3 | app.js | Null reference crash | Guard with `?.` and `?? 0` |
| #4-6 | data.js | No cascade deletes | Filter jobs, assets, tasks when deleting |
| #7 | scripts.js | Permission logic | Explicit boolean wrap |
| #8 | modals-brief.js | Race condition in job numbers | Use project variable directly |
| #9 | modals-brief.js | BriefModal not resetting | Call full resetForm() in useEffect |
| #10 | reviews.js | Rejection not persisted | Add dispatch() call |
| #11 | app.js, data.js | Storage errors silent | Fix useEffect + add clearError handler |
| #12 | utils.js | Asset name parser | Fix split() to handle full size string |
| #13 | index.html, wiki.js | XSS vulnerability | Add DOMPurify script + sanitize content |
| #15 | wiki.js | Wiki author hardcoding | Pass currentUser, use currentUser.id |
| #16-17 | ui.js, modals-person.js | No click-outside close | Add document.addEventListener pattern |
| #18 | capacity.js | Calendar weekend display | Check dayOfWeek === 0 for Sunday |
| #19 | work.js | Badge counts ignore permissions | Apply canUserViewItem filter |
| #20 | wiki.js | Template not updating editor | Fix useEffect dependency |
| #25 | utils.js | Random ID generation | Use crypto.randomUUID with fallback |
| #26 | modals-person.js | Email validation | Add validateEmail() check |
| #31 | app.js | Search debounce | Add 200ms debounce on input |

---

## Git Status Summary

### Currently Untracked Files (Phase 2 work not yet committed)
```
PLAN.md (750+ lines)
README.md (75 lines)
FUNCTIONS.md (185 lines)
src/components/dashboard.js (NEW)
src/components/work.js (NEW)
src/components/more-menu.js (NEW)
src/components/reviews-unified.js (NEW)
```

### Currently Modified Files (Phase 2.5a bug fixes)
```
index.html - Added DOMPurify script
scripts.js - 8,876 changes (bug fixes + Phase 2 features)
src/app.js - 410 lines
src/components/*.js - Various fixes
src/constants.js - 363 lines
src/data.js - 780 lines (cascade deletes, error handling)
src/utils.js - 124 lines
styles.css - 2,045 changes
```

### Commands to Commit
```bash
# Stage new documentation and components
git add PLAN.md README.md FUNCTIONS.md
git add src/components/dashboard.js
git add src/components/work.js
git add src/components/more-menu.js
git add src/components/reviews-unified.js

# Stage bug fixes
git add src/ styles.css index.html scripts.js

# Commit
git commit -m "Phase 2.5a: Fix 26 bugs, complete Phase 2 UI polish"
```

---

**Summary:** Well-organized modular source code in src/, deployed as monolithic scripts.js bundle. Phase 2.5a fixes complete and deployed. Ready for Phase 2.5b (Data Abstraction) and Phase 3 (Backend).

