const {
  useState,
  useEffect,
  useReducer,
  useCallback,
  useMemo
} = React;

// ============================================================================
// CONSTANTS
// ============================================================================

// Task-level statuses (core workflow)
const TASK_STATUSES = ['Backlog', 'To Do', 'In Progress', 'Waiting', 'Done'];

// Job/Campaign statuses (includes task statuses + additional workflow states)
const STATUSES = [
// Task statuses (can roll up)
'Backlog', 'To Do', 'In Progress', 'Waiting', 'Done',
// Additional job/campaign statuses
'Inbox', 'Today', 'This Week', 'On Hold', 'In Review', 'Approved (Internal)', 'Approved (External)', 'Scheduled', 'Live', 'Archived'];
const STATUSES_WITH_DONE = STATUSES; // Done is now included

// Traffic light status colors
// Red family (light/mid/dark): Needs attention, blocked, urgent
// Orange family (light/mid/dark): In progress, waiting, pending
// Green family (light/mid/dark): Completed, approved, live
const STATUS_COLORS = {
  // RED - Needs attention / Urgent / Blocked
  'Inbox': '#e57373',
  // light red - new items need triage
  'Today': '#ef5350',
  // mid red - urgent, due today
  'On Hold': '#c62828',
  // dark red - blocked/stopped
  'Backlog': '#ef9a9a',
  // lightest red - backlog items

  // ORANGE - In progress / Waiting / Pending review
  'To Do': '#ffb74d',
  // light orange - ready to start
  'This Week': '#ffa726',
  // mid orange - due this week
  'In Progress': '#ff9800',
  // mid orange - actively working
  'Waiting': '#f57c00',
  // darker orange - waiting on something
  'In Review': '#e65100',
  // dark orange - pending review

  // GREEN - Completed / Approved / Live
  'Done': '#81c784',
  // light green - task complete
  'Approved (Internal)': '#66bb6a',
  // mid green - internal sign-off
  'Approved (External)': '#4caf50',
  // mid-dark green - client approved
  'Scheduled': '#43a047',
  // dark green - ready to go live
  'Live': '#2e7d32',
  // darkest green - published/live

  // NEUTRAL - Archived
  'Archived': '#9e9e9e' // grey - no longer active
};
const ROLES = ['COO', 'PM', 'Traffic', 'ECD', 'CD', 'Copywriter', 'Designer', 'QA', 'Client', 'Producer'];

// ============================================================================
// PERMISSIONS SYSTEM
// ============================================================================

// Permission levels for each role
const ROLE_PERMISSIONS = {
  'COO': {
    level: 'superadmin',
    canViewAll: true,
    canEditProjects: true,
    canCreateProjects: true,
    canEditJobs: true,
    canCreateJobs: true,
    canEditAssets: true,
    canAssignRoles: true,
    canEditPeople: true,
    canEditWiki: true,
    canDeleteAny: true
  },
  'Traffic': {
    level: 'admin',
    canViewAll: true,
    canEditProjects: true,
    canCreateProjects: false,
    canEditJobs: true,
    canCreateJobs: true,
    canEditAssets: true,
    canAssignRoles: true,
    canEditPeople: false,
    canEditWiki: true,
    canDeleteAny: false
  },
  'PM': {
    level: 'manager',
    canViewAll: true,
    canEditProjects: true,
    canCreateProjects: true,
    canEditJobs: true,
    canCreateJobs: true,
    canEditAssets: false,
    // Can only add via Brief, not edit directly
    canAssignRoles: true,
    canEditPeople: false,
    canEditWiki: true,
    canDeleteAny: false
  },
  // Default permissions for other roles
  'default': {
    level: 'user',
    canViewAll: false,
    // Can only see assigned items
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: false,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: true // Can edit status on assigned items
  }
};

// Get permission level for a user based on their role
const getUserPermissions = user => {
  if (!user || !user.role) {
    return ROLE_PERMISSIONS['default'];
  }

  // Check if user's role has specific permissions
  if (ROLE_PERMISSIONS[user.role]) {
    return ROLE_PERMISSIONS[user.role];
  }
  return ROLE_PERMISSIONS['default'];
};

// Check if user can view an item
const canUserViewItem = (user, item, itemType, data) => {
  const permissions = getUserPermissions(user);
  if (permissions.canViewAll) return true;

  // For restricted users, check if they're assigned to the item
  switch (itemType) {
    case 'Projects':
      // Can view project if assigned to any job in that project
      return data.jobs.some(job => job.projectId === item.id && Object.values(job.assignments || {}).includes(user.id));
    case 'Jobs':
      return Object.values(item.assignments || {}).includes(user.id);
    case 'Assets':
      return item.assignedTo === user.id || data.jobs.find(j => j.id === item.jobId) && Object.values(data.jobs.find(j => j.id === item.jobId).assignments || {}).includes(user.id);
    default:
      return true;
  }
};

// Check if user can edit an item
const canUserEditItem = (user, item, itemType, editType = 'full') => {
  const permissions = getUserPermissions(user);
  switch (itemType) {
    case 'Projects':
      return permissions.canEditProjects;
    case 'Jobs':
      if (editType === 'status') {
        // Check if user can edit status (assigned users can edit status)
        return permissions.canEditJobs || permissions.canEditOwnStatus && Object.values(item.assignments || {}).includes(user.id);
      }
      return permissions.canEditJobs;
    case 'Assets':
      if (editType === 'status') {
        return permissions.canEditAssets || permissions.canEditOwnStatus && item.assignedTo === user.id;
      }
      return permissions.canEditAssets;
    case 'People':
      return permissions.canEditPeople;
    default:
      return false;
  }
};

// Check if user can create items
const canUserCreate = (user, itemType) => {
  const permissions = getUserPermissions(user);
  switch (itemType) {
    case 'Projects':
      return permissions.canCreateProjects;
    case 'Jobs':
      return permissions.canCreateJobs;
    case 'Assets':
      return permissions.canCreateJobs;
    // Creating assets is part of creating jobs/briefs
    case 'People':
      return permissions.canEditPeople;
    default:
      return false;
  }
};

// Check if user can delete an item
const canUserDelete = (user, item, itemType) => {
  const permissions = getUserPermissions(user);
  return permissions.canDeleteAny;
};

// Check if user can assign roles
const canUserAssignRoles = user => {
  const permissions = getUserPermissions(user);
  return permissions.canAssignRoles;
};
const ASSET_TEMPLATES = [{
  id: 'social-static',
  name: 'Social Post (Static)',
  type: 'image'
}, {
  id: 'social-video',
  name: 'Social Post (Video)',
  type: 'video'
}, {
  id: 'social-carousel',
  name: 'Social Carousel',
  type: 'image'
}, {
  id: 'social-story',
  name: 'Story/Reel',
  type: 'video'
}, {
  id: 'banner-display',
  name: 'Display Banner',
  type: 'image'
}, {
  id: 'banner-animated',
  name: 'Animated Banner',
  type: 'video'
}, {
  id: 'email-template',
  name: 'Email Template',
  type: 'document'
}, {
  id: 'email-copy',
  name: 'Email Copy',
  type: 'copy'
}, {
  id: 'landing-page',
  name: 'Landing Page',
  type: 'document'
}, {
  id: 'video-edit-short',
  name: 'Video Edit (Short)',
  type: 'video'
}, {
  id: 'video-edit-long',
  name: 'Video Edit (Long)',
  type: 'video'
}, {
  id: 'print-ad',
  name: 'Print Ad',
  type: 'document'
}, {
  id: 'ooh-billboard',
  name: 'OOH/Billboard',
  type: 'image'
}, {
  id: 'radio-spot',
  name: 'Radio Spot',
  type: 'audio'
}, {
  id: 'podcast-ad',
  name: 'Podcast Ad',
  type: 'audio'
}, {
  id: 'blog-post',
  name: 'Blog Post',
  type: 'copy'
}, {
  id: 'press-release',
  name: 'Press Release',
  type: 'copy'
}, {
  id: 'presentation',
  name: 'Presentation',
  type: 'document'
}];

// Task Templates - checklist items for jobs
// Copy and Media are REQUIRED for every job
const TASK_TEMPLATES = [{
  id: 'copy',
  name: 'Copy',
  description: 'Written content for the asset',
  assignedRole: 'Copywriter',
  required: true,
  hasCharacterCount: true,
  hasFileUpload: false
}, {
  id: 'media',
  name: 'Media',
  description: 'Visual content (image or video)',
  assignedRole: 'Designer',
  required: true,
  hasCharacterCount: false,
  hasFileUpload: true,
  fileTypes: ['image', 'video']
}, {
  id: 'review-internal',
  name: 'Internal Review',
  description: 'Review by CD/ECD',
  assignedRole: 'CD',
  required: false,
  hasCharacterCount: false,
  hasFileUpload: false
}, {
  id: 'review-client',
  name: 'Client Review',
  description: 'Review by client stakeholder',
  assignedRole: 'Client',
  required: false,
  hasCharacterCount: false,
  hasFileUpload: false
}, {
  id: 'qa',
  name: 'QA Check',
  description: 'Quality assurance review',
  assignedRole: 'QA',
  required: false,
  hasCharacterCount: false,
  hasFileUpload: false
}];

// New consolidated 5-tab navigation (Phase 2)
const MAIN_TABS = ['Dashboard', 'Work', 'Capacity', 'Reviews'];
// Note: 'More' is a dropdown menu, not a tab

// Legacy tabs (kept for backward compatibility with existing components)
const TABS = ['Dashboard', 'Projects', 'Jobs', 'Assets', 'People', 'Wiki', 'Capacity', 'Operations', 'Job Review', 'Client Review'];

// Items in the "More" dropdown menu
const MORE_MENU_ITEMS = ['People', 'Wiki', 'Operations'];

// Sparse icons for tabs (Unicode characters for simplicity)
const TAB_ICONS = {
  // Main tabs (Phase 2)
  'Dashboard': '⌂',
  'Work': '◎',
  'Capacity': '◐',
  'Reviews': '◇',
  // Legacy tabs (kept for compatibility)
  'Projects': '◈',
  'Jobs': '◎',
  'Assets': '◇',
  'People': '○',
  'Wiki': '▤',
  'Operations': '▦',
  'Job Review': '◇',
  'Client Review': '◉'
};

// Wiki page types
const WIKI_TYPES = ['client', 'campaign', 'award', 'report', 'general'];

// Wiki Templates
const WIKI_TEMPLATES = [{
  id: 'client-bible',
  name: 'Client Bible',
  description: 'Complete brand and client reference guide',
  type: 'client',
  sections: ['Brand Overview', 'Visual Identity', 'Tone of Voice', 'Key Contacts', 'Approval Process', 'Do\'s and Don\'ts', 'Asset Library', 'Campaign History'],
  content: `<h1>Client Bible</h1>
<h2>Brand Overview</h2>
<p>Company background, mission, vision, and values...</p>
<h2>Visual Identity</h2>
<p><strong>Primary Colors:</strong> </p>
<p><strong>Secondary Colors:</strong> </p>
<p><strong>Typography:</strong> </p>
<p><strong>Logo Usage:</strong> </p>
<h2>Tone of Voice</h2>
<p>Brand personality, writing style, key messages...</p>
<h2>Key Contacts</h2>
<ul>
<li><strong>Primary Contact:</strong> </li>
<li><strong>Approver:</strong> </li>
<li><strong>Finance:</strong> </li>
</ul>
<h2>Approval Process</h2>
<p>Steps and timeline for getting work approved...</p>
<h2>Do's and Don'ts</h2>
<p><strong>Do:</strong></p>
<ul><li></li></ul>
<p><strong>Don't:</strong></p>
<ul><li></li></ul>
<h2>Asset Library</h2>
<p>Links to brand assets, templates, and resources...</p>
<h2>Campaign History</h2>
<p>Previous campaigns and learnings...</p>`
}, {
  id: 'campaign-log',
  name: 'Campaign Log',
  description: 'Campaign documentation and results tracking',
  type: 'campaign',
  sections: ['Campaign Overview', 'Objectives & KPIs', 'Target Audience', 'Timeline', 'Creative Approach', 'Media Plan', 'Results', 'Learnings'],
  content: `<h1>Campaign Log</h1>
<h2>Campaign Overview</h2>
<p><strong>Campaign Name:</strong> </p>
<p><strong>Client:</strong> </p>
<p><strong>Launch Date:</strong> </p>
<p><strong>End Date:</strong> </p>
<h2>Objectives & KPIs</h2>
<p><strong>Primary Objective:</strong> </p>
<p><strong>KPIs:</strong></p>
<ul><li></li></ul>
<h2>Target Audience</h2>
<p>Demographics, psychographics, behaviors...</p>
<h2>Timeline & Milestones</h2>
<table>
<tr><th>Date</th><th>Milestone</th><th>Status</th></tr>
<tr><td></td><td></td><td></td></tr>
</table>
<h2>Creative Approach</h2>
<p>Creative strategy and key messaging...</p>
<h2>Media Plan</h2>
<p>Channels, budget allocation, targeting...</p>
<h2>Results & Metrics</h2>
<p><strong>Reach:</strong> </p>
<p><strong>Engagement:</strong> </p>
<p><strong>Conversions:</strong> </p>
<p><strong>ROI:</strong> </p>
<h2>Learnings & Recommendations</h2>
<p>What worked, what didn't, recommendations for next time...</p>`
}, {
  id: 'award-submission',
  name: 'Award Submission',
  description: 'Track award entries and submissions',
  type: 'award',
  sections: ['Award Details', 'Entry Requirements', 'Campaign Summary', 'Results & Impact', 'Supporting Materials', 'Submission Status'],
  content: `<h1>Award Submission</h1>
<h2>Award Details</h2>
<p><strong>Award Name:</strong> </p>
<p><strong>Category:</strong> </p>
<p><strong>Entry Deadline:</strong> </p>
<p><strong>Entry Fee:</strong> </p>
<h2>Entry Requirements</h2>
<ul>
<li><input type="checkbox"> Case study document</li>
<li><input type="checkbox"> Video (max 2 min)</li>
<li><input type="checkbox"> Supporting images</li>
<li><input type="checkbox"> Results data</li>
</ul>
<h2>Campaign Summary</h2>
<p><strong>Campaign:</strong> </p>
<p><strong>Client:</strong> </p>
<p><strong>Brief:</strong> </p>
<p><strong>Solution:</strong> </p>
<h2>Results & Impact</h2>
<p>Quantifiable results and business impact...</p>
<h2>Supporting Materials</h2>
<p>Links to assets, videos, case study...</p>
<h2>Submission Status</h2>
<p><strong>Status:</strong> Draft / Submitted / Shortlisted / Winner</p>
<p><strong>Submitted Date:</strong> </p>
<p><strong>Result:</strong> </p>`
}, {
  id: 'report-template',
  name: 'Report Template',
  description: 'Measurement and reporting framework',
  type: 'report',
  sections: ['Executive Summary', 'Performance Overview', 'Channel Breakdown', 'Key Insights', 'Recommendations'],
  content: `<h1>Performance Report</h1>
<h2>Executive Summary</h2>
<p>High-level overview of performance...</p>
<h2>Performance Overview</h2>
<table>
<tr><th>Metric</th><th>Target</th><th>Actual</th><th>Variance</th></tr>
<tr><td></td><td></td><td></td><td></td></tr>
</table>
<h2>Channel Breakdown</h2>
<h3>Social Media</h3>
<p></p>
<h3>Paid Media</h3>
<p></p>
<h3>Email</h3>
<p></p>
<h2>Key Insights</h2>
<ul><li></li></ul>
<h2>Recommendations</h2>
<p>Next steps and optimizations...</p>`
}];
// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

const generateId = () => Math.random().toString(36).substr(2, 9);

// ============================================================================
// ASSET NAMING CONVENTION
// ============================================================================
// Pattern: {JobNumber}-{Client}-{CampaignShortName}-{AssetType}{Sequence}-{Version}-{YYYYMMDD}_{Size}
// Example: SUMM-001-Acme-Summer-Hero1-v1-20260121_1x1

const ASSET_TYPES = ['Hero1', 'Hero2', 'Tactical1', 'Tactical2', 'Tactical3', 'Tactical4', 'Organic1', 'Organic2', 'Competition', 'Comp-Winners', 'Wrapup'];
const ASSET_SIZES = ['1x1', '4x5', '16x9', '9x16'];

// Generate a compliant asset name
const generateAssetName = ({
  jobNumber,
  client,
  campaignName,
  assetType,
  sequence = 1,
  version = 1,
  size
}) => {
  const clientShort = (client || 'Client').replace(/[^a-zA-Z0-9]/g, '').slice(0, 10);
  const campaignShort = (campaignName || 'Campaign').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join('').replace(/[^a-zA-Z0-9]/g, '').slice(0, 15);
  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const typeWithSeq = assetType || `Asset${sequence}`;
  const versionStr = `v${version}`;
  const sizeStr = size ? `_${size}` : '';
  return `${jobNumber}-${clientShort}-${campaignShort}-${typeWithSeq}-${versionStr}-${dateStr}${sizeStr}`;
};

// Validate if a name follows the convention
const validateAssetName = name => {
  // Pattern: XXXX-NNN-Client-Campaign-Type-vN-YYYYMMDD(_Size)?
  const pattern = /^[A-Z]{2,4}-\d{3}-[A-Za-z0-9]+-[A-Za-z0-9]+-[A-Za-z0-9]+-v\d+-\d{8}(_\d+x\d+)?$/;
  return pattern.test(name);
};

// Parse an existing asset name into components
const parseAssetName = name => {
  const parts = name.split('-');
  if (parts.length < 7) return null;
  const sizePart = parts[parts.length - 1];
  const hasSize = sizePart.includes('_');
  const dateAndSize = hasSize ? sizePart.split('_') : [sizePart, null];
  return {
    jobNumber: `${parts[0]}-${parts[1]}`,
    client: parts[2],
    campaign: parts[3],
    assetType: parts[4],
    version: parts[5],
    date: dateAndSize[0],
    size: dateAndSize[1]
  };
};
const generateJobNumber = (projectCode, jobCount) => {
  return `${projectCode}-${String(jobCount).padStart(3, '0')}`;
};
const getProjectCode = projectName => {
  return projectName.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 4);
};
const formatDate = date => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  });
};

// Capacity calculation utilities
const DAILY_CAPACITY = 7; // hours
const WEEKLY_CAPACITY = 35; // hours

const calculateJobHours = (job, assets) => {
  const jobAssets = assets.filter(a => a.jobId === job.id);
  return Math.max(jobAssets.length * 0.25, 0.25);
};
const getPersonJobs = (personId, jobs) => {
  return jobs.filter(job => Object.values(job.assignments || {}).includes(personId));
};
const categorizeJobsByCapacity = (jobs, assets) => {
  const today = [];
  const thisWeek = [];
  const overflow = [];
  let todayHours = 0;
  let weekHours = 0;

  // Sort by status priority: Today first, then This Week, then others
  const statusPriority = {
    'Today': 1,
    'In Progress': 2,
    'This Week': 3
  };
  const sortedJobs = [...jobs].sort((a, b) => {
    const aPriority = statusPriority[a.status] || 99;
    const bPriority = statusPriority[b.status] || 99;
    return aPriority - bPriority;
  });
  for (const job of sortedJobs) {
    const hours = calculateJobHours(job, assets);
    const isToday = job.status === 'Today' || job.status === 'In Progress';
    const isThisWeek = job.status === 'This Week';
    if (isToday) {
      if (todayHours + hours <= DAILY_CAPACITY) {
        today.push({
          ...job,
          hours
        });
        todayHours += hours;
        weekHours += hours;
      } else {
        overflow.push({
          ...job,
          hours
        });
      }
    } else if (isThisWeek) {
      if (weekHours + hours <= WEEKLY_CAPACITY) {
        thisWeek.push({
          ...job,
          hours
        });
        weekHours += hours;
      } else {
        overflow.push({
          ...job,
          hours
        });
      }
    }
  }
  return {
    today,
    thisWeek,
    overflow,
    todayHours,
    weekHours: todayHours + thisWeek.reduce((sum, j) => sum + j.hours, 0),
    overflowHours: overflow.reduce((sum, j) => sum + j.hours, 0)
  };
};
const getNextWeekdayAt9am = () => {
  const now = new Date();
  const tomorrow = new Date(now);
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(9, 0, 0, 0);
  const day = tomorrow.getDay();
  if (day === 0) tomorrow.setDate(tomorrow.getDate() + 1); // Sunday -> Monday
  if (day === 6) tomorrow.setDate(tomorrow.getDate() + 2); // Saturday -> Monday

  return tomorrow;
};
const generateCapacityEmailContent = (person, categorizedJobs, data) => {
  const {
    today,
    thisWeek,
    todayHours,
    weekHours
  } = categorizedJobs;
  const todayDate = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });
  const getJobDetails = job => {
    const project = data.projects.find(p => p.id === job.projectId);
    return `• ${job.jobNumber} - ${job.name}
  Client: ${project?.client || 'N/A'} | Campaign: ${project?.name || 'N/A'}
  Hours: ${job.hours}h | Due: ${formatDate(job.dueDate)}`;
  };
  let content = `Hi ${person.name.split(' ')[0]},

Here's your workload summary:

TODAY - ${todayHours.toFixed(1)} hours
─────────────────────
${today.length > 0 ? today.map(getJobDetails).join('\n\n') : 'No jobs scheduled'}

THIS WEEK - ${(weekHours - todayHours).toFixed(1)} hours
─────────────────────
${thisWeek.length > 0 ? thisWeek.map(getJobDetails).join('\n\n') : 'No additional jobs this week'}

Total: ${weekHours.toFixed(1)} hours scheduled

---
Generated by Slash 301 PM`;
  return {
    subject: `Your Workload for ${todayDate}`,
    body: content
  };
};
// ============================================================================
// SCHEMA VERSION & MIGRATIONS
// ============================================================================

const SCHEMA_VERSION = 1;

// Migration functions: each migrates from version N to N+1
const migrations = {
  // Example for future use:
  // 1: (data) => {
  //   // Migrate from v1 to v2
  //   data.newField = 'default';
  //   return data;
  // },
};

// Run all necessary migrations to bring data up to current version
const migrateData = data => {
  const dataVersion = data._schemaVersion || 0;
  if (dataVersion === SCHEMA_VERSION) {
    return data; // Already up to date
  }
  if (dataVersion > SCHEMA_VERSION) {
    console.warn(`Data schema version (${dataVersion}) is newer than app version (${SCHEMA_VERSION}). This may cause issues.`);
    return data;
  }
  let migratedData = {
    ...data
  };

  // Run each migration in sequence
  for (let v = dataVersion; v < SCHEMA_VERSION; v++) {
    if (migrations[v]) {
      console.log(`Migrating data from schema v${v} to v${v + 1}`);
      migratedData = migrations[v](migratedData);
    }
  }
  migratedData._schemaVersion = SCHEMA_VERSION;
  return migratedData;
};

// ============================================================================
// INITIAL/MOCK DATA
// ============================================================================

