# SLASH301 PM APPLICATION - COMPREHENSIVE CODEBASE ANALYSIS

**Document Date:** February 19, 2026  
**Application URL:** https://projects.slash301.com/slash301pm/  
**Source Repository:** /home/dev/nickjacksonza/slash301pm/  
**Status:** Phase 2.5a Complete (26+ bugs fixed), Ready for Phase 2.5b

---

## EXECUTIVE SUMMARY

Slash 301 PM is a **React 18-based, browser-only SPA (Single Page Application)** for managing creative agency workflows. It's deployed as a static HTML/CSS/JS bundle with **no backend server** — all data lives in browser localStorage.

### Key Characteristics
- **Framework:** React 18 (via CDN) + Babel (in-browser JSX)
- **Storage:** localStorage only (5-10MB limit, single-browser)
- **Data Format:** JSON with schema versioning and migration system
- **Deployment:** Static HTML/CSS/JS files (FTP deployed)
- **Source Code:** Modular in `/src/`, bundled for deployment as `/scripts.js`
- **User Base:** Small creative agencies (5-20 people)

---

## 1. APPLICATION LOCATIONS & DEPLOYMENT

### Development Location
```
/home/dev/nickjacksonza/slash301pm/          # Development repo
├── index.html                                # Entry point
├── scripts.js                                # Bundled script (DEPLOYED)
├── styles.css                                # All styles
├── src/                                      # Modular source code
├── PLAN.md, ANALYSIS.md, README.md           # Documentation
└── .git/                                     # Git repository
```

### Deployment Location
```
/home/dev/nickjacksonza/slash301pm-deploy/   # Build/deploy staging
├── dist/                                     # Compiled output
├── package.json
├── src/                                      # Source
└── index.html
```

### Web Serving
- **URL:** https://projects.slash301.com/slash301pm/
- **Server:** Nginx (likely)
- **Type:** Static HTML/CSS/JS serving
- **Cache Busting:** Query param version (currently v=3)

---

## 2. APPLICATION ARCHITECTURE

### High-Level Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                      BROWSER WINDOW                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  React App Component                                    │   │
│  │  ├─ Main Tabs: Dashboard, Work, Capacity, Reviews       │   │
│  │  ├─ More Menu: People, Wiki, Operations                 │   │
│  │  ├─ useReducer(dataReducer) → Global State              │   │
│  │  └─ currentUser (localStorage)                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│           ↓                                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Data Layer (data.js)                                   │   │
│  │  ├─ dataReducer (Redux-style state machine)             │   │
│  │  ├─ loadFromStorage() → localStorage → JSON             │   │
│  │  ├─ saveToStorage(newState)                             │   │
│  │  ├─ Schema version & migrations                         │   │
│  │  └─ Cascade delete logic                                │   │
│  └─────────────────────────────────────────────────────────┘   │
│           ↓                                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  localStorage (Browser Storage)                         │   │
│  │  Key: 'slash301pm_data'                                 │   │
│  │  Format: JSON string (compressed)                       │   │
│  │  Size: ~2-8MB (5-10MB limit)                            │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Technology Stack

