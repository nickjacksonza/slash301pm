# Functions Reference

## Permissions (constants.js)

| Function | Parameters | Returns | Description |
|----------|------------|---------|-------------|
| `getUserPermissions` | `user` | `object` | Get permission object for user's role |
| `canUserViewItem` | `user, item, itemType, data` | `boolean` | Check if user can view item |
| `canUserEditItem` | `user, item, itemType, editType` | `boolean` | Check if user can edit item |
| `canUserCreate` | `user, itemType` | `boolean` | Check if user can create item type |
| `canUserDelete` | `user, item, itemType` | `boolean` | Check if user can delete item |
| `canUserAssignRoles` | `user` | `boolean` | Check if user can assign team roles |

## Access Control (operations.js)

| Function | Parameters | Returns | Description |
|----------|------------|---------|-------------|
| `canAccessOperations` | `user` | `boolean` | Check if user can access Operations tab (COO, PM, Traffic, ECD, CD, Producer) |
| `canAccessClientReview` | `user` | `boolean` | Check if user can access Client Review tab (Client role only) |
| `canAccessJobReview` | `user` | `boolean` | Check if user can access Job Review tab (CD, ECD, Producer, PM, COO) |

## Utilities (utils.js)

| Function | Parameters | Returns | Description |
|----------|------------|---------|-------------|
| `generateId` | - | `string` | Generate random 9-char ID |
| `generateJobNumber` | `projectCode, jobCount` | `string` | Generate job number (e.g., "SUMM-001") |
| `getProjectCode` | `projectName` | `string` | Extract 4-char code from project name |
| `formatDate` | `date` | `string` | Format date as "Jan 15" |

### Asset Naming

| Function | Parameters | Returns | Description |
|----------|------------|---------|-------------|
| `generateAssetName` | `{jobNumber, client, campaignName, assetType, sequence, version, size}` | `string` | Generate compliant asset name |
| `validateAssetName` | `name` | `boolean` | Check if name follows convention |
| `parseAssetName` | `name` | `object\|null` | Parse name into components |

**Asset Naming Pattern:** `{JobNumber}-{Client}-{CampaignShortName}-{AssetType}-{Version}-{YYYYMMDD}_{Size}`

**Example:** `SUMM-001-Acme-Summer-Hero1-v1-20260121_1x1`

### Capacity

| Function | Parameters | Returns | Description |
|----------|------------|---------|-------------|
| `calculateJobHours` | `job, assets` | `number` | Calculate hours for job (assets × 0.25) |
| `getPersonJobs` | `personId, jobs` | `array` | Get jobs assigned to person |
| `categorizeJobsByCapacity` | `jobs, assets` | `object` | Split jobs into today/thisWeek/overflow |
| `getNextWeekdayAt9am` | - | `Date` | Get next weekday at 9am |
| `generateCapacityEmailContent` | `person, categorizedJobs, data` | `{subject, body}` | Generate workload email |

## Data/Storage (data.js)

| Function | Parameters | Returns | Description |
|----------|------------|---------|-------------|
| `migrateData` | `data` | `object` | Run schema migrations on data |
| `createInitialData` | - | `object` | Create default/mock data |
| `loadFromStorage` | - | `object` | Load data from localStorage |
| `saveToStorage` | `data` | `{success, error?, message?}` | Save data to localStorage |
| `getLastStorageError` | - | `object\|null` | Get last storage error |
| `clearLastStorageError` | - | - | Clear stored error |
| `dataReducer` | `state, action` | `object` | Redux-style reducer for state |

### Reducer Actions

| Action Type | Payload | Description |
|-------------|---------|-------------|
| `SET_DATA` | `data` | Replace all data |
| `ADD_PROJECT` | `project` | Add new project |
| `UPDATE_PROJECT` | `project` | Update existing project |
| `DELETE_PROJECT` | `id` | Delete project by ID |
| `ADD_JOB` | `job` | Add new job |
| `UPDATE_JOB` | `job` | Update existing job |
| `DELETE_JOB` | `id` | Delete job by ID |
| `REORDER_JOBS` | `jobs[]` | Replace jobs array |
| `ADD_ASSET` | `asset` | Add new asset |
| `UPDATE_ASSET` | `asset` | Update existing asset |
| `DELETE_ASSET` | `id` | Delete asset by ID |
| `REORDER_ASSETS` | `assets[]` | Replace assets array |
| `ADD_PERSON` | `person` | Add new person |
| `UPDATE_PERSON` | `person` | Update existing person |
| `DELETE_PERSON` | `id` | Delete person by ID |
| `ADD_WIKI_PAGE` | `page` | Add new wiki page |
| `UPDATE_WIKI_PAGE` | `page` | Update existing page |
| `DELETE_WIKI_PAGE` | `id` | Delete page by ID |
| `ADD_TASK` | `task` | Add new task |
| `UPDATE_TASK` | `task` | Update existing task |
| `DELETE_TASK` | `id` | Delete task by ID |
| `ADD_SCHEDULED_EMAIL` | `email` | Schedule email |
| `DELETE_SCHEDULED_EMAIL` | `id` | Remove scheduled email |