const createInitialData = () => ({
  _schemaVersion: SCHEMA_VERSION,
  people: [{
    id: 'p0',
    name: 'Rachel Adams',
    email: 'rachel@agency.com',
    role: 'COO',
    color: '#dc2626'
  }, {
    id: 'p1',
    name: 'Sarah Chen',
    email: 'sarah@agency.com',
    role: 'PM',
    color: '#3b82f6'
  }, {
    id: 'p2',
    name: 'James Wilson',
    email: 'james@agency.com',
    role: 'ECD',
    color: '#8b5cf6'
  }, {
    id: 'p3',
    name: 'Maria Garcia',
    email: 'maria@agency.com',
    role: 'CD',
    color: '#ec4899'
  }, {
    id: 'p4',
    name: 'Alex Thompson',
    email: 'alex@agency.com',
    role: 'Copywriter',
    color: '#f97316'
  }, {
    id: 'p5',
    name: 'Kim Lee',
    email: 'kim@agency.com',
    role: 'Designer',
    color: '#14b8a6'
  }, {
    id: 'p6',
    name: 'Jordan Blake',
    email: 'jordan@agency.com',
    role: 'QA',
    color: '#6366f1'
  }, {
    id: 'p7',
    name: 'Chris Martin',
    email: 'chris@client.com',
    role: 'Client',
    color: '#84cc16'
  }, {
    id: 'p8',
    name: 'Taylor Swift',
    email: 'taylor@agency.com',
    role: 'Producer',
    color: '#f43f5e'
  }, {
    id: 'p9',
    name: 'Morgan Davis',
    email: 'morgan@agency.com',
    role: 'Traffic',
    color: '#0ea5e9'
  }],
  projects: [{
    id: 'proj1',
    name: 'Summer Campaign',
    client: 'Acme Corp',
    description: 'Q3 summer product launch',
    status: 'In Progress',
    jobCount: 3,
    createdAt: '2024-01-15',
    clientColors: {
      primary: '#d4847a',
      secondary: '#c9a86c'
    }
  }, {
    id: 'proj2',
    name: 'Brand Refresh',
    client: 'TechStart',
    description: 'Complete brand identity overhaul',
    status: 'In Review',
    jobCount: 2,
    createdAt: '2024-01-20',
    clientColors: {
      primary: '#7a9bc4',
      secondary: '#a890c4'
    }
  }],
  jobs: [{
    id: 'job1',
    jobNumber: 'SUMM-001',
    name: 'Hero Video',
    description: 'Main campaign hero video',
    projectId: 'proj1',
    status: 'In Progress',
    assignments: {
      PM: 'p1',
      Traffic: 'p9',
      ECD: 'p2',
      CD: 'p3',
      Copywriter: 'p4',
      Designer: 'p5',
      QA: 'p6',
      Client: 'p7',
      Producer: 'p8'
    },
    dueDate: '2024-02-15',
    order: 0,
    createdAt: '2024-01-16'
  }, {
    id: 'job2',
    jobNumber: 'SUMM-002',
    name: 'Social Package',
    description: 'Social media content suite',
    projectId: 'proj1',
    status: 'Today',
    assignments: {
      PM: 'p1',
      Traffic: 'p1',
      ECD: 'p2',
      CD: 'p3',
      Copywriter: 'p4',
      Designer: 'p5',
      QA: 'p6',
      Client: 'p7',
      Producer: 'p8'
    },
    dueDate: '2024-02-10',
    order: 1,
    createdAt: '2024-01-17'
  }, {
    id: 'job3',
    jobNumber: 'SUMM-003',
    name: 'Email Campaign',
    description: 'Drip email sequence',
    projectId: 'proj1',
    status: 'Inbox',
    assignments: {
      PM: 'p1',
      Traffic: 'p9',
      ECD: 'p2',
      CD: 'p3',
      Copywriter: 'p4',
      Designer: 'p5',
      QA: 'p6',
      Client: 'p7',
      Producer: 'p8'
    },
    dueDate: '2024-02-20',
    order: 2,
    createdAt: '2024-01-18'
  }, {
    id: 'job4',
    jobNumber: 'BRAN-001',
    name: 'Logo Design',
    description: 'New logo concepts',
    projectId: 'proj2',
    status: 'In Review',
    assignments: {
      PM: 'p9',
      Traffic: 'p1',
      ECD: 'p2',
      CD: 'p3',
      Copywriter: 'p4',
      Designer: 'p5',
      QA: 'p6',
      Client: 'p7',
      Producer: 'p8'
    },
    dueDate: '2024-02-05',
    order: 0,
    createdAt: '2024-01-21'
  }, {
    id: 'job5',
    jobNumber: 'BRAN-002',
    name: 'Brand Guidelines',
    description: 'Complete brand book',
    projectId: 'proj2',
    status: 'On Hold',
    assignments: {
      PM: 'p9',
      Traffic: 'p1',
      ECD: 'p2',
      CD: 'p3',
      Copywriter: 'p4',
      Designer: 'p5',
      QA: 'p6',
      Client: 'p7',
      Producer: 'p8'
    },
    dueDate: '2024-02-28',
    order: 1,
    createdAt: '2024-01-22'
  }],
  assets: [{
    id: 'a1',
    name: 'Hero Video 60s',
    type: 'video',
    templateId: 'video-edit-long',
    jobId: 'job1',
    projectId: 'proj1',
    status: 'In Progress',
    assignedTo: 'p8',
    dueDate: '2024-02-12',
    order: 0
  }, {
    id: 'a2',
    name: 'Hero Video 30s',
    type: 'video',
    templateId: 'video-edit-short',
    jobId: 'job1',
    projectId: 'proj1',
    status: 'Inbox',
    assignedTo: 'p8',
    dueDate: '2024-02-14',
    order: 1
  }, {
    id: 'a3',
    name: 'Instagram Post 1',
    type: 'image',
    templateId: 'social-static',
    jobId: 'job2',
    projectId: 'proj1',
    status: 'Today',
    assignedTo: 'p5',
    dueDate: '2024-02-08',
    order: 0
  }, {
    id: 'a4',
    name: 'Instagram Post 2',
    type: 'image',
    templateId: 'social-static',
    jobId: 'job2',
    projectId: 'proj1',
    status: 'Today',
    assignedTo: 'p5',
    dueDate: '2024-02-08',
    order: 1
  }, {
    id: 'a5',
    name: 'Instagram Story',
    type: 'video',
    templateId: 'social-story',
    jobId: 'job2',
    projectId: 'proj1',
    status: 'Inbox',
    assignedTo: 'p5',
    dueDate: '2024-02-09',
    order: 2
  }, {
    id: 'a6',
    name: 'Welcome Email',
    type: 'document',
    templateId: 'email-template',
    jobId: 'job3',
    projectId: 'proj1',
    status: 'Inbox',
    assignedTo: 'p4',
    dueDate: '2024-02-18',
    order: 0
  }, {
    id: 'a7',
    name: 'Logo Concept A',
    type: 'image',
    templateId: 'presentation',
    jobId: 'job4',
    projectId: 'proj2',
    status: 'In Review',
    assignedTo: 'p3',
    dueDate: '2024-02-03',
    order: 0
  }, {
    id: 'a8',
    name: 'Logo Concept B',
    type: 'image',
    templateId: 'presentation',
    jobId: 'job4',
    projectId: 'proj2',
    status: 'In Review',
    assignedTo: 'p5',
    dueDate: '2024-02-03',
    order: 1
  }],
  tasks: [
  // Job 1 - Hero Video tasks
  {
    id: 't1',
    templateId: 'copy',
    jobId: 'job1',
    status: 'Done',
    assignedTo: 'p4',
    characterCount: 245,
    content: 'Summer is here! Experience the thrill...',
    fileUrl: null,
    fileType: null,
    completedAt: '2024-01-20',
    order: 0
  }, {
    id: 't2',
    templateId: 'media',
    jobId: 'job1',
    status: 'In Progress',
    assignedTo: 'p5',
    characterCount: null,
    content: null,
    fileUrl: 'hero-video-draft.mp4',
    fileType: 'video',
    completedAt: null,
    order: 1
  },
  // Job 2 - Social Package tasks
  {
    id: 't3',
    templateId: 'copy',
    jobId: 'job2',
    status: 'In Progress',
    assignedTo: 'p4',
    characterCount: 180,
    content: 'Get ready for summer vibes...',
    fileUrl: null,
    fileType: null,
    completedAt: null,
    order: 0
  }, {
    id: 't4',
    templateId: 'media',
    jobId: 'job2',
    status: 'Inbox',
    assignedTo: 'p5',
    characterCount: null,
    content: null,
    fileUrl: null,
    fileType: null,
    completedAt: null,
    order: 1
  },
  // Job 3 - Email Campaign tasks
  {
    id: 't5',
    templateId: 'copy',
    jobId: 'job3',
    status: 'Inbox',
    assignedTo: 'p4',
    characterCount: null,
    content: null,
    fileUrl: null,
    fileType: null,
    completedAt: null,
    order: 0
  }, {
    id: 't6',
    templateId: 'media',
    jobId: 'job3',
    status: 'Inbox',
    assignedTo: 'p5',
    characterCount: null,
    content: null,
    fileUrl: null,
    fileType: null,
    completedAt: null,
    order: 1
  },
  // Job 4 - Logo Design tasks
  {
    id: 't7',
    templateId: 'copy',
    jobId: 'job4',
    status: 'Done',
    assignedTo: 'p4',
    characterCount: 50,
    content: 'TechStart - Innovation Forward',
    fileUrl: null,
    fileType: null,
    completedAt: '2024-01-25',
    order: 0
  }, {
    id: 't8',
    templateId: 'media',
    jobId: 'job4',
    status: 'In Review',
    assignedTo: 'p5',
    characterCount: null,
    content: null,
    fileUrl: 'logo-concepts.png',
    fileType: 'image',
    completedAt: null,
    order: 1
  },
  // Job 5 - Brand Guidelines tasks
  {
    id: 't9',
    templateId: 'copy',
    jobId: 'job5',
    status: 'On Hold',
    assignedTo: 'p4',
    characterCount: null,
    content: null,
    fileUrl: null,
    fileType: null,
    completedAt: null,
    order: 0
  }, {
    id: 't10',
    templateId: 'media',
    jobId: 'job5',
    status: 'On Hold',
    assignedTo: 'p5',
    characterCount: null,
    content: null,
    fileUrl: null,
    fileType: null,
    completedAt: null,
    order: 1
  }],
  wikiPages: [{
    id: 'wiki1',
    title: 'Acme Corp',
    slug: 'acme-corp',
    content: '<h1>Acme Corp Client Bible</h1><h2>Brand Overview</h2><p>Acme Corp is a leading provider of innovative solutions. Founded in 1985, they have grown to serve millions of customers worldwide.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Director:</strong> John Smith (john@acme.com)</li><li><strong>Brand Manager:</strong> Jane Doe (jane@acme.com)</li></ul><h2>Brand Colors</h2><p><strong>Primary:</strong> #FF5733 (Acme Orange)</p><p><strong>Secondary:</strong> #333333 (Charcoal)</p>',
    templateId: 'client-bible',
    parentId: null,
    type: 'client',
    linkedJobs: [],
    linkedProjects: ['proj1'],
    tags: ['client', 'brand'],
    createdAt: '2024-01-10',
    updatedAt: '2024-01-15',
    createdBy: 'p1',
    order: 0
  }, {
    id: 'wiki2',
    title: 'Summer Campaign 2024',
    slug: 'summer-campaign-2024',
    content: '<h1>Summer Campaign 2024</h1><h2>Campaign Overview</h2><p><strong>Launch Date:</strong> June 1, 2024</p><p><strong>Objective:</strong> Drive awareness and sales for Q3 product line</p><h2>Target Audience</h2><p>Adults 25-45, urban professionals, household income $75k+</p><h2>Creative Approach</h2><p>Bright, energetic visuals emphasizing summer lifestyle and product benefits.</p>',
    templateId: 'campaign-log',
    parentId: 'wiki1',
    type: 'campaign',
    linkedJobs: ['job1', 'job2', 'job3'],
    linkedProjects: ['proj1'],
    tags: ['campaign', 'summer', '2024'],
    createdAt: '2024-01-16',
    updatedAt: '2024-01-20',
    createdBy: 'p1',
    order: 0
  }, {
    id: 'wiki3',
    title: 'TechStart',
    slug: 'techstart',
    content: '<h1>TechStart Client Bible</h1><h2>Brand Overview</h2><p>TechStart is a disruptive tech startup focused on B2B solutions.</p><h2>Tone of Voice</h2><p>Professional yet approachable. Innovative but not jargon-heavy.</p>',
    templateId: 'client-bible',
    parentId: null,
    type: 'client',
    linkedJobs: [],
    linkedProjects: ['proj2'],
    tags: ['client', 'tech'],
    createdAt: '2024-01-20',
    updatedAt: '2024-01-20',
    createdBy: 'p9',
    order: 1
  }, {
    id: 'wiki4',
    title: 'Cannes Lions 2024',
    slug: 'cannes-lions-2024',
    content: '<h1>Cannes Lions 2024 Entry</h1><h2>Award Details</h2><p><strong>Category:</strong> Digital Craft</p><p><strong>Deadline:</strong> March 15, 2024</p><h2>Campaign</h2><p>Summer Campaign Hero Video for Acme Corp</p><h2>Status</h2><p>In preparation</p>',
    templateId: 'award-submission',
    parentId: null,
    type: 'award',
    linkedJobs: ['job1'],
    linkedProjects: ['proj1'],
    tags: ['awards', 'cannes', '2024'],
    createdAt: '2024-01-25',
    updatedAt: '2024-01-25',
    createdBy: 'p2',
    order: 0
  }],
  scheduledEmails: []
});

// ============================================================================
// STATE MANAGEMENT
// ============================================================================

const STORAGE_KEY = 'slash301pm_data';
const loadFromStorage = () => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      let data = JSON.parse(saved);
      // Check and run migrations if needed
      const migratedData = migrateData(data);
      // Save migrated data if version changed
      if (migratedData._schemaVersion !== data._schemaVersion) {
        saveToStorage(migratedData);
      }
      return migratedData;
    }
  } catch (e) {
    console.error('Failed to load from storage:', e);
  }
  return createInitialData();
};
const saveToStorage = data => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    return {
      success: true
    };
  } catch (e) {
    console.error('Failed to save to storage:', e);

    // Handle QuotaExceededError
    if (e.name === 'QuotaExceededError' || e.code === 22 ||
    // Legacy Chrome
    e.code === 1014 ||
    // Legacy Firefox
    e.name === 'NS_ERROR_DOM_QUOTA_REACHED') {
      return {
        success: false,
        error: 'quota_exceeded',
        message: 'Storage quota exceeded. Your data may not be saved.'
      };
    }
    return {
      success: false,
      error: 'unknown',
      message: 'Failed to save data: ' + e.message
    };
  }
};

// Track last storage error for UI notification
let lastStorageError = null;
const getLastStorageError = () => lastStorageError;
const clearLastStorageError = () => {
  lastStorageError = null;
};
const dataReducer = (state, action) => {
  let newState;
  switch (action.type) {
    case 'SET_DATA':
      newState = action.payload;
      break;
    case 'ADD_PROJECT':
      newState = {
        ...state,
        projects: [...state.projects, action.payload]
      };
      break;
    case 'UPDATE_PROJECT':
      newState = {
        ...state,
        projects: state.projects.map(p => p.id === action.payload.id ? action.payload : p)
      };
      break;
    case 'DELETE_PROJECT': {
      const projectJobIds = state.jobs.filter(j => j.projectId === action.payload).map(j => j.id);
      newState = {
        ...state,
        projects: state.projects.filter(p => p.id !== action.payload),
        jobs: state.jobs.filter(j => j.projectId !== action.payload),
        assets: state.assets.filter(a => !projectJobIds.includes(a.jobId)),
        tasks: (state.tasks || []).filter(t => !projectJobIds.includes(t.jobId))
      };
      break;
    }
    case 'ADD_JOB':
      newState = {
        ...state,
        jobs: [...state.jobs, action.payload]
      };
      break;
    case 'UPDATE_JOB':
      newState = {
        ...state,
        jobs: state.jobs.map(j => j.id === action.payload.id ? action.payload : j)
      };
      break;
    case 'DELETE_JOB':
      newState = {
        ...state,
        jobs: state.jobs.filter(j => j.id !== action.payload),
        assets: state.assets.filter(a => a.jobId !== action.payload),
        tasks: (state.tasks || []).filter(t => t.jobId !== action.payload)
      };
      break;
    case 'REORDER_JOBS':
      newState = {
        ...state,
        jobs: action.payload
      };
      break;
    case 'ADD_ASSET':
      newState = {
        ...state,
        assets: [...state.assets, action.payload]
      };
      break;
    case 'UPDATE_ASSET':
      newState = {
        ...state,
        assets: state.assets.map(a => a.id === action.payload.id ? action.payload : a)
      };
      break;
    case 'DELETE_ASSET':
      newState = {
        ...state,
        assets: state.assets.filter(a => a.id !== action.payload)
      };
      break;
    case 'REORDER_ASSETS':
      newState = {
        ...state,
        assets: action.payload
      };
      break;
    case 'ADD_PERSON':
      newState = {
        ...state,
        people: [...state.people, action.payload]
      };
      break;
    case 'UPDATE_PERSON':
      newState = {
        ...state,
        people: state.people.map(p => p.id === action.payload.id ? action.payload : p)
      };
      break;
    case 'DELETE_PERSON': {
      const personId = action.payload;
      newState = {
        ...state,
        people: state.people.filter(p => p.id !== personId),
        jobs: state.jobs.map(j => {
          if (!j.assignments) return j;
          const cleaned = { ...j.assignments };
          Object.keys(cleaned).forEach(role => {
            if (cleaned[role] === personId) delete cleaned[role];
          });
          return { ...j, assignments: cleaned };
        }),
        assets: state.assets.map(a =>
          a.assignedTo === personId ? { ...a, assignedTo: null } : a
        )
      };
      break;
    }
    // Wiki actions
    case 'ADD_WIKI_PAGE':
      newState = {
        ...state,
        wikiPages: [...(state.wikiPages || []), action.payload]
      };
      break;
    case 'UPDATE_WIKI_PAGE':
      newState = {
        ...state,
        wikiPages: (state.wikiPages || []).map(p => p.id === action.payload.id ? action.payload : p)
      };
      break;
    case 'DELETE_WIKI_PAGE':
      newState = {
        ...state,
        wikiPages: (state.wikiPages || []).filter(p => p.id !== action.payload)
      };
      break;
    // Scheduled email actions
    case 'ADD_SCHEDULED_EMAIL':
      newState = {
        ...state,
        scheduledEmails: [...(state.scheduledEmails || []), action.payload]
      };
      break;
    case 'DELETE_SCHEDULED_EMAIL':
      newState = {
        ...state,
        scheduledEmails: (state.scheduledEmails || []).filter(e => e.id !== action.payload)
      };
      break;
    // Task actions
    case 'ADD_TASK':
      newState = {
        ...state,
        tasks: [...(state.tasks || []), action.payload]
      };
      break;
    case 'UPDATE_TASK':
      newState = {
        ...state,
        tasks: (state.tasks || []).map(t => t.id === action.payload.id ? action.payload : t)
      };
      break;
    case 'DELETE_TASK':
      newState = {
        ...state,
        tasks: (state.tasks || []).filter(t => t.id !== action.payload)
      };
      break;
    default:
      return state;
  }
  const saveResult = saveToStorage(newState);
  if (!saveResult.success) {
    lastStorageError = saveResult;
  }
  return newState;
};
// ============================================================================
// SMALL COMPONENTS
// ============================================================================

// Get status type for traffic light styling
const getStatusType = status => {
  const redStatuses = ['Inbox', 'Today', 'On Hold', 'Backlog'];
  const orangeStatuses = ['To Do', 'This Week', 'In Progress', 'Waiting', 'In Review'];
  const greenStatuses = ['Done', 'Approved (Internal)', 'Approved (External)', 'Scheduled', 'Live'];
  if (redStatuses.includes(status)) return 'red';
  if (orangeStatuses.includes(status)) return 'orange';
  if (greenStatuses.includes(status)) return 'green';
  return 'grey';
};

// Group statuses by traffic light color
const STATUS_GROUPS = {
  red: {
    label: 'Needs Attention',
    statuses: ['Backlog', 'Inbox', 'Today', 'On Hold']
  },
  orange: {
    label: 'In Progress',
    statuses: ['To Do', 'This Week', 'In Progress', 'Waiting', 'In Review']
  },
  green: {
    label: 'Completed',
    statuses: ['Done', 'Approved (Internal)', 'Approved (External)', 'Scheduled', 'Live']
  },
  grey: {
    label: 'Other',
    statuses: ['Archived']
  }
};
const StatusBadge = ({
  status,
  onChange,
  statuses = STATUSES
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const color = STATUS_COLORS[status] || '#6b7280';
  const statusType = getStatusType(status);

  // Filter available statuses based on what's passed in
  const getGroupedStatuses = () => {
    const groups = [];
    Object.entries(STATUS_GROUPS).forEach(([type, group]) => {
      const availableStatuses = group.statuses.filter(s => statuses.includes(s));
      if (availableStatuses.length > 0) {
        groups.push({
          type,
          label: group.label,
          statuses: availableStatuses
        });
      }
    });
    return groups;
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "status-badge-wrapper"
  }, /*#__PURE__*/React.createElement("span", {
    className: "status-badge",
    "data-status-type": statusType,
    style: {
      backgroundColor: color + '15',
      color: color
    },
    onClick: () => onChange && setIsOpen(!isOpen)
  }, status), isOpen && onChange && /*#__PURE__*/React.createElement("div", {
    className: "status-dropdown"
  }, getGroupedStatuses().map(group => /*#__PURE__*/React.createElement("div", {
    key: group.type,
    className: "status-dropdown-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "status-dropdown-label"
  }, group.label), group.statuses.map(s => /*#__PURE__*/React.createElement("div", {
    key: s,
    className: "status-option",
    style: {
      color: STATUS_COLORS[s]
    },
    onClick: () => {
      onChange(s);
      setIsOpen(false);
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "status-dot",
    style: {
      backgroundColor: STATUS_COLORS[s]
    }
  }), s))))));
};
const PersonAvatar = ({
  person,
  size = 'small',
  showName = false
}) => {
  if (!person) return /*#__PURE__*/React.createElement("span", {
    className: "avatar-placeholder"
  }, "?");
  const initials = person.name.split(' ').map(n => n[0]).join('').slice(0, 2);
  return /*#__PURE__*/React.createElement("div", {
    className: `person-avatar ${size}`,
    title: person.name
  }, /*#__PURE__*/React.createElement("span", {
    className: "avatar-circle",
    style: {
      backgroundColor: person.color
    }
  }, initials), showName && /*#__PURE__*/React.createElement("span", {
    className: "avatar-name"
  }, person.name));
};
const PersonAvatarGroup = ({
  personIds,
  people,
  max = 3
}) => {
  const persons = personIds.map(id => people.find(p => p.id === id)).filter(Boolean);
  const visible = persons.slice(0, max);
  const extra = persons.length - max;
  return /*#__PURE__*/React.createElement("div", {
    className: "avatar-group"
  }, visible.map(p => /*#__PURE__*/React.createElement(PersonAvatar, {
    key: p.id,
    person: p
  })), extra > 0 && /*#__PURE__*/React.createElement("span", {
    className: "avatar-extra"
  }, "+", extra));
};

// ============================================================================
// STORAGE WARNING COMPONENT
// ============================================================================

const StorageWarning = ({
  error,
  onDismiss,
  onExport
}) => {
  if (!error) return null;
  const isQuotaError = error.error === 'quota_exceeded';
  return /*#__PURE__*/React.createElement("div", {
    className: "storage-warning"
  }, /*#__PURE__*/React.createElement("div", {
    className: "storage-warning-content"
  }, /*#__PURE__*/React.createElement("span", {
    className: "storage-warning-icon"
  }, "\u26A0"), /*#__PURE__*/React.createElement("div", {
    className: "storage-warning-text"
  }, /*#__PURE__*/React.createElement("strong", null, isQuotaError ? 'Storage Full' : 'Save Error'), /*#__PURE__*/React.createElement("p", null, error.message), isQuotaError && /*#__PURE__*/React.createElement("p", {
    className: "storage-warning-hint"
  }, "Export your data to avoid losing work, then clear old browser data.")), /*#__PURE__*/React.createElement("div", {
    className: "storage-warning-actions"
  }, isQuotaError && onExport && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: onExport
  }, "Export Data"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onDismiss
  }, "Dismiss"))));
};

// ============================================================================
// TABLE VIEW COMPONENT
// ============================================================================
const TableView = ({
  tab,
  data,
  dispatch,
  people,
  projects,
  onRowClick,
  searchQuery,
  currentUser
}) => {
  const [sortField, setSortField] = useState(null);
  const [sortDir, setSortDir] = useState('asc');
  const handleSort = field => {
    if (sortField === field) {
      setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };
  const getItems = () => {
    let items = [];
    switch (tab) {
      case 'Projects':
        items = data.projects;
        break;
      case 'Jobs':
        items = data.jobs;
        break;
      case 'Assets':
        items = data.assets;
        break;
      case 'People':
        items = data.people;
        break;
    }

    // Filter by permissions - only show items user can view
    if (currentUser && tab !== 'People') {
      items = items.filter(item => canUserViewItem(currentUser, item, tab, data));
    }

    // Filter by search
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      items = items.filter(item => {
        const name = item.name || '';
        const desc = item.description || '';
        const email = item.email || '';
        return name.toLowerCase().includes(q) || desc.toLowerCase().includes(q) || email.toLowerCase().includes(q);
      });
    }

    // Sort
    if (sortField) {
      items = [...items].sort((a, b) => {
        let aVal = a[sortField] || '';
        let bVal = b[sortField] || '';
        if (typeof aVal === 'string') aVal = aVal.toLowerCase();
        if (typeof bVal === 'string') bVal = bVal.toLowerCase();
        if (aVal < bVal) return sortDir === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDir === 'asc' ? 1 : -1;
        return 0;
      });
    }
    return items;
  };
  const handleStatusChange = (item, newStatus) => {
    // Check if user can edit status
    if (!canUserEditItem(currentUser, item, tab, 'status')) {
      return;
    }
    switch (tab) {
      case 'Projects':
        dispatch({
          type: 'UPDATE_PROJECT',
          payload: {
            ...item,
            status: newStatus
          }
        });
        break;
      case 'Jobs':
        dispatch({
          type: 'UPDATE_JOB',
          payload: {
            ...item,
            status: newStatus
          }
        });
        break;
      case 'Assets':
        dispatch({
          type: 'UPDATE_ASSET',
          payload: {
            ...item,
            status: newStatus
          }
        });
        break;
    }
  };
  const items = getItems();
  const getProjectName = projectId => {
    const proj = projects.find(p => p.id === projectId);
    return proj ? proj.name : '';
  };
  const renderColumns = () => {
    switch (tab) {
      case 'Projects':
        return /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('name')
        }, "Name ", sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('client')
        }, "Client ", sortField === 'client' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('status')
        }, "Status ", sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Jobs"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('createdAt')
        }, "Created ", sortField === 'createdAt' && (sortDir === 'asc' ? '↑' : '↓')));
      case 'Jobs':
        return /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('jobNumber')
        }, "Job # ", sortField === 'jobNumber' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('name')
        }, "Name ", sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Project"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('status')
        }, "Status ", sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Team"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('dueDate')
        }, "Due ", sortField === 'dueDate' && (sortDir === 'asc' ? '↑' : '↓')));
      case 'Assets':
        return /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('name')
        }, "Name ", sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('type')
        }, "Type ", sortField === 'type' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Job"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('status')
        }, "Status ", sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Assigned"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('dueDate')
        }, "Due ", sortField === 'dueDate' && (sortDir === 'asc' ? '↑' : '↓')));
      case 'People':
        return /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('name')
        }, "Name ", sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('email')
        }, "Email ", sortField === 'email' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Role"), /*#__PURE__*/React.createElement("th", null, "Active Jobs"));
    }
  };
  const renderRow = item => {
    const canEditStatus = canUserEditItem(currentUser, item, tab, 'status');
    switch (tab) {
      case 'Projects':
        const jobCount = data.jobs.filter(j => j.projectId === item.id).length;
        return /*#__PURE__*/React.createElement("tr", {
          key: item.id,
          onClick: () => onRowClick(item)
        }, /*#__PURE__*/React.createElement("td", {
          className: "cell-name"
        }, item.name), /*#__PURE__*/React.createElement("td", null, item.client), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(StatusBadge, {
          status: item.status,
          onChange: canEditStatus ? s => handleStatusChange(item, s) : null
        })), /*#__PURE__*/React.createElement("td", null, jobCount), /*#__PURE__*/React.createElement("td", null, formatDate(item.createdAt)));
      case 'Jobs':
        const job = item;
        const assignedIds = Object.values(job.assignments || {}).filter(Boolean);
        return /*#__PURE__*/React.createElement("tr", {
          key: item.id,
          onClick: () => onRowClick(item)
        }, /*#__PURE__*/React.createElement("td", {
          className: "cell-jobnum"
        }, job.jobNumber), /*#__PURE__*/React.createElement("td", {
          className: "cell-name"
        }, job.name), /*#__PURE__*/React.createElement("td", null, getProjectName(job.projectId)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(StatusBadge, {
          status: job.status,
          onChange: canEditStatus ? s => handleStatusChange(item, s) : null,
          statuses: STATUSES_WITH_DONE
        })), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(PersonAvatarGroup, {
          personIds: assignedIds,
          people: people
        })), /*#__PURE__*/React.createElement("td", null, formatDate(job.dueDate)));
      case 'Assets':
        const asset = item;
        const assignee = people.find(p => p.id === asset.assignedTo);
        const assetJob = data.jobs.find(j => j.id === asset.jobId);
        return /*#__PURE__*/React.createElement("tr", {
          key: item.id,
          onClick: () => onRowClick(item)
        }, /*#__PURE__*/React.createElement("td", {
          className: "cell-name"
        }, asset.name), /*#__PURE__*/React.createElement("td", {
          className: "cell-type"
        }, asset.type), /*#__PURE__*/React.createElement("td", null, assetJob ? assetJob.jobNumber : ''), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(StatusBadge, {
          status: asset.status,
          onChange: canEditStatus ? s => handleStatusChange(item, s) : null,
          statuses: STATUSES_WITH_DONE
        })), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(PersonAvatar, {
          person: assignee,
          showName: true
        })), /*#__PURE__*/React.createElement("td", null, formatDate(asset.dueDate)));
      case 'People':
        const person = item;
        const activeJobs = data.jobs.filter(j => Object.values(j.assignments || {}).includes(person.id)).length;
        return /*#__PURE__*/React.createElement("tr", {
          key: item.id,
          onClick: () => onRowClick(item)
        }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(PersonAvatar, {
          person: person,
          showName: true,
          size: "medium"
        })), /*#__PURE__*/React.createElement("td", null, person.email), /*#__PURE__*/React.createElement("td", {
          className: "cell-roles"
        }, person.role), /*#__PURE__*/React.createElement("td", null, activeJobs));
    }
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "table-view"
  }, /*#__PURE__*/React.createElement("table", null, /*#__PURE__*/React.createElement("thead", null, renderColumns()), /*#__PURE__*/React.createElement("tbody", null, items.map(item => renderRow(item)), items.length === 0 && /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", {
    colSpan: "6",
    className: "empty-row"
  }, "\u25CC No items found")))));
};