| Layer | Technology | Details |
|-------|-----------|---------|
| **UI Framework** | React 18 | Via CDN (https://react.dev) |
| **JSX Compilation** | Babel | In-browser, transpiles JSX on load |
| **State Management** | useReducer | Redux-style pattern, no Redux library |
| **Styling** | Custom CSS | 5,563 lines, no CSS framework |
| **Data Storage** | localStorage | Single-browser, no sync |
| **HTTP Library** | None | No API calls (static site) |
| **Date Library** | Native JS | No Moment/date-fns |
| **Module System** | ES6 imports/exports (src/) | Concatenated for deployment |

---

## 3. DATA MODEL & DATABASE

### Data Schema

```javascript
{
  _schemaVersion: 1,
  people: [
    { id, name, email, role, color }
  ],
  projects: [
    { id, name, client, description, status, jobCount, createdAt, clientColors }
  ],
  jobs: [
    { 
      id, jobNumber, name, description, projectId, 
      status, assignments, briefDate, dueDate, 
      firstGoLiveDate, lastGoLiveDate, 
      briefPdfUrl, serverLink, numPosts, hoursEstimate,
      internalApprovedBy, internalApprovedAt,
      internalRejected, internalRejectedBy, internalRejectedAt,
      internalFeedback, internalFeedbackBy, internalFeedbackAt,
      clientFeedback, clientFeedbackDate, clientFeedbackBy,
      clientFeedbackAssignedTo, clientFeedbackStatus, clientFeedbackAssignedRole,
      clientFeedbackReassignedBy, clientFeedbackReassignedAt,
      order, createdAt
    }
  ],
  assets: [
    { id, name, displayName, type, templateId, jobId, projectId, status, assignedTo, dueDate, order }
  ],
  tasks: [
    { 
      id, templateId, jobId, status, assignedTo, 
      characterCount, content, fileUrl, fileType, 
      completedAt, order,
      completed, internalFeedback, feedbackBy, feedbackAt
    }
  ],
  wikiPages: [
    { id, title, slug, content, templateId, parentId, type, linkedJobs, linkedProjects, tags, createdAt, updatedAt, createdBy, order }
  ],
  scheduledEmails: []
}
```

### Key Storage Facts

- **Storage Key:** `slash301pm_data`
- **Format:** JSON string (minified)
- **Save Trigger:** Every action dispatch via dataReducer
- **Load Time:** App startup (loadFromStorage)
- **Size Limit:** 5-10MB (localStorage quota)
- **Error Handling:** QuotaExceededError handling in saveToStorage()
- **Current Data Version:** 1 (migrations ready for future)
- **Backup User:** `slash301pm_currentUser` (separate key)

---

## 4. ROLES & PERMISSIONS SYSTEM

### Role Hierarchy

```
┌─────────────────────────────────────────────────────────┐
│                    COO (Superadmin)                      │
│         Full access to all features & data               │
└─────────────────────────────────────────────────────────┘
                           │
    ┌──────────────────────┼──────────────────────┐
    │                      │                      │
┌───────────┐      ┌───────────────┐      ┌──────────────┐
│  Traffic  │      │      PM       │      │     ECD      │
│  (Admin)  │      │   (Manager)   │      │   (Manager)  │
├───────────┤      ├───────────────┤      ├──────────────┤
│ canViewAll│      │  canViewAll   │      │ canViewAll   │
│ canEditAll│      │  Edit jobs    │      │ Edit jobs    │
│           │      │  Create jobs  │      │ Edit status  │
└───────────┘      └───────────────┘      └──────────────┘
    │                      │                      │
    └──────────────────────┼──────────────────────┘
                           │
    ┌──────────────────────┼──────────────────────┐
    │                      │                      │
┌───────┐        ┌──────────────┐       ┌────────────┐
│  CD   │        │  Copywriter  │       │  Designer  │
│(User) │        │    (User)    │       │   (User)   │
├───────┤        ├──────────────┤       ├────────────┤
│View   │        │View assigned │       │View assign.│
│ assigned       │Edit own status│       │Edit status │
└───────┘        └──────────────┘       └────────────┘
    │                      │                      │
    └──────────────────────┼──────────────────────┘
                           │
                    ┌──────┴──────┐
                    │             │
                 ┌────────┐    ┌────────┐
                 │  QA    │    │ Client │
                 │ (User) │    │(Read)  │
                 ├────────┤    ├────────┤
                 │View    │    │View    │
                 │ assigned     │assigned│
                 └────────┘    └────────┘
```

### Permission Matrix

| Role | canViewAll | canEditProjects | canCreateJobs | canEditJobs | canEditStatus | canAssignRoles | canDeleteAny |
|------|-----------|-----------------|---------------|-------------|---------------|----------------|--------------|
| **COO** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Traffic** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| **PM** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| **ECD** | ✓ | ✗ | ✗ | ✓ | ✓ | ✗ | ✗ |
| **CD** | ✓ | ✗ | ✗ | ✗ | ✓* | ✗ | ✗ |
| **Copywriter** | ✗ | ✗ | ✗ | ✗ | ✓* | ✗ | ✗ |
| **Designer** | ✗ | ✗ | ✗ | ✗ | ✓* | ✗ | ✗ |
| **QA** | ✗ | ✗ | ✗ | ✗ | ✓* | ✗ | ✗ |
| **Producer** | ✓ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ |
| **Client** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

*Can only edit status on assigned items (canEditOwnStatus)

### Permission Functions

**Location:** `/home/dev/nickjacksonza/slash301pm/src/constants.js` (lines 175-260)

```javascript
// Get user's role permissions
getUserPermissions(user) → ROLE_PERMISSIONS[user.role]

// Check view access
canUserViewItem(user, item, itemType, data) → boolean
  // If canViewAll=true: return true
  // Otherwise: check if user is assigned to item

// Check edit access
canUserEditItem(user, item, itemType, editType='full') → boolean
  // editType: 'full' or 'status'
  // CD can edit status on assigned jobs only

// Check creation rights
canUserCreate(user, itemType) → boolean

// Check delete rights
canUserDelete(user, item, itemType) → boolean
  // Only COO can delete anything

// Check role assignment rights
canUserAssignRoles(user) → boolean
```

### Access Control Functions (Reviews)

**Location:** `/home/dev/nickjacksonza/slash301pm/scripts.js` (lines 3497, 3749)

```javascript
// Client Review Access (Client portal)
const canAccessClientReview = user => {
  return user?.role === 'Client'
}

// Internal Review Access (CD, ECD, Producers, PMs, COO)
const canAccessJobReview = user => {
  const reviewerRoles = ['CD', 'ECD', 'Producer', 'PM', 'COO']
  return reviewerRoles.includes(user?.role)
}
```

---

## 5. JOB STATUS WORKFLOW & ROUTING

### Status Flow Diagram

```
CREATION → TRIAGE → EXECUTION → REVIEW → APPROVAL → LIVE
   ↓         ↓         ↓          ↓        ↓        ↓
 Inbox  →  Today  →  In Prog  →  In Rev  →  Int.   → Client → Approved → Scheduled → Live
           This Wk    Waiting      On Hold  Approved (Int)   (Ext)     Archived
```

### Job Statuses (10 Total)

| Status | Color | Context | Description |
|--------|-------|---------|-------------|
| **Inbox** | Red (#e57373) | Triage | New job, not started |
| **Today** | Red (#ef5350) | Urgent | Due today, high priority |
| **This Week** | Orange (#ffa726) | Active | Due within 7 days |
| **In Progress** | Orange (#ff9800) | Active | Currently being worked on |
| **Waiting** | Orange (#f57c00) | Blocked | Waiting on something (client feedback, resource) |
| **On Hold** | Dark Red (#c62828) | Blocked | Explicitly paused |
| **In Review** | Dark Orange (#e65100) | Review | Ready for internal approval |
| **Approved (Internal)** | Green (#66bb6a) | Review | Internal sign-off, sent to client |
| **Approved (External)** | Green (#4caf50) | Approved | Client approved, ready to go live |
| **Scheduled** | Green (#43a047) | Live | Approved, scheduled for publish |
| **Live** | Dark Green (#2e7d32) | Live | Published/live |
| **Archived** | Grey (#9e9e9e) | Archive | No longer active |

### Task Statuses (5 Total)

| Status | Use | Description |
|--------|-----|-------------|
| **Backlog** | Prep | Not yet started |
| **To Do** | Prep | Ready to start |
| **In Progress** | Active | Currently being worked |
| **Waiting** | Blocked | Waiting on something |
| **Done** | Complete | Task completed |

---

## 6. REVIEW ROUTING LOGIC (CRITICAL FEATURE)

### 6A. How a Task Gets Created (Task Save Logic)

**File Location:** `/home/dev/nickjacksonza/slash301pm/src/components/modals-brief.js` (lines 92-227)

**When:** User creates a new Brief via "New Brief" button

**Process:**

1. **Form Validation** (lines 93-105)
   ```javascript
   // Validate required fields
   if (!name) alert('Job name required')
   
   // Validate required role assignments
   const requiredRoles = ['PM', 'Copywriter', 'Designer', 'CD']
   
   // Warn if no Client assigned (but allow to continue)
   if (!assignments['Client']) {
     confirm('No Client reviewer assigned. Continue anyway?')
   }
   ```

2. **Create Project** (lines 107-128)
   ```javascript
   // If user selected "Create New"
   if (!projectId && newProjectName) {
     dispatch({ type: 'ADD_PROJECT', payload: newProject })
     targetProjectId = project.id
   }
   ```

3. **Generate Job Number** (lines 131-143)
   ```javascript
   const projectCode = getProjectCode(project.name)  // "SUMM" from "Summer Campaign"
   const jobNumber = generateJobNumber(projectCode, jobCount + 1)  // "SUMM-001"
   ```

4. **Create Job** (lines 145-168)
   ```javascript
   const job = {
     jobNumber,
     name,
     status: 'Inbox',  // ← INITIAL STATUS
     assignments,      // Roles assigned
     briefDate,
     dueDate,
     // ... extended fields
   }
   dispatch({ type: 'ADD_JOB', payload: job })
   ```

5. **Create Required Tasks** (lines 171-189)
   ```javascript
   // ALWAYS create these two tasks:
   TASK_TEMPLATES.filter(t => t.required).forEach((template) => {
     const task = {
       templateId: template.id,  // 'copy' or 'media'
       jobId: job.id,
       status: 'Inbox',          // ← INITIAL STATUS
       assignedTo: assignments[template.assignedRole]
     }
     dispatch({ type: 'ADD_TASK', payload: task })
   })
   ```

6. **Create Assets** (lines 191-222)
   ```javascript
   // For each asset type selected
   selectedAssets.forEach((template) => {
     const assetName = generateAssetName({
       jobNumber: 'SUMM-001',
       client: 'Acme Corp',
       campaignName: 'Summer Campaign',
       assetType: 'SocialPost',
       sequence: 1,
       version: 1
     })
     // Asset name result: "SUMM-001-Acme-Summer-SocialPost-1-v1"
     
     const asset = {
       name: assetName,
       status: 'Inbox',
       // ...
     }
     dispatch({ type: 'ADD_ASSET', payload: asset })
   })
   ```

### 6B. Task Status Update & Auto-Routing to Reviews

**File Location:** `/home/dev/nickjacksonza/slash301pm/src/data.js` (lines 748-772)

**When:** User saves a task as "Done" (Copy or Media)

**Key Logic:**

```javascript
case 'UPDATE_TASK':
  newState = {
    ...state,
    tasks: (state.tasks || []).map(t => t.id === action.payload.id ? action.payload : t)
  }
  
  // BUG-04 FIX: Auto-transition job status when all required tasks are Done
  if (action.payload.status === 'Done' && action.payload.jobId) {
    const jobId = action.payload.jobId
    const jobTasks = newState.tasks.filter(t => t.jobId === jobId)
    
    // Check if ALL tasks for this job are now Done
    const allDone = jobTasks.length > 0 && 
                    jobTasks.every(t => t.status === 'Done')
    
    if (allDone) {
      const job = newState.jobs.find(j => j.id === jobId)
      
      // Only auto-transition from "active work" statuses
      // (not from review/approved states)
      const activeStatuses = ['In Progress', 'Today', 'This Week', 'Inbox']
      
      if (job && activeStatuses.includes(job.status)) {
        // Update job status to 'In Progress'
        newState = {
          ...newState,
          jobs: newState.jobs.map(j => j.id === jobId ? { 
            ...j, 
            status: 'In Progress',  // ← Moved from whatever active status
            allTasksCompletedAt: new Date().toISOString()
          } : j)
        }
      }
    }
  }
```

**Outcome:** Job automatically moves to "In Progress" status, making it visible in Internal Review queue.

### 6C. Internal Review Routing (CD/ECD Approves)

**File Location:** `/home/dev/nickjacksonza/slash301pm/src/components/reviews.js` (lines 81-158)

**Review Eligibility Check:**

```javascript
// Get jobs ready for internal review (lines 83-101)
const reviewableJobs = useMemo(() => {
  const reviewableStatuses = ['In Progress', 'Today', 'This Week', 'In Review']
  
  return data.jobs.filter(job => {
    // Must be in an active status
    if (!reviewableStatuses.includes(job.status)) return false
    
    // Must NOT already be internally approved
    if (job.internalApprovedBy) return false
    
    // CRITICAL: Copy and Media tasks must BOTH be Done
    const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id)
    const copyTask = jobTasks.find(t => t.templateId === 'copy')
    const mediaTask = jobTasks.find(t => t.templateId === 'media')
    
    const copyComplete = copyTask?.status === 'Done'
    const mediaComplete = mediaTask?.status === 'Done'
    
    return copyComplete && mediaComplete
  })
}, [data.jobs, data.tasks])
```

**Access Control:**

```javascript
// Line 239
if (!canAccessJobReview(currentUser)) {
  return <AccessDenied message="Job Review is available to CDs, ECDs, Producers, PMs, and COO roles." />
}
```

**Approval Action:**

```javascript
// Lines 142-158
const handleApprove = job => {
  // BLOCKING: Check if Client is assigned
  if (!job.assignments?.Client) {
    alert('Cannot approve: No client reviewer is assigned.')
    return
  }
  
  dispatch({
    type: 'UPDATE_JOB',
    payload: {
      ...job,
      status: 'Approved (Internal)',  // ← NEW STATUS
      internalApprovedBy: currentUser?.id,
      internalApprovedAt: new Date().toISOString()
    }
  })
}
```

**Rejection Action (with Feedback):**

```javascript
// Lines 174-221
const handleRejectWithFeedback = job => {
  const jobTasks = getJobTasks(job.id)
  const copyTask = jobTasks.find(t => t.templateId === 'copy')
  const mediaTask = jobTasks.find(t => t.templateId === 'media')
  
  // Mark both tasks incomplete and attach feedback
  if (copyTask) {
    dispatch({
      type: 'UPDATE_TASK',
      payload: {
        ...copyTask,
        completed: false,
        internalFeedback: feedbackText,
        feedbackBy: currentUser?.id
      }
    })
  }
  
  // Same for media task...
  
  // Update job with feedback
  dispatch({
    type: 'UPDATE_JOB',
    payload: {
      ...job,
      internalFeedback: feedbackText,
      internalFeedbackBy: currentUser?.id,
      internalFeedbackAt: new Date().toISOString()
    }
  })
}
```

### 6D. Client Review Routing (Client Approves)

**File Location:** `/home/dev/nickjacksonza/slash301pm/scripts.js` (lines 3501-3574)

**Access Control:**

```javascript
// Lines 3497-3500
const canAccessClientReview = user => {
  if (!user || !user.role) return false
  return user.role === 'Client'  // ONLY Client role
}
```

**Review Eligibility:**

```javascript
// Lines 3513-3517
const pendingReviewJobs = useMemo(() => {
  if (!currentUser) return []
  
  const clientReviewStatuses = ['Approved (Internal)', 'In Review']
  
  return data.jobs.filter(job => 
    clientReviewStatuses.includes(job.status) && 
    job.assignments?.Client === currentUser.id  // Must be THIS client user
  )
}, [data.jobs, currentUser?.id])
```

**Approval Action:**

```javascript
// Lines 3530-3539
const handleApprove = job => {
  dispatch({
    type: 'UPDATE_JOB',
    payload: {
      ...job,
      status: 'Approved (External)'  // ← FINAL APPROVAL
    }
  })
}
```

**Rejection Action (with Feedback):**

```javascript
// Lines 3554-3574
const handleRejectWithFeedback = job => {
  const assignedCD = job.assignments?.CD
  const fallbackCD = data.people.find(p => p.role === 'CD')?.id
  const feedbackAssignee = assignedCD || fallbackCD
  
  dispatch({
    type: 'UPDATE_JOB',
    payload: {
      ...job,
      status: 'In Progress',  // ← SENT BACK FOR REVISIONS
      clientFeedback: feedbackText,
      clientFeedbackDate: new Date().toISOString(),
      clientFeedbackBy: currentUser?.id,
      clientFeedbackAssignedTo: feedbackAssignee,
      clientFeedbackStatus: 'pending'  // CD must action this
    }
  })
}
```

---

## 7. COMPLETE REVIEW WORKFLOW SEQUENCE

### Workflow Overview

```
BRIEF CREATED                              CLIENT REVIEW
    ↓                                            ↓
JOB STATUS: Inbox                         JOB STATUS: Approved (Internal)
    ↓                                            ↓
WORK COMPLETED                            CLIENT APPROVES
  (Copy & Media tasks Done)                      ↓
    ↓                                      JOB STATUS: Approved (External)
AUTO-TRANSITION                                  ↓
    ↓                                      JOB READY FOR LIVE
JOB STATUS: In Progress                         ↓
    ↓                                    Scheduled → Live
INTERNAL REVIEW
(Visible in Reviews tab for CD/ECD)
    ↓
CD APPROVES
    ↓
JOB STATUS: Approved (Internal)
    ↓
JOB ROUTED TO CLIENT REVIEW
(Visible in Client portal)
```

### Decision Points (Routing Conditions)

| Condition | Decision | Action | Result |
|-----------|----------|--------|--------|
| Copy & Media both Done | Auto-route to Internal Review | Job status → In Progress | Visible in CD/ECD Reviews |
| No Client assigned + CD tries to approve | BLOCK | Alert shown | Job stays in limbo |
| CD approves job | Client Review | Job status → Approved (Internal) | Sent to client portal |
| Client approves | DONE | Job status → Approved (External) | Ready to go live |
| Client rejects with feedback | Feedback to CD | Job status → In Progress, feedback queued | CD handles feedback |

---

## 8. FILE STRUCTURE & KEY FILES

### Core Application Files

| File | Location | Lines | Purpose |
|------|----------|-------|---------|
| **App Entry** | `index.html` | 15 | React CDN loader, cache busting |
| **Main App** | `src/app.js` | 410 | Root component, state init, tab navigation |
| **State Machine** | `src/data.js` | 780 | dataReducer, storage, migrations, auto-transitions |
| **Constants** | `src/constants.js` | 363 | Roles, statuses, permissions, templates |
| **Utilities** | `src/utils.js` | 124 | Asset naming, ID generation, capacity calc |
| **UI Components** | `src/components/ui.js` | 185 | StatusBadge, PersonAvatar, etc. |

### Component Files

| Component | Lines | Purpose |
|-----------|-------|---------|
| `dashboard.js` | 8,700 | Landing page with role-based views |
| `work.js` | 18,500 | Unified job/asset management |
| `capacity.js` | 21,977 | Workload & capacity planning |
| `reviews.js` | 23,050 | Internal & Client review workflows |
| `operations.js` | 17,558 | Agency metrics & reporting |
| `wiki.js` | 25,911 | Knowledge base system |
| `modals-brief.js` | 457 | Brief creation form |
| `modals-person.js` | 5,468 | Add person & user selector |
| `panels.js` | 22,046 | Detail panel (side drawer) |
| `views.js` | 18,582 | Table & Kanban views |
| `more-menu.js` | 2,892 | Overflow navigation menu |

### Deployment File

| File | Location | Size | Purpose |
|------|----------|------|---------|
| **Bundled Script** | `scripts.js` | 6,588 lines | Concatenated src/ files (DEPLOYED) |
| **Styles** | `styles.css` | 5,563 lines | All CSS (DEPLOYED) |

---

## 9. JOB STATUS AUTO-TRANSITIONS

### Auto-Transition Rules

**Condition:** When ALL tasks for a job reach status "Done"

**Trigger Code Location:** `/home/dev/nickjacksonza/slash301pm/src/data.js` (lines 753-771)

```javascript
case 'UPDATE_TASK':
  // When a task is marked Done
  if (action.payload.status === 'Done' && action.payload.jobId) {
    const jobTasks = newState.tasks.filter(t => t.jobId === jobId)
    const allDone = jobTasks.every(t => t.status === 'Done')
    
    if (allDone) {
      const job = newState.jobs.find(j => j.id === jobId)
      const activeStatuses = ['In Progress', 'Today', 'This Week', 'Inbox']
      
      if (job && activeStatuses.includes(job.status)) {
        // Auto-transition to 'In Progress'
        newState.jobs = newState.jobs.map(j => j.id === jobId ? {
          ...j,
          status: 'In Progress',
          allTasksCompletedAt: new Date().toISOString()
        } : j)
      }
    }
  }
  break
```

### What This Means

1. When Copywriter marks "Copy" task Done
2. AND Designer marks "Media" task Done
3. System automatically sets job status to "In Progress"
4. Job becomes visible in Internal Review queue (Reviews tab)
5. CD/ECD can now see it and approve/reject

**No manual status change needed** — the system auto-routes based on task completion.

---

## 10. USER FLOWS BY ROLE

### Traffic Manager (Admin)
```
Dashboard
  ↓
See all projects & team workload
  ↓
Capacity Tab
  ↓
View who's available
  ↓
Work Tab
  ↓
Assign jobs to team members
  ↓
Edit job status based on progress
```

### Copywriter
```
Dashboard
  ↓
See assigned tasks in "My Work"
  ↓
Click job → Detail panel
  ↓
Read brief, download PDF
  ↓
Mark Copy task "In Progress"
  ↓
Write copy content, save
  ↓
Mark Copy task "Done"
  ↓
Wait for internal review feedback
  (if rejected, feedback appears with revisions requested)
```

### Designer
```
[Similar to Copywriter, but for Media task]
```

### CD (Creative Director)
```
Dashboard / Reviews Tab
  ↓
See jobs ready for internal review
  (Copy & Media both Done)
  ↓
Review assets in mockup frames
  ↓
Approve → Job sent to client
  OR
Reject with feedback → Feedback queued to copywriter/designer
```

### Client
```
Reviews Tab / Client Portal
  ↓
See jobs approved internally
  (Status: Approved (Internal))
  ↓
Review assets in social mockups
  ↓
Approve → Status: Approved (External)
  OR
Reject with feedback → Feedback sent to CD
```

---

## 11. KEY CODE SECTIONS (TASK SAVE & REVIEW ROUTING)

### Section A: Task Save (Create)

**File:** `/home/dev/nickjacksonza/slash301pm/src/components/modals-brief.js`

**Lines:** 171-189

```javascript
// Create required Copy and Media tasks
TASK_TEMPLATES.filter(t => t.required).forEach((template, index) => {
  const task = {
    id: generateId(),
    templateId: template.id,  // 'copy' or 'media'
    jobId: job.id,
    status: 'Inbox',
    assignedTo: assignments[template.assignedRole],
    characterCount: null,
    content: null,
    fileUrl: null,
    fileType: null,
    completedAt: null,
    order: index
  }
  dispatch({ type: 'ADD_TASK', payload: task })
})
```

### Section B: Task Status Update → Auto-Routing

**File:** `/home/dev/nickjacksonza/slash301pm/src/data.js`

**Lines:** 748-772

```javascript
case 'UPDATE_TASK':
  newState = {
    ...state,
    tasks: (state.tasks || []).map(t => t.id === action.payload.id ? action.payload : t)
  }
  // AUTO-ROUTE when tasks are Done
  if (action.payload.status === 'Done' && action.payload.jobId) {
    const jobId = action.payload.jobId
    const jobTasks = newState.tasks.filter(t => t.jobId === jobId)
    const allDone = jobTasks.length > 0 && jobTasks.every(t => t.status === 'Done')
    if (allDone) {
      const job = newState.jobs.find(j => j.id === jobId)
      const activeStatuses = ['In Progress', 'Today', 'This Week', 'Inbox']
      if (job && activeStatuses.includes(job.status)) {
        newState = {
          ...newState,
          jobs: newState.jobs.map(j => j.id === jobId ? { 
            ...j, 
            status: 'In Progress', 
            allTasksCompletedAt: new Date().toISOString() 
          } : j)
        }
      }
    }
  }
  break
```

### Section C: Internal Review Eligibility

**File:** `/home/dev/nickjacksonza/slash301pm/src/components/reviews.js`

**Lines:** 83-101

```javascript
const reviewableJobs = useMemo(() => {
  const reviewableStatuses = ['In Progress', 'Today', 'This Week', 'In Review']
  return data.jobs.filter(job => {
    if (!reviewableStatuses.includes(job.status)) return false
    if (job.internalApprovedBy) return false
    
    const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id)
    const copyTask = jobTasks.find(t => t.templateId === 'copy')
    const mediaTask = jobTasks.find(t => t.templateId === 'media')
    
    const copyComplete = copyTask?.status === 'Done'
    const mediaComplete = mediaTask?.status === 'Done'
    return copyComplete && mediaComplete
  })
}, [data.jobs, data.tasks])
```

### Section D: Internal Review Approval

**File:** `/home/dev/nickjacksonza/slash301pm/src/components/reviews.js`

**Lines:** 142-158

```javascript
const handleApprove = job => {
  // Check Client is assigned (BUG-05 fix)
  if (!job.assignments?.Client) {
    alert('Cannot approve: No client reviewer is assigned.')
    return
  }
  
  dispatch({
    type: 'UPDATE_JOB',
    payload: {
      ...job,
      status: 'Approved (Internal)',  // ← Routes to Client Review
      internalApprovedBy: currentUser?.id,
      internalApprovedAt: new Date().toISOString()
    }
  })
}
```

### Section E: Client Review Access

**File:** `/home/dev/nickjacksonza/slash301pm/scripts.js`

**Lines:** 3497-3500

```javascript
const canAccessClientReview = user => {
  if (!user || !user.role) return false
  return user.role === 'Client'
}
```

### Section F: Client Review Eligibility

**File:** `/home/dev/nickjacksonza/slash301pm/scripts.js`

**Lines:** 3513-3517

```javascript
const pendingReviewJobs = useMemo(() => {
  if (!currentUser) return []
  const clientReviewStatuses = ['Approved (Internal)', 'In Review']
  return data.jobs.filter(job => 
    clientReviewStatuses.includes(job.status) && 
    job.assignments?.Client === currentUser.id
  )
}, [data.jobs, currentUser?.id])
```

### Section G: Client Approval Action

**File:** `/home/dev/nickjacksonza/slash301pm/scripts.js`

**Lines:** 3530-3539

```javascript
const handleApprove = job => {
  dispatch({
    type: 'UPDATE_JOB',
    payload: {
      ...job,
      status: 'Approved (External)'  // ← FINAL APPROVAL
    }
  })
  setApprovedJobs(prev => new Set([...prev, job.id]))
}
```

---

## 12. KNOWN ISSUES & LIMITATIONS

### Data Persistence Limitations
- **Single Browser Only:** Data doesn't sync across browsers/tabs
- **Storage Quota:** Limited to 5-10MB (localStorage limit)
- **No Backup:** No automatic backups; users must export manually
- **No Undo:** Changes are permanent once saved

### Role/Permission Limitations
- **No Custom Roles:** Roles are hardcoded (COO, Traffic, PM, etc.)
- **No Team Hierarchies:** No department/team structures
- **No Delegation:** Can't delegate permissions temporarily

### Workflow Limitations
- **No Workflow Templates:** Can't define custom approval workflows
- **No SLA Tracking:** No built-in deadline alerts or escalations
- **No Automation:** All transitions are manual or based on task completion

### Client Portal Limitations
- **Client Must Have Account:** No guest review links
- **Limited Feedback:** Feedback is text-only, can't annotate assets
- **No Notifications:** No email alerts for assigned work

---

## 13. QUICK REFERENCE: FINDING THINGS

### To Find...

| What | Where |
|------|-------|
| **Status colors & definitions** | `/src/constants.js` lines 28-65 |
| **Role permissions** | `/src/constants.js` lines 73-173 |
| **Data reducer & auto-transitions** | `/src/data.js` lines 582-787 |
| **Task creation** | `/src/components/modals-brief.js` lines 171-189 |
| **Task status change** | `/src/data.js` lines 748-772 |
| **Internal review eligibility** | `/src/components/reviews.js` lines 83-101 |
| **Internal review approval** | `/src/components/reviews.js` lines 142-158 |
| **Client review access** | `/scripts.js` lines 3497-3500 |
| **Client review eligibility** | `/scripts.js` lines 3513-3517 |
| **Client approval action** | `/scripts.js` lines 3530-3539 |
| **Brief form validation** | `/src/components/modals-brief.js` lines 92-105 |
| **Asset auto-naming** | `/src/utils.js` lines 96-121 |
| **Capacity calculation** | `/src/utils.js` lines 102-107 |
| **Permission checking** | `/src/constants.js` lines 189-254 |

---

## APPENDIX A: COMPLETE STATUS LIST

### Job Statuses (12)
1. Inbox (red)
2. Today (red)
3. This Week (orange)
4. In Progress (orange)
5. Waiting (dark orange)
6. On Hold (dark red)
7. In Review (dark orange)
8. Approved (Internal) (green)
9. Approved (External) (green)
10. Scheduled (green)
11. Live (dark green)
12. Archived (grey)

### Task Statuses (5)
1. Backlog
2. To Do
3. In Progress
4. Waiting
5. Done

### User Roles (10)
1. COO
2. PM
3. Traffic
4. ECD
5. CD
6. Copywriter
7. Designer
8. QA
9. Producer
10. Client

---

## APPENDIX B: SAMPLE DATA FLOW

### Example: Creating a Brief

```
User clicks "New Brief"
  ↓ (BriefModal opens)
  ↓
User fills form:
  - Job Name: "Hero Video"
  - Project: "Summer Campaign"
  - Brief Date: 2026-02-20
  - Due Date: 2026-02-27
  - Assignments: PM=Sarah, Designer=Kim, Copywriter=Alex, CD=Maria, Client=Chris
  ↓
User clicks "Create Brief"
  ↓
Form validation:
  - Check job name not empty ✓
  - Check required roles assigned (PM, Designer, Copywriter, CD) ✓
  - Warn if Client not assigned (but allow to proceed)
  ↓
dispatch({ type: 'ADD_PROJECT', ... }) if creating new project
dispatch({ type: 'UPDATE_PROJECT', jobCount++ })
  ↓
dispatch({ type: 'ADD_JOB', payload: {
  id: 'job123',
  jobNumber: 'SUMM-001',
  name: 'Hero Video',
  status: 'Inbox',  ← Initial status
  assignments: { PM: 'p1', Designer: 'p5', Copywriter: 'p4', CD: 'p3', Client: 'p7' }
}})
  ↓
// Create required tasks
dispatch({ type: 'ADD_TASK', payload: {
  id: 'task1',
  templateId: 'copy',
  jobId: 'job123',
  status: 'Inbox',  ← Initial status
  assignedTo: 'p4'  ← Copywriter
}})

dispatch({ type: 'ADD_TASK', payload: {
  id: 'task2',
  templateId: 'media',
  jobId: 'job123',
  status: 'Inbox',  ← Initial status
  assignedTo: 'p5'  ← Designer
}})
  ↓
// Create assets
dispatch({ type: 'ADD_ASSET', payload: {
  id: 'asset1',
  name: 'SUMM-001-Acme-Summer-VideoEdit-1-v1',
  jobId: 'job123',
  status: 'Inbox'
}})
  ↓
saveToStorage(newState)  ← Persists to localStorage
  ↓
Modal closes
  ↓
NEW STATE:
├── Job 'SUMM-001' with status 'Inbox'
├── Copy task assigned to Alex with status 'Inbox'
├── Media task assigned to Kim with status 'Inbox'
└── Assets created with status 'Inbox'
```

---

## APPENDIX C: TASK-TO-JOB STATUS MAPPING

### When Tasks Change, Job Status Reacts

```
Copywriter marks Copy task "In Progress"
  → Job stays in current status (doesn't auto-change)

Designer marks Media task "In Progress"
  → Job stays in current status

Copywriter marks Copy task "Done"
  → Check: Is Media task also Done?
  → No? Job stays in current status
  → Yes? Job auto-transitions to 'In Progress'
       (becomes visible in Internal Review)

Designer marks Media task "Done"
  → Same check as above
  → Auto-transition to 'In Progress'
```

### Complete Task Status Flow

```
User creates brief with Copy & Media tasks
  ↓
Tasks created with status = 'Inbox'
  ↓
Copywriter works on Copy task
  ↓
Copywriter marks "In Progress"
  ↓ (No auto-transition)
  ↓
Copywriter writes copy content
  ↓
Copywriter marks "Done"
  ↓ (Check all tasks Done?)
  ↓
Designer also working on Media task
  ↓
Designer marks "In Progress"
  ↓ (No auto-transition)
  ↓
Designer marks "Done"
  ↓ (NOW both Copy and Media are Done!)
  ↓
AUTO-TRANSITION: Job status → 'In Progress'
  ↓
Job becomes visible in Internal Review queue
  ↓
CD/ECD can now review and approve
```

---

**END OF DOCUMENT**