## Constants

### Status Arrays
- `TASK_STATUSES` - Core workflow: Backlog, To Do, In Progress, Waiting, Done
- `STATUSES` - All statuses including Inbox, Today, In Review, Approved, etc.
- `STATUSES_WITH_DONE` - Alias for STATUSES (Done is included)
- `STATUS_COLORS` - Traffic light colors mapped to statuses

### Navigation
- `TABS` - Dashboard, Projects, Jobs, Assets, People, Wiki, Capacity, Operations, Job Review, Client Review
- `TAB_ICONS` - Unicode icons for each tab (⌂, ◈, ◎, ◇, ○, ▤, ◐, ▦, ◇, ◉)

### Roles & Permissions
- `ROLES` - COO, PM, Traffic, ECD, CD, Copywriter, Designer, QA, Client, Producer
- `ROLE_PERMISSIONS` - Permission objects for COO, Traffic, PM, and default

### Asset Configuration
- `ASSET_TEMPLATES` - 18 asset types (social, banner, email, video, etc.)
- `ASSET_TYPES` - Hero1, Hero2, Tactical1-4, Organic1-2, Competition, Comp-Winners, Wrapup
- `ASSET_SIZES` - 1x1, 4x5, 16x9, 9x16

### Task Configuration
- `TASK_TEMPLATES` - Copy, Media, Internal Review, Client Review, QA

### Wiki Configuration
- `WIKI_TYPES` - client, campaign, award, report, general
- `WIKI_TEMPLATES` - Client Bible, Campaign Log, Award Submission, Report

### Capacity Constants
- `DAILY_CAPACITY` = 7 hours
- `WEEKLY_CAPACITY` = 35 hours
- `SCHEMA_VERSION` = 1

## Components

### UI Components (ui.js)
- `StatusBadge` - Colored status dropdown
- `PersonAvatar` - User avatar with initials
- `PersonAvatarGroup` - Multiple avatars with overflow
- `StorageWarning` - Storage quota warning banner

### View Components (views.js)
- `TableView` - Sortable table with status editing
- `KanbanBoard` - Drag-drop kanban columns
- `KanbanColumn` - Single kanban column
- `KanbanCard` - Draggable item card

### Dashboard Components (dashboard.js)
- `Dashboard` - Role-based landing page (default tab)
- `DashboardMyWork` - Assigned jobs list by priority
- `DashboardJobCard` - Compact job card with status
- `DashboardQuickActions` - New Brief, Check Capacity buttons
- `DashboardRecentActivity` - Recent job activity feed

### Capacity Components (capacity.js)
- `CapacityTab` - Main capacity view with list/calendar toggle
- `CapacityCalendar` - Weekly calendar grid (Mon-Fri)
- `CapacityBar` - Progress bar for hours
- `CapacitySection` - Today/This Week/Overflow groups
- `PersonSelector` - Person carousel navigation

### Review Components (reviews.js, operations.js)
- `JobReviewTab` - Internal review for CD/ECD (In Progress → In Review)
- `ClientReviewTab` - Client approval workflow (In Review → Approved)
- `OperationsDashboard` - Agency metrics and status overview

### Modal Components
- `BriefModal` (modals-brief.js) - Create new brief with jobs/assets
- `AddPersonModal` (modals-person.js) - Add new team member
- `UserSelector` (modals-person.js) - Switch current user

### Panel Components (panels.js)
- `DetailPanel` - Side panel for item details and editing

### Wiki Components (wiki.js)
- `WikiTab` - Wiki page management and editing

## App Entry Point (app.js)

### Main App Component
- Manages global state via `useReducer` with `dataReducer`
- Handles tab navigation (default: Dashboard)
- Manages view mode toggle (table/kanban)
- Handles import/export JSON functionality
- Renders `StorageWarning` for quota errors

### Key State
- `data` - All application data (projects, jobs, assets, people, tasks, wiki)
- `activeTab` - Current tab (default: 'Dashboard')
- `viewMode` - 'table' or 'kanban'
- `currentUser` - Currently logged in user (persisted to localStorage)
- `storageError` - Last storage error for UI notification