// ============================================================================
// KANBAN COMPONENTS
// ============================================================================

const KanbanCard = ({
  item,
  type,
  people,
  projects,
  jobs,
  onDragStart,
  onDragEnd,
  onClick
}) => {
  const getProjectName = projectId => {
    const proj = projects.find(p => p.id === projectId);
    return proj ? proj.name : '';
  };
  const getJobNumber = jobId => {
    const job = jobs.find(j => j.id === jobId);
    return job ? job.jobNumber : '';
  };
  const renderContent = () => {
    switch (type) {
      case 'Projects':
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
          className: "card-title"
        }, item.name), /*#__PURE__*/React.createElement("div", {
          className: "card-meta"
        }, item.client));
      case 'Jobs':
        const assignedIds = Object.values(item.assignments || {}).filter(Boolean);
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
          className: "card-jobnum"
        }, item.jobNumber), /*#__PURE__*/React.createElement("div", {
          className: "card-title"
        }, item.name), /*#__PURE__*/React.createElement("div", {
          className: "card-meta"
        }, getProjectName(item.projectId)), /*#__PURE__*/React.createElement("div", {
          className: "card-footer"
        }, /*#__PURE__*/React.createElement(PersonAvatarGroup, {
          personIds: assignedIds,
          people: people,
          max: 4
        }), item.dueDate && /*#__PURE__*/React.createElement("span", {
          className: "card-due"
        }, formatDate(item.dueDate))));
      case 'Assets':
        const assignee = people.find(p => p.id === item.assignedTo);
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
          className: "card-type"
        }, item.type), /*#__PURE__*/React.createElement("div", {
          className: "card-title"
        }, item.name), /*#__PURE__*/React.createElement("div", {
          className: "card-meta"
        }, getJobNumber(item.jobId)), /*#__PURE__*/React.createElement("div", {
          className: "card-footer"
        }, assignee && /*#__PURE__*/React.createElement(PersonAvatar, {
          person: assignee
        }), item.dueDate && /*#__PURE__*/React.createElement("span", {
          className: "card-due"
        }, formatDate(item.dueDate))));
      case 'People':
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(PersonAvatar, {
          person: item,
          size: "medium"
        }), /*#__PURE__*/React.createElement("div", {
          className: "card-title"
        }, item.name), /*#__PURE__*/React.createElement("div", {
          className: "card-meta"
        }, item.role));
    }
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "kanban-card",
    draggable: true,
    onDragStart: e => onDragStart(e, item),
    onDragEnd: onDragEnd,
    onClick: () => onClick(item)
  }, renderContent());
};
const KanbanColumn = ({
  status,
  items,
  type,
  people,
  projects,
  jobs,
  onDrop,
  onDragStart,
  onDragEnd,
  onCardClick
}) => {
  const [isDragOver, setIsDragOver] = useState(false);
  const handleDragOver = e => {
    e.preventDefault();
    setIsDragOver(true);
  };
  const handleDragLeave = () => {
    setIsDragOver(false);
  };
  const handleDrop = e => {
    e.preventDefault();
    setIsDragOver(false);
    onDrop(status, e);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: `kanban-column ${isDragOver ? 'drag-over' : ''}`,
    onDragOver: handleDragOver,
    onDragLeave: handleDragLeave,
    onDrop: handleDrop
  }, /*#__PURE__*/React.createElement("div", {
    className: "column-header"
  }, /*#__PURE__*/React.createElement("span", {
    className: "column-status",
    style: {
      color: STATUS_COLORS[status]
    }
  }, status), /*#__PURE__*/React.createElement("span", {
    className: "column-count"
  }, items.length)), /*#__PURE__*/React.createElement("div", {
    className: "column-cards"
  }, items.map(item => /*#__PURE__*/React.createElement(KanbanCard, {
    key: item.id,
    item: item,
    type: type,
    people: people,
    projects: projects,
    jobs: jobs,
    onDragStart: onDragStart,
    onDragEnd: onDragEnd,
    onClick: onCardClick
  }))));
};
const KanbanBoard = ({
  tab,
  data,
  dispatch,
  people,
  projects,
  onCardClick,
  searchQuery,
  currentUser
}) => {
  const [draggedItem, setDraggedItem] = useState(null);
  const statuses = tab === 'Jobs' || tab === 'Assets' ? STATUSES_WITH_DONE : STATUSES;
  const getItems = () => {
    let items = [];
    switch (tab) {
      case 'Projects':
        items = data.projects;
        break;
      case 'Jobs':
        items = data.jobs;
        break;
      case 'Assets':
        items = data.assets;
        break;
      case 'People':
        items = data.people;
        break;
    }

    // Filter by permissions - only show items user can view
    if (currentUser && tab !== 'People') {
      items = items.filter(item => canUserViewItem(currentUser, item, tab, data));
    }

    // Filter by search
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      items = items.filter(item => {
        const name = item.name || '';
        const desc = item.description || '';
        return name.toLowerCase().includes(q) || desc.toLowerCase().includes(q);
      });
    }
    return items;
  };
  const items = getItems();
  const getItemsByStatus = status => {
    if (tab === 'People') {
      // For people, we'll use their primary role as a pseudo-status
      return items.filter(p => p.role === status);
    }
    return items.filter(item => item.status === status).sort((a, b) => (a.order || 0) - (b.order || 0));
  };
  const handleDragStart = (e, item) => {
    setDraggedItem(item);
    e.dataTransfer.effectAllowed = 'move';
  };
  const handleDragEnd = () => {
    setDraggedItem(null);
  };
  const handleDrop = (newStatus, e) => {
    if (!draggedItem) return;

    // Check if user can edit status on this item
    if (!canUserEditItem(currentUser, draggedItem, tab, 'status')) {
      setDraggedItem(null);
      return;
    }
    const updatedItem = {
      ...draggedItem,
      status: newStatus
    };
    switch (tab) {
      case 'Projects':
        dispatch({
          type: 'UPDATE_PROJECT',
          payload: updatedItem
        });
        break;
      case 'Jobs':
        // Reorder within status
        const jobsInStatus = items.filter(j => j.status === newStatus && j.id !== draggedItem.id);
        const newOrder = jobsInStatus.length;
        dispatch({
          type: 'UPDATE_JOB',
          payload: {
            ...updatedItem,
            order: newOrder
          }
        });
        break;
      case 'Assets':
        const assetsInStatus = items.filter(a => a.status === newStatus && a.id !== draggedItem.id);
        const assetOrder = assetsInStatus.length;
        dispatch({
          type: 'UPDATE_ASSET',
          payload: {
            ...updatedItem,
            order: assetOrder
          }
        });
        break;
    }
    setDraggedItem(null);
  };

  // For People tab, use roles as columns instead of statuses
  if (tab === 'People') {
    return /*#__PURE__*/React.createElement("div", {
      className: "kanban-board"
    }, ROLES.map(role => /*#__PURE__*/React.createElement(KanbanColumn, {
      key: role,
      status: role,
      items: items.filter(p => p.role === role),
      type: tab,
      people: people,
      projects: projects,
      jobs: data.jobs,
      onDrop: () => {},
      onDragStart: handleDragStart,
      onDragEnd: handleDragEnd,
      onCardClick: onCardClick
    })));
  }
  return /*#__PURE__*/React.createElement("div", {
    className: "kanban-board"
  }, statuses.map(status => /*#__PURE__*/React.createElement(KanbanColumn, {
    key: status,
    status: status,
    items: getItemsByStatus(status),
    type: tab,
    people: people,
    projects: projects,
    jobs: data.jobs,
    onDrop: handleDrop,
    onDragStart: handleDragStart,
    onDragEnd: handleDragEnd,
    onCardClick: onCardClick
  })));
};

// ============================================================================
// BRIEF MODAL COMPONENT
// ============================================================================
// ============================================================================
// BRIEF MODAL (Phase 2 - Collapsible Sections)
// ============================================================================
//
// Form UX with collapsible sections:
// - Brief Details (expanded by default)
// - Team Assignments (expanded by default)
// - Creative Direction (expanded by default)
// - Content Details (collapsed by default)
// - Attachments (collapsed by default)
// - Additional Dates (collapsed by default)
// ============================================================================

const CollapsibleSection = ({
  title,
  defaultOpen = false,
  children,
  required = false
}) => {
  const [isOpen, setIsOpen] = useState(defaultOpen);
  return /*#__PURE__*/React.createElement("div", {
    className: `collapsible-section ${isOpen ? 'open' : ''}`
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "collapsible-header",
    onClick: () => setIsOpen(!isOpen)
  }, /*#__PURE__*/React.createElement("span", {
    className: "collapsible-icon"
  }, isOpen ? '▼' : '▶'), /*#__PURE__*/React.createElement("span", {
    className: "collapsible-title"
  }, title, required && /*#__PURE__*/React.createElement("span", {
    className: "required-indicator"
  }, "*"))), isOpen && /*#__PURE__*/React.createElement("div", {
    className: "collapsible-content"
  }, children));
};
const BriefModal = ({
  isOpen,
  onClose,
  data,
  dispatch
}) => {
  // Brief Details
  const [name, setName] = useState('');
  const [projectId, setProjectId] = useState('');
  const [newProjectName, setNewProjectName] = useState('');
  const [newProjectClient, setNewProjectClient] = useState('');
  const [briefDate, setBriefDate] = useState(() => new Date().toISOString().split('T')[0]);
  const [dueDate, setDueDate] = useState('');

  // Creative Direction
  const [description, setDescription] = useState('');

  // Content Details
  const [selectedAssets, setSelectedAssets] = useState([]);
  const [numPosts, setNumPosts] = useState('');
  const [hoursEstimate, setHoursEstimate] = useState('');

  // Team Assignments
  const [assignments, setAssignments] = useState({});

  // Attachments
  const [briefPdfUrl, setBriefPdfUrl] = useState('');
  const [serverLink, setServerLink] = useState('');

  // Additional Dates
  const [firstGoLiveDate, setFirstGoLiveDate] = useState('');
  const [lastGoLiveDate, setLastGoLiveDate] = useState('');
  useEffect(() => {
    // Initialize assignments with empty values for each role
    const initial = {};
    ROLES.forEach(role => {
      initial[role] = '';
    });
    setAssignments(initial);
    // Reset brief date to today
    setBriefDate(new Date().toISOString().split('T')[0]);
  }, [isOpen]);
  const handleAddAsset = templateId => {
    const template = ASSET_TEMPLATES.find(t => t.id === templateId);
    if (template) {
      setSelectedAssets([...selectedAssets, {
        ...template,
        tempId: generateId(),
        quantity: 1
      }]);
    }
  };
  const handleRemoveAsset = tempId => {
    setSelectedAssets(selectedAssets.filter(a => a.tempId !== tempId));
  };
  const handleSubmit = () => {
    if (!name) return alert('Job name is required');
    let targetProjectId = projectId;
    let project;

    // Create new project if needed
    if (!projectId && newProjectName) {
      project = {
        id: generateId(),
        name: newProjectName,
        client: newProjectClient,
        description: '',
        status: 'In Progress',
        jobCount: 0,
        createdAt: new Date().toISOString()
      };
      dispatch({
        type: 'ADD_PROJECT',
        payload: project
      });
      targetProjectId = project.id;
    } else {
      project = data.projects.find(p => p.id === projectId);
    }
    if (!targetProjectId) return alert('Please select or create a project');

    // Generate job number
    const projectCode = getProjectCode(project ? project.name : newProjectName);
    const currentProject = data.projects.find(p => p.id === targetProjectId) || project;
    const jobNumber = generateJobNumber(projectCode, (currentProject.jobCount || 0) + 1);

    // Update project job count
    dispatch({
      type: 'UPDATE_PROJECT',
      payload: {
        ...currentProject,
        jobCount: (currentProject.jobCount || 0) + 1
      }
    });

    // Create the job with extended fields
    const job = {
      id: generateId(),
      jobNumber,
      name,
      description,
      projectId: targetProjectId,
      status: 'Inbox',
      assignments,
      briefDate,
      dueDate,
      firstGoLiveDate,
      lastGoLiveDate,
      briefPdfUrl,
      serverLink,
      numPosts: numPosts ? parseInt(numPosts) : null,
      hoursEstimate: hoursEstimate ? parseFloat(hoursEstimate) : null,
      order: data.jobs.filter(j => j.projectId === targetProjectId).length,
      createdAt: new Date().toISOString()
    };
    dispatch({
      type: 'ADD_JOB',
      payload: job
    });

    // Create required tasks (Copy and Media) for every job
    TASK_TEMPLATES.filter(t => t.required).forEach((template, index) => {
      const task = {
        id: generateId(),
        templateId: template.id,
        jobId: job.id,
        status: 'Inbox',
        assignedTo: assignments[template.assignedRole] || '',
        characterCount: null,
        content: null,
        fileUrl: null,
        fileType: null,
        completedAt: null,
        order: index
      };
      dispatch({
        type: 'ADD_TASK',
        payload: task
      });
    });

    // Create assets from selected templates with auto-naming
    const clientName = project ? project.client : newProjectClient;
    const campaignName = project ? project.name : newProjectName;
    selectedAssets.forEach((template, index) => {
      // Generate compliant asset name
      const assetName = generateAssetName({
        jobNumber: job.jobNumber,
        client: clientName,
        campaignName: campaignName,
        assetType: template.name.replace(/[^a-zA-Z0-9]/g, ''),
        sequence: index + 1,
        version: 1
      });
      const asset = {
        id: generateId(),
        name: assetName,
        displayName: `${name} - ${template.name}`,
        // Human-readable fallback
        type: template.type,
        templateId: template.id,
        jobId: job.id,
        projectId: targetProjectId,
        status: 'Inbox',
        assignedTo: '',
        dueDate,
        order: index
      };
      dispatch({
        type: 'ADD_ASSET',
        payload: asset
      });
    });

    // Reset form and close
    resetForm();
    onClose();
  };
  const resetForm = () => {
    setName('');
    setDescription('');
    setProjectId('');
    setNewProjectName('');
    setNewProjectClient('');
    setBriefDate(new Date().toISOString().split('T')[0]);
    setDueDate('');
    setSelectedAssets([]);
    setAssignments({});
    setBriefPdfUrl('');
    setServerLink('');
    setFirstGoLiveDate('');
    setLastGoLiveDate('');
    setNumPosts('');
    setHoursEstimate('');
  };
  if (!isOpen) return null;

  // Key roles for quick assignment
  const KEY_ROLES = ['PM', 'Traffic', 'Copywriter', 'Designer', 'CD', 'ECD'];
  return /*#__PURE__*/React.createElement("div", {
    className: "modal-overlay",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-content brief-modal collapsible-modal",
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-header"
  }, /*#__PURE__*/React.createElement("h2", null, "New Brief"), /*#__PURE__*/React.createElement("button", {
    className: "modal-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "modal-body"
  }, /*#__PURE__*/React.createElement(CollapsibleSection, {
    title: "Brief Details",
    defaultOpen: true,
    required: true
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-2"
  }, /*#__PURE__*/React.createElement("label", null, "Job Name *"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: name,
    onChange: e => setName(e.target.value),
    placeholder: "e.g., Summer Campaign Video"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "Project/Campaign *"), /*#__PURE__*/React.createElement("select", {
    value: projectId,
    onChange: e => setProjectId(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- Create New --"), data.projects.map(p => /*#__PURE__*/React.createElement("option", {
    key: p.id,
    value: p.id
  }, p.client, " - ", p.name))))), !projectId && /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "New Project Name"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: newProjectName,
    onChange: e => setNewProjectName(e.target.value),
    placeholder: "e.g., Summer Campaign 2024"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "Client"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: newProjectClient,
    onChange: e => setNewProjectClient(e.target.value),
    placeholder: "e.g., Acme Corp"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "Brief Date *"), /*#__PURE__*/React.createElement("input", {
    type: "date",
    value: briefDate,
    onChange: e => setBriefDate(e.target.value)
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "Delivery Date *"), /*#__PURE__*/React.createElement("input", {
    type: "date",
    value: dueDate,
    onChange: e => setDueDate(e.target.value)
  })))), /*#__PURE__*/React.createElement(CollapsibleSection, {
    title: "Team Assignments",
    defaultOpen: true
  }, /*#__PURE__*/React.createElement("div", {
    className: "role-grid compact"
  }, KEY_ROLES.map(role => /*#__PURE__*/React.createElement("div", {
    key: role,
    className: "form-group role-select"
  }, /*#__PURE__*/React.createElement("label", null, role), /*#__PURE__*/React.createElement("select", {
    value: assignments[role] || '',
    onChange: e => setAssignments({
      ...assignments,
      [role]: e.target.value
    })
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- Select --"), data.people.filter(p => p.role === role).map(p => /*#__PURE__*/React.createElement("option", {
    key: p.id,
    value: p.id
  }, p.name)))))), /*#__PURE__*/React.createElement("details", {
    className: "more-roles"
  }, /*#__PURE__*/React.createElement("summary", null, "More roles..."), /*#__PURE__*/React.createElement("div", {
    className: "role-grid compact"
  }, ROLES.filter(r => !KEY_ROLES.includes(r)).map(role => /*#__PURE__*/React.createElement("div", {
    key: role,
    className: "form-group role-select"
  }, /*#__PURE__*/React.createElement("label", null, role), /*#__PURE__*/React.createElement("select", {
    value: assignments[role] || '',
    onChange: e => setAssignments({
      ...assignments,
      [role]: e.target.value
    })
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- Select --"), data.people.filter(p => p.role === role).map(p => /*#__PURE__*/React.createElement("option", {
    key: p.id,
    value: p.id
  }, p.name)))))))), /*#__PURE__*/React.createElement(CollapsibleSection, {
    title: "Creative Direction",
    defaultOpen: true
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Brief / Creative Direction"), /*#__PURE__*/React.createElement("textarea", {
    value: description,
    onChange: e => setDescription(e.target.value),
    placeholder: "Describe the creative direction, key messages, tone of voice, and any specific requirements...",
    rows: 5
  }))), /*#__PURE__*/React.createElement(CollapsibleSection, {
    title: "Content Details",
    defaultOpen: false
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "Number of Posts"), /*#__PURE__*/React.createElement("input", {
    type: "number",
    value: numPosts,
    onChange: e => setNumPosts(e.target.value),
    placeholder: "e.g., 6",
    min: "0"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "Hours Estimate"), /*#__PURE__*/React.createElement("input", {
    type: "number",
    value: hoursEstimate,
    onChange: e => setHoursEstimate(e.target.value),
    placeholder: "e.g., 8",
    min: "0",
    step: "0.5"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Required Assets"), /*#__PURE__*/React.createElement("select", {
    onChange: e => {
      if (e.target.value) handleAddAsset(e.target.value);
      e.target.value = '';
    }
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- Add Asset Type --"), ASSET_TEMPLATES.map(t => /*#__PURE__*/React.createElement("option", {
    key: t.id,
    value: t.id
  }, t.name, " (", t.type, ")")))), /*#__PURE__*/React.createElement("div", {
    className: "asset-list"
  }, selectedAssets.map(asset => /*#__PURE__*/React.createElement("div", {
    key: asset.tempId,
    className: "asset-item"
  }, /*#__PURE__*/React.createElement("span", {
    className: "asset-type-badge"
  }, asset.type), /*#__PURE__*/React.createElement("span", null, asset.name), /*#__PURE__*/React.createElement("button", {
    className: "btn-remove",
    onClick: () => handleRemoveAsset(asset.tempId)
  }, "\xD7"))), selectedAssets.length === 0 && /*#__PURE__*/React.createElement("p", {
    className: "empty-text"
  }, "No assets added"))), /*#__PURE__*/React.createElement(CollapsibleSection, {
    title: "Attachments",
    defaultOpen: false
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Brief PDF URL"), /*#__PURE__*/React.createElement("input", {
    type: "url",
    value: briefPdfUrl,
    onChange: e => setBriefPdfUrl(e.target.value),
    placeholder: "https://drive.google.com/..."
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Server Link"), /*#__PURE__*/React.createElement("input", {
    type: "url",
    value: serverLink,
    onChange: e => setServerLink(e.target.value),
    placeholder: "https://server.agency.com/..."
  }))), /*#__PURE__*/React.createElement(CollapsibleSection, {
    title: "Additional Dates",
    defaultOpen: false
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "First Go-Live Date"), /*#__PURE__*/React.createElement("input", {
    type: "date",
    value: firstGoLiveDate,
    onChange: e => setFirstGoLiveDate(e.target.value)
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group flex-1"
  }, /*#__PURE__*/React.createElement("label", null, "Last Go-Live Date"), /*#__PURE__*/React.createElement("input", {
    type: "date",
    value: lastGoLiveDate,
    onChange: e => setLastGoLiveDate(e.target.value)
  }))))), /*#__PURE__*/React.createElement("div", {
    className: "modal-footer"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSubmit
  }, "Create Brief"))));
};

// ============================================================================
// TASK LIST COMPONENT
// ============================================================================
const AddPersonModal = ({
  isOpen,
  onClose,
  dispatch
}) => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState('');
  const [color, setColor] = useState('#3b82f6');
  const handleSubmit = () => {
    if (!name || !email || !role) {
      return alert('Please fill in all required fields');
    }
    const person = {
      id: generateId(),
      name,
      email,
      role,
      color
    };
    dispatch({
      type: 'ADD_PERSON',
      payload: person
    });
    setName('');
    setEmail('');
    setRole('');
    setColor('#3b82f6');
    onClose();
  };
  if (!isOpen) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "modal-overlay",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-content",
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-header"
  }, /*#__PURE__*/React.createElement("h2", null, "Add Person"), /*#__PURE__*/React.createElement("button", {
    className: "modal-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "modal-body"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Name *"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: name,
    onChange: e => setName(e.target.value),
    placeholder: "Full name"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Email *"), /*#__PURE__*/React.createElement("input", {
    type: "email",
    value: email,
    onChange: e => setEmail(e.target.value),
    placeholder: "email@example.com"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Role *"), /*#__PURE__*/React.createElement("select", {
    value: role,
    onChange: e => setRole(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "Select a role"), ROLES.map(r => /*#__PURE__*/React.createElement("option", {
    key: r,
    value: r
  }, r)))), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Color"), /*#__PURE__*/React.createElement("input", {
    type: "color",
    value: color,
    onChange: e => setColor(e.target.value)
  }))), /*#__PURE__*/React.createElement("div", {
    className: "modal-footer"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSubmit
  }, "Add Person"))));
};

// ============================================================================
// USER SELECTOR COMPONENT
// ============================================================================

const UserSelector = ({
  people,
  currentUser,
  onUserChange
}) => {
  const [isOpen, setIsOpen] = useState(false);
  if (!currentUser) return null;
  const permissions = getUserPermissions(currentUser);
  const permissionLabel = permissions.level === 'superadmin' ? 'COO' : permissions.level === 'admin' ? 'Admin' : permissions.level === 'manager' ? 'Manager' : 'User';
  return /*#__PURE__*/React.createElement("div", {
    className: "user-selector"
  }, /*#__PURE__*/React.createElement("div", {
    className: "user-selector-trigger",
    onClick: () => setIsOpen(!isOpen)
  }, /*#__PURE__*/React.createElement(PersonAvatar, {
    person: currentUser,
    size: "small"
  }), /*#__PURE__*/React.createElement("div", {
    className: "user-selector-info"
  }, /*#__PURE__*/React.createElement("span", {
    className: "user-selector-name"
  }, currentUser.name), /*#__PURE__*/React.createElement("span", {
    className: "user-selector-role"
  }, permissionLabel)), /*#__PURE__*/React.createElement("span", {
    className: "user-selector-arrow"
  }, isOpen ? '▲' : '▼')), isOpen && /*#__PURE__*/React.createElement("div", {
    className: "user-selector-dropdown"
  }, /*#__PURE__*/React.createElement("div", {
    className: "user-selector-header"
  }, "Switch User"), people.map(person => {
    const perms = getUserPermissions(person);
    const label = perms.level === 'superadmin' ? 'COO' : perms.level === 'admin' ? 'Admin' : perms.level === 'manager' ? 'Manager' : 'User';
    return /*#__PURE__*/React.createElement("div", {
      key: person.id,
      className: `user-selector-option ${person.id === currentUser.id ? 'active' : ''}`,
      onClick: () => {
        onUserChange(person.id);
        setIsOpen(false);
      }
    }, /*#__PURE__*/React.createElement(PersonAvatar, {
      person: person,
      size: "small"
    }), /*#__PURE__*/React.createElement("div", {
      className: "user-option-info"
    }, /*#__PURE__*/React.createElement("span", {
      className: "user-option-name"
    }, person.name), /*#__PURE__*/React.createElement("span", {
      className: "user-option-role"
    }, person.role, " \u2022 ", label)));
  })));
};

// ============================================================================
// OPERATIONS DASHBOARD COMPONENT
// ============================================================================

