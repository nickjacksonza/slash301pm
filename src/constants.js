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
const ROLES = ['COO', 'PM', 'Traffic', 'ECD', 'CD', 'Copywriter', 'Designer', 'QA', 'Client', 'Producer', 'AM', 'Developer', 'SEO', 'Social'];

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
  // BUG-12 fix: Explicit permissions for CD, ECD, and Client roles
  'ECD': {
    level: 'manager',
    canViewAll: true,
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: true,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: true,
    canDeleteAny: false,
    canEditOwnStatus: true
  },
  'CD': {
    level: 'user',
    canViewAll: true,
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: false,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: true
  },
  'Client': {
    level: 'user',
    canViewAll: false,
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: false,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: false // Clients are read-only on assigned items
  },
  // DB-02 fix: Explicit Producer permissions (was falling to default with canViewAll: false)
  'Producer': {
    level: 'manager',
    canViewAll: true,
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: true,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: true
  },
  // Phase 3.4: Account Manager -- manager-level, similar to PM
  'AM': {
    level: 'manager',
    canViewAll: true,
    canEditProjects: true,
    canCreateProjects: false,
    canEditJobs: true,
    canCreateJobs: true,
    canEditAssets: false,
    canAssignRoles: true,
    canEditPeople: false,
    canEditWiki: true,
    canDeleteAny: false,
    canEditOwnStatus: true
  },
  // Phase 3.4: Developer -- creative-level
  'Developer': {
    level: 'user',
    canViewAll: false,
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: false,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: true
  },
  // Phase 3.4: SEO -- creative-level
  'SEO': {
    level: 'user',
    canViewAll: false,
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: false,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: true
  },
  // Phase 3.4: Social -- creative-level
  'Social': {
    level: 'user',
    canViewAll: false,
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: false,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: true
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
  if (!item) return false; // BUG-01 fix: guard against null item
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