// Check if user can access Operations dashboard (COO, PM, Traffic, ECD, CD, Producer)
const TaskList = ({
  jobId,
  data,
  dispatch,
  people,
  currentUser
}) => {
  const tasks = (data.tasks || []).filter(t => t.jobId === jobId);
  const [expandedTask, setExpandedTask] = useState(null);

  // Check if current user can edit tasks (based on assignment or permissions)
  const canEditTask = task => {
    const permissions = getUserPermissions(currentUser);
    if (permissions.canEditAssets) return true;
    // Users can edit tasks assigned to them
    return task.assignedTo === currentUser?.id;
  };
  const handleTaskStatusChange = (task, newStatus) => {
    const updates = {
      ...task,
      status: newStatus,
      completedAt: newStatus === 'Done' ? new Date().toISOString() : task.completedAt
    };
    dispatch({
      type: 'UPDATE_TASK',
      payload: updates
    });
  };
  const handleTaskUpdate = (task, updates) => {
    dispatch({
      type: 'UPDATE_TASK',
      payload: {
        ...task,
        ...updates
      }
    });
  };
  const getTaskTemplate = templateId => {
    return TASK_TEMPLATES.find(t => t.id === templateId);
  };
  const getAssignedPerson = assignedTo => {
    return people.find(p => p.id === assignedTo);
  };
  if (tasks.length === 0) {
    return /*#__PURE__*/React.createElement("div", {
      className: "task-list-empty"
    }, /*#__PURE__*/React.createElement("span", {
      className: "empty-icon"
    }, "\u25CE"), /*#__PURE__*/React.createElement("p", null, "No tasks for this job"));
  }
  return /*#__PURE__*/React.createElement("div", {
    className: "task-list"
  }, tasks.sort((a, b) => a.order - b.order).map(task => {
    const template = getTaskTemplate(task.templateId);
    const assignedPerson = getAssignedPerson(task.assignedTo);
    const isExpanded = expandedTask === task.id;
    const canEdit = canEditTask(task);
    return /*#__PURE__*/React.createElement("div", {
      key: task.id,
      className: `task-item ${task.status === 'Done' ? 'completed' : ''}`
    }, /*#__PURE__*/React.createElement("div", {
      className: "task-header",
      onClick: () => setExpandedTask(isExpanded ? null : task.id)
    }, /*#__PURE__*/React.createElement("div", {
      className: "task-checkbox"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      checked: task.status === 'Done',
      onChange: e => {
        e.stopPropagation();
        if (canEdit) {
          handleTaskStatusChange(task, e.target.checked ? 'Done' : 'In Progress');
        }
      },
      disabled: !canEdit
    })), /*#__PURE__*/React.createElement("div", {
      className: "task-info"
    }, /*#__PURE__*/React.createElement("span", {
      className: "task-name"
    }, template?.name || task.templateId), assignedPerson && /*#__PURE__*/React.createElement("span", {
      className: "task-assignee"
    }, /*#__PURE__*/React.createElement(PersonAvatar, {
      person: assignedPerson,
      size: "small"
    }))), /*#__PURE__*/React.createElement("div", {
      className: "task-meta"
    }, template?.hasCharacterCount && task.characterCount && /*#__PURE__*/React.createElement("span", {
      className: "task-char-count"
    }, task.characterCount, " chars"), template?.hasFileUpload && task.fileUrl && /*#__PURE__*/React.createElement("span", {
      className: "task-file-indicator"
    }, task.fileType === 'video' ? '🎬' : '🖼️', " ", task.fileUrl), /*#__PURE__*/React.createElement(StatusBadge, {
      status: task.status,
      onChange: canEdit ? s => handleTaskStatusChange(task, s) : null,
      statuses: STATUSES_WITH_DONE
    })), /*#__PURE__*/React.createElement("span", {
      className: "task-expand-icon"
    }, isExpanded ? '▼' : '▶')), isExpanded && /*#__PURE__*/React.createElement("div", {
      className: "task-details"
    }, /*#__PURE__*/React.createElement("p", {
      className: "task-description"
    }, template?.description), template?.hasCharacterCount && /*#__PURE__*/React.createElement("div", {
      className: "task-field"
    }, /*#__PURE__*/React.createElement("label", null, "Copy Content"), /*#__PURE__*/React.createElement("textarea", {
      value: task.content || '',
      onChange: e => {
        if (canEdit) {
          handleTaskUpdate(task, {
            content: e.target.value,
            characterCount: e.target.value.length
          });
        }
      },
      placeholder: "Enter copy text...",
      disabled: !canEdit
    }), /*#__PURE__*/React.createElement("span", {
      className: "char-counter"
    }, task.content?.length || 0, " characters")), template?.hasFileUpload && /*#__PURE__*/React.createElement("div", {
      className: "task-field"
    }, /*#__PURE__*/React.createElement("label", null, "Media File (", template.fileTypes?.join(' / '), ")"), /*#__PURE__*/React.createElement("div", {
      className: "task-file-input"
    }, /*#__PURE__*/React.createElement("input", {
      type: "text",
      value: task.fileUrl || '',
      onChange: e => {
        if (canEdit) {
          handleTaskUpdate(task, {
            fileUrl: e.target.value
          });
        }
      },
      placeholder: "Enter file URL or path...",
      disabled: !canEdit
    }), /*#__PURE__*/React.createElement("select", {
      value: task.fileType || '',
      onChange: e => {
        if (canEdit) {
          handleTaskUpdate(task, {
            fileType: e.target.value
          });
        }
      },
      disabled: !canEdit
    }, /*#__PURE__*/React.createElement("option", {
      value: ""
    }, "Type"), /*#__PURE__*/React.createElement("option", {
      value: "image"
    }, "Image"), /*#__PURE__*/React.createElement("option", {
      value: "video"
    }, "Video")))), /*#__PURE__*/React.createElement("div", {
      className: "task-field"
    }, /*#__PURE__*/React.createElement("label", null, "Assigned To"), /*#__PURE__*/React.createElement("select", {
      value: task.assignedTo || '',
      onChange: e => handleTaskUpdate(task, {
        assignedTo: e.target.value
      }),
      disabled: !getUserPermissions(currentUser).canAssignRoles
    }, /*#__PURE__*/React.createElement("option", {
      value: ""
    }, "-- Select --"), people.filter(p => p.role === template?.assignedRole).map(p => /*#__PURE__*/React.createElement("option", {
      key: p.id,
      value: p.id
    }, p.name)))), task.completedAt && /*#__PURE__*/React.createElement("p", {
      className: "task-completed-date"
    }, "Completed: ", new Date(task.completedAt).toLocaleDateString())));
  }));
};

// ============================================================================
// DETAIL PANEL COMPONENT
// ============================================================================

const DetailPanel = ({
  item,
  type,
  isOpen,
  onClose,
  data,
  dispatch,
  people,
  currentUser
}) => {
  const [editedItem, setEditedItem] = useState(null);
  useEffect(() => {
    if (item) {
      setEditedItem({
        ...item
      });
    }
  }, [item]);

  // Permission checks
  const canEditFull = canUserEditItem(currentUser, item, type, 'full');
  const canEditStatus = canUserEditItem(currentUser, item, type, 'status');
  const canDelete = canUserDelete(currentUser, item, type);
  const canAssignRoles = canUserAssignRoles(currentUser);
  const handleSave = () => {
    if (!editedItem) return;

    // Check if user has permission to save
    if (!canEditFull && !canEditStatus) {
      alert('You do not have permission to edit this item.');
      return;
    }
    switch (type) {
      case 'Projects':
        dispatch({
          type: 'UPDATE_PROJECT',
          payload: editedItem
        });
        break;
      case 'Jobs':
        dispatch({
          type: 'UPDATE_JOB',
          payload: editedItem
        });
        break;
      case 'Assets':
        dispatch({
          type: 'UPDATE_ASSET',
          payload: editedItem
        });
        break;
      case 'People':
        dispatch({
          type: 'UPDATE_PERSON',
          payload: editedItem
        });
        break;
    }
    onClose();
  };
  const handleDelete = () => {
    if (!canDelete) {
      alert('You do not have permission to delete this item.');
      return;
    }
    if (!confirm('Are you sure you want to delete this item?')) return;
    switch (type) {
      case 'Projects':
        dispatch({
          type: 'DELETE_PROJECT',
          payload: item.id
        });
        break;
      case 'Jobs':
        dispatch({
          type: 'DELETE_JOB',
          payload: item.id
        });
        break;
      case 'Assets':
        dispatch({
          type: 'DELETE_ASSET',
          payload: item.id
        });
        break;
      case 'People':
        dispatch({
          type: 'DELETE_PERSON',
          payload: item.id
        });
        break;
    }
    onClose();
  };
  if (!isOpen || !editedItem) return null;
  const statuses = type === 'Jobs' || type === 'Assets' ? STATUSES_WITH_DONE : STATUSES;
  const renderFields = () => {
    switch (type) {
      case 'Projects':
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Name"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.name,
          onChange: e => setEditedItem({
            ...editedItem,
            name: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Client"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.client || '',
          onChange: e => setEditedItem({
            ...editedItem,
            client: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Description"), /*#__PURE__*/React.createElement("textarea", {
          value: editedItem.description || '',
          onChange: e => setEditedItem({
            ...editedItem,
            description: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Status"), /*#__PURE__*/React.createElement("select", {
          value: editedItem.status,
          onChange: e => setEditedItem({
            ...editedItem,
            status: e.target.value
          }),
          disabled: !canEditFull && !canEditStatus
        }, statuses.map(s => /*#__PURE__*/React.createElement("option", {
          key: s,
          value: s
        }, s)))), !canEditFull && !canEditStatus && /*#__PURE__*/React.createElement("p", {
          className: "permission-notice"
        }, "You have view-only access to this item."));
      case 'Jobs':
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Job Number"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.jobNumber,
          disabled: true
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Name"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.name,
          onChange: e => setEditedItem({
            ...editedItem,
            name: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Description"), /*#__PURE__*/React.createElement("textarea", {
          value: editedItem.description || '',
          onChange: e => setEditedItem({
            ...editedItem,
            description: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Status"), /*#__PURE__*/React.createElement("select", {
          value: editedItem.status,
          onChange: e => setEditedItem({
            ...editedItem,
            status: e.target.value
          }),
          disabled: !canEditFull && !canEditStatus
        }, statuses.map(s => /*#__PURE__*/React.createElement("option", {
          key: s,
          value: s
        }, s)))), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Due Date"), /*#__PURE__*/React.createElement("input", {
          type: "date",
          value: editedItem.dueDate || '',
          onChange: e => setEditedItem({
            ...editedItem,
            dueDate: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-section"
        }, /*#__PURE__*/React.createElement("h4", null, "Team Assignment"), /*#__PURE__*/React.createElement("div", {
          className: "role-grid"
        }, ROLES.map(role => /*#__PURE__*/React.createElement("div", {
          key: role,
          className: "form-group role-select"
        }, /*#__PURE__*/React.createElement("label", null, role), /*#__PURE__*/React.createElement("select", {
          value: editedItem.assignments?.[role] || '',
          onChange: e => setEditedItem({
            ...editedItem,
            assignments: {
              ...editedItem.assignments,
              [role]: e.target.value
            }
          }),
          disabled: !canAssignRoles
        }, /*#__PURE__*/React.createElement("option", {
          value: ""
        }, "-- Select --"), people.filter(p => p.role === role).map(p => /*#__PURE__*/React.createElement("option", {
          key: p.id,
          value: p.id
        }, p.name))))))), /*#__PURE__*/React.createElement("div", {
          className: "form-section"
        }, /*#__PURE__*/React.createElement("h4", null, "Tasks (", (data.tasks || []).filter(t => t.jobId === editedItem.id).length, ")"), /*#__PURE__*/React.createElement(TaskList, {
          jobId: editedItem.id,
          data: data,
          dispatch: dispatch,
          people: people,
          currentUser: currentUser
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-section"
        }, /*#__PURE__*/React.createElement("h4", null, "Assets (", data.assets.filter(a => a.jobId === editedItem.id).length, ")"), /*#__PURE__*/React.createElement("div", {
          className: "asset-checklist"
        }, data.assets.filter(a => a.jobId === editedItem.id).map(asset => /*#__PURE__*/React.createElement("div", {
          key: asset.id,
          className: "checklist-item"
        }, /*#__PURE__*/React.createElement(StatusBadge, {
          status: asset.status
        }), /*#__PURE__*/React.createElement("span", null, asset.name))))), !canEditFull && !canEditStatus && /*#__PURE__*/React.createElement("p", {
          className: "permission-notice"
        }, "You have view-only access to this item."));
      case 'Assets':
        const isNameValid = validateAssetName(editedItem.name);
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Name"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.name,
          onChange: e => setEditedItem({
            ...editedItem,
            name: e.target.value
          }),
          disabled: !canEditFull
        }), !isNameValid && editedItem.name && /*#__PURE__*/React.createElement("div", {
          className: "field-warning"
        }, /*#__PURE__*/React.createElement("span", {
          className: "warning-icon"
        }, "\u26A0"), "Name doesn't follow naming convention. Expected: XXXX-NNN-Client-Campaign-Type-vN-YYYYMMDD")), editedItem.displayName && /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Display Name"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.displayName,
          disabled: true,
          className: "readonly-field"
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Type"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.type,
          disabled: true
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Status"), /*#__PURE__*/React.createElement("select", {
          value: editedItem.status,
          onChange: e => setEditedItem({
            ...editedItem,
            status: e.target.value
          }),
          disabled: !canEditFull && !canEditStatus
        }, statuses.map(s => /*#__PURE__*/React.createElement("option", {
          key: s,
          value: s
        }, s)))), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Assigned To"), /*#__PURE__*/React.createElement("select", {
          value: editedItem.assignedTo || '',
          onChange: e => setEditedItem({
            ...editedItem,
            assignedTo: e.target.value
          }),
          disabled: !canEditFull
        }, /*#__PURE__*/React.createElement("option", {
          value: ""
        }, "-- Select --"), people.map(p => /*#__PURE__*/React.createElement("option", {
          key: p.id,
          value: p.id
        }, p.name)))), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Due Date"), /*#__PURE__*/React.createElement("input", {
          type: "date",
          value: editedItem.dueDate || '',
          onChange: e => setEditedItem({
            ...editedItem,
            dueDate: e.target.value
          }),
          disabled: !canEditFull
        })), !canEditFull && !canEditStatus && /*#__PURE__*/React.createElement("p", {
          className: "permission-notice"
        }, "You have view-only access to this item."));
      case 'People':
        return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Name"), /*#__PURE__*/React.createElement("input", {
          type: "text",
          value: editedItem.name,
          onChange: e => setEditedItem({
            ...editedItem,
            name: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Email"), /*#__PURE__*/React.createElement("input", {
          type: "email",
          value: editedItem.email,
          onChange: e => setEditedItem({
            ...editedItem,
            email: e.target.value
          }),
          disabled: !canEditFull
        })), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Role"), /*#__PURE__*/React.createElement("select", {
          value: editedItem.role || '',
          onChange: e => setEditedItem({
            ...editedItem,
            role: e.target.value
          }),
          disabled: !canEditFull
        }, /*#__PURE__*/React.createElement("option", {
          value: ""
        }, "Select a role"), ROLES.map(role => /*#__PURE__*/React.createElement("option", {
          key: role,
          value: role
        }, role)))), /*#__PURE__*/React.createElement("div", {
          className: "form-group"
        }, /*#__PURE__*/React.createElement("label", null, "Color"), /*#__PURE__*/React.createElement("input", {
          type: "color",
          value: editedItem.color || '#3b82f6',
          onChange: e => setEditedItem({
            ...editedItem,
            color: e.target.value
          }),
          disabled: !canEditFull
        })), !canEditFull && /*#__PURE__*/React.createElement("p", {
          className: "permission-notice"
        }, "You do not have permission to edit people."));
    }
  };
  return /*#__PURE__*/React.createElement("div", {
    className: `detail-panel ${isOpen ? 'open' : ''}`
  }, /*#__PURE__*/React.createElement("div", {
    className: "panel-header"
  }, /*#__PURE__*/React.createElement("h2", null, type.slice(0, -1), " Details"), /*#__PURE__*/React.createElement("button", {
    className: "panel-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "panel-body"
  }, renderFields()), /*#__PURE__*/React.createElement("div", {
    className: "panel-footer"
  }, canDelete && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-danger",
    onClick: handleDelete
  }, "Delete"), !canDelete && /*#__PURE__*/React.createElement("div", null), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), (canEditFull || canEditStatus) && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSave
  }, "Save"))));
};

// ============================================================================
// WIKI COMPONENTS
// ============================================================================

// WYSIWYG Editor Component
const WysiwygEditor = ({
  content,
  onChange,
  onSave
}) => {
  const editorRef = React.useRef(null);
  const [showLinkModal, setShowLinkModal] = useState(false);
  const [linkUrl, setLinkUrl] = useState('');
  const execCommand = (command, value = null) => {
    document.execCommand(command, false, value);
    editorRef.current?.focus();
  };
  const handleInsertLink = () => {
    if (linkUrl) {
      execCommand('createLink', linkUrl);
      setLinkUrl('');
    }
    setShowLinkModal(false);
  };
  const handleInput = () => {
    if (editorRef.current) {
      onChange(editorRef.current.innerHTML);
    }
  };
  useEffect(() => {
    if (editorRef.current && editorRef.current.innerHTML !== content) {
      editorRef.current.innerHTML = content || '';
    }
  }, []);
  return /*#__PURE__*/React.createElement("div", {
    className: "wysiwyg-editor"
  }, /*#__PURE__*/React.createElement("div", {
    className: "editor-toolbar"
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('bold'),
    title: "Bold"
  }, /*#__PURE__*/React.createElement("b", null, "B")), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('italic'),
    title: "Italic"
  }, /*#__PURE__*/React.createElement("i", null, "I")), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('underline'),
    title: "Underline"
  }, /*#__PURE__*/React.createElement("u", null, "U")), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('strikeThrough'),
    title: "Strikethrough"
  }, /*#__PURE__*/React.createElement("s", null, "S")), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'h1'),
    title: "Heading 1"
  }, "H1"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'h2'),
    title: "Heading 2"
  }, "H2"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'h3'),
    title: "Heading 3"
  }, "H3"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'p'),
    title: "Paragraph"
  }, "P"), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('insertUnorderedList'),
    title: "Bullet List"
  }, "\u2022"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('insertOrderedList'),
    title: "Numbered List"
  }, "1."), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => setShowLinkModal(true),
    title: "Insert Link"
  }, "\uD83D\uDD17"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'blockquote'),
    title: "Quote"
  }, "\u201C"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'pre'),
    title: "Code"
  }, "</>"), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('removeFormat'),
    title: "Clear Formatting"
  }, "\u2716")), /*#__PURE__*/React.createElement("div", {
    ref: editorRef,
    className: "editor-content",
    contentEditable: true,
    onInput: handleInput,
    onBlur: handleInput,
    suppressContentEditableWarning: true
  }), showLinkModal && /*#__PURE__*/React.createElement("div", {
    className: "link-modal"
  }, /*#__PURE__*/React.createElement("input", {
    type: "url",
    placeholder: "Enter URL...",
    value: linkUrl,
    onChange: e => setLinkUrl(e.target.value),
    onKeyDown: e => e.key === 'Enter' && handleInsertLink()
  }), /*#__PURE__*/React.createElement("button", {
    onClick: handleInsertLink
  }, "Insert"), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowLinkModal(false)
  }, "Cancel")));
};

// Wiki Tree Node Component
const WikiTreeNode = ({
  page,
  pages,
  level = 0,
  selectedId,
  onSelect,
  onToggle,
  expanded
}) => {
  const children = pages.filter(p => p.parentId === page.id).sort((a, b) => a.order - b.order);
  const hasChildren = children.length > 0;
  const isExpanded = expanded[page.id];
  const isSelected = selectedId === page.id;
  const typeIcons = {
    client: '&#128188;',
    campaign: '&#128200;',
    award: '&#127942;',
    report: '&#128202;',
    general: '&#128196;'
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-tree-node"
  }, /*#__PURE__*/React.createElement("div", {
    className: `tree-item ${isSelected ? 'selected' : ''}`,
    style: {
      paddingLeft: `${level * 16 + 8}px`
    },
    onClick: () => onSelect(page)
  }, hasChildren && /*#__PURE__*/React.createElement("span", {
    className: "tree-toggle",
    onClick: e => {
      e.stopPropagation();
      onToggle(page.id);
    }
  }, isExpanded ? '&#9660;' : '&#9654;'), !hasChildren && /*#__PURE__*/React.createElement("span", {
    className: "tree-spacer"
  }), /*#__PURE__*/React.createElement("span", {
    className: "tree-icon",
    dangerouslySetInnerHTML: {
      __html: typeIcons[page.type] || typeIcons.general
    }
  }), /*#__PURE__*/React.createElement("span", {
    className: "tree-title"
  }, page.title)), hasChildren && isExpanded && /*#__PURE__*/React.createElement("div", {
    className: "tree-children"
  }, children.map(child => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: child.id,
    page: child,
    pages: pages,
    level: level + 1,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: onToggle,
    expanded: expanded
  }))));
};

// Wiki Sidebar Component
const WikiSidebar = ({
  pages,
  selectedId,
  onSelect,
  onNewPage,
  searchQuery,
  onSearchChange
}) => {
  const [expanded, setExpanded] = useState({});
  const toggleExpand = id => {
    setExpanded(prev => ({
      ...prev,
      [id]: !prev[id]
    }));
  };

  // Get root pages (no parent)
  const rootPages = pages.filter(p => !p.parentId).sort((a, b) => a.order - b.order);

  // Group by type
  const clientPages = rootPages.filter(p => p.type === 'client');
  const awardPages = rootPages.filter(p => p.type === 'award');
  const reportPages = rootPages.filter(p => p.type === 'report');
  const generalPages = rootPages.filter(p => p.type === 'general');

  // Filter by search
  const filteredPages = searchQuery ? pages.filter(p => p.title.toLowerCase().includes(searchQuery.toLowerCase())) : null;
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-sidebar"
  }, /*#__PURE__*/React.createElement("div", {
    className: "sidebar-search"
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Search wiki...",
    value: searchQuery,
    onChange: e => onSearchChange(e.target.value)
  })), filteredPages ? /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Search Results"), filteredPages.map(page => /*#__PURE__*/React.createElement("div", {
    key: page.id,
    className: `tree-item ${selectedId === page.id ? 'selected' : ''}`,
    onClick: () => onSelect(page)
  }, /*#__PURE__*/React.createElement("span", {
    className: "tree-title"
  }, page.title))), filteredPages.length === 0 && /*#__PURE__*/React.createElement("div", {
    className: "empty-text"
  }, "No results found")) : /*#__PURE__*/React.createElement(React.Fragment, null, clientPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Clients"), clientPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  }))), awardPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Award Submissions"), awardPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  }))), reportPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Reports"), reportPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  }))), generalPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "General"), generalPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  })))), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary sidebar-new-btn",
    onClick: onNewPage
  }, "+ New Page"));
};

// Wiki Breadcrumb Component
const WikiBreadcrumb = ({
  page,
  pages,
  onNavigate
}) => {
  const getBreadcrumbPath = currentPage => {
    const path = [currentPage];
    let parent = pages.find(p => p.id === currentPage.parentId);
    while (parent) {
      path.unshift(parent);
      parent = pages.find(p => p.id === parent.parentId);
    }
    return path;
  };
  if (!page) return null;
  const path = getBreadcrumbPath(page);
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-breadcrumb"
  }, /*#__PURE__*/React.createElement("span", {
    className: "breadcrumb-item",
    onClick: () => onNavigate(null)
  }, "Wiki"), path.map((p, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: p.id
  }, /*#__PURE__*/React.createElement("span", {
    className: "breadcrumb-sep"
  }, "/"), /*#__PURE__*/React.createElement("span", {
    className: `breadcrumb-item ${i === path.length - 1 ? 'current' : ''}`,
    onClick: () => onNavigate(p)
  }, p.title))));
};

// Wiki Page View Component
const WikiPageView = ({
  page,
  pages,
  data,
  people,
  onEdit,
  onDelete,
  onNavigate,
  onLinkItem
}) => {
  if (!page) {
    return /*#__PURE__*/React.createElement("div", {
      className: "wiki-page-empty"
    }, /*#__PURE__*/React.createElement("span", {
      className: "empty-icon"
    }, "\u25A4"), /*#__PURE__*/React.createElement("h2", null, "Welcome to the Wiki"), /*#__PURE__*/React.createElement("p", null, "Select a page from the sidebar or create a new one to get started."));
  }
  const linkedJobs = (page.linkedJobs || []).map(id => data.jobs.find(j => j.id === id)).filter(Boolean);
  const linkedProjects = (page.linkedProjects || []).map(id => data.projects.find(p => p.id === id)).filter(Boolean);
  const author = people.find(p => p.id === page.createdBy);
  const template = WIKI_TEMPLATES.find(t => t.id === page.templateId);
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-page-view"
  }, /*#__PURE__*/React.createElement(WikiBreadcrumb, {
    page: page,
    pages: pages,
    onNavigate: onNavigate
  }), /*#__PURE__*/React.createElement("div", {
    className: "page-header"
  }, /*#__PURE__*/React.createElement("h1", null, page.title), /*#__PURE__*/React.createElement("div", {
    className: "page-actions"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onEdit
  }, "Edit"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onLinkItem
  }, "Link Item"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-danger",
    onClick: onDelete
  }, "Delete"))), /*#__PURE__*/React.createElement("div", {
    className: "page-meta"
  }, template && /*#__PURE__*/React.createElement("span", {
    className: "meta-template"
  }, template.name), /*#__PURE__*/React.createElement("span", {
    className: "meta-type"
  }, page.type), author && /*#__PURE__*/React.createElement("span", {
    className: "meta-author"
  }, "by ", author.name), /*#__PURE__*/React.createElement("span", {
    className: "meta-date"
  }, "Updated ", formatDate(page.updatedAt))), page.tags && page.tags.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "page-tags"
  }, page.tags.map(tag => /*#__PURE__*/React.createElement("span", {
    key: tag,
    className: "tag"
  }, tag))), /*#__PURE__*/React.createElement("div", {
    className: "page-content",
    dangerouslySetInnerHTML: {
      __html: typeof DOMPurify !== 'undefined' ? DOMPurify.sanitize(page.content) : page.content
    }
  }), (linkedJobs.length > 0 || linkedProjects.length > 0) && /*#__PURE__*/React.createElement("div", {
    className: "page-links"
  }, /*#__PURE__*/React.createElement("h3", null, "Linked Items"), linkedProjects.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "link-group"
  }, /*#__PURE__*/React.createElement("h4", null, "Projects"), linkedProjects.map(proj => /*#__PURE__*/React.createElement("div", {
    key: proj.id,
    className: "link-item"
  }, /*#__PURE__*/React.createElement("span", {
    className: "link-icon"
  }, "\uD83D\uDCC1"), /*#__PURE__*/React.createElement("span", null, proj.name), /*#__PURE__*/React.createElement(StatusBadge, {
    status: proj.status
  })))), linkedJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "link-group"
  }, /*#__PURE__*/React.createElement("h4", null, "Jobs"), linkedJobs.map(job => /*#__PURE__*/React.createElement("div", {
    key: job.id,
    className: "link-item"
  }, /*#__PURE__*/React.createElement("span", {
    className: "link-icon"
  }, "\uD83D\uDCBC"), /*#__PURE__*/React.createElement("span", null, job.jobNumber, " - ", job.name), /*#__PURE__*/React.createElement(StatusBadge, {
    status: job.status
  }))))));
};

// Wiki Page Modal (Create/Edit)
const WikiPageModal = ({
  isOpen,
  onClose,
  page,
  pages,
  data,
  dispatch
}) => {
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [type, setType] = useState('general');
  const [templateId, setTemplateId] = useState('');
  const [parentId, setParentId] = useState('');
  const [tags, setTags] = useState('');
  useEffect(() => {
    if (page) {
      setTitle(page.title);
      setContent(page.content);
      setType(page.type);
      setTemplateId(page.templateId || '');
      setParentId(page.parentId || '');
      setTags((page.tags || []).join(', '));
    } else {
      setTitle('');
      setContent('');
      setType('general');
      setTemplateId('');
      setParentId('');
      setTags('');
    }
  }, [page, isOpen]);
  const handleTemplateChange = newTemplateId => {
    setTemplateId(newTemplateId);
    if (newTemplateId && !page) {
      const template = WIKI_TEMPLATES.find(t => t.id === newTemplateId);
      if (template) {
        setContent(template.content);
        setType(template.type);
      }
    }
  };
  const handleSubmit = () => {
    if (!title) return alert('Title is required');
    const pageData = {
      id: page?.id || generateId(),
      title,
      slug: title.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, ''),
      content,
      templateId: templateId || null,
      parentId: parentId || null,
      type,
      linkedJobs: page?.linkedJobs || [],
      linkedProjects: page?.linkedProjects || [],
      tags: tags.split(',').map(t => t.trim()).filter(Boolean),
      createdAt: page?.createdAt || new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      createdBy: page?.createdBy || 'p1',
      order: page?.order || pages.filter(p => p.parentId === parentId).length
    };
    if (page) {
      dispatch({
        type: 'UPDATE_WIKI_PAGE',
        payload: pageData
      });
    } else {
      dispatch({
        type: 'ADD_WIKI_PAGE',
        payload: pageData
      });
    }
    onClose(pageData);
  };
  if (!isOpen) return null;

  // Get potential parent pages (excluding self and descendants)
  const getDescendantIds = pageId => {
    const descendants = new Set();
    const addDescendants = id => {
      pages.filter(p => p.parentId === id).forEach(p => {
        descendants.add(p.id);
        addDescendants(p.id);
      });
    };
    addDescendants(pageId);
    return descendants;
  };
  const excludeIds = page ? new Set([page.id, ...getDescendantIds(page.id)]) : new Set();
  const parentOptions = pages.filter(p => !excludeIds.has(p.id));
  return /*#__PURE__*/React.createElement("div", {
    className: "modal-overlay",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-content wiki-modal",
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-header"
  }, /*#__PURE__*/React.createElement("h2", null, page ? 'Edit Page' : 'New Wiki Page'), /*#__PURE__*/React.createElement("button", {
    className: "modal-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "modal-body"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Title *"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: title,
    onChange: e => setTitle(e.target.value),
    placeholder: "Page title"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Type"), /*#__PURE__*/React.createElement("select", {
    value: type,
    onChange: e => setType(e.target.value)
  }, WIKI_TYPES.map(t => /*#__PURE__*/React.createElement("option", {
    key: t,
    value: t
  }, t.charAt(0).toUpperCase() + t.slice(1)))))), /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Template"), /*#__PURE__*/React.createElement("select", {
    value: templateId,
    onChange: e => handleTemplateChange(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- No Template --"), WIKI_TEMPLATES.map(t => /*#__PURE__*/React.createElement("option", {
    key: t.id,
    value: t.id
  }, t.name)))), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Parent Page"), /*#__PURE__*/React.createElement("select", {
    value: parentId,
    onChange: e => setParentId(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- None (Root Level) --"), parentOptions.map(p => /*#__PURE__*/React.createElement("option", {
    key: p.id,
    value: p.id
  }, p.title))))), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Tags (comma separated)"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: tags,
    onChange: e => setTags(e.target.value),
    placeholder: "e.g., client, brand, 2024"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Content"), /*#__PURE__*/React.createElement(WysiwygEditor, {
    content: content,
    onChange: setContent
  }))), /*#__PURE__*/React.createElement("div", {
    className: "modal-footer"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSubmit
  }, page ? 'Save Changes' : 'Create Page'))));
};

// Wiki Link Modal
const WikiLinkModal = ({
  isOpen,
  onClose,
  page,
  data,
  dispatch
}) => {
  const [selectedJobs, setSelectedJobs] = useState([]);
  const [selectedProjects, setSelectedProjects] = useState([]);
  useEffect(() => {
    if (page) {
      setSelectedJobs(page.linkedJobs || []);
      setSelectedProjects(page.linkedProjects || []);
    }
  }, [page, isOpen]);
  const handleSave = () => {
    if (page) {
      dispatch({
        type: 'UPDATE_WIKI_PAGE',
        payload: {
          ...page,
          linkedJobs: selectedJobs,
          linkedProjects: selectedProjects,
          updatedAt: new Date().toISOString()
        }
      });
    }
    onClose();
  };
  const toggleJob = jobId => {
    setSelectedJobs(prev => prev.includes(jobId) ? prev.filter(id => id !== jobId) : [...prev, jobId]);
  };
  const toggleProject = projectId => {
    setSelectedProjects(prev => prev.includes(projectId) ? prev.filter(id => id !== projectId) : [...prev, projectId]);
  };
  if (!isOpen || !page) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "modal-overlay",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-content",
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-header"
  }, /*#__PURE__*/React.createElement("h2", null, "Link Items to \"", page.title, "\""), /*#__PURE__*/React.createElement("button", {
    className: "modal-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "modal-body"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-section"
  }, /*#__PURE__*/React.createElement("h3", null, "Projects"), /*#__PURE__*/React.createElement("div", {
    className: "link-checkbox-list"
  }, data.projects.map(proj => /*#__PURE__*/React.createElement("label", {
    key: proj.id,
    className: "checkbox-label"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: selectedProjects.includes(proj.id),
    onChange: () => toggleProject(proj.id)
  }), proj.name, " (", proj.client, ")")))), /*#__PURE__*/React.createElement("div", {
    className: "form-section"
  }, /*#__PURE__*/React.createElement("h3", null, "Jobs"), /*#__PURE__*/React.createElement("div", {
    className: "link-checkbox-list"
  }, data.jobs.map(job => /*#__PURE__*/React.createElement("label", {
    key: job.id,
    className: "checkbox-label"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: selectedJobs.includes(job.id),
    onChange: () => toggleJob(job.id)
  }), job.jobNumber, " - ", job.name))))), /*#__PURE__*/React.createElement("div", {
    className: "modal-footer"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSave
  }, "Save Links"))));
};

// Main Wiki Tab Component
const WikiTab = ({
  data,
  dispatch,
  people
}) => {
  const [selectedPage, setSelectedPage] = useState(null);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [linkModalOpen, setLinkModalOpen] = useState(false);
  const [editingPage, setEditingPage] = useState(null);
  const [wikiSearchQuery, setWikiSearchQuery] = useState('');
  const wikiPages = data.wikiPages || [];
  const handleSelectPage = page => {
    setSelectedPage(page);
  };
  const handleNewPage = () => {
    setEditingPage(null);
    setEditModalOpen(true);
  };
  const handleEditPage = () => {
    setEditingPage(selectedPage);
    setEditModalOpen(true);
  };
  const handleDeletePage = () => {
    if (!selectedPage) return;
    if (!confirm(`Delete "${selectedPage.title}"? This cannot be undone.`)) return;
    dispatch({
      type: 'DELETE_WIKI_PAGE',
      payload: selectedPage.id
    });
    setSelectedPage(null);
  };
  const handleModalClose = savedPage => {
    setEditModalOpen(false);
    if (savedPage && savedPage.id) {
      setSelectedPage(savedPage);
    }
  };
  const handleNavigate = page => {
    setSelectedPage(page);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-container"
  }, /*#__PURE__*/React.createElement(WikiSidebar, {
    pages: wikiPages,
    selectedId: selectedPage?.id,
    onSelect: handleSelectPage,
    onNewPage: handleNewPage,
    searchQuery: wikiSearchQuery,
    onSearchChange: setWikiSearchQuery
  }), /*#__PURE__*/React.createElement("div", {
    className: "wiki-main"
  }, /*#__PURE__*/React.createElement(WikiPageView, {
    page: selectedPage,
    pages: wikiPages,
    data: data,
    people: people,
    onEdit: handleEditPage,
    onDelete: handleDeletePage,
    onNavigate: handleNavigate,
    onLinkItem: () => setLinkModalOpen(true)
  })), /*#__PURE__*/React.createElement(WikiPageModal, {
    isOpen: editModalOpen,
    onClose: handleModalClose,
    page: editingPage,
    pages: wikiPages,
    data: data,
    dispatch: dispatch
  }), /*#__PURE__*/React.createElement(WikiLinkModal, {
    isOpen: linkModalOpen,
    onClose: () => setLinkModalOpen(false),
    page: selectedPage,
    data: data,
    dispatch: dispatch
  }));
};

// ============================================================================
// CAPACITY TAB COMPONENTS
// ============================================================================

// Person Selector with carousel navigation
const PersonSelector = ({
  people,
  currentIndex,
  onNavigate
}) => {
  const person = people[currentIndex];
  if (!person) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "person-selector"
  }, /*#__PURE__*/React.createElement("button", {
    className: "nav-arrow nav-arrow-left",
    onClick: () => onNavigate(-1),
    disabled: currentIndex === 0
  }, "\u2039"), /*#__PURE__*/React.createElement("div", {
    className: "person-info"
  }, /*#__PURE__*/React.createElement(PersonAvatar, {
    person: person,
    size: "medium"
  }), /*#__PURE__*/React.createElement("div", {
    className: "person-details"
  }, /*#__PURE__*/React.createElement("span", {
    className: "person-name"
  }, person.name), /*#__PURE__*/React.createElement("span", {
    className: "person-roles"
  }, person.role))), /*#__PURE__*/React.createElement("button", {
    className: "nav-arrow nav-arrow-right",
    onClick: () => onNavigate(1),
    disabled: currentIndex === people.length - 1
  }, "\u203A"));
};

// Capacity progress bar
const CapacityBar = ({
  current,
  max,
  label
}) => {
  const percentage = Math.min(current / max * 100, 100);
  const isOverCapacity = current > max;
  return /*#__PURE__*/React.createElement("div", {
    className: "capacity-bar-container"
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-bar-label"
  }, /*#__PURE__*/React.createElement("span", null, label), /*#__PURE__*/React.createElement("span", {
    className: `capacity-hours ${isOverCapacity ? 'over-capacity' : ''}`
  }, current.toFixed(1), "h / ", max, "h")), /*#__PURE__*/React.createElement("div", {
    className: "capacity-bar"
  }, /*#__PURE__*/React.createElement("div", {
    className: `capacity-bar-fill ${isOverCapacity ? 'over-capacity' : ''}`,
    style: {
      width: `${percentage}%`
    }
  })));
};

// Job card for capacity view
const CapacityJobCard = ({
  job,
  project,
  isOverflow
}) => {
  return /*#__PURE__*/React.createElement("div", {
    className: `capacity-job-card ${isOverflow ? 'overflow' : ''}`
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-card-header"
  }, /*#__PURE__*/React.createElement("span", {
    className: "capacity-job-number"
  }, job.jobNumber), /*#__PURE__*/React.createElement("span", {
    className: "capacity-job-name"
  }, job.name)), /*#__PURE__*/React.createElement("div", {
    className: "capacity-card-meta"
  }, project?.client, " / ", project?.name), /*#__PURE__*/React.createElement("div", {
    className: "capacity-card-footer"
  }, /*#__PURE__*/React.createElement(StatusBadge, {
    status: job.status
  }), /*#__PURE__*/React.createElement("span", {
    className: "capacity-hours-badge"
  }, job.hours, "h"), /*#__PURE__*/React.createElement("span", {
    className: "capacity-due"
  }, "Due: ", formatDate(job.dueDate))), isOverflow && /*#__PURE__*/React.createElement("div", {
    className: "overflow-warning"
  }, "Overflows to next week"));
};

// Section grouping jobs
const CapacitySection = ({
  title,
  jobs,
  totalHours,
  data,
  isOverflow = false
}) => {
  if (jobs.length === 0) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: `capacity-section ${isOverflow ? 'overflow-section' : ''}`
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-section-header"
  }, /*#__PURE__*/React.createElement("h3", null, title), /*#__PURE__*/React.createElement("span", {
    className: "section-hours"
  }, totalHours.toFixed(1), " hours")), /*#__PURE__*/React.createElement("div", {
    className: "capacity-jobs-list"
  }, jobs.map(job => {
    const project = data.projects.find(p => p.id === job.projectId);
    return /*#__PURE__*/React.createElement(CapacityJobCard, {
      key: job.id,
      job: job,
      project: project,
      isOverflow: isOverflow
    });
  })));
};

// Email action buttons
const EmailActions = ({
  person,
  categorizedJobs,
  data,
  dispatch
}) => {
  const [showScheduleConfirm, setShowScheduleConfirm] = useState(false);
  const handleSendEmail = () => {
    const {
      subject,
      body
    } = generateCapacityEmailContent(person, categorizedJobs, data);
    const mailtoLink = `mailto:${person.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    window.location.href = mailtoLink;
  };
  const handleScheduleEmail = () => {
    const scheduledFor = getNextWeekdayAt9am();
    const {
      subject,
      body
    } = generateCapacityEmailContent(person, categorizedJobs, data);
    const scheduledEmail = {
      id: generateId(),
      personId: person.id,
      personEmail: person.email,
      scheduledFor: scheduledFor.toISOString(),
      subject,
      content: body,
      createdAt: new Date().toISOString()
    };
    dispatch({
      type: 'ADD_SCHEDULED_EMAIL',
      payload: scheduledEmail
    });
    setShowScheduleConfirm(true);
    setTimeout(() => setShowScheduleConfirm(false), 3000);
  };
  const nextWeekday = getNextWeekdayAt9am();
  const scheduleLabel = nextWeekday.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric'
  });
  return /*#__PURE__*/React.createElement("div", {
    className: "email-actions"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary email-btn",
    onClick: handleSendEmail
  }, /*#__PURE__*/React.createElement("span", {
    className: "email-icon"
  }, "\u2709"), "Send by Email"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary email-btn",
    onClick: handleScheduleEmail
  }, /*#__PURE__*/React.createElement("span", {
    className: "email-icon"
  }, "\uD83D\uDD53"), "Schedule Email (9am ", scheduleLabel, ")"), showScheduleConfirm && /*#__PURE__*/React.createElement("div", {
    className: "schedule-confirm"
  }, "Email scheduled for 9am on ", scheduleLabel));
};

// ============================================================================
// CAPACITY CALENDAR VIEW
// ============================================================================

const CapacityCalendar = ({
  person,
  jobs,
  data
}) => {
  // Get current week's dates (Monday to Friday)
  const getWeekDates = () => {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const monday = new Date(today);
    monday.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
    const dates = [];
    for (let i = 0; i < 5; i++) {
      const date = new Date(monday);
      date.setDate(monday.getDate() + i);
      dates.push(date);
    }
    return dates;
  };
  const weekDates = getWeekDates();

  // Get day name and date string
  const formatDayHeader = date => {
    const dayName = date.toLocaleDateString('en-US', {
      weekday: 'short'
    });
    const dateNum = date.getDate();
    return {
      dayName,
      dateNum
    };
  };

  // Check if a date is today
  const isToday = date => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
  };

  // Get jobs for a specific day based on status and due date
  const getJobsForDay = date => {
    const dateStr = date.toISOString().split('T')[0];
    const today = new Date().toISOString().split('T')[0];
    return jobs.filter(job => {
      // Jobs due on this date
      if (job.dueDate === dateStr) return true;

      // "Today" status jobs on today's date
      if (dateStr === today && (job.status === 'Today' || job.status === 'In Progress')) {
        return true;
      }
      return false;
    });
  };

  // Calculate hours for a day
  const getDayHours = dayJobs => {
    return dayJobs.reduce((sum, job) => {
      const hours = calculateJobHours(job, data.assets);
      return sum + hours;
    }, 0);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "capacity-calendar"
  }, /*#__PURE__*/React.createElement("div", {
    className: "calendar-header"
  }, /*#__PURE__*/React.createElement("h3", null, "Week View"), /*#__PURE__*/React.createElement("span", {
    className: "calendar-week-label"
  }, weekDates[0].toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  }), " - ", weekDates[4].toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  }))), /*#__PURE__*/React.createElement("div", {
    className: "calendar-grid"
  }, weekDates.map((date, index) => {
    const {
      dayName,
      dateNum
    } = formatDayHeader(date);
    const dayJobs = getJobsForDay(date);
    const dayHours = getDayHours(dayJobs);
    const isOverCapacity = dayHours > DAILY_CAPACITY;
    return /*#__PURE__*/React.createElement("div", {
      key: index,
      className: `calendar-day ${isToday(date) ? 'is-today' : ''}`
    }, /*#__PURE__*/React.createElement("div", {
      className: "calendar-day-header"
    }, /*#__PURE__*/React.createElement("span", {
      className: "day-name"
    }, dayName), /*#__PURE__*/React.createElement("span", {
      className: "day-date"
    }, dateNum), /*#__PURE__*/React.createElement("span", {
      className: `day-hours ${isOverCapacity ? 'over-capacity' : ''}`
    }, dayHours.toFixed(1), "h")), /*#__PURE__*/React.createElement("div", {
      className: "calendar-day-content"
    }, dayJobs.length === 0 ? /*#__PURE__*/React.createElement("div", {
      className: "calendar-empty-day"
    }, "No jobs") : dayJobs.map(job => {
      const project = data.projects.find(p => p.id === job.projectId);
      const hours = calculateJobHours(job, data.assets);
      return /*#__PURE__*/React.createElement("div", {
        key: job.id,
        className: "calendar-job-block",
        style: {
          backgroundColor: STATUS_COLORS[job.status] + '20',
          borderLeftColor: STATUS_COLORS[job.status]
        }
      }, /*#__PURE__*/React.createElement("span", {
        className: "job-block-number"
      }, job.jobNumber), /*#__PURE__*/React.createElement("span", {
        className: "job-block-name"
      }, job.name), /*#__PURE__*/React.createElement("span", {
        className: "job-block-client"
      }, project?.client), /*#__PURE__*/React.createElement("span", {
        className: "job-block-hours"
      }, hours.toFixed(1), "h"));
    })), /*#__PURE__*/React.createElement("div", {
      className: "calendar-day-capacity"
    }, /*#__PURE__*/React.createElement("div", {
      className: `capacity-indicator ${isOverCapacity ? 'over' : ''}`,
      style: {
        width: `${Math.min(dayHours / DAILY_CAPACITY * 100, 100)}%`
      }
    })));
  })));
};

// ============================================================================
// TEAM OVERVIEW CARD (for Team Overview mode)
// ============================================================================

const TeamMemberCard = ({
  person,
  data,
  onSelect
}) => {
  const personJobs = getPersonJobs(person.id, data.jobs);
  const categorizedJobs = categorizeJobsByCapacity(personJobs, data.assets);
  const weekUtilization = categorizedJobs.weekHours / WEEKLY_CAPACITY * 100;
  const isOverCapacity = weekUtilization > 100;
  const utilizationColor = isOverCapacity ? 'var(--status-red-mid)' : weekUtilization > 80 ? 'var(--status-orange-mid)' : 'var(--status-green-mid)';
  return /*#__PURE__*/React.createElement("div", {
    className: "team-member-card",
    onClick: () => onSelect(person)
  }, /*#__PURE__*/React.createElement("div", {
    className: "team-card-header"
  }, /*#__PURE__*/React.createElement(PersonAvatar, {
    person: person,
    size: "small"
  }), /*#__PURE__*/React.createElement("div", {
    className: "team-card-info"
  }, /*#__PURE__*/React.createElement("span", {
    className: "team-card-name"
  }, person.name), /*#__PURE__*/React.createElement("span", {
    className: "team-card-role"
  }, person.role)), /*#__PURE__*/React.createElement("div", {
    className: "team-card-utilization",
    style: {
      color: utilizationColor
    }
  }, weekUtilization.toFixed(0), "%")), /*#__PURE__*/React.createElement("div", {
    className: "team-card-capacity"
  }, /*#__PURE__*/React.createElement("div", {
    className: "mini-capacity-bar"
  }, /*#__PURE__*/React.createElement("div", {
    className: `mini-capacity-fill ${isOverCapacity ? 'over' : ''}`,
    style: {
      width: `${Math.min(weekUtilization, 100)}%`
    }
  })), /*#__PURE__*/React.createElement("div", {
    className: "team-card-stats"
  }, /*#__PURE__*/React.createElement("span", null, categorizedJobs.weekHours.toFixed(1), "h / ", WEEKLY_CAPACITY, "h"), /*#__PURE__*/React.createElement("span", null, personJobs.length, " jobs"))), categorizedJobs.overflow.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "team-card-overflow"
  }, categorizedJobs.overflow.length, " overflow"));
};

// Main Capacity Tab Component
const CapacityTab = ({
  data,
  dispatch,
  people
}) => {
  const [currentPersonIndex, setCurrentPersonIndex] = useState(0);
  const [viewMode, setViewMode] = useState('team'); // 'team', 'list', or 'calendar'
  const [sortBy, setSortBy] = useState('name'); // 'name', 'role', 'capacity', 'jobs'
  const [filterRole, setFilterRole] = useState('all');

  // Filter to only people with agency roles (not Client)
  const agencyPeople = people.filter(p => p.role !== 'Client');

  // Get capacity data for sorting
  const getPeopleWithCapacity = () => {
    return agencyPeople.map(person => {
      const personJobs = getPersonJobs(person.id, data.jobs);
      const categorizedJobs = categorizeJobsByCapacity(personJobs, data.assets);
      return {
        ...person,
        jobCount: personJobs.length,
        weekHours: categorizedJobs.weekHours,
        utilization: categorizedJobs.weekHours / WEEKLY_CAPACITY * 100
      };
    });
  };

  // Sort people
  const sortPeople = peopleList => {
    return [...peopleList].sort((a, b) => {
      switch (sortBy) {
        case 'name':
          return a.name.localeCompare(b.name);
        case 'role':
          return a.role.localeCompare(b.role);
        case 'capacity':
          return b.utilization - a.utilization;
        // High to low
        case 'jobs':
          return b.jobCount - a.jobCount;
        // High to low
        default:
          return 0;
      }
    });
  };

  // Filter people by role
  const filterPeople = peopleList => {
    if (filterRole === 'all') return peopleList;
    return peopleList.filter(p => p.role === filterRole);
  };
  const processedPeople = sortPeople(filterPeople(getPeopleWithCapacity()));

  // Get unique roles for filter dropdown
  const uniqueRoles = [...new Set(agencyPeople.map(p => p.role))].sort();
  const currentPerson = agencyPeople[currentPersonIndex];
  const handleNavigate = direction => {
    const newIndex = currentPersonIndex + direction;
    if (newIndex >= 0 && newIndex < agencyPeople.length) {
      setCurrentPersonIndex(newIndex);
    }
  };
  const handleSelectPerson = person => {
    const index = agencyPeople.findIndex(p => p.id === person.id);
    if (index !== -1) {
      setCurrentPersonIndex(index);
      setViewMode('list');
    }
  };
  if (agencyPeople.length === 0) {
    return /*#__PURE__*/React.createElement("div", {
      className: "capacity-empty"
    }, /*#__PURE__*/React.createElement("div", {
      className: "empty-icon"
    }, "\u25CB"), /*#__PURE__*/React.createElement("h2", null, "No team members found"), /*#__PURE__*/React.createElement("p", null, "Add people to see their capacity."));
  }

  // Get jobs assigned to current person (for individual view)
  const personJobs = currentPerson ? getPersonJobs(currentPerson.id, data.jobs) : [];
  const categorizedJobs = currentPerson ? categorizeJobsByCapacity(personJobs, data.assets) : null;
  const scheduledEmails = currentPerson ? (data.scheduledEmails || []).filter(e => e.personId === currentPerson.id) : [];
  return /*#__PURE__*/React.createElement("div", {
    className: "capacity-tab"
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-header"
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-view-toggle"
  }, /*#__PURE__*/React.createElement("button", {
    className: `toggle-btn ${viewMode === 'team' ? 'active' : ''}`,
    onClick: () => setViewMode('team'),
    title: "Team Overview"
  }, "\u25CE Team"), /*#__PURE__*/React.createElement("button", {
    className: `toggle-btn ${viewMode === 'list' ? 'active' : ''}`,
    onClick: () => setViewMode('list'),
    title: "Individual List"
  }, "\u2630 List"), /*#__PURE__*/React.createElement("button", {
    className: `toggle-btn ${viewMode === 'calendar' ? 'active' : ''}`,
    onClick: () => setViewMode('calendar'),
    title: "Individual Calendar"
  }, "\u25A6 Calendar")), viewMode === 'team' && /*#__PURE__*/React.createElement("div", {
    className: "capacity-filters"
  }, /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Sort by:"), /*#__PURE__*/React.createElement("select", {
    value: sortBy,
    onChange: e => setSortBy(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "name"
  }, "Name"), /*#__PURE__*/React.createElement("option", {
    value: "role"
  }, "Role"), /*#__PURE__*/React.createElement("option", {
    value: "capacity"
  }, "Capacity (High \u2192 Low)"), /*#__PURE__*/React.createElement("option", {
    value: "jobs"
  }, "Jobs (High \u2192 Low)"))), /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Role:"), /*#__PURE__*/React.createElement("select", {
    value: filterRole,
    onChange: e => setFilterRole(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Roles"), uniqueRoles.map(role => /*#__PURE__*/React.createElement("option", {
    key: role,
    value: role
  }, role))))), (viewMode === 'list' || viewMode === 'calendar') && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(PersonSelector, {
    people: agencyPeople,
    currentIndex: currentPersonIndex,
    onNavigate: handleNavigate
  }), categorizedJobs && /*#__PURE__*/React.createElement("div", {
    className: "capacity-bars"
  }, /*#__PURE__*/React.createElement(CapacityBar, {
    current: categorizedJobs.todayHours,
    max: DAILY_CAPACITY,
    label: "Today"
  }), /*#__PURE__*/React.createElement(CapacityBar, {
    current: categorizedJobs.weekHours,
    max: WEEKLY_CAPACITY,
    label: "This Week"
  })))), viewMode === 'team' && /*#__PURE__*/React.createElement("div", {
    className: "team-overview"
  }, /*#__PURE__*/React.createElement("div", {
    className: "team-summary"
  }, /*#__PURE__*/React.createElement("div", {
    className: "summary-stat"
  }, /*#__PURE__*/React.createElement("span", {
    className: "stat-value"
  }, processedPeople.length), /*#__PURE__*/React.createElement("span", {
    className: "stat-label"
  }, "Team Members")), /*#__PURE__*/React.createElement("div", {
    className: "summary-stat"
  }, /*#__PURE__*/React.createElement("span", {
    className: "stat-value"
  }, processedPeople.filter(p => p.utilization > 100).length), /*#__PURE__*/React.createElement("span", {
    className: "stat-label"
  }, "Over Capacity")), /*#__PURE__*/React.createElement("div", {
    className: "summary-stat"
  }, /*#__PURE__*/React.createElement("span", {
    className: "stat-value"
  }, processedPeople.filter(p => p.utilization < 50).length), /*#__PURE__*/React.createElement("span", {
    className: "stat-label"
  }, "Under 50%"))), /*#__PURE__*/React.createElement("div", {
    className: "team-grid"
  }, processedPeople.map(person => /*#__PURE__*/React.createElement(TeamMemberCard, {
    key: person.id,
    person: person,
    data: data,
    onSelect: handleSelectPerson
  }))), processedPeople.length === 0 && /*#__PURE__*/React.createElement("div", {
    className: "capacity-empty-jobs"
  }, /*#__PURE__*/React.createElement("span", {
    className: "empty-icon"
  }, "\u25CC"), /*#__PURE__*/React.createElement("p", null, "No team members match the selected filter"))), viewMode === 'calendar' && currentPerson && /*#__PURE__*/React.createElement(CapacityCalendar, {
    person: currentPerson,
    jobs: personJobs,
    data: data
  }), viewMode === 'list' && currentPerson && /*#__PURE__*/React.createElement("div", {
    className: "capacity-content"
  }, /*#__PURE__*/React.createElement(CapacitySection, {
    title: "TODAY",
    jobs: categorizedJobs.today,
    totalHours: categorizedJobs.todayHours,
    data: data
  }), /*#__PURE__*/React.createElement(CapacitySection, {
    title: "THIS WEEK",
    jobs: categorizedJobs.thisWeek,
    totalHours: categorizedJobs.thisWeek.reduce((sum, j) => sum + j.hours, 0),
    data: data
  }), categorizedJobs.overflow.length > 0 && /*#__PURE__*/React.createElement(CapacitySection, {
    title: "OVERFLOW (Over Capacity)",
    jobs: categorizedJobs.overflow,
    totalHours: categorizedJobs.overflowHours,
    data: data,
    isOverflow: true
  }), personJobs.length === 0 && /*#__PURE__*/React.createElement("div", {
    className: "capacity-empty-jobs"
  }, /*#__PURE__*/React.createElement("span", {
    className: "empty-icon"
  }, "\u25CC"), /*#__PURE__*/React.createElement("p", null, "No jobs assigned to ", currentPerson.name))), (viewMode === 'list' || viewMode === 'calendar') && currentPerson && categorizedJobs && /*#__PURE__*/React.createElement("div", {
    className: "capacity-footer"
  }, /*#__PURE__*/React.createElement(EmailActions, {
    person: currentPerson,
    categorizedJobs: categorizedJobs,
    data: data,
    dispatch: dispatch
  }), scheduledEmails.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "scheduled-emails-indicator"
  }, scheduledEmails.length, " email(s) scheduled")));
};

// ============================================================================
// ADD PERSON MODAL
// ============================================================================
const canAccessOperations = user => {
  if (!user || !user.role) return false;
  const allowedRoles = ['COO', 'PM', 'Traffic', 'ECD', 'CD', 'Producer'];
  return allowedRoles.includes(user.role);
};
const OperationsDashboard = ({
  data,
  currentUser
}) => {
  // Calculate all metrics
  const metrics = useMemo(() => {
    // Get unique clients from projects
    const clients = [...new Set(data.projects.map(p => p.client).filter(Boolean))];

    // Calculate total job hours
    const totalJobHours = data.jobs.reduce((total, job) => {
      return total + calculateJobHours(job, data.assets);
    }, 0);

    // Calculate hours capacity per role
    const roleCapacity = {};
    ROLES.forEach(role => {
      const peopleInRole = data.people.filter(p => p.role === role);
      roleCapacity[role] = {
        count: peopleInRole.length,
        hours: peopleInRole.length * WEEKLY_CAPACITY
      };
    });

    // Count jobs by status
    const jobsByStatus = {};
    STATUSES.forEach(status => {
      jobsByStatus[status] = data.jobs.filter(j => j.status === status).length;
    });
    return {
      clientCount: clients.length,
      campaignCount: data.projects.length,
      jobCount: data.jobs.length,
      totalJobHours,
      roleCapacity,
      jobsByStatus
    };
  }, [data]);

  // Check access
  if (!canAccessOperations(currentUser)) {
    return /*#__PURE__*/React.createElement("div", {
      className: "operations-restricted"
    }, /*#__PURE__*/React.createElement("span", {
      className: "empty-icon"
    }, "\u25C9"), /*#__PURE__*/React.createElement("h2", null, "Access Restricted"), /*#__PURE__*/React.createElement("p", null, "Operations Dashboard is only available to COO, PM, Traffic, ECD, CD, and Producer roles."));
  }
  return /*#__PURE__*/React.createElement("div", {
    className: "operations-dashboard"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-header"
  }, /*#__PURE__*/React.createElement("h2", null, "Operations Dashboard"), /*#__PURE__*/React.createElement("p", null, "Overview of agency capacity and workload")), /*#__PURE__*/React.createElement("div", {
    className: "ops-metrics-grid"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-card clients"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-icon"
  }, "\uD83C\uDFE2"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-content"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-value"
  }, metrics.clientCount), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-label"
  }, "Clients"))), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-card campaigns"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-icon"
  }, "\uD83D\uDCCB"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-content"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-value"
  }, metrics.campaignCount), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-label"
  }, "Campaigns"))), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-card jobs"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-icon"
  }, "\uD83D\uDCC1"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-content"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-value"
  }, metrics.jobCount), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-label"
  }, "Jobs"))), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-card hours"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-icon"
  }, "\u23F1\uFE0F"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-content"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-value"
  }, metrics.totalJobHours.toFixed(1), "h"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-label"
  }, "Hours in Jobs")))), /*#__PURE__*/React.createElement("div", {
    className: "ops-section"
  }, /*#__PURE__*/React.createElement("h3", null, "Hours Capacity per Role (Weekly)"), /*#__PURE__*/React.createElement("div", {
    className: "ops-capacity-grid"
  }, ROLES.filter(role => role !== 'Client').map(role => /*#__PURE__*/React.createElement("div", {
    key: role,
    className: "ops-capacity-card"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-capacity-header"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ops-capacity-role"
  }, role), /*#__PURE__*/React.createElement("span", {
    className: "ops-capacity-people"
  }, metrics.roleCapacity[role].count, " people")), /*#__PURE__*/React.createElement("div", {
    className: "ops-capacity-bar"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-capacity-fill",
    style: {
      width: `${Math.min(metrics.roleCapacity[role].hours / 280 * 100, 100)}%`
    }
  })), /*#__PURE__*/React.createElement("div", {
    className: "ops-capacity-hours"
  }, metrics.roleCapacity[role].hours, "h / week"))))), /*#__PURE__*/React.createElement("div", {
    className: "ops-section"
  }, /*#__PURE__*/React.createElement("h3", null, "Jobs by Status"), /*#__PURE__*/React.createElement("div", {
    className: "ops-status-grid"
  }, STATUSES.map(status => {
    const count = metrics.jobsByStatus[status];
    const color = STATUS_COLORS[status];
    return /*#__PURE__*/React.createElement("div", {
      key: status,
      className: "ops-status-card"
    }, /*#__PURE__*/React.createElement("div", {
      className: "ops-status-indicator",
      style: {
        backgroundColor: color
      }
    }), /*#__PURE__*/React.createElement("div", {
      className: "ops-status-info"
    }, /*#__PURE__*/React.createElement("span", {
      className: "ops-status-name"
    }, status), /*#__PURE__*/React.createElement("span", {
      className: "ops-status-count"
    }, count)));
  }))));
};

// ============================================================================
// CLIENT REVIEW TAB COMPONENT
// ============================================================================

// Check if user can access Client Review (Client role only)
const canAccessClientReview = user => {
  if (!user || !user.role) return false;
  return user.role === 'Client';
};
const ClientReviewTab = ({
  data,
  dispatch,
  currentUser
}) => {
  const [feedbackJobId, setFeedbackJobId] = useState(null);
  const [feedbackText, setFeedbackText] = useState('');
  const [approvedJobs, setApprovedJobs] = useState(new Set());
  const [rejectedJobs, setRejectedJobs] = useState(new Set());

  // Get jobs pending client review where current user is the assigned Client
  const pendingReviewJobs = useMemo(() => {
    if (!currentUser) return [];
    return data.jobs.filter(job => job.status === 'In Review' && job.assignments?.Client === currentUser.id);
  }, [data.jobs, currentUser]);

  // Get tasks for a job
  const getJobTasks = jobId => {
    return (data.tasks || []).filter(t => t.jobId === jobId);
  };

  // Get project for a job
  const getProject = projectId => {
    return data.projects.find(p => p.id === projectId);
  };

  // Handle approval
  const handleApprove = job => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        status: 'Approved (External)'
      }
    });
    setApprovedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection
  const handleReject = job => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        status: 'In Progress'
      }
    });
    setRejectedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection with feedback - assigns to CD
  const handleRejectWithFeedback = job => {
    // Find the CD assigned to this job, or fall back to any CD in the system
    const assignedCD = job.assignments?.CD;
    const fallbackCD = data.people.find(p => p.role === 'CD')?.id;
    const feedbackAssignee = assignedCD || fallbackCD;
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        status: 'In Progress',
        clientFeedback: feedbackText,
        clientFeedbackDate: new Date().toISOString(),
        clientFeedbackBy: currentUser?.id,
        clientFeedbackAssignedTo: feedbackAssignee,
        clientFeedbackStatus: 'pending' // pending, actioned, reassigned
      }
    });
    setRejectedJobs(prev => new Set([...prev, job.id]));
    setFeedbackJobId(null);
    setFeedbackText('');
  };

  // Check access
  if (!canAccessClientReview(currentUser)) {
    return /*#__PURE__*/React.createElement("div", {
      className: "client-review-restricted"
    }, /*#__PURE__*/React.createElement("div", {
      className: "restricted-icon"
    }, "\uD83D\uDD12"), /*#__PURE__*/React.createElement("h2", null, "Client Portal"), /*#__PURE__*/React.createElement("p", null, "This area is exclusively for client review access."));
  }
  if (pendingReviewJobs.length === 0) {
    return /*#__PURE__*/React.createElement("div", {
      className: "client-review-empty"
    }, /*#__PURE__*/React.createElement("div", {
      className: "empty-illustration"
    }, /*#__PURE__*/React.createElement("div", {
      className: "check-circle"
    }, "\u2713")), /*#__PURE__*/React.createElement("h2", null, "All Caught Up!"), /*#__PURE__*/React.createElement("p", null, "No creative work pending your review at the moment."), /*#__PURE__*/React.createElement("p", {
      className: "empty-subtext"
    }, "We'll notify you when new content is ready."));
  }
  return /*#__PURE__*/React.createElement("div", {
    className: "client-review-tab"
  }, /*#__PURE__*/React.createElement("div", {
    className: "client-review-header"
  }, /*#__PURE__*/React.createElement("div", {
    className: "review-header-content"
  }, /*#__PURE__*/React.createElement("h1", null, "Creative Review"), /*#__PURE__*/React.createElement("p", {
    className: "review-subtitle"
  }, pendingReviewJobs.length, " item", pendingReviewJobs.length !== 1 ? 's' : '', " awaiting your approval"))), /*#__PURE__*/React.createElement("div", {
    className: "review-items-container"
  }, pendingReviewJobs.map(job => {
    const tasks = getJobTasks(job.id);
    const copyTask = tasks.find(t => t.templateId === 'copy');
    const mediaTask = tasks.find(t => t.templateId === 'media');
    const project = getProject(job.projectId);
    const isApproved = approvedJobs.has(job.id);
    const isRejected = rejectedJobs.has(job.id);
    const showFeedbackInput = feedbackJobId === job.id;
    return /*#__PURE__*/React.createElement("div", {
      key: job.id,
      className: `review-item ${isApproved ? 'approved' : ''} ${isRejected ? 'rejected' : ''}`
    }, /*#__PURE__*/React.createElement("div", {
      className: "social-post-mockup"
    }, /*#__PURE__*/React.createElement("div", {
      className: "post-header"
    }, /*#__PURE__*/React.createElement("div", {
      className: "post-avatar"
    }, project?.client?.charAt(0) || 'C'), /*#__PURE__*/React.createElement("div", {
      className: "post-account"
    }, /*#__PURE__*/React.createElement("span", {
      className: "account-name"
    }, project?.client || 'Client'), /*#__PURE__*/React.createElement("span", {
      className: "account-handle"
    }, "@", (project?.client || 'client').toLowerCase().replace(/\s+/g, ''))), /*#__PURE__*/React.createElement("div", {
      className: "post-platform"
    }, /*#__PURE__*/React.createElement("span", {
      className: "platform-badge"
    }, "Preview"))), /*#__PURE__*/React.createElement("div", {
      className: "post-media"
    }, mediaTask?.fileUrl ? /*#__PURE__*/React.createElement("div", {
      className: "media-preview"
    }, mediaTask.fileType === 'video' ? /*#__PURE__*/React.createElement("div", {
      className: "video-placeholder"
    }, /*#__PURE__*/React.createElement("span", {
      className: "play-icon"
    }, "\u25B6"), /*#__PURE__*/React.createElement("span", {
      className: "video-filename"
    }, mediaTask.fileUrl)) : /*#__PURE__*/React.createElement("div", {
      className: "image-placeholder"
    }, /*#__PURE__*/React.createElement("span", {
      className: "image-icon"
    }, "\uD83D\uDDBC"), /*#__PURE__*/React.createElement("span", {
      className: "image-filename"
    }, mediaTask.fileUrl))) : /*#__PURE__*/React.createElement("div", {
      className: "media-pending"
    }, /*#__PURE__*/React.createElement("span", null, "Media pending"))), /*#__PURE__*/React.createElement("div", {
      className: "post-engagement"
    }, /*#__PURE__*/React.createElement("div", {
      className: "engagement-icons"
    }, /*#__PURE__*/React.createElement("span", {
      className: "engagement-icon"
    }, "\u2661"), /*#__PURE__*/React.createElement("span", {
      className: "engagement-icon"
    }, "\uD83D\uDCAC"), /*#__PURE__*/React.createElement("span", {
      className: "engagement-icon"
    }, "\u2197")), /*#__PURE__*/React.createElement("span", {
      className: "bookmark-icon"
    }, "\u2690")), /*#__PURE__*/React.createElement("div", {
      className: "post-content"
    }, /*#__PURE__*/React.createElement("div", {
      className: "post-caption"
    }, /*#__PURE__*/React.createElement("span", {
      className: "caption-account"
    }, (project?.client || 'client').toLowerCase().replace(/\s+/g, '')), /*#__PURE__*/React.createElement("span", {
      className: "caption-text"
    }, copyTask?.content || 'Copy pending...')), copyTask?.characterCount && /*#__PURE__*/React.createElement("div", {
      className: "char-count-badge"
    }, copyTask.characterCount, " characters")), /*#__PURE__*/React.createElement("div", {
      className: "post-meta"
    }, /*#__PURE__*/React.createElement("span", {
      className: "job-reference"
    }, job.jobNumber), /*#__PURE__*/React.createElement("span", {
      className: "job-name"
    }, job.name))), /*#__PURE__*/React.createElement("div", {
      className: "review-actions"
    }, /*#__PURE__*/React.createElement("div", {
      className: "job-info-card"
    }, /*#__PURE__*/React.createElement("div", {
      className: "info-row"
    }, /*#__PURE__*/React.createElement("span", {
      className: "info-label"
    }, "Campaign"), /*#__PURE__*/React.createElement("span", {
      className: "info-value"
    }, project?.name || 'N/A')), /*#__PURE__*/React.createElement("div", {
      className: "info-row"
    }, /*#__PURE__*/React.createElement("span", {
      className: "info-label"
    }, "Due Date"), /*#__PURE__*/React.createElement("span", {
      className: "info-value"
    }, job.dueDate ? new Date(job.dueDate).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric'
    }) : 'No deadline')), job.description && /*#__PURE__*/React.createElement("div", {
      className: "info-row description"
    }, /*#__PURE__*/React.createElement("span", {
      className: "info-label"
    }, "Brief"), /*#__PURE__*/React.createElement("span", {
      className: "info-value"
    }, job.description))), isApproved ? /*#__PURE__*/React.createElement("div", {
      className: "action-result approved"
    }, /*#__PURE__*/React.createElement("span", {
      className: "result-icon"
    }, "\u2713"), /*#__PURE__*/React.createElement("span", null, "Approved")) : isRejected ? /*#__PURE__*/React.createElement("div", {
      className: "action-result rejected"
    }, /*#__PURE__*/React.createElement("span", {
      className: "result-icon"
    }, "\u2717"), /*#__PURE__*/React.createElement("span", null, "Returned for revisions")) : /*#__PURE__*/React.createElement("div", {
      className: "action-buttons"
    }, /*#__PURE__*/React.createElement("button", {
      className: "review-btn approve",
      onClick: () => handleApprove(job)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u2713"), "Approve"), /*#__PURE__*/React.createElement("button", {
      className: "review-btn reject",
      onClick: () => handleReject(job)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u2717"), "Not Approved"), /*#__PURE__*/React.createElement("button", {
      className: "review-btn feedback",
      onClick: () => setFeedbackJobId(showFeedbackInput ? null : job.id)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u270E"), showFeedbackInput ? 'Cancel' : 'Add Feedback'), showFeedbackInput && /*#__PURE__*/React.createElement("div", {
      className: "feedback-input-container"
    }, /*#__PURE__*/React.createElement("textarea", {
      className: "feedback-textarea",
      placeholder: "Share your feedback for the creative team...",
      value: feedbackText,
      onChange: e => setFeedbackText(e.target.value),
      autoFocus: true
    }), /*#__PURE__*/React.createElement("button", {
      className: "submit-feedback-btn",
      onClick: () => handleRejectWithFeedback(job),
      disabled: !feedbackText.trim()
    }, "Submit Feedback & Return")))));
  })));
};

// ============================================================================
// JOB REVIEW TAB (Internal Review for CDs, ECDs, Producers, PMs, COO)
// ============================================================================

// Check if user can access Job Review (internal reviewers)
const canAccessJobReview = user => {
  if (!user || !user.role) return false;
  const reviewerRoles = ['CD', 'ECD', 'Producer', 'PM', 'COO'];
  return reviewerRoles.includes(user.role);
};
const JobReviewTab = ({
  data,
  dispatch,
  currentUser
}) => {
  const [feedbackJobId, setFeedbackJobId] = useState(null);
  const [feedbackText, setFeedbackText] = useState('');
  const [approvedJobs, setApprovedJobs] = useState(new Set());
  const [rejectedJobs, setRejectedJobs] = useState(new Set());

  // Client feedback queue state
  const [assignDropdownJobId, setAssignDropdownJobId] = useState(null);
  const [actionedFeedback, setActionedFeedback] = useState(new Set());

  // Filters
  const [clientFilter, setClientFilter] = useState('all');
  const [campaignFilter, setCampaignFilter] = useState('all');
  const [jobFilter, setJobFilter] = useState('all');
  const [taskFilter, setTaskFilter] = useState('all');

  // Roles that can be assigned client feedback
  const FEEDBACK_ASSIGNABLE_ROLES = ['ECD', 'Copywriter', 'Designer', 'Traffic', 'PM', 'Producer'];

  // Get jobs with pending client feedback assigned to current user
  const clientFeedbackJobs = useMemo(() => {
    if (!currentUser) return [];
    return data.jobs.filter(job => job.clientFeedback && job.clientFeedbackStatus === 'pending' && job.clientFeedbackAssignedTo === currentUser.id);
  }, [data.jobs, currentUser]);

  // Get people by role for assignment dropdown
  const getPeopleByRole = role => {
    return data.people.filter(p => p.role === role);
  };

  // Handle Action & Submit - send back to client review
  const handleActionAndSubmit = job => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        status: 'In Review',
        clientFeedbackStatus: 'actioned',
        clientFeedbackActionedBy: currentUser?.id,
        clientFeedbackActionedAt: new Date().toISOString()
      }
    });
    setActionedFeedback(prev => new Set([...prev, job.id]));
  };

  // Handle reassignment of client feedback
  const handleAssignFeedback = (job, personId, role) => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        clientFeedbackAssignedTo: personId,
        clientFeedbackAssignedRole: role,
        clientFeedbackReassignedBy: currentUser?.id,
        clientFeedbackReassignedAt: new Date().toISOString()
      }
    });
    setAssignDropdownJobId(null);
    setActionedFeedback(prev => new Set([...prev, job.id]));
  };

  // Get unique clients from projects
  const clients = useMemo(() => {
    const clientSet = new Set(data.projects.map(p => p.client));
    return Array.from(clientSet).sort();
  }, [data.projects]);

  // Get campaigns (projects) filtered by client
  const campaigns = useMemo(() => {
    let filtered = data.projects;
    if (clientFilter !== 'all') {
      filtered = filtered.filter(p => p.client === clientFilter);
    }
    return filtered;
  }, [data.projects, clientFilter]);

  // Get jobs ready for internal review
  // Jobs in "In Progress" where Copy and Media tasks are completed
  const reviewableJobs = useMemo(() => {
    return data.jobs.filter(job => {
      // Must be in "In Progress" status
      if (job.status !== 'In Progress') return false;

      // Check if Copy and Media tasks exist and are completed
      const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id);
      const copyTask = jobTasks.find(t => t.templateId === 'copy');
      const mediaTask = jobTasks.find(t => t.templateId === 'media');

      // Both Copy and Media must be completed for internal review
      const copyComplete = copyTask?.status === 'Done';
      const mediaComplete = mediaTask?.status === 'Done';
      return copyComplete && mediaComplete;
    });
  }, [data.jobs, data.tasks]);

  // Apply filters
  const filteredJobs = useMemo(() => {
    let filtered = reviewableJobs;
    if (clientFilter !== 'all') {
      const projectIds = data.projects.filter(p => p.client === clientFilter).map(p => p.id);
      filtered = filtered.filter(j => projectIds.includes(j.projectId));
    }
    if (campaignFilter !== 'all') {
      filtered = filtered.filter(j => j.projectId === campaignFilter);
    }
    if (jobFilter !== 'all') {
      filtered = filtered.filter(j => j.id === jobFilter);
    }
    if (taskFilter !== 'all') {
      filtered = filtered.filter(job => {
        const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id);
        return jobTasks.some(t => t.templateId === taskFilter);
      });
    }
    return filtered;
  }, [reviewableJobs, clientFilter, campaignFilter, jobFilter, taskFilter, data.projects, data.tasks]);

  // Get tasks for a job
  const getJobTasks = jobId => {
    return (data.tasks || []).filter(t => t.jobId === jobId);
  };

  // Get project for a job
  const getProject = projectId => {
    return data.projects.find(p => p.id === projectId);
  };

  // Get person by ID
  const getPerson = personId => {
    return data.people.find(p => p.id === personId);
  };

  // Handle internal approval - moves to "In Review" for client
  const handleApprove = job => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        status: 'In Review',
        internalApprovedBy: currentUser?.id,
        internalApprovedAt: new Date().toISOString()
      }
    });
    setApprovedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection without feedback
  const handleReject = job => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        internalRejected: true,
        internalRejectedBy: currentUser?.id,
        internalRejectedAt: new Date().toISOString()
      }
    });
    setRejectedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection with feedback - assigns feedback to tasks and creates todo
  const handleRejectWithFeedback = job => {
    const jobTasks = getJobTasks(job.id);
    const copyTask = jobTasks.find(t => t.templateId === 'copy');
    const mediaTask = jobTasks.find(t => t.templateId === 'media');

    // Update Copy task with feedback and mark incomplete
    if (copyTask) {
      dispatch({
        type: 'UPDATE_TASK',
        payload: {
          ...copyTask,
          completed: false,
          internalFeedback: feedbackText,
          feedbackBy: currentUser?.id,
          feedbackAt: new Date().toISOString()
        }
      });
    }

    // Update Media task with feedback and mark incomplete
    if (mediaTask) {
      dispatch({
        type: 'UPDATE_TASK',
        payload: {
          ...mediaTask,
          completed: false,
          internalFeedback: feedbackText,
          feedbackBy: currentUser?.id,
          feedbackAt: new Date().toISOString()
        }
      });
    }

    // Update job with feedback record
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        internalFeedback: feedbackText,
        internalFeedbackBy: currentUser?.id,
        internalFeedbackAt: new Date().toISOString()
      }
    });
    setRejectedJobs(prev => new Set([...prev, job.id]));
    setFeedbackJobId(null);
    setFeedbackText('');
  };

  // Reset campaign filter when client changes
  useEffect(() => {
    if (clientFilter !== 'all') {
      setCampaignFilter('all');
      setJobFilter('all');
    }
  }, [clientFilter]);

  // Reset job filter when campaign changes
  useEffect(() => {
    if (campaignFilter !== 'all') {
      setJobFilter('all');
    }
  }, [campaignFilter]);

  // Check access
  if (!canAccessJobReview(currentUser)) {
    return /*#__PURE__*/React.createElement("div", {
      className: "job-review-restricted"
    }, /*#__PURE__*/React.createElement("span", {
      className: "empty-icon"
    }, "\u25C9"), /*#__PURE__*/React.createElement("h2", null, "Access Restricted"), /*#__PURE__*/React.createElement("p", null, "Job Review is available to CDs, ECDs, Producers, PMs, and COO roles."));
  }
  return /*#__PURE__*/React.createElement("div", {
    className: "job-review-tab"
  }, /*#__PURE__*/React.createElement("div", {
    className: "job-review-header"
  }, /*#__PURE__*/React.createElement("div", {
    className: "review-header-content"
  }, /*#__PURE__*/React.createElement("h1", null, "Internal Review"), /*#__PURE__*/React.createElement("p", {
    className: "review-subtitle"
  }, filteredJobs.length, " job", filteredJobs.length !== 1 ? 's' : '', " ready for review"))), /*#__PURE__*/React.createElement("div", {
    className: "job-review-filters"
  }, /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Client"), /*#__PURE__*/React.createElement("select", {
    value: clientFilter,
    onChange: e => setClientFilter(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Clients"), clients.map(client => /*#__PURE__*/React.createElement("option", {
    key: client,
    value: client
  }, client)))), /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Campaign"), /*#__PURE__*/React.createElement("select", {
    value: campaignFilter,
    onChange: e => setCampaignFilter(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Campaigns"), campaigns.map(campaign => /*#__PURE__*/React.createElement("option", {
    key: campaign.id,
    value: campaign.id
  }, campaign.name)))), /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Job"), /*#__PURE__*/React.createElement("select", {
    value: jobFilter,
    onChange: e => setJobFilter(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Jobs"), (campaignFilter !== 'all' ? reviewableJobs.filter(j => j.projectId === campaignFilter) : reviewableJobs).map(job => /*#__PURE__*/React.createElement("option", {
    key: job.id,
    value: job.id
  }, job.jobNumber, " - ", job.name)))), /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Task Type"), /*#__PURE__*/React.createElement("select", {
    value: taskFilter,
    onChange: e => setTaskFilter(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Tasks"), /*#__PURE__*/React.createElement("option", {
    value: "copy"
  }, "Copy"), /*#__PURE__*/React.createElement("option", {
    value: "media"
  }, "Media"))), (clientFilter !== 'all' || campaignFilter !== 'all' || jobFilter !== 'all' || taskFilter !== 'all') && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary filter-clear",
    onClick: () => {
      setClientFilter('all');
      setCampaignFilter('all');
      setJobFilter('all');
      setTaskFilter('all');
    }
  }, "Clear Filters")), clientFeedbackJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "client-feedback-queue"
  }, /*#__PURE__*/React.createElement("div", {
    className: "feedback-queue-header"
  }, /*#__PURE__*/React.createElement("h2", null, "\u25C9 Client Feedback Requiring Action"), /*#__PURE__*/React.createElement("span", {
    className: "feedback-count"
  }, clientFeedbackJobs.filter(j => !actionedFeedback.has(j.id)).length, " pending")), /*#__PURE__*/React.createElement("div", {
    className: "feedback-queue-list"
  }, clientFeedbackJobs.map(job => {
    const project = getProject(job.projectId);
    const feedbackFrom = getPerson(job.clientFeedbackBy);
    const isActioned = actionedFeedback.has(job.id);
    const showAssignDropdown = assignDropdownJobId === job.id;
    if (isActioned) {
      return /*#__PURE__*/React.createElement("div", {
        key: job.id,
        className: "feedback-card actioned"
      }, /*#__PURE__*/React.createElement("div", {
        className: "feedback-card-header"
      }, /*#__PURE__*/React.createElement("span", {
        className: "job-number"
      }, job.jobNumber), /*#__PURE__*/React.createElement("h4", null, job.name)), /*#__PURE__*/React.createElement("div", {
        className: "feedback-actioned-badge"
      }, /*#__PURE__*/React.createElement("span", {
        className: "result-icon"
      }, "\u2713"), "Feedback actioned"));
    }
    return /*#__PURE__*/React.createElement("div", {
      key: job.id,
      className: "feedback-card"
    }, /*#__PURE__*/React.createElement("div", {
      className: "feedback-card-header"
    }, /*#__PURE__*/React.createElement("div", {
      className: "feedback-card-title"
    }, /*#__PURE__*/React.createElement("span", {
      className: "job-number"
    }, job.jobNumber), /*#__PURE__*/React.createElement("h4", null, job.name)), /*#__PURE__*/React.createElement("div", {
      className: "feedback-card-meta"
    }, /*#__PURE__*/React.createElement("span", {
      className: "client-name"
    }, project?.client), /*#__PURE__*/React.createElement("span", {
      className: "campaign-name"
    }, project?.name))), /*#__PURE__*/React.createElement("div", {
      className: "feedback-content"
    }, /*#__PURE__*/React.createElement("div", {
      className: "feedback-from"
    }, /*#__PURE__*/React.createElement("span", {
      className: "feedback-label"
    }, "Feedback from:"), /*#__PURE__*/React.createElement("span", {
      className: "feedback-source"
    }, feedbackFrom?.name || 'Client'), /*#__PURE__*/React.createElement("span", {
      className: "feedback-date"
    }, job.clientFeedbackDate && new Date(job.clientFeedbackDate).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    }))), /*#__PURE__*/React.createElement("div", {
      className: "feedback-text"
    }, "\"", job.clientFeedback, "\"")), /*#__PURE__*/React.createElement("div", {
      className: "feedback-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "btn feedback-action-btn action-submit",
      onClick: () => handleActionAndSubmit(job)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u21A9"), "Action & Submit to Client"), /*#__PURE__*/React.createElement("div", {
      className: "assign-dropdown-container"
    }, /*#__PURE__*/React.createElement("button", {
      className: "btn feedback-action-btn assign-btn",
      onClick: () => setAssignDropdownJobId(showAssignDropdown ? null : job.id)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u2192"), "Assign to...", /*#__PURE__*/React.createElement("span", {
      className: "dropdown-arrow"
    }, showAssignDropdown ? '▲' : '▼')), showAssignDropdown && /*#__PURE__*/React.createElement("div", {
      className: "assign-dropdown"
    }, FEEDBACK_ASSIGNABLE_ROLES.map(role => {
      const peopleInRole = getPeopleByRole(role);
      if (peopleInRole.length === 0) return null;
      return /*#__PURE__*/React.createElement("div", {
        key: role,
        className: "assign-role-group"
      }, /*#__PURE__*/React.createElement("div", {
        className: "assign-role-header"
      }, role), peopleInRole.map(person => /*#__PURE__*/React.createElement("button", {
        key: person.id,
        className: "assign-option",
        onClick: () => handleAssignFeedback(job, person.id, role)
      }, /*#__PURE__*/React.createElement("span", {
        className: "assign-avatar",
        style: {
          background: person.color
        }
      }, person.name.split(' ').map(n => n[0]).join('')), /*#__PURE__*/React.createElement("span", {
        className: "assign-name"
      }, person.name))));
    })))));
  }))), filteredJobs.length === 0 ? /*#__PURE__*/React.createElement("div", {
    className: "job-review-empty"
  }, /*#__PURE__*/React.createElement("span", {
    className: "empty-icon"
  }, "\u25CC"), /*#__PURE__*/React.createElement("h2", null, "No Jobs Ready for Review"), /*#__PURE__*/React.createElement("p", null, "Jobs will appear here when their Copy and Media tasks are completed.")) : /*#__PURE__*/React.createElement("div", {
    className: "job-review-list"
  }, filteredJobs.map(job => {
    const tasks = getJobTasks(job.id);
    const copyTask = tasks.find(t => t.templateId === 'copy');
    const mediaTask = tasks.find(t => t.templateId === 'media');
    const project = getProject(job.projectId);
    const isApproved = approvedJobs.has(job.id);
    const isRejected = rejectedJobs.has(job.id);
    const showFeedbackInput = feedbackJobId === job.id;

    // Get assigned people
    const copywriter = job.assignments?.Copywriter ? getPerson(job.assignments.Copywriter) : null;
    const designer = job.assignments?.Designer ? getPerson(job.assignments.Designer) : null;
    return /*#__PURE__*/React.createElement("div", {
      key: job.id,
      className: `job-review-card ${isApproved ? 'approved' : ''} ${isRejected ? 'rejected' : ''}`
    }, /*#__PURE__*/React.createElement("div", {
      className: "review-card-header"
    }, /*#__PURE__*/React.createElement("div", {
      className: "review-card-title"
    }, /*#__PURE__*/React.createElement("span", {
      className: "job-number"
    }, job.jobNumber), /*#__PURE__*/React.createElement("h3", null, job.name)), /*#__PURE__*/React.createElement("div", {
      className: "review-card-meta"
    }, /*#__PURE__*/React.createElement("span", {
      className: "client-name"
    }, project?.client), /*#__PURE__*/React.createElement("span", {
      className: "campaign-name"
    }, project?.name))), /*#__PURE__*/React.createElement("div", {
      className: "review-card-content"
    }, /*#__PURE__*/React.createElement("div", {
      className: "review-task-section"
    }, /*#__PURE__*/React.createElement("div", {
      className: "task-section-header"
    }, /*#__PURE__*/React.createElement("span", {
      className: "task-label"
    }, "\u25C7 Copy"), copywriter && /*#__PURE__*/React.createElement("span", {
      className: "task-assignee"
    }, "by ", copywriter.name), copyTask?.characterCount && /*#__PURE__*/React.createElement("span", {
      className: "char-badge"
    }, copyTask.characterCount, " chars")), /*#__PURE__*/React.createElement("div", {
      className: "task-content-preview"
    }, copyTask?.content || /*#__PURE__*/React.createElement("span", {
      className: "no-content"
    }, "No copy content")), copyTask?.internalFeedback && /*#__PURE__*/React.createElement("div", {
      className: "task-feedback-display"
    }, /*#__PURE__*/React.createElement("span", {
      className: "feedback-label"
    }, "Previous Feedback:"), /*#__PURE__*/React.createElement("p", null, copyTask.internalFeedback))), /*#__PURE__*/React.createElement("div", {
      className: "review-task-section"
    }, /*#__PURE__*/React.createElement("div", {
      className: "task-section-header"
    }, /*#__PURE__*/React.createElement("span", {
      className: "task-label"
    }, "\u25C8 Media"), designer && /*#__PURE__*/React.createElement("span", {
      className: "task-assignee"
    }, "by ", designer.name), mediaTask?.fileType && /*#__PURE__*/React.createElement("span", {
      className: "file-type-badge"
    }, mediaTask.fileType)), /*#__PURE__*/React.createElement("div", {
      className: "task-media-preview"
    }, mediaTask?.fileUrl ? /*#__PURE__*/React.createElement("div", {
      className: "media-file-info"
    }, /*#__PURE__*/React.createElement("span", {
      className: "media-icon"
    }, mediaTask.fileType === 'video' ? '▶' : '🖼'), /*#__PURE__*/React.createElement("span", {
      className: "media-filename"
    }, mediaTask.fileUrl)) : /*#__PURE__*/React.createElement("span", {
      className: "no-content"
    }, "No media file")), mediaTask?.internalFeedback && /*#__PURE__*/React.createElement("div", {
      className: "task-feedback-display"
    }, /*#__PURE__*/React.createElement("span", {
      className: "feedback-label"
    }, "Previous Feedback:"), /*#__PURE__*/React.createElement("p", null, mediaTask.internalFeedback)))), /*#__PURE__*/React.createElement("div", {
      className: "review-card-details"
    }, job.description && /*#__PURE__*/React.createElement("div", {
      className: "detail-row"
    }, /*#__PURE__*/React.createElement("span", {
      className: "detail-label"
    }, "Brief"), /*#__PURE__*/React.createElement("span", {
      className: "detail-value"
    }, job.description)), /*#__PURE__*/React.createElement("div", {
      className: "detail-row"
    }, /*#__PURE__*/React.createElement("span", {
      className: "detail-label"
    }, "Due Date"), /*#__PURE__*/React.createElement("span", {
      className: "detail-value"
    }, job.dueDate ? new Date(job.dueDate).toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric'
    }) : 'No deadline'))), /*#__PURE__*/React.createElement("div", {
      className: "review-card-actions"
    }, isApproved ? /*#__PURE__*/React.createElement("div", {
      className: "action-result approved"
    }, /*#__PURE__*/React.createElement("span", {
      className: "result-icon"
    }, "\u2713"), /*#__PURE__*/React.createElement("span", null, "Approved \u2013 Sent to Client Review")) : isRejected ? /*#__PURE__*/React.createElement("div", {
      className: "action-result rejected"
    }, /*#__PURE__*/React.createElement("span", {
      className: "result-icon"
    }, "\u21A9"), /*#__PURE__*/React.createElement("span", null, "Returned for revisions")) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      className: "action-buttons-row"
    }, /*#__PURE__*/React.createElement("button", {
      className: "review-btn approve",
      onClick: () => handleApprove(job)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u2713"), "Approve"), /*#__PURE__*/React.createElement("button", {
      className: "review-btn reject",
      onClick: () => handleReject(job)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u2717"), "Not Approved"), /*#__PURE__*/React.createElement("button", {
      className: "review-btn feedback",
      onClick: () => setFeedbackJobId(showFeedbackInput ? null : job.id)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u270E"), showFeedbackInput ? 'Cancel' : 'Feedback')), showFeedbackInput && /*#__PURE__*/React.createElement("div", {
      className: "feedback-input-section"
    }, /*#__PURE__*/React.createElement("textarea", {
      className: "feedback-textarea",
      placeholder: "Provide feedback for the Copywriter and Designer...",
      value: feedbackText,
      onChange: e => setFeedbackText(e.target.value),
      autoFocus: true
    }), /*#__PURE__*/React.createElement("div", {
      className: "feedback-note"
    }, "This feedback will be assigned to the Copy and Media tasks for ", copywriter?.name || 'Copywriter', " and ", designer?.name || 'Designer', " to address."), /*#__PURE__*/React.createElement("button", {
      className: "submit-feedback-btn",
      onClick: () => handleRejectWithFeedback(job),
      disabled: !feedbackText.trim()
    }, "Submit Feedback & Return for Revisions")))));
  })));
};

// ============================================================================
// MAIN APP COMPONENT
// ============================================================================
// ============================================================================
// DASHBOARD COMPONENT (Phase 2 - Role-Based Landing Page)
// ============================================================================
//
// This component will be the default landing page, replacing the current
// Jobs tab as the first thing users see.
//
// Features planned:
// - Role-based content (different views for COO, Traffic, Designer, etc.)
// - "My Work" section showing jobs assigned to current user
// - Recent activity feed
// - Quick actions (new brief, check capacity)
//
// Navigation consolidation (9 → 5 tabs):
// 1. DASHBOARD (this component)
// 2. WORK (merged Projects/Jobs/Assets)
// 3. CAPACITY (Traffic/PM only)
// 4. REVIEWS (merged Job Review/Client Review)
// 5. MORE (People, Wiki, Operations, Settings)
// ============================================================================

const DashboardMyWork = ({
  jobs,
  currentUser,
  data,
  onJobClick
}) => {
  // Filter jobs assigned to current user
  const myJobs = jobs.filter(job => Object.values(job.assignments || {}).includes(currentUser?.id));

  // Group by status priority
  const todayJobs = myJobs.filter(j => j.status === 'Today' || j.status === 'In Progress');
  const thisWeekJobs = myJobs.filter(j => j.status === 'This Week');
  const inboxJobs = myJobs.filter(j => j.status === 'Inbox');
  return /*#__PURE__*/React.createElement("div", {
    className: "dashboard-my-work"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-header"
  }, /*#__PURE__*/React.createElement("h2", null, "My Work"), /*#__PURE__*/React.createElement("span", {
    className: "section-count"
  }, myJobs.length, " jobs")), myJobs.length === 0 ? /*#__PURE__*/React.createElement("div", {
    className: "dashboard-empty"
  }, /*#__PURE__*/React.createElement("span", {
    className: "empty-icon"
  }, "\u25CC"), /*#__PURE__*/React.createElement("p", null, "No jobs assigned to you")) : /*#__PURE__*/React.createElement("div", {
    className: "my-work-sections"
  }, todayJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "work-section urgent"
  }, /*#__PURE__*/React.createElement("h3", null, "Today / In Progress (", todayJobs.length, ")"), /*#__PURE__*/React.createElement("div", {
    className: "work-cards"
  }, todayJobs.map(job => /*#__PURE__*/React.createElement(DashboardJobCard, {
    key: job.id,
    job: job,
    data: data,
    onClick: onJobClick
  })))), thisWeekJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "work-section"
  }, /*#__PURE__*/React.createElement("h3", null, "This Week (", thisWeekJobs.length, ")"), /*#__PURE__*/React.createElement("div", {
    className: "work-cards"
  }, thisWeekJobs.map(job => /*#__PURE__*/React.createElement(DashboardJobCard, {
    key: job.id,
    job: job,
    data: data,
    onClick: onJobClick
  })))), inboxJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "work-section"
  }, /*#__PURE__*/React.createElement("h3", null, "Inbox (", inboxJobs.length, ")"), /*#__PURE__*/React.createElement("div", {
    className: "work-cards"
  }, inboxJobs.map(job => /*#__PURE__*/React.createElement(DashboardJobCard, {
    key: job.id,
    job: job,
    data: data,
    onClick: onJobClick
  }))))));
};
const DashboardJobCard = ({
  job,
  data,
  onClick
}) => {
  const project = data.projects.find(p => p.id === job.projectId);
  return /*#__PURE__*/React.createElement("div", {
    className: "dashboard-job-card",
    onClick: () => onClick && onClick(job)
  }, /*#__PURE__*/React.createElement("div", {
    className: "job-card-header"
  }, /*#__PURE__*/React.createElement("span", {
    className: "job-number"
  }, job.jobNumber), /*#__PURE__*/React.createElement(StatusBadge, {
    status: job.status
  })), /*#__PURE__*/React.createElement("div", {
    className: "job-card-name"
  }, job.name), /*#__PURE__*/React.createElement("div", {
    className: "job-card-meta"
  }, /*#__PURE__*/React.createElement("span", {
    className: "client"
  }, project?.client), job.dueDate && /*#__PURE__*/React.createElement("span", {
    className: "due-date"
  }, "Due: ", formatDate(job.dueDate))));
};
const DashboardQuickActions = ({
  currentUser,
  onNewBrief,
  onCheckCapacity
}) => {
  const permissions = getUserPermissions(currentUser);
  return /*#__PURE__*/React.createElement("div", {
    className: "dashboard-quick-actions"
  }, /*#__PURE__*/React.createElement("h2", null, "Quick Actions"), /*#__PURE__*/React.createElement("div", {
    className: "action-buttons"
  }, permissions.canCreateJobs && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary action-btn",
    onClick: onNewBrief
  }, "+ New Brief"), (permissions.level === 'admin' || permissions.level === 'manager') && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary action-btn",
    onClick: onCheckCapacity
  }, "Check Capacity")));
};
const DashboardRecentActivity = ({
  data,
  currentUser
}) => {
  // Get recent jobs (last 7 days) with activity
  const recentJobs = data.jobs.filter(job => {
    const created = new Date(job.createdAt);
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    return created > weekAgo;
  }).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)).slice(0, 5);
  return /*#__PURE__*/React.createElement("div", {
    className: "dashboard-recent-activity"
  }, /*#__PURE__*/React.createElement("h2", null, "Recent Activity"), recentJobs.length === 0 ? /*#__PURE__*/React.createElement("p", {
    className: "no-activity"
  }, "No recent activity") : /*#__PURE__*/React.createElement("div", {
    className: "activity-list"
  }, recentJobs.map(job => {
    const project = data.projects.find(p => p.id === job.projectId);
    return /*#__PURE__*/React.createElement("div", {
      key: job.id,
      className: "activity-item"
    }, /*#__PURE__*/React.createElement("span", {
      className: "activity-icon"
    }, "\u25CE"), /*#__PURE__*/React.createElement("div", {
      className: "activity-content"
    }, /*#__PURE__*/React.createElement("span", {
      className: "activity-title"
    }, job.jobNumber, " - ", job.name), /*#__PURE__*/React.createElement("span", {
      className: "activity-meta"
    }, project?.client, " \u2022 Created ", formatDate(job.createdAt))));
  })));
};

// Main Dashboard Component
const Dashboard = ({
  data,
  dispatch,
  currentUser,
  onNewBrief,
  onCheckCapacity,
  onJobClick
}) => {
  const permissions = getUserPermissions(currentUser);

  // Role-specific greeting
  const getGreeting = () => {
    const hour = new Date().getHours();
    let timeGreeting = 'Good morning';
    if (hour >= 12 && hour < 17) timeGreeting = 'Good afternoon';
    if (hour >= 17) timeGreeting = 'Good evening';
    return `${timeGreeting}, ${currentUser?.name?.split(' ')[0] || 'there'}`;
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "dashboard-tab"
  }, /*#__PURE__*/React.createElement("div", {
    className: "dashboard-header"
  }, /*#__PURE__*/React.createElement("h1", null, getGreeting()), /*#__PURE__*/React.createElement("p", {
    className: "dashboard-subtitle"
  }, currentUser?.role, " \u2022 ", data.jobs.filter(j => Object.values(j.assignments || {}).includes(currentUser?.id) && j.status !== 'Done' && j.status !== 'Archived').length, " active jobs")), /*#__PURE__*/React.createElement("div", {
    className: "dashboard-grid"
  }, /*#__PURE__*/React.createElement("div", {
    className: "dashboard-main"
  }, /*#__PURE__*/React.createElement(DashboardMyWork, {
    jobs: data.jobs,
    currentUser: currentUser,
    data: data,
    onJobClick: onJobClick
  })), /*#__PURE__*/React.createElement("div", {
    className: "dashboard-sidebar"
  }, /*#__PURE__*/React.createElement(DashboardQuickActions, {
    currentUser: currentUser,
    onNewBrief: onNewBrief,
    onCheckCapacity: onCheckCapacity
  }), /*#__PURE__*/React.createElement(DashboardRecentActivity, {
    data: data,
    currentUser: currentUser
  }))));
};

// ============================================================================
// PHASE 2 TODO: Integration Steps
// ============================================================================
// 1. Add Dashboard to TABS constant in constants.js (as first item)
// 2. Add Dashboard case to App component's tab rendering
// 3. Set default activeTab to 'Dashboard' instead of 'Jobs'
// 4. Add dashboard.js to index.html script loading (before app.js)
// 5. Create CSS styles for dashboard components
// ============================================================================
// ============================================================================
// WORK TAB COMPONENT (Phase 2 - Unified Projects/Jobs/Assets View)
// ============================================================================
//
// Consolidates the previous Projects, Jobs, and Assets tabs into a single
// unified "Work" view with sub-navigation and filters.
//
// Features:
// - Sub-tabs: Jobs (default), Projects, Assets
// - Filters: My Work, All, By Project, By Status
// - Both Table and Kanban views
// - "New Brief" CTA always visible
// ============================================================================

const WorkTab = ({
  data,
  dispatch,
  currentUser,
  viewMode,
  searchQuery,
  onRowClick,
  onNewBrief
}) => {
  const [activeSubTab, setActiveSubTab] = useState('Jobs');
  const [filterMode, setFilterMode] = useState('all'); // 'all', 'mine', 'project'
  const [selectedProjectId, setSelectedProjectId] = useState(null);
  const [statusFilter, setStatusFilter] = useState('all');
  const SUB_TABS = ['Jobs', 'Projects', 'Assets'];

  // Get filtered items based on sub-tab and filters
  const getFilteredItems = () => {
    let items = [];
    switch (activeSubTab) {
      case 'Projects':
        items = data.projects;
        break;
      case 'Jobs':
        items = data.jobs;
        break;
      case 'Assets':
        items = data.assets;
        break;
      default:
        items = data.jobs;
    }

    // Filter by permissions - only show items user can view
    if (currentUser && activeSubTab !== 'People') {
      items = items.filter(item => canUserViewItem(currentUser, item, activeSubTab, data));
    }

    // Filter by "My Work"
    if (filterMode === 'mine' && currentUser) {
      if (activeSubTab === 'Jobs') {
        items = items.filter(job => Object.values(job.assignments || {}).includes(currentUser.id));
      } else if (activeSubTab === 'Assets') {
        items = items.filter(asset => asset.assignedTo === currentUser.id);
      } else if (activeSubTab === 'Projects') {
        // Show projects where user is assigned to at least one job
        const userJobProjectIds = data.jobs.filter(job => Object.values(job.assignments || {}).includes(currentUser.id)).map(job => job.projectId);
        items = items.filter(project => userJobProjectIds.includes(project.id));
      }
    }

    // Filter by selected project
    if (selectedProjectId && activeSubTab !== 'Projects') {
      if (activeSubTab === 'Jobs') {
        items = items.filter(job => job.projectId === selectedProjectId);
      } else if (activeSubTab === 'Assets') {
        const projectJobs = data.jobs.filter(j => j.projectId === selectedProjectId).map(j => j.id);
        items = items.filter(asset => projectJobs.includes(asset.jobId));
      }
    }

    // Filter by status
    if (statusFilter !== 'all') {
      items = items.filter(item => item.status === statusFilter);
    }

    // Filter by search query
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      items = items.filter(item => {
        const name = item.name || '';
        const desc = item.description || '';
        const jobNumber = item.jobNumber || '';
        const client = item.client || '';
        return name.toLowerCase().includes(q) || desc.toLowerCase().includes(q) || jobNumber.toLowerCase().includes(q) || client.toLowerCase().includes(q);
      });
    }
    return items;
  };
  const items = getFilteredItems();

  // Get counts for sub-tabs
  const getSubTabCount = subTab => {
    switch (subTab) {
      case 'Projects':
        return data.projects.length;
      case 'Jobs':
        return data.jobs.length;
      case 'Assets':
        return data.assets.length;
      default:
        return 0;
    }
  };

  // Get unique statuses for filter dropdown
  const getAvailableStatuses = () => {
    const statusSet = new Set();
    let sourceItems = [];
    switch (activeSubTab) {
      case 'Projects':
        sourceItems = data.projects;
        break;
      case 'Jobs':
        sourceItems = data.jobs;
        break;
      case 'Assets':
        sourceItems = data.assets;
        break;
    }
    sourceItems.forEach(item => {
      if (item.status) statusSet.add(item.status);
    });
    return Array.from(statusSet).sort();
  };

  // Handle project selection from filter
  const handleProjectSelect = projectId => {
    setSelectedProjectId(projectId === 'all' ? null : projectId);
    setFilterMode('project');
  };

  // Clear all filters
  const clearFilters = () => {
    setFilterMode('all');
    setSelectedProjectId(null);
    setStatusFilter('all');
  };
  const hasActiveFilters = filterMode !== 'all' || selectedProjectId || statusFilter !== 'all';
  return /*#__PURE__*/React.createElement("div", {
    className: "work-tab"
  }, /*#__PURE__*/React.createElement("div", {
    className: "work-sub-nav"
  }, /*#__PURE__*/React.createElement("div", {
    className: "sub-tabs"
  }, SUB_TABS.map(subTab => /*#__PURE__*/React.createElement("button", {
    key: subTab,
    className: `sub-tab ${activeSubTab === subTab ? 'active' : ''}`,
    onClick: () => {
      setActiveSubTab(subTab);
      // Reset project filter when switching sub-tabs
      if (subTab === 'Projects') {
        setSelectedProjectId(null);
      }
    }
  }, subTab, /*#__PURE__*/React.createElement("span", {
    className: "sub-tab-count"
  }, getSubTabCount(subTab))))), /*#__PURE__*/React.createElement("div", {
    className: "work-filters"
  }, /*#__PURE__*/React.createElement("div", {
    className: "filter-toggle"
  }, /*#__PURE__*/React.createElement("button", {
    className: `filter-btn ${filterMode === 'all' && !selectedProjectId ? 'active' : ''}`,
    onClick: () => {
      setFilterMode('all');
      setSelectedProjectId(null);
    }
  }, "All"), /*#__PURE__*/React.createElement("button", {
    className: `filter-btn ${filterMode === 'mine' ? 'active' : ''}`,
    onClick: () => {
      setFilterMode('mine');
      setSelectedProjectId(null);
    }
  }, "My Work")), activeSubTab !== 'Projects' && /*#__PURE__*/React.createElement("select", {
    className: "filter-select",
    value: selectedProjectId || 'all',
    onChange: e => handleProjectSelect(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Projects"), data.projects.map(project => /*#__PURE__*/React.createElement("option", {
    key: project.id,
    value: project.id
  }, project.client, " - ", project.name))), /*#__PURE__*/React.createElement("select", {
    className: "filter-select",
    value: statusFilter,
    onChange: e => setStatusFilter(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Statuses"), getAvailableStatuses().map(status => /*#__PURE__*/React.createElement("option", {
    key: status,
    value: status
  }, status))), hasActiveFilters && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary btn-sm",
    onClick: clearFilters
  }, "Clear Filters"), canUserCreate(currentUser, 'Jobs') && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: onNewBrief
  }, "+ New Brief"))), /*#__PURE__*/React.createElement("div", {
    className: "work-results-summary"
  }, /*#__PURE__*/React.createElement("span", {
    className: "results-count"
  }, items.length, " ", activeSubTab.toLowerCase(), items.length !== 1 ? '' : '', filterMode === 'mine' && ' assigned to you', selectedProjectId && ` in ${data.projects.find(p => p.id === selectedProjectId)?.name || 'selected project'}`)), viewMode === 'table' ? /*#__PURE__*/React.createElement(WorkTableView, {
    items: items,
    type: activeSubTab,
    data: data,
    dispatch: dispatch,
    people: data.people,
    projects: data.projects,
    currentUser: currentUser,
    onRowClick: onRowClick
  }) : /*#__PURE__*/React.createElement(WorkKanbanView, {
    items: items,
    type: activeSubTab,
    data: data,
    dispatch: dispatch,
    people: data.people,
    projects: data.projects,
    currentUser: currentUser,
    onCardClick: onRowClick
  }));
};

// ============================================================================
// WORK TABLE VIEW
// ============================================================================

const WorkTableView = ({
  items,
  type,
  data,
  dispatch,
  people,
  projects,
  currentUser,
  onRowClick
}) => {
  const [sortField, setSortField] = useState(null);
  const [sortDir, setSortDir] = useState('asc');
  const handleSort = field => {
    if (sortField === field) {
      setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  // Sort items
  const sortedItems = useMemo(() => {
    if (!sortField) return items;
    return [...items].sort((a, b) => {
      let aVal = a[sortField] || '';
      let bVal = b[sortField] || '';
      if (typeof aVal === 'string') aVal = aVal.toLowerCase();
      if (typeof bVal === 'string') bVal = bVal.toLowerCase();
      if (aVal < bVal) return sortDir === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortDir === 'asc' ? 1 : -1;
      return 0;
    });
  }, [items, sortField, sortDir]);
  const handleStatusChange = (item, newStatus) => {
    if (!canUserEditItem(currentUser, item, type, 'status')) return;
    switch (type) {
      case 'Projects':
        dispatch({
          type: 'UPDATE_PROJECT',
          payload: {
            ...item,
            status: newStatus
          }
        });
        break;
      case 'Jobs':
        dispatch({
          type: 'UPDATE_JOB',
          payload: {
            ...item,
            status: newStatus
          }
        });
        break;
      case 'Assets':
        dispatch({
          type: 'UPDATE_ASSET',
          payload: {
            ...item,
            status: newStatus
          }
        });
        break;
    }
  };
  const getProjectName = projectId => {
    const proj = projects.find(p => p.id === projectId);
    return proj ? proj.name : '';
  };
  const getProjectClient = projectId => {
    const proj = projects.find(p => p.id === projectId);
    return proj ? proj.client : '';
  };
  const renderColumns = () => {
    switch (type) {
      case 'Projects':
        return /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('name')
        }, "Name ", sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('client')
        }, "Client ", sortField === 'client' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('status')
        }, "Status ", sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Jobs"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('createdAt')
        }, "Created ", sortField === 'createdAt' && (sortDir === 'asc' ? '↑' : '↓')));
      case 'Jobs':
        return /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('jobNumber')
        }, "Job # ", sortField === 'jobNumber' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('name')
        }, "Name ", sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Client"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('status')
        }, "Status ", sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Team"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('dueDate')
        }, "Due ", sortField === 'dueDate' && (sortDir === 'asc' ? '↑' : '↓')));
      case 'Assets':
        return /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('name')
        }, "Name ", sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('type')
        }, "Type ", sortField === 'type' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Job"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('status')
        }, "Status ", sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')), /*#__PURE__*/React.createElement("th", null, "Assigned"), /*#__PURE__*/React.createElement("th", {
          onClick: () => handleSort('dueDate')
        }, "Due ", sortField === 'dueDate' && (sortDir === 'asc' ? '↑' : '↓')));
    }
  };
  const renderRow = item => {
    const canEditStatus = canUserEditItem(currentUser, item, type, 'status');
    switch (type) {
      case 'Projects':
        const jobCount = data.jobs.filter(j => j.projectId === item.id).length;
        return /*#__PURE__*/React.createElement("tr", {
          key: item.id,
          onClick: () => onRowClick(item)
        }, /*#__PURE__*/React.createElement("td", {
          className: "cell-name"
        }, item.name), /*#__PURE__*/React.createElement("td", null, item.client), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(StatusBadge, {
          status: item.status,
          onChange: canEditStatus ? s => handleStatusChange(item, s) : null
        })), /*#__PURE__*/React.createElement("td", null, jobCount), /*#__PURE__*/React.createElement("td", null, formatDate(item.createdAt)));
      case 'Jobs':
        const assignedIds = Object.values(item.assignments || {}).filter(Boolean);
        return /*#__PURE__*/React.createElement("tr", {
          key: item.id,
          onClick: () => onRowClick(item)
        }, /*#__PURE__*/React.createElement("td", {
          className: "cell-jobnum"
        }, item.jobNumber), /*#__PURE__*/React.createElement("td", {
          className: "cell-name"
        }, item.name), /*#__PURE__*/React.createElement("td", null, getProjectClient(item.projectId)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(StatusBadge, {
          status: item.status,
          onChange: canEditStatus ? s => handleStatusChange(item, s) : null,
          statuses: STATUSES_WITH_DONE
        })), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(PersonAvatarGroup, {
          personIds: assignedIds,
          people: people
        })), /*#__PURE__*/React.createElement("td", null, formatDate(item.dueDate)));
      case 'Assets':
        const assignee = people.find(p => p.id === item.assignedTo);
        const assetJob = data.jobs.find(j => j.id === item.jobId);
        return /*#__PURE__*/React.createElement("tr", {
          key: item.id,
          onClick: () => onRowClick(item)
        }, /*#__PURE__*/React.createElement("td", {
          className: "cell-name"
        }, item.name), /*#__PURE__*/React.createElement("td", {
          className: "cell-type"
        }, item.type), /*#__PURE__*/React.createElement("td", null, assetJob ? assetJob.jobNumber : ''), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(StatusBadge, {
          status: item.status,
          onChange: canEditStatus ? s => handleStatusChange(item, s) : null,
          statuses: STATUSES_WITH_DONE
        })), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(PersonAvatar, {
          person: assignee,
          showName: true
        })), /*#__PURE__*/React.createElement("td", null, formatDate(item.dueDate)));
    }
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "table-view"
  }, /*#__PURE__*/React.createElement("table", null, /*#__PURE__*/React.createElement("thead", null, renderColumns()), /*#__PURE__*/React.createElement("tbody", null, sortedItems.map(item => renderRow(item)), sortedItems.length === 0 && /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", {
    colSpan: "6",
    className: "empty-row"
  }, "No items found")))));
};

// ============================================================================
// WORK KANBAN VIEW
// ============================================================================

const WorkKanbanView = ({
  items,
  type,
  data,
  dispatch,
  people,
  projects,
  currentUser,
  onCardClick
}) => {
  const [draggedItem, setDraggedItem] = useState(null);
  const statuses = type === 'Jobs' || type === 'Assets' ? STATUSES_WITH_DONE : STATUSES;
  const getItemsByStatus = status => {
    return items.filter(item => item.status === status).sort((a, b) => (a.order || 0) - (b.order || 0));
  };
  const handleDragStart = (e, item) => {
    setDraggedItem(item);
    e.dataTransfer.effectAllowed = 'move';
  };
  const handleDragEnd = () => {
    setDraggedItem(null);
  };
  const handleDrop = newStatus => {
    if (!draggedItem) return;
    if (!canUserEditItem(currentUser, draggedItem, type, 'status')) {
      setDraggedItem(null);
      return;
    }
    const updatedItem = {
      ...draggedItem,
      status: newStatus
    };
    switch (type) {
      case 'Projects':
        dispatch({
          type: 'UPDATE_PROJECT',
          payload: updatedItem
        });
        break;
      case 'Jobs':
        const jobsInStatus = items.filter(j => j.status === newStatus && j.id !== draggedItem.id);
        dispatch({
          type: 'UPDATE_JOB',
          payload: {
            ...updatedItem,
            order: jobsInStatus.length
          }
        });
        break;
      case 'Assets':
        const assetsInStatus = items.filter(a => a.status === newStatus && a.id !== draggedItem.id);
        dispatch({
          type: 'UPDATE_ASSET',
          payload: {
            ...updatedItem,
            order: assetsInStatus.length
          }
        });
        break;
    }
    setDraggedItem(null);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "kanban-board"
  }, statuses.map(status => /*#__PURE__*/React.createElement(KanbanColumn, {
    key: status,
    status: status,
    items: getItemsByStatus(status),
    type: type,
    people: people,
    projects: projects,
    jobs: data.jobs,
    onDrop: handleDrop,
    onDragStart: handleDragStart,
    onDragEnd: handleDragEnd,
    onCardClick: onCardClick
  })));
};
// ============================================================================
// UNIFIED REVIEWS TAB (Phase 2 - Merges Job Review + Client Review)
// ============================================================================
//
// Consolidates the previous Job Review and Client Review tabs into a single
// "Reviews" view with Internal/Client sub-tabs.
//
// Features:
// - Sub-tabs: Internal (for CD/ECD/PM/COO), Client (for Client role)
// - Role-based visibility - only shows tabs the user can access
// - Unified UI with consistent styling
// ============================================================================

const ReviewsTab = ({
  data,
  dispatch,
  currentUser
}) => {
  // Determine which sub-tabs the user can access
  const canAccessInternal = canAccessJobReview(currentUser);
  const canAccessClient = canAccessClientReview(currentUser);

  // Set default active sub-tab based on access
  const getDefaultSubTab = () => {
    if (canAccessInternal) return 'internal';
    if (canAccessClient) return 'client';
    return 'internal'; // Fallback
  };
  const [activeSubTab, setActiveSubTab] = useState(getDefaultSubTab());

  // If user has no access to either, show restricted message
  if (!canAccessInternal && !canAccessClient) {
    return /*#__PURE__*/React.createElement("div", {
      className: "reviews-restricted"
    }, /*#__PURE__*/React.createElement("span", {
      className: "empty-icon"
    }, "\u25C9"), /*#__PURE__*/React.createElement("h2", null, "Access Restricted"), /*#__PURE__*/React.createElement("p", null, "Reviews are available to CDs, ECDs, Producers, PMs, COO, and Clients."));
  }

  // Get counts for sub-tabs
  const getInternalCount = () => {
    return data.jobs.filter(job => {
      if (job.status !== 'In Progress') return false;
      const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id);
      const copyComplete = jobTasks.find(t => t.templateId === 'copy')?.status === 'Done';
      const mediaComplete = jobTasks.find(t => t.templateId === 'media')?.status === 'Done';
      return copyComplete && mediaComplete;
    }).length;
  };
  const getClientCount = () => {
    if (!currentUser) return 0;
    return data.jobs.filter(job => job.status === 'In Review' && job.assignments?.Client === currentUser.id).length;
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "reviews-tab"
  }, canAccessInternal && canAccessClient && /*#__PURE__*/React.createElement("div", {
    className: "reviews-sub-nav"
  }, /*#__PURE__*/React.createElement("button", {
    className: `review-sub-tab ${activeSubTab === 'internal' ? 'active' : ''}`,
    onClick: () => setActiveSubTab('internal')
  }, "Internal Review", /*#__PURE__*/React.createElement("span", {
    className: "sub-tab-count"
  }, getInternalCount())), /*#__PURE__*/React.createElement("button", {
    className: `review-sub-tab ${activeSubTab === 'client' ? 'active' : ''}`,
    onClick: () => setActiveSubTab('client')
  }, "Client Review", /*#__PURE__*/React.createElement("span", {
    className: "sub-tab-count"
  }, getClientCount()))), (!canAccessInternal || !canAccessClient) && /*#__PURE__*/React.createElement("div", {
    className: "reviews-single-header"
  }, /*#__PURE__*/React.createElement("h1", null, canAccessInternal ? 'Internal Review' : 'Client Review')), activeSubTab === 'internal' && canAccessInternal && /*#__PURE__*/React.createElement(JobReviewTab, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser
  }), activeSubTab === 'client' && canAccessClient && /*#__PURE__*/React.createElement(ClientReviewTab, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser
  }));
};
// ============================================================================
// MORE MENU COMPONENT (Phase 2 - Overflow Menu for Secondary Items)
// ============================================================================
//
// A dropdown menu that contains secondary navigation items:
// - People directory
// - Wiki
// - Operations dashboard (role-restricted)
// - Settings (future)
//
// Features:
// - Dropdown behavior with click-outside-to-close
// - Role-based visibility for Operations
// - Active state indicator
// ============================================================================

const MoreMenu = ({
  data,
  currentUser,
  activeItem,
  onItemSelect
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef(null);

  // Close menu when clicking outside
  useEffect(() => {
    const handleClickOutside = event => {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Menu items with role-based visibility
  const menuItems = [{
    id: 'People',
    label: 'People',
    icon: '○',
    count: data.people.length,
    visible: true
  }, {
    id: 'Wiki',
    label: 'Wiki',
    icon: '▤',
    count: (data.wikiPages || []).length,
    visible: true
  }, {
    id: 'Operations',
    label: 'Operations',
    icon: '▦',
    count: null,
    visible: canAccessOperations(currentUser)
  }];
  const visibleItems = menuItems.filter(item => item.visible);
  const isActiveInMore = visibleItems.some(item => item.id === activeItem);
  const handleItemClick = itemId => {
    onItemSelect(itemId);
    setIsOpen(false);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "more-menu",
    ref: menuRef
  }, /*#__PURE__*/React.createElement("button", {
    className: `more-menu-trigger ${isOpen ? 'open' : ''} ${isActiveInMore ? 'active' : ''}`,
    onClick: () => setIsOpen(!isOpen)
  }, /*#__PURE__*/React.createElement("span", {
    className: "more-icon"
  }, "\u22EF"), "More", /*#__PURE__*/React.createElement("span", {
    className: "more-arrow"
  }, isOpen ? '▲' : '▼')), isOpen && /*#__PURE__*/React.createElement("div", {
    className: "more-menu-dropdown"
  }, visibleItems.map(item => /*#__PURE__*/React.createElement("button", {
    key: item.id,
    className: `more-menu-item ${activeItem === item.id ? 'active' : ''}`,
    onClick: () => handleItemClick(item.id)
  }, /*#__PURE__*/React.createElement("span", {
    className: "menu-item-icon"
  }, item.icon), /*#__PURE__*/React.createElement("span", {
    className: "menu-item-label"
  }, item.label), item.count !== null && /*#__PURE__*/React.createElement("span", {
    className: "menu-item-count"
  }, item.count)))));
};
// ============================================================================
// APP ENTRY POINT (Phase 2 - Consolidated 5-Tab Navigation)
// ============================================================================
//
// Navigation structure:
// 1. DASHBOARD - Role-based landing page
// 2. WORK - Unified Projects/Jobs/Assets view
// 3. CAPACITY - Traffic/PM workload view
// 4. REVIEWS - Unified Internal/Client review
// 5. MORE (dropdown) - People, Wiki, Operations
// ============================================================================

const {
  useRef
} = React;
function App() {
  const [data, dispatch] = useReducer(dataReducer, null, loadFromStorage);
  const [activeTab, setActiveTab] = useState('Dashboard');
  const [viewMode, setViewMode] = useState('table'); // 'table' or 'kanban'
  const [searchQuery, setSearchQuery] = useState('');
  const [briefModalOpen, setBriefModalOpen] = useState(false);
  const [addPersonModalOpen, setAddPersonModalOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);
  const [detailPanelOpen, setDetailPanelOpen] = useState(false);
  const [storageError, setStorageError] = useState(null);

  // Check for storage errors after data changes
  useEffect(() => {
    const error = getLastStorageError();
    if (error) {
      setStorageError(error);
      clearLastStorageError();
    }
  }, [data]);

  // Current user state - defaults to first user, stored in localStorage
  const [currentUserId, setCurrentUserId] = useState(() => {
    const saved = localStorage.getItem('slash301pm_currentUser');
    return saved || data?.people?.[0]?.id || 'p1';
  });

  // Get current user object
  const currentUser = data?.people?.find(p => p.id === currentUserId) || data?.people?.[0];

  // Save current user to localStorage when changed
  const handleUserChange = userId => {
    setCurrentUserId(userId);
    localStorage.setItem('slash301pm_currentUser', userId);
  };

  // Get permissions for current user
  const userPermissions = getUserPermissions(currentUser);
  const handleRowClick = item => {
    setSelectedItem(item);
    setDetailPanelOpen(true);
  };
  const handleExport = () => {
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], {
      type: 'application/json'
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `slash301pm-export-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
  };
  const handleImport = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = e => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = event => {
        try {
          const imported = JSON.parse(event.target.result);
          if (imported.people && imported.projects && imported.jobs && imported.assets) {
            dispatch({
              type: 'SET_DATA',
              payload: imported
            });
            alert('Data imported successfully!');
          } else {
            alert('Invalid file format');
          }
        } catch (err) {
          alert('Failed to parse file: ' + err.message);
        }
      };
      reader.readAsText(file);
    };
    input.click();
  };

  // Check if user can access Reviews tab
  const canAccessReviews = canAccessJobReview(currentUser) || canAccessClientReview(currentUser);

  // Get tab counts for badges
  const getTabCount = tab => {
    switch (tab) {
      case 'Dashboard':
        return null;
      // No count for dashboard
      case 'Work':
        return data.jobs.length;
      case 'Capacity':
        return data.people.filter(p => p.role !== 'Client').length;
      case 'Reviews':
        // Count internal review items + client review items
        const internalCount = data.jobs.filter(j => {
          if (j.status !== 'In Progress') return false;
          const jobTasks = (data.tasks || []).filter(t => t.jobId === j.id);
          const copyComplete = jobTasks.find(t => t.templateId === 'copy')?.status === 'Done';
          const mediaComplete = jobTasks.find(t => t.templateId === 'media')?.status === 'Done';
          return copyComplete && mediaComplete;
        }).length;
        const clientCount = data.jobs.filter(j => j.status === 'In Review' && j.assignments?.Client === currentUser?.id).length;
        return internalCount + clientCount;
      default:
        return null;
    }
  };

  // Filter main tabs based on permissions
  const getVisibleMainTabs = () => {
    return MAIN_TABS.filter(tab => {
      // Hide Reviews if user has no access
      if (tab === 'Reviews' && !canAccessReviews) {
        return false;
      }
      // Hide Capacity from non-manager roles (optional - can be removed if you want all to see)
      // if (tab === 'Capacity' && !['COO', 'PM', 'Traffic', 'ECD', 'CD', 'Producer'].includes(currentUser?.role)) {
      //   return false;
      // }
      return true;
    });
  };

  // Check if active tab is in "More" menu
  const isMoreMenuActive = MORE_MENU_ITEMS.includes(activeTab);

  // Determine the detail panel type based on active tab
  const getDetailPanelType = () => {
    if (activeTab === 'Work') {
      // For Work tab, we need to determine the actual item type
      if (selectedItem?.jobNumber) return 'Jobs';
      if (selectedItem?.projectId && !selectedItem?.jobNumber) return 'Assets';
      if (selectedItem?.client) return 'Projects';
      return 'Jobs';
    }
    return activeTab;
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "app-container"
  }, /*#__PURE__*/React.createElement(StorageWarning, {
    error: storageError,
    onDismiss: () => setStorageError(null),
    onExport: handleExport
  }), /*#__PURE__*/React.createElement("header", {
    className: "app-header"
  }, /*#__PURE__*/React.createElement("div", {
    className: "header-left"
  }, /*#__PURE__*/React.createElement("h1", {
    className: "app-title"
  }, "Slash 301 PM")), /*#__PURE__*/React.createElement("div", {
    className: "header-center"
  }, /*#__PURE__*/React.createElement("div", {
    className: "search-box"
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Search...",
    value: searchQuery,
    onChange: e => setSearchQuery(e.target.value)
  }))), /*#__PURE__*/React.createElement("div", {
    className: "header-right"
  }, activeTab === 'Work' && /*#__PURE__*/React.createElement("div", {
    className: "view-toggle"
  }, /*#__PURE__*/React.createElement("button", {
    className: viewMode === 'table' ? 'active' : '',
    onClick: () => setViewMode('table'),
    title: "Table View"
  }, /*#__PURE__*/React.createElement("span", {
    className: "icon-table"
  }, "\u2630")), /*#__PURE__*/React.createElement("button", {
    className: viewMode === 'kanban' ? 'active' : '',
    onClick: () => setViewMode('kanban'),
    title: "Kanban View"
  }, /*#__PURE__*/React.createElement("span", {
    className: "icon-kanban"
  }, "\u25A6"))), userPermissions.level === 'superadmin' && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-icon",
    onClick: handleExport,
    title: "Export JSON"
  }, "\u2193"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-icon",
    onClick: handleImport,
    title: "Import JSON"
  }, "\u2191")), activeTab === 'People' && userPermissions.canEditPeople && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: () => setAddPersonModalOpen(true)
  }, "+ Add Person"), /*#__PURE__*/React.createElement(UserSelector, {
    people: data.people,
    currentUser: currentUser,
    onUserChange: handleUserChange
  }))), /*#__PURE__*/React.createElement("nav", {
    className: "tab-bar"
  }, getVisibleMainTabs().map(tab => /*#__PURE__*/React.createElement("button", {
    key: tab,
    className: `tab ${activeTab === tab ? 'active' : ''}`,
    onClick: () => setActiveTab(tab)
  }, /*#__PURE__*/React.createElement("span", {
    className: "tab-icon"
  }, TAB_ICONS[tab]), tab, getTabCount(tab) !== null && /*#__PURE__*/React.createElement("span", {
    className: "tab-count"
  }, getTabCount(tab)))), /*#__PURE__*/React.createElement(MoreMenu, {
    data: data,
    currentUser: currentUser,
    activeItem: isMoreMenuActive ? activeTab : null,
    onItemSelect: setActiveTab
  })), /*#__PURE__*/React.createElement("main", {
    className: "main-content"
  }, activeTab === 'Dashboard' && /*#__PURE__*/React.createElement(Dashboard, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser,
    onNewBrief: () => setBriefModalOpen(true),
    onCheckCapacity: () => setActiveTab('Capacity'),
    onJobClick: handleRowClick
  }), activeTab === 'Work' && /*#__PURE__*/React.createElement(WorkTab, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser,
    viewMode: viewMode,
    searchQuery: searchQuery,
    onRowClick: handleRowClick,
    onNewBrief: () => setBriefModalOpen(true)
  }), activeTab === 'Capacity' && /*#__PURE__*/React.createElement(CapacityTab, {
    data: data,
    dispatch: dispatch,
    people: data.people
  }), activeTab === 'Reviews' && /*#__PURE__*/React.createElement(ReviewsTab, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser
  }), activeTab === 'People' && /*#__PURE__*/React.createElement(TableView, {
    tab: "People",
    data: data,
    dispatch: dispatch,
    people: data.people,
    projects: data.projects,
    onRowClick: handleRowClick,
    searchQuery: searchQuery,
    currentUser: currentUser
  }), activeTab === 'Wiki' && /*#__PURE__*/React.createElement(WikiTab, {
    data: data,
    dispatch: dispatch,
    people: data.people
  }), activeTab === 'Operations' && /*#__PURE__*/React.createElement(OperationsDashboard, {
    data: data,
    currentUser: currentUser
  })), /*#__PURE__*/React.createElement(BriefModal, {
    isOpen: briefModalOpen,
    onClose: () => setBriefModalOpen(false),
    data: data,
    dispatch: dispatch
  }), /*#__PURE__*/React.createElement(AddPersonModal, {
    isOpen: addPersonModalOpen,
    onClose: () => setAddPersonModalOpen(false),
    dispatch: dispatch
  }), /*#__PURE__*/React.createElement(DetailPanel, {
    item: selectedItem,
    type: getDetailPanelType(),
    isOpen: detailPanelOpen,
    onClose: () => {
      setDetailPanelOpen(false);
      setSelectedItem(null);
    },
    data: data,
    dispatch: dispatch,
    people: data.people,
    currentUser: currentUser
  }));
}

// Render the app
ReactDOM.createRoot(document.getElementById('root')).render(/*#__PURE__*/React.createElement(App, null));
