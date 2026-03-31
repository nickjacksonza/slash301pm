const { useState, useEffect, useReducer, useCallback, useMemo } = React;

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
  'Inbox', 'Today', 'This Week', 'On Hold', 'In Review',
  'Approved (Internal)', 'Approved (External)', 'Scheduled', 'Live', 'Archived'
];

const STATUSES_WITH_DONE = STATUSES; // Done is now included

// Traffic light status colors
// Red family (light/mid/dark): Needs attention, blocked, urgent
// Orange family (light/mid/dark): In progress, waiting, pending
// Green family (light/mid/dark): Completed, approved, live
const STATUS_COLORS = {
  // RED - Needs attention / Urgent / Blocked
  'Inbox': '#e57373',      // light red - new items need triage
  'Today': '#ef5350',      // mid red - urgent, due today
  'On Hold': '#c62828',    // dark red - blocked/stopped
  'Backlog': '#ef9a9a',    // lightest red - backlog items

  // ORANGE - In progress / Waiting / Pending review
  'To Do': '#ffb74d',      // light orange - ready to start
  'This Week': '#ffa726',  // mid orange - due this week
  'In Progress': '#ff9800', // mid orange - actively working
  'Waiting': '#f57c00',    // darker orange - waiting on something
  'In Review': '#e65100',  // dark orange - pending review

  // GREEN - Completed / Approved / Live
  'Done': '#81c784',       // light green - task complete
  'Approved (Internal)': '#66bb6a', // mid green - internal sign-off
  'Approved (External)': '#4caf50', // mid-dark green - client approved
  'Scheduled': '#43a047',  // dark green - ready to go live
  'Live': '#2e7d32',       // darkest green - published/live

  // NEUTRAL - Archived
  'Archived': '#9e9e9e'    // grey - no longer active
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
    canEditAssets: false,  // Can only add via Brief, not edit directly
    canAssignRoles: true,
    canEditPeople: false,
    canEditWiki: true,
    canDeleteAny: false
  },
  // Default permissions for other roles
  'default': {
    level: 'user',
    canViewAll: false,      // Can only see assigned items
    canEditProjects: false,
    canCreateProjects: false,
    canEditJobs: false,
    canCreateJobs: false,
    canEditAssets: false,
    canAssignRoles: false,
    canEditPeople: false,
    canEditWiki: false,
    canDeleteAny: false,
    canEditOwnStatus: true  // Can edit status on assigned items
  }
};

// Get highest permission level for a user based on their roles
const getUserPermissions = (user) => {
  if (!user || !user.roles || user.roles.length === 0) {
    return ROLE_PERMISSIONS['default'];
  }

  // Priority order: COO > Traffic > PM > default
  const priorityOrder = ['COO', 'Traffic', 'PM'];

  for (const role of priorityOrder) {
    if (user.roles.includes(role)) {
      return ROLE_PERMISSIONS[role];
    }
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
      return data.jobs.some(job =>
        job.projectId === item.id &&
        Object.values(job.assignments || {}).includes(user.id)
      );
    case 'Jobs':
      return Object.values(item.assignments || {}).includes(user.id);
    case 'Assets':
      return item.assignedTo === user.id ||
        (data.jobs.find(j => j.id === item.jobId) &&
         Object.values(data.jobs.find(j => j.id === item.jobId).assignments || {}).includes(user.id));
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
        return permissions.canEditJobs ||
          (permissions.canEditOwnStatus && Object.values(item.assignments || {}).includes(user.id));
      }
      return permissions.canEditJobs;
    case 'Assets':
      if (editType === 'status') {
        return permissions.canEditAssets ||
          (permissions.canEditOwnStatus && item.assignedTo === user.id);
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
      return permissions.canCreateJobs; // Creating assets is part of creating jobs/briefs
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
const canUserAssignRoles = (user) => {
  const permissions = getUserPermissions(user);
  return permissions.canAssignRoles;
};

const ASSET_TEMPLATES = [
  { id: 'social-static', name: 'Social Post (Static)', type: 'image' },
  { id: 'social-video', name: 'Social Post (Video)', type: 'video' },
  { id: 'social-carousel', name: 'Social Carousel', type: 'image' },
  { id: 'social-story', name: 'Story/Reel', type: 'video' },
  { id: 'banner-display', name: 'Display Banner', type: 'image' },
  { id: 'banner-animated', name: 'Animated Banner', type: 'video' },
  { id: 'email-template', name: 'Email Template', type: 'document' },
  { id: 'email-copy', name: 'Email Copy', type: 'copy' },
  { id: 'landing-page', name: 'Landing Page', type: 'document' },
  { id: 'video-edit-short', name: 'Video Edit (Short)', type: 'video' },
  { id: 'video-edit-long', name: 'Video Edit (Long)', type: 'video' },
  { id: 'print-ad', name: 'Print Ad', type: 'document' },
  { id: 'ooh-billboard', name: 'OOH/Billboard', type: 'image' },
  { id: 'radio-spot', name: 'Radio Spot', type: 'audio' },
  { id: 'podcast-ad', name: 'Podcast Ad', type: 'audio' },
  { id: 'blog-post', name: 'Blog Post', type: 'copy' },
  { id: 'press-release', name: 'Press Release', type: 'copy' },
  { id: 'presentation', name: 'Presentation', type: 'document' }
];

// Task Templates - checklist items for jobs
// Copy and Media are REQUIRED for every job
const TASK_TEMPLATES = [
  {
    id: 'copy',
    name: 'Copy',
    description: 'Written content for the asset',
    assignedRole: 'Copywriter',
    required: true,
    hasCharacterCount: true,
    hasFileUpload: false
  },
  {
    id: 'media',
    name: 'Media',
    description: 'Visual content (image or video)',
    assignedRole: 'Designer',
    required: true,
    hasCharacterCount: false,
    hasFileUpload: true,
    fileTypes: ['image', 'video']
  },
  {
    id: 'review-internal',
    name: 'Internal Review',
    description: 'Review by CD/ECD',
    assignedRole: 'CD',
    required: false,
    hasCharacterCount: false,
    hasFileUpload: false
  },
  {
    id: 'review-client',
    name: 'Client Review',
    description: 'Review by client stakeholder',
    assignedRole: 'Client',
    required: false,
    hasCharacterCount: false,
    hasFileUpload: false
  },
  {
    id: 'qa',
    name: 'QA Check',
    description: 'Quality assurance review',
    assignedRole: 'QA',
    required: false,
    hasCharacterCount: false,
    hasFileUpload: false
  }
];

const TABS = ['Projects', 'Jobs', 'Assets', 'People', 'Wiki', 'Capacity', 'Operations', 'Job Review', 'Client Review'];

// Sparse icons for tabs (Unicode characters for simplicity)
const TAB_ICONS = {
  'Projects': '◈',
  'Jobs': '◎',
  'Assets': '◇',
  'People': '○',
  'Wiki': '▤',
  'Capacity': '◐',
  'Operations': '▦',
  'Job Review': '◇',
  'Client Review': '◉'
};

// Wiki page types
const WIKI_TYPES = ['client', 'campaign', 'award', 'report', 'general'];

// Wiki Templates
const WIKI_TEMPLATES = [
  {
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
  },
  {
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
  },
  {
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
  },
  {
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
  }
];

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

const generateId = () => Math.random().toString(36).substr(2, 9);

const generateJobNumber = (projectCode, jobCount) => {
  return `${projectCode}-${String(jobCount).padStart(3, '0')}`;
};

const getProjectCode = (projectName) => {
  return projectName.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 4);
};

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Capacity calculation utilities
const DAILY_CAPACITY = 7;   // hours
const WEEKLY_CAPACITY = 35; // hours

const calculateJobHours = (job, assets) => {
  const jobAssets = assets.filter(a => a.jobId === job.id);
  return Math.max(jobAssets.length * 0.25, 0.25);
};

const getPersonJobs = (personId, jobs) => {
  return jobs.filter(job =>
    Object.values(job.assignments || {}).includes(personId)
  );
};

const categorizeJobsByCapacity = (jobs, assets) => {
  const today = [];
  const thisWeek = [];
  const overflow = [];

  let todayHours = 0;
  let weekHours = 0;

  // Sort by status priority: Today first, then This Week, then others
  const statusPriority = { 'Today': 1, 'In Progress': 2, 'This Week': 3 };
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
        today.push({ ...job, hours });
        todayHours += hours;
        weekHours += hours;
      } else {
        overflow.push({ ...job, hours });
      }
    } else if (isThisWeek) {
      if (weekHours + hours <= WEEKLY_CAPACITY) {
        thisWeek.push({ ...job, hours });
        weekHours += hours;
      } else {
        overflow.push({ ...job, hours });
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
  const { today, thisWeek, todayHours, weekHours } = categorizedJobs;
  const todayDate = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

  const getJobDetails = (job) => {
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
// INITIAL/MOCK DATA
// ============================================================================

const createInitialData = () => ({
  people: [
    { id: 'p0', name: 'Rachel Adams', email: 'rachel@agency.com', roles: ['COO'], color: '#dc2626' },
    { id: 'p1', name: 'Sarah Chen', email: 'sarah@agency.com', roles: ['PM', 'Traffic'], color: '#3b82f6' },
    { id: 'p2', name: 'James Wilson', email: 'james@agency.com', roles: ['ECD'], color: '#8b5cf6' },
    { id: 'p3', name: 'Maria Garcia', email: 'maria@agency.com', roles: ['CD', 'Designer'], color: '#ec4899' },
    { id: 'p4', name: 'Alex Thompson', email: 'alex@agency.com', roles: ['Copywriter'], color: '#f97316' },
    { id: 'p5', name: 'Kim Lee', email: 'kim@agency.com', roles: ['Designer'], color: '#14b8a6' },
    { id: 'p6', name: 'Jordan Blake', email: 'jordan@agency.com', roles: ['QA'], color: '#6366f1' },
    { id: 'p7', name: 'Chris Martin', email: 'chris@client.com', roles: ['Client'], color: '#84cc16' },
    { id: 'p8', name: 'Taylor Swift', email: 'taylor@agency.com', roles: ['Producer'], color: '#f43f5e' },
    { id: 'p9', name: 'Morgan Davis', email: 'morgan@agency.com', roles: ['Traffic', 'PM'], color: '#0ea5e9' }
  ],
  projects: [
    { id: 'proj1', name: 'Summer Campaign', client: 'Acme Corp', description: 'Q3 summer product launch', status: 'In Progress', jobCount: 3, createdAt: '2024-01-15', clientColors: { primary: '#d4847a', secondary: '#c9a86c' } },
    { id: 'proj2', name: 'Brand Refresh', client: 'TechStart', description: 'Complete brand identity overhaul', status: 'In Review', jobCount: 2, createdAt: '2024-01-20', clientColors: { primary: '#7a9bc4', secondary: '#a890c4' } }
  ],
  jobs: [
    { id: 'job1', jobNumber: 'SUMM-001', name: 'Hero Video', description: 'Main campaign hero video', projectId: 'proj1', status: 'In Progress', assignments: { PM: 'p1', Traffic: 'p9', ECD: 'p2', CD: 'p3', Copywriter: 'p4', Designer: 'p5', QA: 'p6', Client: 'p7', Producer: 'p8' }, dueDate: '2024-02-15', order: 0, createdAt: '2024-01-16' },
    { id: 'job2', jobNumber: 'SUMM-002', name: 'Social Package', description: 'Social media content suite', projectId: 'proj1', status: 'Today', assignments: { PM: 'p1', Traffic: 'p1', ECD: 'p2', CD: 'p3', Copywriter: 'p4', Designer: 'p5', QA: 'p6', Client: 'p7', Producer: 'p8' }, dueDate: '2024-02-10', order: 1, createdAt: '2024-01-17' },
    { id: 'job3', jobNumber: 'SUMM-003', name: 'Email Campaign', description: 'Drip email sequence', projectId: 'proj1', status: 'Inbox', assignments: { PM: 'p1', Traffic: 'p9', ECD: 'p2', CD: 'p3', Copywriter: 'p4', Designer: 'p5', QA: 'p6', Client: 'p7', Producer: 'p8' }, dueDate: '2024-02-20', order: 2, createdAt: '2024-01-18' },
    { id: 'job4', jobNumber: 'BRAN-001', name: 'Logo Design', description: 'New logo concepts', projectId: 'proj2', status: 'In Review', assignments: { PM: 'p9', Traffic: 'p1', ECD: 'p2', CD: 'p3', Copywriter: 'p4', Designer: 'p5', QA: 'p6', Client: 'p7', Producer: 'p8' }, dueDate: '2024-02-05', order: 0, createdAt: '2024-01-21' },
    { id: 'job5', jobNumber: 'BRAN-002', name: 'Brand Guidelines', description: 'Complete brand book', projectId: 'proj2', status: 'On Hold', assignments: { PM: 'p9', Traffic: 'p1', ECD: 'p2', CD: 'p3', Copywriter: 'p4', Designer: 'p5', QA: 'p6', Client: 'p7', Producer: 'p8' }, dueDate: '2024-02-28', order: 1, createdAt: '2024-01-22' }
  ],
  assets: [
    { id: 'a1', name: 'Hero Video 60s', type: 'video', templateId: 'video-edit-long', jobId: 'job1', projectId: 'proj1', status: 'In Progress', assignedTo: 'p8', dueDate: '2024-02-12', order: 0 },
    { id: 'a2', name: 'Hero Video 30s', type: 'video', templateId: 'video-edit-short', jobId: 'job1', projectId: 'proj1', status: 'Inbox', assignedTo: 'p8', dueDate: '2024-02-14', order: 1 },
    { id: 'a3', name: 'Instagram Post 1', type: 'image', templateId: 'social-static', jobId: 'job2', projectId: 'proj1', status: 'Today', assignedTo: 'p5', dueDate: '2024-02-08', order: 0 },
    { id: 'a4', name: 'Instagram Post 2', type: 'image', templateId: 'social-static', jobId: 'job2', projectId: 'proj1', status: 'Today', assignedTo: 'p5', dueDate: '2024-02-08', order: 1 },
    { id: 'a5', name: 'Instagram Story', type: 'video', templateId: 'social-story', jobId: 'job2', projectId: 'proj1', status: 'Inbox', assignedTo: 'p5', dueDate: '2024-02-09', order: 2 },
    { id: 'a6', name: 'Welcome Email', type: 'document', templateId: 'email-template', jobId: 'job3', projectId: 'proj1', status: 'Inbox', assignedTo: 'p4', dueDate: '2024-02-18', order: 0 },
    { id: 'a7', name: 'Logo Concept A', type: 'image', templateId: 'presentation', jobId: 'job4', projectId: 'proj2', status: 'In Review', assignedTo: 'p3', dueDate: '2024-02-03', order: 0 },
    { id: 'a8', name: 'Logo Concept B', type: 'image', templateId: 'presentation', jobId: 'job4', projectId: 'proj2', status: 'In Review', assignedTo: 'p5', dueDate: '2024-02-03', order: 1 }
  ],
  tasks: [
    // Job 1 - Hero Video tasks
    { id: 't1', templateId: 'copy', jobId: 'job1', status: 'Done', assignedTo: 'p4', characterCount: 245, content: 'Summer is here! Experience the thrill...', fileUrl: null, fileType: null, completedAt: '2024-01-20', order: 0 },
    { id: 't2', templateId: 'media', jobId: 'job1', status: 'In Progress', assignedTo: 'p5', characterCount: null, content: null, fileUrl: 'hero-video-draft.mp4', fileType: 'video', completedAt: null, order: 1 },
    // Job 2 - Social Package tasks
    { id: 't3', templateId: 'copy', jobId: 'job2', status: 'In Progress', assignedTo: 'p4', characterCount: 180, content: 'Get ready for summer vibes...', fileUrl: null, fileType: null, completedAt: null, order: 0 },
    { id: 't4', templateId: 'media', jobId: 'job2', status: 'Inbox', assignedTo: 'p5', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },
    // Job 3 - Email Campaign tasks
    { id: 't5', templateId: 'copy', jobId: 'job3', status: 'Inbox', assignedTo: 'p4', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
    { id: 't6', templateId: 'media', jobId: 'job3', status: 'Inbox', assignedTo: 'p5', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },
    // Job 4 - Logo Design tasks
    { id: 't7', templateId: 'copy', jobId: 'job4', status: 'Done', assignedTo: 'p4', characterCount: 50, content: 'TechStart - Innovation Forward', fileUrl: null, fileType: null, completedAt: '2024-01-25', order: 0 },
    { id: 't8', templateId: 'media', jobId: 'job4', status: 'In Review', assignedTo: 'p5', characterCount: null, content: null, fileUrl: 'logo-concepts.png', fileType: 'image', completedAt: null, order: 1 },
    // Job 5 - Brand Guidelines tasks
    { id: 't9', templateId: 'copy', jobId: 'job5', status: 'On Hold', assignedTo: 'p4', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
    { id: 't10', templateId: 'media', jobId: 'job5', status: 'On Hold', assignedTo: 'p5', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 }
  ],
  wikiPages: [
    {
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
    },
    {
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
    },
    {
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
    },
    {
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
    }
  ],
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
      return JSON.parse(saved);
    }
  } catch (e) {
    console.error('Failed to load from storage:', e);
  }
  return createInitialData();
};

const saveToStorage = (data) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  } catch (e) {
    console.error('Failed to save to storage:', e);
  }
};

const dataReducer = (state, action) => {
  let newState;
  switch (action.type) {
    case 'SET_DATA':
      newState = action.payload;
      break;
    case 'ADD_PROJECT':
      newState = { ...state, projects: [...state.projects, action.payload] };
      break;
    case 'UPDATE_PROJECT':
      newState = { ...state, projects: state.projects.map(p => p.id === action.payload.id ? action.payload : p) };
      break;
    case 'DELETE_PROJECT':
      newState = { ...state, projects: state.projects.filter(p => p.id !== action.payload) };
      break;
    case 'ADD_JOB':
      newState = { ...state, jobs: [...state.jobs, action.payload] };
      break;
    case 'UPDATE_JOB':
      newState = { ...state, jobs: state.jobs.map(j => j.id === action.payload.id ? action.payload : j) };
      break;
    case 'DELETE_JOB':
      newState = { ...state, jobs: state.jobs.filter(j => j.id !== action.payload) };
      break;
    case 'REORDER_JOBS':
      newState = { ...state, jobs: action.payload };
      break;
    case 'ADD_ASSET':
      newState = { ...state, assets: [...state.assets, action.payload] };
      break;
    case 'UPDATE_ASSET':
      newState = { ...state, assets: state.assets.map(a => a.id === action.payload.id ? action.payload : a) };
      break;
    case 'DELETE_ASSET':
      newState = { ...state, assets: state.assets.filter(a => a.id !== action.payload) };
      break;
    case 'REORDER_ASSETS':
      newState = { ...state, assets: action.payload };
      break;
    case 'ADD_PERSON':
      newState = { ...state, people: [...state.people, action.payload] };
      break;
    case 'UPDATE_PERSON':
      newState = { ...state, people: state.people.map(p => p.id === action.payload.id ? action.payload : p) };
      break;
    case 'DELETE_PERSON':
      newState = { ...state, people: state.people.filter(p => p.id !== action.payload) };
      break;
    // Wiki actions
    case 'ADD_WIKI_PAGE':
      newState = { ...state, wikiPages: [...(state.wikiPages || []), action.payload] };
      break;
    case 'UPDATE_WIKI_PAGE':
      newState = { ...state, wikiPages: (state.wikiPages || []).map(p => p.id === action.payload.id ? action.payload : p) };
      break;
    case 'DELETE_WIKI_PAGE':
      newState = { ...state, wikiPages: (state.wikiPages || []).filter(p => p.id !== action.payload) };
      break;
    // Scheduled email actions
    case 'ADD_SCHEDULED_EMAIL':
      newState = { ...state, scheduledEmails: [...(state.scheduledEmails || []), action.payload] };
      break;
    case 'DELETE_SCHEDULED_EMAIL':
      newState = { ...state, scheduledEmails: (state.scheduledEmails || []).filter(e => e.id !== action.payload) };
      break;
    // Task actions
    case 'ADD_TASK':
      newState = { ...state, tasks: [...(state.tasks || []), action.payload] };
      break;
    case 'UPDATE_TASK':
      newState = { ...state, tasks: (state.tasks || []).map(t => t.id === action.payload.id ? action.payload : t) };
      break;
    case 'DELETE_TASK':
      newState = { ...state, tasks: (state.tasks || []).filter(t => t.id !== action.payload) };
      break;
    default:
      return state;
  }
  saveToStorage(newState);
  return newState;
};

// ============================================================================
// SMALL COMPONENTS
// ============================================================================

// Get status type for traffic light styling
const getStatusType = (status) => {
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
  red: { label: 'Needs Attention', statuses: ['Backlog', 'Inbox', 'Today', 'On Hold'] },
  orange: { label: 'In Progress', statuses: ['To Do', 'This Week', 'In Progress', 'Waiting', 'In Review'] },
  green: { label: 'Completed', statuses: ['Done', 'Approved (Internal)', 'Approved (External)', 'Scheduled', 'Live'] },
  grey: { label: 'Other', statuses: ['Archived'] }
};

const StatusBadge = ({ status, onChange, statuses = STATUSES }) => {
  const [isOpen, setIsOpen] = useState(false);
  const color = STATUS_COLORS[status] || '#6b7280';
  const statusType = getStatusType(status);

  // Filter available statuses based on what's passed in
  const getGroupedStatuses = () => {
    const groups = [];
    Object.entries(STATUS_GROUPS).forEach(([type, group]) => {
      const availableStatuses = group.statuses.filter(s => statuses.includes(s));
      if (availableStatuses.length > 0) {
        groups.push({ type, label: group.label, statuses: availableStatuses });
      }
    });
    return groups;
  };

  return (
    <div className="status-badge-wrapper">
      <span
        className="status-badge"
        data-status-type={statusType}
        style={{ backgroundColor: color + '15', color: color }}
        onClick={() => onChange && setIsOpen(!isOpen)}
      >
        {status}
      </span>
      {isOpen && onChange && (
        <div className="status-dropdown">
          {getGroupedStatuses().map(group => (
            <div key={group.type} className="status-dropdown-section">
              <div className="status-dropdown-label">{group.label}</div>
              {group.statuses.map(s => (
                <div
                  key={s}
                  className="status-option"
                  style={{ color: STATUS_COLORS[s] }}
                  onClick={() => { onChange(s); setIsOpen(false); }}
                >
                  <span className="status-dot" style={{ backgroundColor: STATUS_COLORS[s] }}></span>
                  {s}
                </div>
              ))}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

const PersonAvatar = ({ person, size = 'small', showName = false }) => {
  if (!person) return <span className="avatar-placeholder">?</span>;
  const initials = person.name.split(' ').map(n => n[0]).join('').slice(0, 2);
  return (
    <div className={`person-avatar ${size}`} title={person.name}>
      <span className="avatar-circle" style={{ backgroundColor: person.color }}>
        {initials}
      </span>
      {showName && <span className="avatar-name">{person.name}</span>}
    </div>
  );
};

const PersonAvatarGroup = ({ personIds, people, max = 3 }) => {
  const persons = personIds.map(id => people.find(p => p.id === id)).filter(Boolean);
  const visible = persons.slice(0, max);
  const extra = persons.length - max;

  return (
    <div className="avatar-group">
      {visible.map(p => <PersonAvatar key={p.id} person={p} />)}
      {extra > 0 && <span className="avatar-extra">+{extra}</span>}
    </div>
  );
};

// ============================================================================
// TABLE VIEW COMPONENT
// ============================================================================

const TableView = ({ tab, data, dispatch, people, projects, onRowClick, searchQuery, currentUser }) => {
  const [sortField, setSortField] = useState(null);
  const [sortDir, setSortDir] = useState('asc');

  const handleSort = (field) => {
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
      case 'Projects': items = data.projects; break;
      case 'Jobs': items = data.jobs; break;
      case 'Assets': items = data.assets; break;
      case 'People': items = data.people; break;
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
        dispatch({ type: 'UPDATE_PROJECT', payload: { ...item, status: newStatus } });
        break;
      case 'Jobs':
        dispatch({ type: 'UPDATE_JOB', payload: { ...item, status: newStatus } });
        break;
      case 'Assets':
        dispatch({ type: 'UPDATE_ASSET', payload: { ...item, status: newStatus } });
        break;
    }
  };

  const items = getItems();

  const getProjectName = (projectId) => {
    const proj = projects.find(p => p.id === projectId);
    return proj ? proj.name : '';
  };

  const renderColumns = () => {
    switch (tab) {
      case 'Projects':
        return (
          <tr>
            <th onClick={() => handleSort('name')}>Name {sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th onClick={() => handleSort('client')}>Client {sortField === 'client' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th onClick={() => handleSort('status')}>Status {sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th>Jobs</th>
            <th onClick={() => handleSort('createdAt')}>Created {sortField === 'createdAt' && (sortDir === 'asc' ? '↑' : '↓')}</th>
          </tr>
        );
      case 'Jobs':
        return (
          <tr>
            <th onClick={() => handleSort('jobNumber')}>Job # {sortField === 'jobNumber' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th onClick={() => handleSort('name')}>Name {sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th>Project</th>
            <th onClick={() => handleSort('status')}>Status {sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th>Team</th>
            <th onClick={() => handleSort('dueDate')}>Due {sortField === 'dueDate' && (sortDir === 'asc' ? '↑' : '↓')}</th>
          </tr>
        );
      case 'Assets':
        return (
          <tr>
            <th onClick={() => handleSort('name')}>Name {sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th onClick={() => handleSort('type')}>Type {sortField === 'type' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th>Job</th>
            <th onClick={() => handleSort('status')}>Status {sortField === 'status' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th>Assigned</th>
            <th onClick={() => handleSort('dueDate')}>Due {sortField === 'dueDate' && (sortDir === 'asc' ? '↑' : '↓')}</th>
          </tr>
        );
      case 'People':
        return (
          <tr>
            <th onClick={() => handleSort('name')}>Name {sortField === 'name' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th onClick={() => handleSort('email')}>Email {sortField === 'email' && (sortDir === 'asc' ? '↑' : '↓')}</th>
            <th>Roles</th>
            <th>Active Jobs</th>
          </tr>
        );
    }
  };

  const renderRow = (item) => {
    const canEditStatus = canUserEditItem(currentUser, item, tab, 'status');

    switch (tab) {
      case 'Projects':
        const jobCount = data.jobs.filter(j => j.projectId === item.id).length;
        return (
          <tr key={item.id} onClick={() => onRowClick(item)}>
            <td className="cell-name">{item.name}</td>
            <td>{item.client}</td>
            <td><StatusBadge status={item.status} onChange={canEditStatus ? (s) => handleStatusChange(item, s) : null} /></td>
            <td>{jobCount}</td>
            <td>{formatDate(item.createdAt)}</td>
          </tr>
        );
      case 'Jobs':
        const job = item;
        const assignedIds = Object.values(job.assignments || {}).filter(Boolean);
        return (
          <tr key={item.id} onClick={() => onRowClick(item)}>
            <td className="cell-jobnum">{job.jobNumber}</td>
            <td className="cell-name">{job.name}</td>
            <td>{getProjectName(job.projectId)}</td>
            <td><StatusBadge status={job.status} onChange={canEditStatus ? (s) => handleStatusChange(item, s) : null} statuses={STATUSES_WITH_DONE} /></td>
            <td><PersonAvatarGroup personIds={assignedIds} people={people} /></td>
            <td>{formatDate(job.dueDate)}</td>
          </tr>
        );
      case 'Assets':
        const asset = item;
        const assignee = people.find(p => p.id === asset.assignedTo);
        const assetJob = data.jobs.find(j => j.id === asset.jobId);
        return (
          <tr key={item.id} onClick={() => onRowClick(item)}>
            <td className="cell-name">{asset.name}</td>
            <td className="cell-type">{asset.type}</td>
            <td>{assetJob ? assetJob.jobNumber : ''}</td>
            <td><StatusBadge status={asset.status} onChange={canEditStatus ? (s) => handleStatusChange(item, s) : null} statuses={STATUSES_WITH_DONE} /></td>
            <td><PersonAvatar person={assignee} showName /></td>
            <td>{formatDate(asset.dueDate)}</td>
          </tr>
        );
      case 'People':
        const person = item;
        const activeJobs = data.jobs.filter(j => Object.values(j.assignments || {}).includes(person.id)).length;
        return (
          <tr key={item.id} onClick={() => onRowClick(item)}>
            <td><PersonAvatar person={person} showName size="medium" /></td>
            <td>{person.email}</td>
            <td className="cell-roles">{person.roles.join(', ')}</td>
            <td>{activeJobs}</td>
          </tr>
        );
    }
  };

  return (
    <div className="table-view">
      <table>
        <thead>{renderColumns()}</thead>
        <tbody>
          {items.map(item => renderRow(item))}
          {items.length === 0 && (
            <tr><td colSpan="6" className="empty-row">◌ No items found</td></tr>
          )}
        </tbody>
      </table>
    </div>
  );
};

// ============================================================================
// KANBAN COMPONENTS
// ============================================================================

const KanbanCard = ({ item, type, people, projects, jobs, onDragStart, onDragEnd, onClick }) => {
  const getProjectName = (projectId) => {
    const proj = projects.find(p => p.id === projectId);
    return proj ? proj.name : '';
  };

  const getJobNumber = (jobId) => {
    const job = jobs.find(j => j.id === jobId);
    return job ? job.jobNumber : '';
  };

  const renderContent = () => {
    switch (type) {
      case 'Projects':
        return (
          <>
            <div className="card-title">{item.name}</div>
            <div className="card-meta">{item.client}</div>
          </>
        );
      case 'Jobs':
        const assignedIds = Object.values(item.assignments || {}).filter(Boolean);
        return (
          <>
            <div className="card-jobnum">{item.jobNumber}</div>
            <div className="card-title">{item.name}</div>
            <div className="card-meta">{getProjectName(item.projectId)}</div>
            <div className="card-footer">
              <PersonAvatarGroup personIds={assignedIds} people={people} max={4} />
              {item.dueDate && <span className="card-due">{formatDate(item.dueDate)}</span>}
            </div>
          </>
        );
      case 'Assets':
        const assignee = people.find(p => p.id === item.assignedTo);
        return (
          <>
            <div className="card-type">{item.type}</div>
            <div className="card-title">{item.name}</div>
            <div className="card-meta">{getJobNumber(item.jobId)}</div>
            <div className="card-footer">
              {assignee && <PersonAvatar person={assignee} />}
              {item.dueDate && <span className="card-due">{formatDate(item.dueDate)}</span>}
            </div>
          </>
        );
      case 'People':
        return (
          <>
            <PersonAvatar person={item} size="medium" />
            <div className="card-title">{item.name}</div>
            <div className="card-meta">{item.roles.join(', ')}</div>
          </>
        );
    }
  };

  return (
    <div
      className="kanban-card"
      draggable
      onDragStart={(e) => onDragStart(e, item)}
      onDragEnd={onDragEnd}
      onClick={() => onClick(item)}
    >
      {renderContent()}
    </div>
  );
};

const KanbanColumn = ({ status, items, type, people, projects, jobs, onDrop, onDragStart, onDragEnd, onCardClick }) => {
  const [isDragOver, setIsDragOver] = useState(false);

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragOver(true);
  };

  const handleDragLeave = () => {
    setIsDragOver(false);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragOver(false);
    onDrop(status, e);
  };

  return (
    <div
      className={`kanban-column ${isDragOver ? 'drag-over' : ''}`}
      onDragOver={handleDragOver}
      onDragLeave={handleDragLeave}
      onDrop={handleDrop}
    >
      <div className="column-header">
        <span className="column-status" style={{ color: STATUS_COLORS[status] }}>{status}</span>
        <span className="column-count">{items.length}</span>
      </div>
      <div className="column-cards">
        {items.map(item => (
          <KanbanCard
            key={item.id}
            item={item}
            type={type}
            people={people}
            projects={projects}
            jobs={jobs}
            onDragStart={onDragStart}
            onDragEnd={onDragEnd}
            onClick={onCardClick}
          />
        ))}
      </div>
    </div>
  );
};

const KanbanBoard = ({ tab, data, dispatch, people, projects, onCardClick, searchQuery, currentUser }) => {
  const [draggedItem, setDraggedItem] = useState(null);

  const statuses = (tab === 'Jobs' || tab === 'Assets') ? STATUSES_WITH_DONE : STATUSES;

  const getItems = () => {
    let items = [];
    switch (tab) {
      case 'Projects': items = data.projects; break;
      case 'Jobs': items = data.jobs; break;
      case 'Assets': items = data.assets; break;
      case 'People': items = data.people; break;
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

  const getItemsByStatus = (status) => {
    if (tab === 'People') {
      // For people, we'll use their primary role as a pseudo-status
      return items.filter(p => p.roles[0] === status);
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

    const updatedItem = { ...draggedItem, status: newStatus };

    switch (tab) {
      case 'Projects':
        dispatch({ type: 'UPDATE_PROJECT', payload: updatedItem });
        break;
      case 'Jobs':
        // Reorder within status
        const jobsInStatus = items.filter(j => j.status === newStatus && j.id !== draggedItem.id);
        const newOrder = jobsInStatus.length;
        dispatch({ type: 'UPDATE_JOB', payload: { ...updatedItem, order: newOrder } });
        break;
      case 'Assets':
        const assetsInStatus = items.filter(a => a.status === newStatus && a.id !== draggedItem.id);
        const assetOrder = assetsInStatus.length;
        dispatch({ type: 'UPDATE_ASSET', payload: { ...updatedItem, order: assetOrder } });
        break;
    }

    setDraggedItem(null);
  };

  // For People tab, use roles as columns instead of statuses
  if (tab === 'People') {
    return (
      <div className="kanban-board">
        {ROLES.map(role => (
          <KanbanColumn
            key={role}
            status={role}
            items={items.filter(p => p.roles.includes(role))}
            type={tab}
            people={people}
            projects={projects}
            jobs={data.jobs}
            onDrop={() => {}}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onCardClick={onCardClick}
          />
        ))}
      </div>
    );
  }

  return (
    <div className="kanban-board">
      {statuses.map(status => (
        <KanbanColumn
          key={status}
          status={status}
          items={getItemsByStatus(status)}
          type={tab}
          people={people}
          projects={projects}
          jobs={data.jobs}
          onDrop={handleDrop}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
          onCardClick={onCardClick}
        />
      ))}
    </div>
  );
};

// ============================================================================
// BRIEF MODAL COMPONENT
// ============================================================================

const BriefModal = ({ isOpen, onClose, data, dispatch }) => {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [projectId, setProjectId] = useState('');
  const [newProjectName, setNewProjectName] = useState('');
  const [newProjectClient, setNewProjectClient] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [selectedAssets, setSelectedAssets] = useState([]);
  const [assignments, setAssignments] = useState({});

  useEffect(() => {
    // Initialize assignments with empty values for each role
    const initial = {};
    ROLES.forEach(role => { initial[role] = ''; });
    setAssignments(initial);
  }, [isOpen]);

  const handleAddAsset = (templateId) => {
    const template = ASSET_TEMPLATES.find(t => t.id === templateId);
    if (template) {
      setSelectedAssets([...selectedAssets, { ...template, tempId: generateId(), quantity: 1 }]);
    }
  };

  const handleRemoveAsset = (tempId) => {
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
      dispatch({ type: 'ADD_PROJECT', payload: project });
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
      payload: { ...currentProject, jobCount: (currentProject.jobCount || 0) + 1 }
    });

    // Create the job
    const job = {
      id: generateId(),
      jobNumber,
      name,
      description,
      projectId: targetProjectId,
      status: 'Inbox',
      assignments,
      dueDate,
      order: data.jobs.filter(j => j.projectId === targetProjectId).length,
      createdAt: new Date().toISOString()
    };
    dispatch({ type: 'ADD_JOB', payload: job });

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
      dispatch({ type: 'ADD_TASK', payload: task });
    });

    // Create assets from selected templates
    selectedAssets.forEach((template, index) => {
      const asset = {
        id: generateId(),
        name: `${name} - ${template.name}`,
        type: template.type,
        templateId: template.id,
        jobId: job.id,
        projectId: targetProjectId,
        status: 'Inbox',
        assignedTo: '',
        dueDate,
        order: index
      };
      dispatch({ type: 'ADD_ASSET', payload: asset });
    });

    // Reset form and close
    setName('');
    setDescription('');
    setProjectId('');
    setNewProjectName('');
    setNewProjectClient('');
    setDueDate('');
    setSelectedAssets([]);
    setAssignments({});
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content brief-modal" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>New Brief</h2>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        <div className="modal-body">
          <div className="form-section">
            <h3>Job Details</h3>
            <div className="form-group">
              <label>Job Name *</label>
              <input type="text" value={name} onChange={e => setName(e.target.value)} placeholder="e.g., Summer Campaign Video" />
            </div>
            <div className="form-group">
              <label>Description</label>
              <textarea value={description} onChange={e => setDescription(e.target.value)} placeholder="Brief description..." />
            </div>
            <div className="form-group">
              <label>Due Date</label>
              <input type="date" value={dueDate} onChange={e => setDueDate(e.target.value)} />
            </div>
          </div>

          <div className="form-section">
            <h3>Project</h3>
            <div className="form-group">
              <label>Select Existing Project</label>
              <select value={projectId} onChange={e => setProjectId(e.target.value)}>
                <option value="">-- Create New Project --</option>
                {data.projects.map(p => (
                  <option key={p.id} value={p.id}>{p.name} ({p.client})</option>
                ))}
              </select>
            </div>
            {!projectId && (
              <>
                <div className="form-group">
                  <label>New Project Name</label>
                  <input type="text" value={newProjectName} onChange={e => setNewProjectName(e.target.value)} placeholder="e.g., Summer Campaign 2024" />
                </div>
                <div className="form-group">
                  <label>Client</label>
                  <input type="text" value={newProjectClient} onChange={e => setNewProjectClient(e.target.value)} placeholder="e.g., Acme Corp" />
                </div>
              </>
            )}
          </div>

          <div className="form-section">
            <h3>Required Assets</h3>
            <div className="form-group">
              <label>Add Asset</label>
              <select onChange={e => { if(e.target.value) handleAddAsset(e.target.value); e.target.value = ''; }}>
                <option value="">-- Select Asset Type --</option>
                {ASSET_TEMPLATES.map(t => (
                  <option key={t.id} value={t.id}>{t.name} ({t.type})</option>
                ))}
              </select>
            </div>
            <div className="asset-list">
              {selectedAssets.map(asset => (
                <div key={asset.tempId} className="asset-item">
                  <span className="asset-type-badge">{asset.type}</span>
                  <span>{asset.name}</span>
                  <button className="btn-remove" onClick={() => handleRemoveAsset(asset.tempId)}>&times;</button>
                </div>
              ))}
              {selectedAssets.length === 0 && <p className="empty-text">No assets added yet</p>}
            </div>
          </div>

          <div className="form-section">
            <h3>Team Assignment</h3>
            <div className="role-grid">
              {ROLES.map(role => (
                <div key={role} className="form-group role-select">
                  <label>{role}</label>
                  <select value={assignments[role] || ''} onChange={e => setAssignments({...assignments, [role]: e.target.value})}>
                    <option value="">-- Select --</option>
                    {data.people.filter(p => p.roles.includes(role)).map(p => (
                      <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                  </select>
                </div>
              ))}
            </div>
          </div>
        </div>
        <div className="modal-footer">
          <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" onClick={handleSubmit}>Create Brief</button>
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// TASK LIST COMPONENT
// ============================================================================

const TaskList = ({ jobId, data, dispatch, people, currentUser }) => {
  const tasks = (data.tasks || []).filter(t => t.jobId === jobId);
  const [expandedTask, setExpandedTask] = useState(null);

  // Check if current user can edit tasks (based on assignment or permissions)
  const canEditTask = (task) => {
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
    dispatch({ type: 'UPDATE_TASK', payload: updates });
  };

  const handleTaskUpdate = (task, updates) => {
    dispatch({ type: 'UPDATE_TASK', payload: { ...task, ...updates } });
  };

  const getTaskTemplate = (templateId) => {
    return TASK_TEMPLATES.find(t => t.id === templateId);
  };

  const getAssignedPerson = (assignedTo) => {
    return people.find(p => p.id === assignedTo);
  };

  if (tasks.length === 0) {
    return (
      <div className="task-list-empty">
        <span className="empty-icon">◎</span>
        <p>No tasks for this job</p>
      </div>
    );
  }

  return (
    <div className="task-list">
      {tasks.sort((a, b) => a.order - b.order).map(task => {
        const template = getTaskTemplate(task.templateId);
        const assignedPerson = getAssignedPerson(task.assignedTo);
        const isExpanded = expandedTask === task.id;
        const canEdit = canEditTask(task);

        return (
          <div key={task.id} className={`task-item ${task.status === 'Done' ? 'completed' : ''}`}>
            <div className="task-header" onClick={() => setExpandedTask(isExpanded ? null : task.id)}>
              <div className="task-checkbox">
                <input
                  type="checkbox"
                  checked={task.status === 'Done'}
                  onChange={(e) => {
                    e.stopPropagation();
                    if (canEdit) {
                      handleTaskStatusChange(task, e.target.checked ? 'Done' : 'In Progress');
                    }
                  }}
                  disabled={!canEdit}
                />
              </div>
              <div className="task-info">
                <span className="task-name">{template?.name || task.templateId}</span>
                {assignedPerson && (
                  <span className="task-assignee">
                    <PersonAvatar person={assignedPerson} size="small" />
                  </span>
                )}
              </div>
              <div className="task-meta">
                {template?.hasCharacterCount && task.characterCount && (
                  <span className="task-char-count">{task.characterCount} chars</span>
                )}
                {template?.hasFileUpload && task.fileUrl && (
                  <span className="task-file-indicator">
                    {task.fileType === 'video' ? '🎬' : '🖼️'} {task.fileUrl}
                  </span>
                )}
                <StatusBadge
                  status={task.status}
                  onChange={canEdit ? (s) => handleTaskStatusChange(task, s) : null}
                  statuses={STATUSES_WITH_DONE}
                />
              </div>
              <span className="task-expand-icon">{isExpanded ? '▼' : '▶'}</span>
            </div>

            {isExpanded && (
              <div className="task-details">
                <p className="task-description">{template?.description}</p>

                {template?.hasCharacterCount && (
                  <div className="task-field">
                    <label>Copy Content</label>
                    <textarea
                      value={task.content || ''}
                      onChange={(e) => {
                        if (canEdit) {
                          handleTaskUpdate(task, {
                            content: e.target.value,
                            characterCount: e.target.value.length
                          });
                        }
                      }}
                      placeholder="Enter copy text..."
                      disabled={!canEdit}
                    />
                    <span className="char-counter">{task.content?.length || 0} characters</span>
                  </div>
                )}

                {template?.hasFileUpload && (
                  <div className="task-field">
                    <label>Media File ({template.fileTypes?.join(' / ')})</label>
                    <div className="task-file-input">
                      <input
                        type="text"
                        value={task.fileUrl || ''}
                        onChange={(e) => {
                          if (canEdit) {
                            handleTaskUpdate(task, { fileUrl: e.target.value });
                          }
                        }}
                        placeholder="Enter file URL or path..."
                        disabled={!canEdit}
                      />
                      <select
                        value={task.fileType || ''}
                        onChange={(e) => {
                          if (canEdit) {
                            handleTaskUpdate(task, { fileType: e.target.value });
                          }
                        }}
                        disabled={!canEdit}
                      >
                        <option value="">Type</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                      </select>
                    </div>
                  </div>
                )}

                <div className="task-field">
                  <label>Assigned To</label>
                  <select
                    value={task.assignedTo || ''}
                    onChange={(e) => handleTaskUpdate(task, { assignedTo: e.target.value })}
                    disabled={!getUserPermissions(currentUser).canAssignRoles}
                  >
                    <option value="">-- Select --</option>
                    {people.filter(p => p.roles.includes(template?.assignedRole)).map(p => (
                      <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                  </select>
                </div>

                {task.completedAt && (
                  <p className="task-completed-date">
                    Completed: {new Date(task.completedAt).toLocaleDateString()}
                  </p>
                )}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
};

// ============================================================================
// DETAIL PANEL COMPONENT
// ============================================================================

const DetailPanel = ({ item, type, isOpen, onClose, data, dispatch, people, currentUser }) => {
  const [editedItem, setEditedItem] = useState(null);

  useEffect(() => {
    if (item) {
      setEditedItem({ ...item });
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
        dispatch({ type: 'UPDATE_PROJECT', payload: editedItem });
        break;
      case 'Jobs':
        dispatch({ type: 'UPDATE_JOB', payload: editedItem });
        break;
      case 'Assets':
        dispatch({ type: 'UPDATE_ASSET', payload: editedItem });
        break;
      case 'People':
        dispatch({ type: 'UPDATE_PERSON', payload: editedItem });
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
        dispatch({ type: 'DELETE_PROJECT', payload: item.id });
        break;
      case 'Jobs':
        dispatch({ type: 'DELETE_JOB', payload: item.id });
        break;
      case 'Assets':
        dispatch({ type: 'DELETE_ASSET', payload: item.id });
        break;
      case 'People':
        dispatch({ type: 'DELETE_PERSON', payload: item.id });
        break;
    }
    onClose();
  };

  if (!isOpen || !editedItem) return null;

  const statuses = (type === 'Jobs' || type === 'Assets') ? STATUSES_WITH_DONE : STATUSES;

  const renderFields = () => {
    switch (type) {
      case 'Projects':
        return (
          <>
            <div className="form-group">
              <label>Name</label>
              <input type="text" value={editedItem.name} onChange={e => setEditedItem({...editedItem, name: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Client</label>
              <input type="text" value={editedItem.client || ''} onChange={e => setEditedItem({...editedItem, client: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Description</label>
              <textarea value={editedItem.description || ''} onChange={e => setEditedItem({...editedItem, description: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Status</label>
              <select value={editedItem.status} onChange={e => setEditedItem({...editedItem, status: e.target.value})} disabled={!canEditFull && !canEditStatus}>
                {statuses.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            {!canEditFull && !canEditStatus && (
              <p className="permission-notice">You have view-only access to this item.</p>
            )}
          </>
        );
      case 'Jobs':
        return (
          <>
            <div className="form-group">
              <label>Job Number</label>
              <input type="text" value={editedItem.jobNumber} disabled />
            </div>
            <div className="form-group">
              <label>Name</label>
              <input type="text" value={editedItem.name} onChange={e => setEditedItem({...editedItem, name: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Description</label>
              <textarea value={editedItem.description || ''} onChange={e => setEditedItem({...editedItem, description: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Status</label>
              <select value={editedItem.status} onChange={e => setEditedItem({...editedItem, status: e.target.value})} disabled={!canEditFull && !canEditStatus}>
                {statuses.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div className="form-group">
              <label>Due Date</label>
              <input type="date" value={editedItem.dueDate || ''} onChange={e => setEditedItem({...editedItem, dueDate: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-section">
              <h4>Team Assignment</h4>
              <div className="role-grid">
                {ROLES.map(role => (
                  <div key={role} className="form-group role-select">
                    <label>{role}</label>
                    <select
                      value={editedItem.assignments?.[role] || ''}
                      onChange={e => setEditedItem({
                        ...editedItem,
                        assignments: {...editedItem.assignments, [role]: e.target.value}
                      })}
                      disabled={!canAssignRoles}
                    >
                      <option value="">-- Select --</option>
                      {people.filter(p => p.roles.includes(role)).map(p => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                      ))}
                    </select>
                  </div>
                ))}
              </div>
            </div>
            <div className="form-section">
              <h4>Tasks ({(data.tasks || []).filter(t => t.jobId === editedItem.id).length})</h4>
              <TaskList
                jobId={editedItem.id}
                data={data}
                dispatch={dispatch}
                people={people}
                currentUser={currentUser}
              />
            </div>
            <div className="form-section">
              <h4>Assets ({data.assets.filter(a => a.jobId === editedItem.id).length})</h4>
              <div className="asset-checklist">
                {data.assets.filter(a => a.jobId === editedItem.id).map(asset => (
                  <div key={asset.id} className="checklist-item">
                    <StatusBadge status={asset.status} />
                    <span>{asset.name}</span>
                  </div>
                ))}
              </div>
            </div>
            {!canEditFull && !canEditStatus && (
              <p className="permission-notice">You have view-only access to this item.</p>
            )}
          </>
        );
      case 'Assets':
        return (
          <>
            <div className="form-group">
              <label>Name</label>
              <input type="text" value={editedItem.name} onChange={e => setEditedItem({...editedItem, name: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Type</label>
              <input type="text" value={editedItem.type} disabled />
            </div>
            <div className="form-group">
              <label>Status</label>
              <select value={editedItem.status} onChange={e => setEditedItem({...editedItem, status: e.target.value})} disabled={!canEditFull && !canEditStatus}>
                {statuses.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div className="form-group">
              <label>Assigned To</label>
              <select value={editedItem.assignedTo || ''} onChange={e => setEditedItem({...editedItem, assignedTo: e.target.value})} disabled={!canEditFull}>
                <option value="">-- Select --</option>
                {people.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </div>
            <div className="form-group">
              <label>Due Date</label>
              <input type="date" value={editedItem.dueDate || ''} onChange={e => setEditedItem({...editedItem, dueDate: e.target.value})} disabled={!canEditFull} />
            </div>
            {!canEditFull && !canEditStatus && (
              <p className="permission-notice">You have view-only access to this item.</p>
            )}
          </>
        );
      case 'People':
        return (
          <>
            <div className="form-group">
              <label>Name</label>
              <input type="text" value={editedItem.name} onChange={e => setEditedItem({...editedItem, name: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Email</label>
              <input type="email" value={editedItem.email} onChange={e => setEditedItem({...editedItem, email: e.target.value})} disabled={!canEditFull} />
            </div>
            <div className="form-group">
              <label>Roles</label>
              <div className="checkbox-group">
                {ROLES.map(role => (
                  <label key={role} className="checkbox-label">
                    <input
                      type="checkbox"
                      checked={editedItem.roles?.includes(role)}
                      onChange={e => {
                        const newRoles = e.target.checked
                          ? [...(editedItem.roles || []), role]
                          : (editedItem.roles || []).filter(r => r !== role);
                        setEditedItem({...editedItem, roles: newRoles});
                      }}
                      disabled={!canEditFull}
                    />
                    {role}
                  </label>
                ))}
              </div>
            </div>
            <div className="form-group">
              <label>Color</label>
              <input type="color" value={editedItem.color || '#3b82f6'} onChange={e => setEditedItem({...editedItem, color: e.target.value})} disabled={!canEditFull} />
            </div>
            {!canEditFull && (
              <p className="permission-notice">You do not have permission to edit people.</p>
            )}
          </>
        );
    }
  };

  return (
    <div className={`detail-panel ${isOpen ? 'open' : ''}`}>
      <div className="panel-header">
        <h2>{type.slice(0, -1)} Details</h2>
        <button className="panel-close" onClick={onClose}>&times;</button>
      </div>
      <div className="panel-body">
        {renderFields()}
      </div>
      <div className="panel-footer">
        {canDelete && (
          <button className="btn btn-danger" onClick={handleDelete}>Delete</button>
        )}
        {!canDelete && <div></div>}
        <div>
          <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
          {(canEditFull || canEditStatus) && (
            <button className="btn btn-primary" onClick={handleSave}>Save</button>
          )}
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// WIKI COMPONENTS
// ============================================================================

// WYSIWYG Editor Component
const WysiwygEditor = ({ content, onChange, onSave }) => {
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

  return (
    <div className="wysiwyg-editor">
      <div className="editor-toolbar">
        <button type="button" onClick={() => execCommand('bold')} title="Bold"><b>B</b></button>
        <button type="button" onClick={() => execCommand('italic')} title="Italic"><i>I</i></button>
        <button type="button" onClick={() => execCommand('underline')} title="Underline"><u>U</u></button>
        <button type="button" onClick={() => execCommand('strikeThrough')} title="Strikethrough"><s>S</s></button>
        <span className="toolbar-divider"></span>
        <button type="button" onClick={() => execCommand('formatBlock', 'h1')} title="Heading 1">H1</button>
        <button type="button" onClick={() => execCommand('formatBlock', 'h2')} title="Heading 2">H2</button>
        <button type="button" onClick={() => execCommand('formatBlock', 'h3')} title="Heading 3">H3</button>
        <button type="button" onClick={() => execCommand('formatBlock', 'p')} title="Paragraph">P</button>
        <span className="toolbar-divider"></span>
        <button type="button" onClick={() => execCommand('insertUnorderedList')} title="Bullet List">&#8226;</button>
        <button type="button" onClick={() => execCommand('insertOrderedList')} title="Numbered List">1.</button>
        <span className="toolbar-divider"></span>
        <button type="button" onClick={() => setShowLinkModal(true)} title="Insert Link">&#128279;</button>
        <button type="button" onClick={() => execCommand('formatBlock', 'blockquote')} title="Quote">&#8220;</button>
        <button type="button" onClick={() => execCommand('formatBlock', 'pre')} title="Code">&lt;/&gt;</button>
        <span className="toolbar-divider"></span>
        <button type="button" onClick={() => execCommand('removeFormat')} title="Clear Formatting">&#10006;</button>
      </div>
      <div
        ref={editorRef}
        className="editor-content"
        contentEditable
        onInput={handleInput}
        onBlur={handleInput}
        suppressContentEditableWarning
      />
      {showLinkModal && (
        <div className="link-modal">
          <input
            type="url"
            placeholder="Enter URL..."
            value={linkUrl}
            onChange={e => setLinkUrl(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && handleInsertLink()}
          />
          <button onClick={handleInsertLink}>Insert</button>
          <button onClick={() => setShowLinkModal(false)}>Cancel</button>
        </div>
      )}
    </div>
  );
};

// Wiki Tree Node Component
const WikiTreeNode = ({ page, pages, level = 0, selectedId, onSelect, onToggle, expanded }) => {
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

  return (
    <div className="wiki-tree-node">
      <div
        className={`tree-item ${isSelected ? 'selected' : ''}`}
        style={{ paddingLeft: `${level * 16 + 8}px` }}
        onClick={() => onSelect(page)}
      >
        {hasChildren && (
          <span className="tree-toggle" onClick={e => { e.stopPropagation(); onToggle(page.id); }}>
            {isExpanded ? '&#9660;' : '&#9654;'}
          </span>
        )}
        {!hasChildren && <span className="tree-spacer"></span>}
        <span className="tree-icon" dangerouslySetInnerHTML={{ __html: typeIcons[page.type] || typeIcons.general }} />
        <span className="tree-title">{page.title}</span>
      </div>
      {hasChildren && isExpanded && (
        <div className="tree-children">
          {children.map(child => (
            <WikiTreeNode
              key={child.id}
              page={child}
              pages={pages}
              level={level + 1}
              selectedId={selectedId}
              onSelect={onSelect}
              onToggle={onToggle}
              expanded={expanded}
            />
          ))}
        </div>
      )}
    </div>
  );
};

// Wiki Sidebar Component
const WikiSidebar = ({ pages, selectedId, onSelect, onNewPage, searchQuery, onSearchChange }) => {
  const [expanded, setExpanded] = useState({});

  const toggleExpand = (id) => {
    setExpanded(prev => ({ ...prev, [id]: !prev[id] }));
  };

  // Get root pages (no parent)
  const rootPages = pages
    .filter(p => !p.parentId)
    .sort((a, b) => a.order - b.order);

  // Group by type
  const clientPages = rootPages.filter(p => p.type === 'client');
  const awardPages = rootPages.filter(p => p.type === 'award');
  const reportPages = rootPages.filter(p => p.type === 'report');
  const generalPages = rootPages.filter(p => p.type === 'general');

  // Filter by search
  const filteredPages = searchQuery
    ? pages.filter(p => p.title.toLowerCase().includes(searchQuery.toLowerCase()))
    : null;

  return (
    <div className="wiki-sidebar">
      <div className="sidebar-search">
        <input
          type="text"
          placeholder="Search wiki..."
          value={searchQuery}
          onChange={e => onSearchChange(e.target.value)}
        />
      </div>

      {filteredPages ? (
        <div className="sidebar-section">
          <div className="section-title">Search Results</div>
          {filteredPages.map(page => (
            <div
              key={page.id}
              className={`tree-item ${selectedId === page.id ? 'selected' : ''}`}
              onClick={() => onSelect(page)}
            >
              <span className="tree-title">{page.title}</span>
            </div>
          ))}
          {filteredPages.length === 0 && <div className="empty-text">No results found</div>}
        </div>
      ) : (
        <>
          {clientPages.length > 0 && (
            <div className="sidebar-section">
              <div className="section-title">Clients</div>
              {clientPages.map(page => (
                <WikiTreeNode
                  key={page.id}
                  page={page}
                  pages={pages}
                  selectedId={selectedId}
                  onSelect={onSelect}
                  onToggle={toggleExpand}
                  expanded={expanded}
                />
              ))}
            </div>
          )}

          {awardPages.length > 0 && (
            <div className="sidebar-section">
              <div className="section-title">Award Submissions</div>
              {awardPages.map(page => (
                <WikiTreeNode
                  key={page.id}
                  page={page}
                  pages={pages}
                  selectedId={selectedId}
                  onSelect={onSelect}
                  onToggle={toggleExpand}
                  expanded={expanded}
                />
              ))}
            </div>
          )}

          {reportPages.length > 0 && (
            <div className="sidebar-section">
              <div className="section-title">Reports</div>
              {reportPages.map(page => (
                <WikiTreeNode
                  key={page.id}
                  page={page}
                  pages={pages}
                  selectedId={selectedId}
                  onSelect={onSelect}
                  onToggle={toggleExpand}
                  expanded={expanded}
                />
              ))}
            </div>
          )}

          {generalPages.length > 0 && (
            <div className="sidebar-section">
              <div className="section-title">General</div>
              {generalPages.map(page => (
                <WikiTreeNode
                  key={page.id}
                  page={page}
                  pages={pages}
                  selectedId={selectedId}
                  onSelect={onSelect}
                  onToggle={toggleExpand}
                  expanded={expanded}
                />
              ))}
            </div>
          )}
        </>
      )}

      <button className="btn btn-primary sidebar-new-btn" onClick={onNewPage}>
        + New Page
      </button>
    </div>
  );
};

// Wiki Breadcrumb Component
const WikiBreadcrumb = ({ page, pages, onNavigate }) => {
  const getBreadcrumbPath = (currentPage) => {
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

  return (
    <div className="wiki-breadcrumb">
      <span className="breadcrumb-item" onClick={() => onNavigate(null)}>Wiki</span>
      {path.map((p, i) => (
        <React.Fragment key={p.id}>
          <span className="breadcrumb-sep">/</span>
          <span
            className={`breadcrumb-item ${i === path.length - 1 ? 'current' : ''}`}
            onClick={() => onNavigate(p)}
          >
            {p.title}
          </span>
        </React.Fragment>
      ))}
    </div>
  );
};

// Wiki Page View Component
const WikiPageView = ({ page, pages, data, people, onEdit, onDelete, onNavigate, onLinkItem }) => {
  if (!page) {
    return (
      <div className="wiki-page-empty">
        <span className="empty-icon">▤</span>
        <h2>Welcome to the Wiki</h2>
        <p>Select a page from the sidebar or create a new one to get started.</p>
      </div>
    );
  }

  const linkedJobs = (page.linkedJobs || [])
    .map(id => data.jobs.find(j => j.id === id))
    .filter(Boolean);

  const linkedProjects = (page.linkedProjects || [])
    .map(id => data.projects.find(p => p.id === id))
    .filter(Boolean);

  const author = people.find(p => p.id === page.createdBy);
  const template = WIKI_TEMPLATES.find(t => t.id === page.templateId);

  return (
    <div className="wiki-page-view">
      <WikiBreadcrumb page={page} pages={pages} onNavigate={onNavigate} />

      <div className="page-header">
        <h1>{page.title}</h1>
        <div className="page-actions">
          <button className="btn btn-secondary" onClick={onEdit}>Edit</button>
          <button className="btn btn-secondary" onClick={onLinkItem}>Link Item</button>
          <button className="btn btn-danger" onClick={onDelete}>Delete</button>
        </div>
      </div>

      <div className="page-meta">
        {template && <span className="meta-template">{template.name}</span>}
        <span className="meta-type">{page.type}</span>
        {author && <span className="meta-author">by {author.name}</span>}
        <span className="meta-date">Updated {formatDate(page.updatedAt)}</span>
      </div>

      {page.tags && page.tags.length > 0 && (
        <div className="page-tags">
          {page.tags.map(tag => (
            <span key={tag} className="tag">{tag}</span>
          ))}
        </div>
      )}

      <div
        className="page-content"
        dangerouslySetInnerHTML={{ __html: page.content }}
      />

      {(linkedJobs.length > 0 || linkedProjects.length > 0) && (
        <div className="page-links">
          <h3>Linked Items</h3>
          {linkedProjects.length > 0 && (
            <div className="link-group">
              <h4>Projects</h4>
              {linkedProjects.map(proj => (
                <div key={proj.id} className="link-item">
                  <span className="link-icon">&#128193;</span>
                  <span>{proj.name}</span>
                  <StatusBadge status={proj.status} />
                </div>
              ))}
            </div>
          )}
          {linkedJobs.length > 0 && (
            <div className="link-group">
              <h4>Jobs</h4>
              {linkedJobs.map(job => (
                <div key={job.id} className="link-item">
                  <span className="link-icon">&#128188;</span>
                  <span>{job.jobNumber} - {job.name}</span>
                  <StatusBadge status={job.status} />
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

// Wiki Page Modal (Create/Edit)
const WikiPageModal = ({ isOpen, onClose, page, pages, data, dispatch }) => {
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

  const handleTemplateChange = (newTemplateId) => {
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
      dispatch({ type: 'UPDATE_WIKI_PAGE', payload: pageData });
    } else {
      dispatch({ type: 'ADD_WIKI_PAGE', payload: pageData });
    }

    onClose(pageData);
  };

  if (!isOpen) return null;

  // Get potential parent pages (excluding self and descendants)
  const getDescendantIds = (pageId) => {
    const descendants = new Set();
    const addDescendants = (id) => {
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

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content wiki-modal" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{page ? 'Edit Page' : 'New Wiki Page'}</h2>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        <div className="modal-body">
          <div className="form-row">
            <div className="form-group">
              <label>Title *</label>
              <input
                type="text"
                value={title}
                onChange={e => setTitle(e.target.value)}
                placeholder="Page title"
              />
            </div>
            <div className="form-group">
              <label>Type</label>
              <select value={type} onChange={e => setType(e.target.value)}>
                {WIKI_TYPES.map(t => (
                  <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label>Template</label>
              <select value={templateId} onChange={e => handleTemplateChange(e.target.value)}>
                <option value="">-- No Template --</option>
                {WIKI_TEMPLATES.map(t => (
                  <option key={t.id} value={t.id}>{t.name}</option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Parent Page</label>
              <select value={parentId} onChange={e => setParentId(e.target.value)}>
                <option value="">-- None (Root Level) --</option>
                {parentOptions.map(p => (
                  <option key={p.id} value={p.id}>{p.title}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="form-group">
            <label>Tags (comma separated)</label>
            <input
              type="text"
              value={tags}
              onChange={e => setTags(e.target.value)}
              placeholder="e.g., client, brand, 2024"
            />
          </div>

          <div className="form-group">
            <label>Content</label>
            <WysiwygEditor
              content={content}
              onChange={setContent}
            />
          </div>
        </div>
        <div className="modal-footer">
          <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" onClick={handleSubmit}>
            {page ? 'Save Changes' : 'Create Page'}
          </button>
        </div>
      </div>
    </div>
  );
};

// Wiki Link Modal
const WikiLinkModal = ({ isOpen, onClose, page, data, dispatch }) => {
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

  const toggleJob = (jobId) => {
    setSelectedJobs(prev =>
      prev.includes(jobId) ? prev.filter(id => id !== jobId) : [...prev, jobId]
    );
  };

  const toggleProject = (projectId) => {
    setSelectedProjects(prev =>
      prev.includes(projectId) ? prev.filter(id => id !== projectId) : [...prev, projectId]
    );
  };

  if (!isOpen || !page) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>Link Items to "{page.title}"</h2>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        <div className="modal-body">
          <div className="form-section">
            <h3>Projects</h3>
            <div className="link-checkbox-list">
              {data.projects.map(proj => (
                <label key={proj.id} className="checkbox-label">
                  <input
                    type="checkbox"
                    checked={selectedProjects.includes(proj.id)}
                    onChange={() => toggleProject(proj.id)}
                  />
                  {proj.name} ({proj.client})
                </label>
              ))}
            </div>
          </div>
          <div className="form-section">
            <h3>Jobs</h3>
            <div className="link-checkbox-list">
              {data.jobs.map(job => (
                <label key={job.id} className="checkbox-label">
                  <input
                    type="checkbox"
                    checked={selectedJobs.includes(job.id)}
                    onChange={() => toggleJob(job.id)}
                  />
                  {job.jobNumber} - {job.name}
                </label>
              ))}
            </div>
          </div>
        </div>
        <div className="modal-footer">
          <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" onClick={handleSave}>Save Links</button>
        </div>
      </div>
    </div>
  );
};

// Main Wiki Tab Component
const WikiTab = ({ data, dispatch, people }) => {
  const [selectedPage, setSelectedPage] = useState(null);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [linkModalOpen, setLinkModalOpen] = useState(false);
  const [editingPage, setEditingPage] = useState(null);
  const [wikiSearchQuery, setWikiSearchQuery] = useState('');

  const wikiPages = data.wikiPages || [];

  const handleSelectPage = (page) => {
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

    dispatch({ type: 'DELETE_WIKI_PAGE', payload: selectedPage.id });
    setSelectedPage(null);
  };

  const handleModalClose = (savedPage) => {
    setEditModalOpen(false);
    if (savedPage && savedPage.id) {
      setSelectedPage(savedPage);
    }
  };

  const handleNavigate = (page) => {
    setSelectedPage(page);
  };

  return (
    <div className="wiki-container">
      <WikiSidebar
        pages={wikiPages}
        selectedId={selectedPage?.id}
        onSelect={handleSelectPage}
        onNewPage={handleNewPage}
        searchQuery={wikiSearchQuery}
        onSearchChange={setWikiSearchQuery}
      />
      <div className="wiki-main">
        <WikiPageView
          page={selectedPage}
          pages={wikiPages}
          data={data}
          people={people}
          onEdit={handleEditPage}
          onDelete={handleDeletePage}
          onNavigate={handleNavigate}
          onLinkItem={() => setLinkModalOpen(true)}
        />
      </div>

      <WikiPageModal
        isOpen={editModalOpen}
        onClose={handleModalClose}
        page={editingPage}
        pages={wikiPages}
        data={data}
        dispatch={dispatch}
      />

      <WikiLinkModal
        isOpen={linkModalOpen}
        onClose={() => setLinkModalOpen(false)}
        page={selectedPage}
        data={data}
        dispatch={dispatch}
      />
    </div>
  );
};

// ============================================================================
// CAPACITY TAB COMPONENTS
// ============================================================================

// Person Selector with carousel navigation
const PersonSelector = ({ people, currentIndex, onNavigate }) => {
  const person = people[currentIndex];
  if (!person) return null;

  return (
    <div className="person-selector">
      <button
        className="nav-arrow nav-arrow-left"
        onClick={() => onNavigate(-1)}
        disabled={currentIndex === 0}
      >
        &#8249;
      </button>
      <div className="person-info">
        <PersonAvatar person={person} size="medium" />
        <div className="person-details">
          <span className="person-name">{person.name}</span>
          <span className="person-roles">{person.roles.join(' • ')}</span>
        </div>
      </div>
      <button
        className="nav-arrow nav-arrow-right"
        onClick={() => onNavigate(1)}
        disabled={currentIndex === people.length - 1}
      >
        &#8250;
      </button>
    </div>
  );
};

// Capacity progress bar
const CapacityBar = ({ current, max, label }) => {
  const percentage = Math.min((current / max) * 100, 100);
  const isOverCapacity = current > max;

  return (
    <div className="capacity-bar-container">
      <div className="capacity-bar-label">
        <span>{label}</span>
        <span className={`capacity-hours ${isOverCapacity ? 'over-capacity' : ''}`}>
          {current.toFixed(1)}h / {max}h
        </span>
      </div>
      <div className="capacity-bar">
        <div
          className={`capacity-bar-fill ${isOverCapacity ? 'over-capacity' : ''}`}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
};

// Job card for capacity view
const CapacityJobCard = ({ job, project, isOverflow }) => {
  return (
    <div className={`capacity-job-card ${isOverflow ? 'overflow' : ''}`}>
      <div className="capacity-card-header">
        <span className="capacity-job-number">{job.jobNumber}</span>
        <span className="capacity-job-name">{job.name}</span>
      </div>
      <div className="capacity-card-meta">
        {project?.client} / {project?.name}
      </div>
      <div className="capacity-card-footer">
        <StatusBadge status={job.status} />
        <span className="capacity-hours-badge">{job.hours}h</span>
        <span className="capacity-due">Due: {formatDate(job.dueDate)}</span>
      </div>
      {isOverflow && (
        <div className="overflow-warning">
          Overflows to next week
        </div>
      )}
    </div>
  );
};

// Section grouping jobs
const CapacitySection = ({ title, jobs, totalHours, data, isOverflow = false }) => {
  if (jobs.length === 0) return null;

  return (
    <div className={`capacity-section ${isOverflow ? 'overflow-section' : ''}`}>
      <div className="capacity-section-header">
        <h3>{title}</h3>
        <span className="section-hours">{totalHours.toFixed(1)} hours</span>
      </div>
      <div className="capacity-jobs-list">
        {jobs.map(job => {
          const project = data.projects.find(p => p.id === job.projectId);
          return (
            <CapacityJobCard
              key={job.id}
              job={job}
              project={project}
              isOverflow={isOverflow}
            />
          );
        })}
      </div>
    </div>
  );
};

// Email action buttons
const EmailActions = ({ person, categorizedJobs, data, dispatch }) => {
  const [showScheduleConfirm, setShowScheduleConfirm] = useState(false);

  const handleSendEmail = () => {
    const { subject, body } = generateCapacityEmailContent(person, categorizedJobs, data);
    const mailtoLink = `mailto:${person.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    window.location.href = mailtoLink;
  };

  const handleScheduleEmail = () => {
    const scheduledFor = getNextWeekdayAt9am();
    const { subject, body } = generateCapacityEmailContent(person, categorizedJobs, data);

    const scheduledEmail = {
      id: generateId(),
      personId: person.id,
      personEmail: person.email,
      scheduledFor: scheduledFor.toISOString(),
      subject,
      content: body,
      createdAt: new Date().toISOString()
    };

    dispatch({ type: 'ADD_SCHEDULED_EMAIL', payload: scheduledEmail });
    setShowScheduleConfirm(true);
    setTimeout(() => setShowScheduleConfirm(false), 3000);
  };

  const nextWeekday = getNextWeekdayAt9am();
  const scheduleLabel = nextWeekday.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric'
  });

  return (
    <div className="email-actions">
      <button className="btn btn-secondary email-btn" onClick={handleSendEmail}>
        <span className="email-icon">&#9993;</span>
        Send by Email
      </button>
      <button className="btn btn-primary email-btn" onClick={handleScheduleEmail}>
        <span className="email-icon">&#128339;</span>
        Schedule Email (9am {scheduleLabel})
      </button>
      {showScheduleConfirm && (
        <div className="schedule-confirm">
          Email scheduled for 9am on {scheduleLabel}
        </div>
      )}
    </div>
  );
};

// Main Capacity Tab Component
const CapacityTab = ({ data, dispatch, people }) => {
  const [currentPersonIndex, setCurrentPersonIndex] = useState(0);

  // Filter to only people with agency roles (not just Client)
  const agencyPeople = people.filter(p =>
    p.roles.some(r => r !== 'Client')
  );

  const currentPerson = agencyPeople[currentPersonIndex];

  const handleNavigate = (direction) => {
    const newIndex = currentPersonIndex + direction;
    if (newIndex >= 0 && newIndex < agencyPeople.length) {
      setCurrentPersonIndex(newIndex);
    }
  };

  if (!currentPerson) {
    return (
      <div className="capacity-empty">
        <div className="empty-icon">○</div>
        <h2>No team members found</h2>
        <p>Add people to see their capacity.</p>
      </div>
    );
  }

  // Get jobs assigned to current person
  const personJobs = getPersonJobs(currentPerson.id, data.jobs);

  // Categorize jobs by capacity
  const categorizedJobs = categorizeJobsByCapacity(personJobs, data.assets);

  // Get scheduled emails for this person
  const scheduledEmails = (data.scheduledEmails || []).filter(
    e => e.personId === currentPerson.id
  );

  return (
    <div className="capacity-tab">
      <div className="capacity-header">
        <PersonSelector
          people={agencyPeople}
          currentIndex={currentPersonIndex}
          onNavigate={handleNavigate}
        />
        <div className="capacity-bars">
          <CapacityBar
            current={categorizedJobs.todayHours}
            max={DAILY_CAPACITY}
            label="Today"
          />
          <CapacityBar
            current={categorizedJobs.weekHours}
            max={WEEKLY_CAPACITY}
            label="This Week"
          />
        </div>
      </div>

      <div className="capacity-content">
        <CapacitySection
          title="TODAY"
          jobs={categorizedJobs.today}
          totalHours={categorizedJobs.todayHours}
          data={data}
        />

        <CapacitySection
          title="THIS WEEK"
          jobs={categorizedJobs.thisWeek}
          totalHours={categorizedJobs.thisWeek.reduce((sum, j) => sum + j.hours, 0)}
          data={data}
        />

        {categorizedJobs.overflow.length > 0 && (
          <CapacitySection
            title="OVERFLOW (Over Capacity)"
            jobs={categorizedJobs.overflow}
            totalHours={categorizedJobs.overflowHours}
            data={data}
            isOverflow={true}
          />
        )}

        {personJobs.length === 0 && (
          <div className="capacity-empty-jobs">
            <span className="empty-icon">◌</span>
            <p>No jobs assigned to {currentPerson.name}</p>
          </div>
        )}
      </div>

      <div className="capacity-footer">
        <EmailActions
          person={currentPerson}
          categorizedJobs={categorizedJobs}
          data={data}
          dispatch={dispatch}
        />
        {scheduledEmails.length > 0 && (
          <div className="scheduled-emails-indicator">
            {scheduledEmails.length} email(s) scheduled
          </div>
        )}
      </div>
    </div>
  );
};

// ============================================================================
// ADD PERSON MODAL
// ============================================================================

const AddPersonModal = ({ isOpen, onClose, dispatch }) => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [roles, setRoles] = useState([]);
  const [color, setColor] = useState('#3b82f6');

  const handleSubmit = () => {
    if (!name || !email || roles.length === 0) {
      return alert('Please fill in all required fields');
    }

    const person = {
      id: generateId(),
      name,
      email,
      roles,
      color
    };

    dispatch({ type: 'ADD_PERSON', payload: person });

    setName('');
    setEmail('');
    setRoles([]);
    setColor('#3b82f6');
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <div className="modal-header">
          <h2>Add Person</h2>
          <button className="modal-close" onClick={onClose}>&times;</button>
        </div>
        <div className="modal-body">
          <div className="form-group">
            <label>Name *</label>
            <input type="text" value={name} onChange={e => setName(e.target.value)} placeholder="Full name" />
          </div>
          <div className="form-group">
            <label>Email *</label>
            <input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="email@example.com" />
          </div>
          <div className="form-group">
            <label>Roles *</label>
            <div className="checkbox-group">
              {ROLES.map(role => (
                <label key={role} className="checkbox-label">
                  <input
                    type="checkbox"
                    checked={roles.includes(role)}
                    onChange={e => {
                      if (e.target.checked) {
                        setRoles([...roles, role]);
                      } else {
                        setRoles(roles.filter(r => r !== role));
                      }
                    }}
                  />
                  {role}
                </label>
              ))}
            </div>
          </div>
          <div className="form-group">
            <label>Color</label>
            <input type="color" value={color} onChange={e => setColor(e.target.value)} />
          </div>
        </div>
        <div className="modal-footer">
          <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" onClick={handleSubmit}>Add Person</button>
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// USER SELECTOR COMPONENT
// ============================================================================

const UserSelector = ({ people, currentUser, onUserChange }) => {
  const [isOpen, setIsOpen] = useState(false);

  if (!currentUser) return null;

  const permissions = getUserPermissions(currentUser);
  const permissionLabel = permissions.level === 'superadmin' ? 'COO' :
                          permissions.level === 'admin' ? 'Admin' :
                          permissions.level === 'manager' ? 'Manager' : 'User';

  return (
    <div className="user-selector">
      <div className="user-selector-trigger" onClick={() => setIsOpen(!isOpen)}>
        <PersonAvatar person={currentUser} size="small" />
        <div className="user-selector-info">
          <span className="user-selector-name">{currentUser.name}</span>
          <span className="user-selector-role">{permissionLabel}</span>
        </div>
        <span className="user-selector-arrow">{isOpen ? '▲' : '▼'}</span>
      </div>
      {isOpen && (
        <div className="user-selector-dropdown">
          <div className="user-selector-header">Switch User</div>
          {people.map(person => {
            const perms = getUserPermissions(person);
            const label = perms.level === 'superadmin' ? 'COO' :
                          perms.level === 'admin' ? 'Admin' :
                          perms.level === 'manager' ? 'Manager' : 'User';
            return (
              <div
                key={person.id}
                className={`user-selector-option ${person.id === currentUser.id ? 'active' : ''}`}
                onClick={() => { onUserChange(person.id); setIsOpen(false); }}
              >
                <PersonAvatar person={person} size="small" />
                <div className="user-option-info">
                  <span className="user-option-name">{person.name}</span>
                  <span className="user-option-role">{person.roles.join(', ')} • {label}</span>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

// ============================================================================
// OPERATIONS DASHBOARD COMPONENT
// ============================================================================

// Check if user can access Operations dashboard (COO, PM, Traffic, ECD, CD, Producer)
const canAccessOperations = (user) => {
  if (!user || !user.roles) return false;
  const allowedRoles = ['COO', 'PM', 'Traffic', 'ECD', 'CD', 'Producer'];
  return user.roles.some(role => allowedRoles.includes(role));
};

const OperationsDashboard = ({ data, currentUser }) => {
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
      const peopleInRole = data.people.filter(p => p.roles.includes(role));
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
    return (
      <div className="operations-restricted">
        <span className="empty-icon">◉</span>
        <h2>Access Restricted</h2>
        <p>Operations Dashboard is only available to COO, PM, Traffic, ECD, CD, and Producer roles.</p>
      </div>
    );
  }

  return (
    <div className="operations-dashboard">
      <div className="ops-header">
        <h2>Operations Dashboard</h2>
        <p>Overview of agency capacity and workload</p>
      </div>

      <div className="ops-metrics-grid">
        {/* Top Row - Key Numbers */}
        <div className="ops-metric-card clients">
          <div className="ops-metric-icon">🏢</div>
          <div className="ops-metric-content">
            <div className="ops-metric-value">{metrics.clientCount}</div>
            <div className="ops-metric-label">Clients</div>
          </div>
        </div>

        <div className="ops-metric-card campaigns">
          <div className="ops-metric-icon">📋</div>
          <div className="ops-metric-content">
            <div className="ops-metric-value">{metrics.campaignCount}</div>
            <div className="ops-metric-label">Campaigns</div>
          </div>
        </div>

        <div className="ops-metric-card jobs">
          <div className="ops-metric-icon">📁</div>
          <div className="ops-metric-content">
            <div className="ops-metric-value">{metrics.jobCount}</div>
            <div className="ops-metric-label">Jobs</div>
          </div>
        </div>

        <div className="ops-metric-card hours">
          <div className="ops-metric-icon">⏱️</div>
          <div className="ops-metric-content">
            <div className="ops-metric-value">{metrics.totalJobHours.toFixed(1)}h</div>
            <div className="ops-metric-label">Hours in Jobs</div>
          </div>
        </div>
      </div>

      {/* Role Capacity Section */}
      <div className="ops-section">
        <h3>Hours Capacity per Role (Weekly)</h3>
        <div className="ops-capacity-grid">
          {ROLES.filter(role => role !== 'Client').map(role => (
            <div key={role} className="ops-capacity-card">
              <div className="ops-capacity-header">
                <span className="ops-capacity-role">{role}</span>
                <span className="ops-capacity-people">{metrics.roleCapacity[role].count} people</span>
              </div>
              <div className="ops-capacity-bar">
                <div
                  className="ops-capacity-fill"
                  style={{
                    width: `${Math.min((metrics.roleCapacity[role].hours / 280) * 100, 100)}%`
                  }}
                />
              </div>
              <div className="ops-capacity-hours">{metrics.roleCapacity[role].hours}h / week</div>
            </div>
          ))}
        </div>
      </div>

      {/* Jobs by Status Section */}
      <div className="ops-section">
        <h3>Jobs by Status</h3>
        <div className="ops-status-grid">
          {STATUSES.map(status => {
            const count = metrics.jobsByStatus[status];
            const color = STATUS_COLORS[status];
            return (
              <div key={status} className="ops-status-card">
                <div
                  className="ops-status-indicator"
                  style={{ backgroundColor: color }}
                />
                <div className="ops-status-info">
                  <span className="ops-status-name">{status}</span>
                  <span className="ops-status-count">{count}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// CLIENT REVIEW TAB COMPONENT
// ============================================================================

// Check if user can access Client Review (Client role only)
const canAccessClientReview = (user) => {
  if (!user || !user.roles) return false;
  return user.roles.includes('Client');
};

const ClientReviewTab = ({ data, dispatch, currentUser }) => {
  const [feedbackJobId, setFeedbackJobId] = useState(null);
  const [feedbackText, setFeedbackText] = useState('');
  const [approvedJobs, setApprovedJobs] = useState(new Set());
  const [rejectedJobs, setRejectedJobs] = useState(new Set());

  // Get jobs pending client review where current user is the assigned Client
  const pendingReviewJobs = useMemo(() => {
    if (!currentUser) return [];
    return data.jobs.filter(job =>
      job.status === 'In Review' &&
      job.assignments?.Client === currentUser.id
    );
  }, [data.jobs, currentUser]);

  // Get tasks for a job
  const getJobTasks = (jobId) => {
    return (data.tasks || []).filter(t => t.jobId === jobId);
  };

  // Get project for a job
  const getProject = (projectId) => {
    return data.projects.find(p => p.id === projectId);
  };

  // Handle approval
  const handleApprove = (job) => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: { ...job, status: 'Approved (External)' }
    });
    setApprovedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection
  const handleReject = (job) => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: { ...job, status: 'In Progress' }
    });
    setRejectedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection with feedback - assigns to CD
  const handleRejectWithFeedback = (job) => {
    // Find the CD assigned to this job, or fall back to any CD in the system
    const assignedCD = job.assignments?.CD;
    const fallbackCD = data.people.find(p => p.roles.includes('CD'))?.id;
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
    return (
      <div className="client-review-restricted">
        <div className="restricted-icon">🔒</div>
        <h2>Client Portal</h2>
        <p>This area is exclusively for client review access.</p>
      </div>
    );
  }

  if (pendingReviewJobs.length === 0) {
    return (
      <div className="client-review-empty">
        <div className="empty-illustration">
          <div className="check-circle">✓</div>
        </div>
        <h2>All Caught Up!</h2>
        <p>No creative work pending your review at the moment.</p>
        <p className="empty-subtext">We'll notify you when new content is ready.</p>
      </div>
    );
  }

  return (
    <div className="client-review-tab">
      <div className="client-review-header">
        <div className="review-header-content">
          <h1>Creative Review</h1>
          <p className="review-subtitle">{pendingReviewJobs.length} item{pendingReviewJobs.length !== 1 ? 's' : ''} awaiting your approval</p>
        </div>
      </div>

      <div className="review-items-container">
        {pendingReviewJobs.map(job => {
          const tasks = getJobTasks(job.id);
          const copyTask = tasks.find(t => t.templateId === 'copy');
          const mediaTask = tasks.find(t => t.templateId === 'media');
          const project = getProject(job.projectId);
          const isApproved = approvedJobs.has(job.id);
          const isRejected = rejectedJobs.has(job.id);
          const showFeedbackInput = feedbackJobId === job.id;

          return (
            <div key={job.id} className={`review-item ${isApproved ? 'approved' : ''} ${isRejected ? 'rejected' : ''}`}>
              {/* Social Media Post Mockup */}
              <div className="social-post-mockup">
                <div className="post-header">
                  <div className="post-avatar">
                    {project?.client?.charAt(0) || 'C'}
                  </div>
                  <div className="post-account">
                    <span className="account-name">{project?.client || 'Client'}</span>
                    <span className="account-handle">@{(project?.client || 'client').toLowerCase().replace(/\s+/g, '')}</span>
                  </div>
                  <div className="post-platform">
                    <span className="platform-badge">Preview</span>
                  </div>
                </div>

                <div className="post-media">
                  {mediaTask?.fileUrl ? (
                    <div className="media-preview">
                      {mediaTask.fileType === 'video' ? (
                        <div className="video-placeholder">
                          <span className="play-icon">▶</span>
                          <span className="video-filename">{mediaTask.fileUrl}</span>
                        </div>
                      ) : (
                        <div className="image-placeholder">
                          <span className="image-icon">🖼</span>
                          <span className="image-filename">{mediaTask.fileUrl}</span>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="media-pending">
                      <span>Media pending</span>
                    </div>
                  )}
                </div>

                <div className="post-engagement">
                  <div className="engagement-icons">
                    <span className="engagement-icon">♡</span>
                    <span className="engagement-icon">💬</span>
                    <span className="engagement-icon">↗</span>
                  </div>
                  <span className="bookmark-icon">⚐</span>
                </div>

                <div className="post-content">
                  <div className="post-caption">
                    <span className="caption-account">{(project?.client || 'client').toLowerCase().replace(/\s+/g, '')}</span>
                    <span className="caption-text">{copyTask?.content || 'Copy pending...'}</span>
                  </div>
                  {copyTask?.characterCount && (
                    <div className="char-count-badge">{copyTask.characterCount} characters</div>
                  )}
                </div>

                <div className="post-meta">
                  <span className="job-reference">{job.jobNumber}</span>
                  <span className="job-name">{job.name}</span>
                </div>
              </div>

              {/* Approval Actions */}
              <div className="review-actions">
                <div className="job-info-card">
                  <div className="info-row">
                    <span className="info-label">Campaign</span>
                    <span className="info-value">{project?.name || 'N/A'}</span>
                  </div>
                  <div className="info-row">
                    <span className="info-label">Due Date</span>
                    <span className="info-value">{job.dueDate ? new Date(job.dueDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'No deadline'}</span>
                  </div>
                  {job.description && (
                    <div className="info-row description">
                      <span className="info-label">Brief</span>
                      <span className="info-value">{job.description}</span>
                    </div>
                  )}
                </div>

                {isApproved ? (
                  <div className="action-result approved">
                    <span className="result-icon">✓</span>
                    <span>Approved</span>
                  </div>
                ) : isRejected ? (
                  <div className="action-result rejected">
                    <span className="result-icon">✗</span>
                    <span>Returned for revisions</span>
                  </div>
                ) : (
                  <div className="action-buttons">
                    <button
                      className="review-btn approve"
                      onClick={() => handleApprove(job)}
                    >
                      <span className="btn-icon">✓</span>
                      Approve
                    </button>

                    <button
                      className="review-btn reject"
                      onClick={() => handleReject(job)}
                    >
                      <span className="btn-icon">✗</span>
                      Not Approved
                    </button>

                    <button
                      className="review-btn feedback"
                      onClick={() => setFeedbackJobId(showFeedbackInput ? null : job.id)}
                    >
                      <span className="btn-icon">✎</span>
                      {showFeedbackInput ? 'Cancel' : 'Add Feedback'}
                    </button>

                    {showFeedbackInput && (
                      <div className="feedback-input-container">
                        <textarea
                          className="feedback-textarea"
                          placeholder="Share your feedback for the creative team..."
                          value={feedbackText}
                          onChange={(e) => setFeedbackText(e.target.value)}
                          autoFocus
                        />
                        <button
                          className="submit-feedback-btn"
                          onClick={() => handleRejectWithFeedback(job)}
                          disabled={!feedbackText.trim()}
                        >
                          Submit Feedback & Return
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};

// ============================================================================
// JOB REVIEW TAB (Internal Review for CDs, ECDs, Producers, PMs, COO)
// ============================================================================

// Check if user can access Job Review (internal reviewers)
const canAccessJobReview = (user) => {
  if (!user || !user.roles) return false;
  const reviewerRoles = ['CD', 'ECD', 'Producer', 'PM', 'COO'];
  return user.roles.some(role => reviewerRoles.includes(role));
};

const JobReviewTab = ({ data, dispatch, currentUser }) => {
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
    return data.jobs.filter(job =>
      job.clientFeedback &&
      job.clientFeedbackStatus === 'pending' &&
      job.clientFeedbackAssignedTo === currentUser.id
    );
  }, [data.jobs, currentUser]);

  // Get people by role for assignment dropdown
  const getPeopleByRole = (role) => {
    return data.people.filter(p => p.roles.includes(role));
  };

  // Handle Action & Submit - send back to client review
  const handleActionAndSubmit = (job) => {
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
      const copyComplete = copyTask?.completed === true;
      const mediaComplete = mediaTask?.completed === true;

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
  const getJobTasks = (jobId) => {
    return (data.tasks || []).filter(t => t.jobId === jobId);
  };

  // Get project for a job
  const getProject = (projectId) => {
    return data.projects.find(p => p.id === projectId);
  };

  // Get person by ID
  const getPerson = (personId) => {
    return data.people.find(p => p.id === personId);
  };

  // Handle internal approval - moves to "In Review" for client
  const handleApprove = (job) => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: { ...job, status: 'In Review', internalApprovedBy: currentUser?.id, internalApprovedAt: new Date().toISOString() }
    });
    setApprovedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection without feedback
  const handleReject = (job) => {
    // Keep in "In Progress" status
    setRejectedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection with feedback - assigns feedback to tasks and creates todo
  const handleRejectWithFeedback = (job) => {
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
    return (
      <div className="job-review-restricted">
        <span className="empty-icon">◉</span>
        <h2>Access Restricted</h2>
        <p>Job Review is available to CDs, ECDs, Producers, PMs, and COO roles.</p>
      </div>
    );
  }

  return (
    <div className="job-review-tab">
      <div className="job-review-header">
        <div className="review-header-content">
          <h1>Internal Review</h1>
          <p className="review-subtitle">
            {filteredJobs.length} job{filteredJobs.length !== 1 ? 's' : ''} ready for review
          </p>
        </div>
      </div>

      {/* Filters */}
      <div className="job-review-filters">
        <div className="filter-group">
          <label>Client</label>
          <select value={clientFilter} onChange={(e) => setClientFilter(e.target.value)}>
            <option value="all">All Clients</option>
            {clients.map(client => (
              <option key={client} value={client}>{client}</option>
            ))}
          </select>
        </div>

        <div className="filter-group">
          <label>Campaign</label>
          <select value={campaignFilter} onChange={(e) => setCampaignFilter(e.target.value)}>
            <option value="all">All Campaigns</option>
            {campaigns.map(campaign => (
              <option key={campaign.id} value={campaign.id}>{campaign.name}</option>
            ))}
          </select>
        </div>

        <div className="filter-group">
          <label>Job</label>
          <select value={jobFilter} onChange={(e) => setJobFilter(e.target.value)}>
            <option value="all">All Jobs</option>
            {(campaignFilter !== 'all' ? reviewableJobs.filter(j => j.projectId === campaignFilter) : reviewableJobs).map(job => (
              <option key={job.id} value={job.id}>{job.jobNumber} - {job.name}</option>
            ))}
          </select>
        </div>

        <div className="filter-group">
          <label>Task Type</label>
          <select value={taskFilter} onChange={(e) => setTaskFilter(e.target.value)}>
            <option value="all">All Tasks</option>
            <option value="copy">Copy</option>
            <option value="media">Media</option>
          </select>
        </div>

        {(clientFilter !== 'all' || campaignFilter !== 'all' || jobFilter !== 'all' || taskFilter !== 'all') && (
          <button
            className="btn btn-secondary filter-clear"
            onClick={() => {
              setClientFilter('all');
              setCampaignFilter('all');
              setJobFilter('all');
              setTaskFilter('all');
            }}
          >
            Clear Filters
          </button>
        )}
      </div>

      {/* Client Feedback Queue */}
      {clientFeedbackJobs.length > 0 && (
        <div className="client-feedback-queue">
          <div className="feedback-queue-header">
            <h2>◉ Client Feedback Requiring Action</h2>
            <span className="feedback-count">{clientFeedbackJobs.filter(j => !actionedFeedback.has(j.id)).length} pending</span>
          </div>
          <div className="feedback-queue-list">
            {clientFeedbackJobs.map(job => {
              const project = getProject(job.projectId);
              const feedbackFrom = getPerson(job.clientFeedbackBy);
              const isActioned = actionedFeedback.has(job.id);
              const showAssignDropdown = assignDropdownJobId === job.id;

              if (isActioned) {
                return (
                  <div key={job.id} className="feedback-card actioned">
                    <div className="feedback-card-header">
                      <span className="job-number">{job.jobNumber}</span>
                      <h4>{job.name}</h4>
                    </div>
                    <div className="feedback-actioned-badge">
                      <span className="result-icon">✓</span>
                      Feedback actioned
                    </div>
                  </div>
                );
              }

              return (
                <div key={job.id} className="feedback-card">
                  <div className="feedback-card-header">
                    <div className="feedback-card-title">
                      <span className="job-number">{job.jobNumber}</span>
                      <h4>{job.name}</h4>
                    </div>
                    <div className="feedback-card-meta">
                      <span className="client-name">{project?.client}</span>
                      <span className="campaign-name">{project?.name}</span>
                    </div>
                  </div>

                  <div className="feedback-content">
                    <div className="feedback-from">
                      <span className="feedback-label">Feedback from:</span>
                      <span className="feedback-source">{feedbackFrom?.name || 'Client'}</span>
                      <span className="feedback-date">
                        {job.clientFeedbackDate && new Date(job.clientFeedbackDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}
                      </span>
                    </div>
                    <div className="feedback-text">
                      "{job.clientFeedback}"
                    </div>
                  </div>

                  <div className="feedback-actions">
                    <button
                      className="btn feedback-action-btn action-submit"
                      onClick={() => handleActionAndSubmit(job)}
                    >
                      <span className="btn-icon">↩</span>
                      Action & Submit to Client
                    </button>

                    <div className="assign-dropdown-container">
                      <button
                        className="btn feedback-action-btn assign-btn"
                        onClick={() => setAssignDropdownJobId(showAssignDropdown ? null : job.id)}
                      >
                        <span className="btn-icon">→</span>
                        Assign to...
                        <span className="dropdown-arrow">{showAssignDropdown ? '▲' : '▼'}</span>
                      </button>

                      {showAssignDropdown && (
                        <div className="assign-dropdown">
                          {FEEDBACK_ASSIGNABLE_ROLES.map(role => {
                            const peopleInRole = getPeopleByRole(role);
                            if (peopleInRole.length === 0) return null;

                            return (
                              <div key={role} className="assign-role-group">
                                <div className="assign-role-header">{role}</div>
                                {peopleInRole.map(person => (
                                  <button
                                    key={person.id}
                                    className="assign-option"
                                    onClick={() => handleAssignFeedback(job, person.id, role)}
                                  >
                                    <span className="assign-avatar" style={{ background: person.color }}>
                                      {person.name.split(' ').map(n => n[0]).join('')}
                                    </span>
                                    <span className="assign-name">{person.name}</span>
                                  </button>
                                ))}
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {filteredJobs.length === 0 ? (
        <div className="job-review-empty">
          <span className="empty-icon">◌</span>
          <h2>No Jobs Ready for Review</h2>
          <p>Jobs will appear here when their Copy and Media tasks are completed.</p>
        </div>
      ) : (
        <div className="job-review-list">
          {filteredJobs.map(job => {
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

            return (
              <div key={job.id} className={`job-review-card ${isApproved ? 'approved' : ''} ${isRejected ? 'rejected' : ''}`}>
                {/* Job Header */}
                <div className="review-card-header">
                  <div className="review-card-title">
                    <span className="job-number">{job.jobNumber}</span>
                    <h3>{job.name}</h3>
                  </div>
                  <div className="review-card-meta">
                    <span className="client-name">{project?.client}</span>
                    <span className="campaign-name">{project?.name}</span>
                  </div>
                </div>

                {/* Task Content Display */}
                <div className="review-card-content">
                  {/* Copy Section */}
                  <div className="review-task-section">
                    <div className="task-section-header">
                      <span className="task-label">◇ Copy</span>
                      {copywriter && (
                        <span className="task-assignee">by {copywriter.name}</span>
                      )}
                      {copyTask?.characterCount && (
                        <span className="char-badge">{copyTask.characterCount} chars</span>
                      )}
                    </div>
                    <div className="task-content-preview">
                      {copyTask?.content || <span className="no-content">No copy content</span>}
                    </div>
                    {copyTask?.internalFeedback && (
                      <div className="task-feedback-display">
                        <span className="feedback-label">Previous Feedback:</span>
                        <p>{copyTask.internalFeedback}</p>
                      </div>
                    )}
                  </div>

                  {/* Media Section */}
                  <div className="review-task-section">
                    <div className="task-section-header">
                      <span className="task-label">◈ Media</span>
                      {designer && (
                        <span className="task-assignee">by {designer.name}</span>
                      )}
                      {mediaTask?.fileType && (
                        <span className="file-type-badge">{mediaTask.fileType}</span>
                      )}
                    </div>
                    <div className="task-media-preview">
                      {mediaTask?.fileUrl ? (
                        <div className="media-file-info">
                          <span className="media-icon">{mediaTask.fileType === 'video' ? '▶' : '🖼'}</span>
                          <span className="media-filename">{mediaTask.fileUrl}</span>
                        </div>
                      ) : (
                        <span className="no-content">No media file</span>
                      )}
                    </div>
                    {mediaTask?.internalFeedback && (
                      <div className="task-feedback-display">
                        <span className="feedback-label">Previous Feedback:</span>
                        <p>{mediaTask.internalFeedback}</p>
                      </div>
                    )}
                  </div>
                </div>

                {/* Job Details */}
                <div className="review-card-details">
                  {job.description && (
                    <div className="detail-row">
                      <span className="detail-label">Brief</span>
                      <span className="detail-value">{job.description}</span>
                    </div>
                  )}
                  <div className="detail-row">
                    <span className="detail-label">Due Date</span>
                    <span className="detail-value">
                      {job.dueDate ? new Date(job.dueDate).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) : 'No deadline'}
                    </span>
                  </div>
                </div>

                {/* Actions */}
                <div className="review-card-actions">
                  {isApproved ? (
                    <div className="action-result approved">
                      <span className="result-icon">✓</span>
                      <span>Approved – Sent to Client Review</span>
                    </div>
                  ) : isRejected ? (
                    <div className="action-result rejected">
                      <span className="result-icon">↩</span>
                      <span>Returned for revisions</span>
                    </div>
                  ) : (
                    <>
                      <div className="action-buttons-row">
                        <button
                          className="review-btn approve"
                          onClick={() => handleApprove(job)}
                        >
                          <span className="btn-icon">✓</span>
                          Approve
                        </button>

                        <button
                          className="review-btn reject"
                          onClick={() => handleReject(job)}
                        >
                          <span className="btn-icon">✗</span>
                          Not Approved
                        </button>

                        <button
                          className="review-btn feedback"
                          onClick={() => setFeedbackJobId(showFeedbackInput ? null : job.id)}
                        >
                          <span className="btn-icon">✎</span>
                          {showFeedbackInput ? 'Cancel' : 'Feedback'}
                        </button>
                      </div>

                      {showFeedbackInput && (
                        <div className="feedback-input-section">
                          <textarea
                            className="feedback-textarea"
                            placeholder="Provide feedback for the Copywriter and Designer..."
                            value={feedbackText}
                            onChange={(e) => setFeedbackText(e.target.value)}
                            autoFocus
                          />
                          <div className="feedback-note">
                            This feedback will be assigned to the Copy and Media tasks for {copywriter?.name || 'Copywriter'} and {designer?.name || 'Designer'} to address.
                          </div>
                          <button
                            className="submit-feedback-btn"
                            onClick={() => handleRejectWithFeedback(job)}
                            disabled={!feedbackText.trim()}
                          >
                            Submit Feedback & Return for Revisions
                          </button>
                        </div>
                      )}
                    </>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

// ============================================================================
// MAIN APP COMPONENT
// ============================================================================

function App() {
  const [data, dispatch] = useReducer(dataReducer, null, loadFromStorage);
  const [activeTab, setActiveTab] = useState('Jobs');
  const [viewMode, setViewMode] = useState('table'); // 'table' or 'kanban'
  const [searchQuery, setSearchQuery] = useState('');
  const [briefModalOpen, setBriefModalOpen] = useState(false);
  const [addPersonModalOpen, setAddPersonModalOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);
  const [detailPanelOpen, setDetailPanelOpen] = useState(false);

  // Current user state - defaults to first user, stored in localStorage
  const [currentUserId, setCurrentUserId] = useState(() => {
    const saved = localStorage.getItem('slash301pm_currentUser');
    return saved || (data?.people?.[0]?.id || 'p1');
  });

  // Get current user object
  const currentUser = data?.people?.find(p => p.id === currentUserId) || data?.people?.[0];

  // Save current user to localStorage when changed
  const handleUserChange = (userId) => {
    setCurrentUserId(userId);
    localStorage.setItem('slash301pm_currentUser', userId);
  };

  // Get permissions for current user
  const userPermissions = getUserPermissions(currentUser);

  const handleRowClick = (item) => {
    setSelectedItem(item);
    setDetailPanelOpen(true);
  };

  const handleExport = () => {
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
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
    input.onchange = (e) => {
      const file = e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (event) => {
        try {
          const imported = JSON.parse(event.target.result);
          if (imported.people && imported.projects && imported.jobs && imported.assets) {
            dispatch({ type: 'SET_DATA', payload: imported });
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

  return (
    <div className="app-container">
      <header className="app-header">
        <div className="header-left">
          <h1 className="app-title">Slash 301 PM</h1>
        </div>
        <div className="header-center">
          <div className="search-box">
            <input
              type="text"
              placeholder="Search..."
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
            />
          </div>
        </div>
        <div className="header-right">
          <div className="view-toggle">
            <button
              className={viewMode === 'table' ? 'active' : ''}
              onClick={() => setViewMode('table')}
              title="Table View"
            >
              <span className="icon-table">☰</span>
            </button>
            <button
              className={viewMode === 'kanban' ? 'active' : ''}
              onClick={() => setViewMode('kanban')}
              title="Kanban View"
            >
              <span className="icon-kanban">▦</span>
            </button>
          </div>
          {userPermissions.level === 'superadmin' && (
            <>
              <button className="btn btn-icon" onClick={handleExport} title="Export JSON">↓</button>
              <button className="btn btn-icon" onClick={handleImport} title="Import JSON">↑</button>
            </>
          )}
          {activeTab === 'People' && userPermissions.canEditPeople && (
            <button className="btn btn-primary" onClick={() => setAddPersonModalOpen(true)}>+ Add Person</button>
          )}
          {activeTab !== 'People' && activeTab !== 'Wiki' && activeTab !== 'Capacity' && activeTab !== 'Operations' && activeTab !== 'Client Review' && userPermissions.canCreateJobs && (
            <button className="btn btn-primary" onClick={() => setBriefModalOpen(true)}>+ New Brief</button>
          )}
          <UserSelector
            people={data.people}
            currentUser={currentUser}
            onUserChange={handleUserChange}
          />
        </div>
      </header>

      <nav className="tab-bar">
        {TABS.filter(tab => {
          // Hide Operations tab from users without permission
          if (tab === 'Operations' && !canAccessOperations(currentUser)) {
            return false;
          }
          // Hide Client Review tab from non-Client users
          if (tab === 'Client Review' && !canAccessClientReview(currentUser)) {
            return false;
          }
          // Hide Job Review tab from non-internal-reviewers
          if (tab === 'Job Review' && !canAccessJobReview(currentUser)) {
            return false;
          }
          return true;
        }).map(tab => (
          <button
            key={tab}
            className={`tab ${activeTab === tab ? 'active' : ''}`}
            onClick={() => setActiveTab(tab)}
          >
            <span className="tab-icon">{TAB_ICONS[tab]}</span>
            {tab}
            <span className="tab-count">
              {tab === 'Projects' && data.projects.length}
              {tab === 'Jobs' && data.jobs.length}
              {tab === 'Assets' && data.assets.length}
              {tab === 'People' && data.people.length}
              {tab === 'Wiki' && (data.wikiPages || []).length}
              {tab === 'Capacity' && data.people.filter(p => p.roles.some(r => r !== 'Client')).length}
              {tab === 'Operations' && '📊'}
              {tab === 'Job Review' && data.jobs.filter(j => {
                if (j.status !== 'In Progress') return false;
                const jobTasks = (data.tasks || []).filter(t => t.jobId === j.id);
                const copyComplete = jobTasks.find(t => t.templateId === 'copy')?.completed;
                const mediaComplete = jobTasks.find(t => t.templateId === 'media')?.completed;
                return copyComplete && mediaComplete;
              }).length}
              {tab === 'Client Review' && data.jobs.filter(j => j.status === 'In Review' && j.assignments?.Client === currentUser?.id).length}
            </span>
          </button>
        ))}
      </nav>

      <main className="main-content">
        {activeTab === 'Wiki' ? (
          <WikiTab
            data={data}
            dispatch={dispatch}
            people={data.people}
          />
        ) : activeTab === 'Capacity' ? (
          <CapacityTab
            data={data}
            dispatch={dispatch}
            people={data.people}
          />
        ) : activeTab === 'Operations' ? (
          <OperationsDashboard
            data={data}
            currentUser={currentUser}
          />
        ) : activeTab === 'Job Review' ? (
          <JobReviewTab
            data={data}
            dispatch={dispatch}
            currentUser={currentUser}
          />
        ) : activeTab === 'Client Review' ? (
          <ClientReviewTab
            data={data}
            dispatch={dispatch}
            currentUser={currentUser}
          />
        ) : viewMode === 'table' ? (
          <TableView
            tab={activeTab}
            data={data}
            dispatch={dispatch}
            people={data.people}
            projects={data.projects}
            onRowClick={handleRowClick}
            searchQuery={searchQuery}
            currentUser={currentUser}
          />
        ) : (
          <KanbanBoard
            tab={activeTab}
            data={data}
            dispatch={dispatch}
            people={data.people}
            projects={data.projects}
            onCardClick={handleRowClick}
            searchQuery={searchQuery}
            currentUser={currentUser}
          />
        )}
      </main>

      <BriefModal
        isOpen={briefModalOpen}
        onClose={() => setBriefModalOpen(false)}
        data={data}
        dispatch={dispatch}
      />

      <AddPersonModal
        isOpen={addPersonModalOpen}
        onClose={() => setAddPersonModalOpen(false)}
        dispatch={dispatch}
      />

      <DetailPanel
        item={selectedItem}
        type={activeTab}
        isOpen={detailPanelOpen}
        onClose={() => { setDetailPanelOpen(false); setSelectedItem(null); }}
        data={data}
        dispatch={dispatch}
        people={data.people}
        currentUser={currentUser}
      />
    </div>
  );
}

// ============================================================================
// TEST HELPER - PM Workflow Test
// Call window.testPMWorkflow() from browser console to run
// ============================================================================
window.testPMWorkflow = () => {
  const storageKey = 'slash301pm_data';
  const data = JSON.parse(localStorage.getItem(storageKey));

  if (!data) {
    console.error('No data found in localStorage');
    return;
  }

  // Find existing people
  const pm = data.people.find(p => p.roles.includes('PM'));
  const cd = data.people.find(p => p.roles.includes('CD'));
  const copywriter = data.people.find(p => p.roles.includes('Copywriter'));
  const designer = data.people.find(p => p.roles.includes('Designer'));
  const client = data.people.find(p => p.roles.includes('Client'));

  console.log('=== PM Workflow Test ===');
  console.log('PM:', pm?.name);
  console.log('CD:', cd?.name);
  console.log('Copywriter:', copywriter?.name);
  console.log('Designer:', designer?.name);
  console.log('Client:', client?.name);

  // Check if test project already exists
  let project = data.projects.find(p => p.name === 'Summer Launch 2026');

  if (!project) {
    // Create project
    project = {
      id: 'proj_test_' + Date.now(),
      name: 'Summer Launch 2026',
      client: 'Acme Corp',
      description: 'PM Workflow Test Campaign',
      status: 'In Progress',
      jobCount: 0,
      createdAt: new Date().toISOString(),
      clientColors: { primary: '#4a90d9', secondary: '#7cb342' }
    };
    data.projects.push(project);
    console.log('✓ Created project:', project.name);
  } else {
    console.log('✓ Using existing project:', project.name);
  }

  // Create job
  const jobId = 'job_test_' + Date.now();
  const job = {
    id: jobId,
    jobNumber: 'TEST-' + Math.floor(Math.random() * 1000),
    name: 'Hero Video',
    description: 'Test hero video for PM workflow',
    projectId: project.id,
    status: 'In Progress',
    assignments: {
      PM: pm?.id,
      CD: cd?.id,
      Copywriter: copywriter?.id,
      Designer: designer?.id,
      Client: client?.id
    },
    dueDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    order: data.jobs.length,
    createdAt: new Date().toISOString()
  };
  data.jobs.push(job);
  console.log('✓ Created job:', job.jobNumber, '-', job.name);

  // Create Copy task (DONE)
  const copyTask = {
    id: 't_copy_' + Date.now(),
    templateId: 'copy',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: copywriter?.id,
    characterCount: 150,
    content: 'Summer is here! Experience the thrill of our new collection.',
    fileUrl: null,
    fileType: null,
    completedAt: new Date().toISOString(),
    order: 0
  };
  data.tasks.push(copyTask);
  console.log('✓ Created Copy task (DONE)');

  // Create Media task (DONE)
  const mediaTask = {
    id: 't_media_' + Date.now(),
    templateId: 'media',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: designer?.id,
    characterCount: null,
    content: null,
    fileUrl: 'hero-video-final.mp4',
    fileType: 'video',
    completedAt: new Date().toISOString(),
    order: 1
  };
  data.tasks.push(mediaTask);
  console.log('✓ Created Media task (DONE)');

  // Update project job count
  project.jobCount = data.jobs.filter(j => j.projectId === project.id).length;

  // Save to localStorage
  localStorage.setItem(storageKey, JSON.stringify(data));

  console.log('');
  console.log('=== Test Data Created ===');
  console.log('Job is now ready for INTERNAL REVIEW (Job Review tab)');
  console.log('');
  console.log('NEXT STEPS:');
  console.log('1. Refresh the page');
  console.log('2. Login as CD (Maria Garcia)');
  console.log('3. Go to "Job Review" tab');
  console.log('4. Find job', job.jobNumber, 'and click "Approve"');
  console.log('5. Job will move to "In Review" status');
  console.log('6. Login as Client (Chris Martin)');
  console.log('7. Go to "Client Review" tab');
  console.log('8. Approve or reject the job');
  console.log('');
  console.log('Call window.testPMWorkflow.verify() after approving to verify status');

  return { project, job, copyTask, mediaTask };
};

// Verify workflow status
window.testPMWorkflow.verify = () => {
  const storageKey = 'slash301pm_data';
  const data = JSON.parse(localStorage.getItem(storageKey));

  const testJobs = data.jobs.filter(j => j.jobNumber?.startsWith('TEST-'));

  console.log('=== Workflow Verification ===');
  testJobs.forEach(job => {
    const project = data.projects.find(p => p.id === job.projectId);
    console.log(`Job: ${job.jobNumber} - ${job.name}`);
    console.log(`  Project: ${project?.name} (${project?.client})`);
    console.log(`  Status: ${job.status}`);

    if (job.internalApprovedBy) {
      const approver = data.people.find(p => p.id === job.internalApprovedBy);
      console.log(`  Internal Approved By: ${approver?.name}`);
    }

    if (job.status === 'In Review') {
      console.log('  ✓ Ready for CLIENT REVIEW');
    } else if (job.status === 'Approved (External)') {
      console.log('  ✓ CLIENT APPROVED - WORKFLOW COMPLETE');
    }
    console.log('');
  });

  // Check if any job has reached Client Review
  const inClientReview = testJobs.some(j => j.status === 'In Review');
  const clientApproved = testJobs.some(j => j.status === 'Approved (External)');

  return { inClientReview, clientApproved, testJobs };
};

// Full automated workflow test - creates job and moves it through to Client Review
window.testPMWorkflow.full = () => {
  const storageKey = 'slash301pm_data';
  const data = JSON.parse(localStorage.getItem(storageKey));

  if (!data) {
    console.error('No data found in localStorage');
    return { success: false, error: 'No data in localStorage' };
  }

  // Find people
  const pm = data.people.find(p => p.roles.includes('PM'));
  const cd = data.people.find(p => p.roles.includes('CD'));
  const copywriter = data.people.find(p => p.roles.includes('Copywriter'));
  const designer = data.people.find(p => p.roles.includes('Designer'));
  const client = data.people.find(p => p.roles.includes('Client'));

  console.log('=== FULL PM WORKFLOW TEST ===');
  console.log('');

  // Step 1: Create or find project
  let project = data.projects.find(p => p.name === 'Summer Launch 2026' && p.client === 'Acme Corp');
  if (!project) {
    project = {
      id: 'proj_full_' + Date.now(),
      name: 'Summer Launch 2026',
      client: 'Acme Corp',
      description: 'Full PM Workflow Test',
      status: 'In Progress',
      jobCount: 0,
      createdAt: new Date().toISOString(),
      clientColors: { primary: '#4a90d9', secondary: '#7cb342' }
    };
    data.projects.push(project);
    console.log('STEP 1: ✓ Created project - Acme Corp / Summer Launch 2026');
  } else {
    console.log('STEP 1: ✓ Using existing project - Acme Corp / Summer Launch 2026');
  }

  // Step 2: Create job
  const jobId = 'job_full_' + Date.now();
  const job = {
    id: jobId,
    jobNumber: 'FULL-' + Math.floor(Math.random() * 1000),
    name: 'Hero Video',
    description: 'Full workflow test job',
    projectId: project.id,
    status: 'In Progress',
    assignments: {
      PM: pm?.id,
      CD: cd?.id,
      Copywriter: copywriter?.id,
      Designer: designer?.id,
      Client: client?.id
    },
    dueDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    order: data.jobs.length,
    createdAt: new Date().toISOString()
  };
  data.jobs.push(job);
  console.log('STEP 2: ✓ Created job:', job.jobNumber, '- Hero Video');

  // Step 3: Create tasks
  const copyTask = {
    id: 't_copy_full_' + Date.now(),
    templateId: 'copy',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: copywriter?.id,
    characterCount: 150,
    content: 'Summer is here! Experience the thrill of our new collection.',
    completedAt: new Date().toISOString(),
    order: 0
  };
  data.tasks.push(copyTask);

  const mediaTask = {
    id: 't_media_full_' + Date.now(),
    templateId: 'media',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: designer?.id,
    fileUrl: 'hero-video-final.mp4',
    fileType: 'video',
    completedAt: new Date().toISOString(),
    order: 1
  };
  data.tasks.push(mediaTask);
  console.log('STEP 3: ✓ Created Copy and Media tasks (both DONE)');

  // Step 4: Simulate CD internal approval (moves to "In Review")
  const jobIndex = data.jobs.findIndex(j => j.id === jobId);
  data.jobs[jobIndex] = {
    ...job,
    status: 'In Review',
    internalApprovedBy: cd?.id,
    internalApprovedAt: new Date().toISOString()
  };
  console.log('STEP 4: ✓ CD (' + cd?.name + ') approved internal review');
  console.log('        Job status changed to "In Review"');

  // Update project job count
  project.jobCount = data.jobs.filter(j => j.projectId === project.id).length;

  // Save to localStorage
  localStorage.setItem(storageKey, JSON.stringify(data));

  console.log('');
  console.log('=== WORKFLOW TEST COMPLETE ===');
  console.log('');
  console.log('Job:', job.jobNumber, '- Hero Video');
  console.log('Project: Acme Corp / Summer Launch 2026');
  console.log('Status: In Review (ready for Client Review)');
  console.log('');
  console.log('The job is now visible in:');
  console.log('  - Client Review tab (for Client role users)');
  console.log('  - The Client (' + client?.name + ') can Approve/Reject');
  console.log('');
  console.log('Refresh the page and login as Client to see the job');

  return {
    success: true,
    job: data.jobs[jobIndex],
    project,
    copyTask,
    mediaTask,
    inClientReview: true
  };
};

// ============================================================================
// MULTI-ROLE WORKFLOW TEST SUITE
// Call window.testRoles() or visit ?roletest=true
// ============================================================================
window.testRoles = {
  // Storage key
  _storageKey: 'slash301pm_data',

  // Get data from localStorage
  _getData() {
    return JSON.parse(localStorage.getItem(this._storageKey));
  },

  // Save data to localStorage
  _saveData(data) {
    localStorage.setItem(this._storageKey, JSON.stringify(data));
  },

  // Find person by role
  _findPerson(data, role) {
    return data.people.find(p => p.roles.includes(role));
  },

  // Generate unique ID
  _genId(prefix) {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  },

  // ========================================
  // PM WORKFLOW TEST
  // ========================================
  pm: {
    name: 'PM Workflow',
    description: 'Create brief, set up project, assign team members',

    run() {
      const data = window.testRoles._getData();
      const pm = window.testRoles._findPerson(data, 'PM');
      const cd = window.testRoles._findPerson(data, 'CD');
      const copywriter = window.testRoles._findPerson(data, 'Copywriter');
      const designer = window.testRoles._findPerson(data, 'Designer');
      const client = window.testRoles._findPerson(data, 'Client');

      console.log('🎯 PM WORKFLOW TEST');
      console.log('Testing as:', pm?.name);
      console.log('');

      // Create project
      const projectId = window.testRoles._genId('proj');
      const project = {
        id: projectId,
        name: 'Q1 Product Launch',
        client: 'TechCorp Industries',
        description: 'New product launch campaign',
        status: 'In Progress',
        jobCount: 0,
        createdAt: new Date().toISOString(),
        clientColors: { primary: '#6366f1', secondary: '#8b5cf6' }
      };
      data.projects.push(project);
      console.log('✓ Created project:', project.name);

      // Create job with full brief
      const jobId = window.testRoles._genId('job');
      const job = {
        id: jobId,
        jobNumber: 'PM-' + Math.floor(Math.random() * 1000),
        name: 'Launch Video',
        description: 'Hero video for product launch landing page',
        projectId: projectId,
        status: 'In Progress',
        assignments: {
          PM: pm?.id,
          CD: cd?.id,
          Copywriter: copywriter?.id,
          Designer: designer?.id,
          Client: client?.id
        },
        dueDate: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        order: data.jobs.length,
        createdAt: new Date().toISOString(),
        briefedBy: pm?.id,
        briefedAt: new Date().toISOString()
      };
      data.jobs.push(job);
      console.log('✓ Created job:', job.jobNumber, '-', job.name);

      // Create tasks (not yet started)
      const copyTask = {
        id: window.testRoles._genId('task'),
        templateId: 'copy',
        jobId: jobId,
        status: 'To Do',
        completed: false,
        assignedTo: copywriter?.id,
        order: 0
      };
      data.tasks.push(copyTask);
      console.log('✓ Assigned Copy task to', copywriter?.name);

      const mediaTask = {
        id: window.testRoles._genId('task'),
        templateId: 'media',
        jobId: jobId,
        status: 'To Do',
        completed: false,
        assignedTo: designer?.id,
        order: 1
      };
      data.tasks.push(mediaTask);
      console.log('✓ Assigned Media task to', designer?.name);

      project.jobCount = 1;
      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ PM WORKFLOW COMPLETE');
      return { success: true, project, job, copyTask, mediaTask };
    }
  },

  // ========================================
  // COPYWRITER WORKFLOW TEST
  // ========================================
  copywriter: {
    name: 'Copywriter Workflow',
    description: 'View assigned tasks, write copy, mark complete',

    run(jobNumber) {
      const data = window.testRoles._getData();
      const copywriter = window.testRoles._findPerson(data, 'Copywriter');

      console.log('✍️ COPYWRITER WORKFLOW TEST');
      console.log('Testing as:', copywriter?.name);
      console.log('');

      // Find job with To Do copy task assigned to copywriter
      let job, copyTask;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        // Find any job with incomplete copy task
        for (const j of data.jobs) {
          const task = data.tasks.find(t =>
            t.jobId === j.id &&
            t.templateId === 'copy' &&
            t.assignedTo === copywriter?.id &&
            !t.completed
          );
          if (task) {
            job = j;
            copyTask = task;
            break;
          }
        }
      }

      if (!job) {
        console.log('⚠️ No pending copy tasks found for', copywriter?.name);
        return { success: false, error: 'No pending tasks' };
      }

      copyTask = copyTask || data.tasks.find(t => t.jobId === job.id && t.templateId === 'copy');
      console.log('✓ Found task for job:', job.jobNumber, '-', job.name);

      // Simulate working on copy
      const taskIndex = data.tasks.findIndex(t => t.id === copyTask.id);
      data.tasks[taskIndex] = {
        ...copyTask,
        status: 'Done',
        completed: true,
        content: 'Introducing the future of technology. Experience innovation like never before.',
        characterCount: 78,
        completedAt: new Date().toISOString()
      };
      console.log('✓ Completed copy task with content');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ COPYWRITER WORKFLOW COMPLETE');
      return { success: true, job, task: data.tasks[taskIndex] };
    }
  },

  // ========================================
  // DESIGNER WORKFLOW TEST
  // ========================================
  designer: {
    name: 'Designer Workflow',
    description: 'View assigned tasks, create media, mark complete',

    run(jobNumber) {
      const data = window.testRoles._getData();
      const designer = window.testRoles._findPerson(data, 'Designer');

      console.log('🎨 DESIGNER WORKFLOW TEST');
      console.log('Testing as:', designer?.name);
      console.log('');

      // Find job with To Do media task assigned to designer
      let job, mediaTask;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        for (const j of data.jobs) {
          const task = data.tasks.find(t =>
            t.jobId === j.id &&
            t.templateId === 'media' &&
            t.assignedTo === designer?.id &&
            !t.completed
          );
          if (task) {
            job = j;
            mediaTask = task;
            break;
          }
        }
      }

      if (!job) {
        console.log('⚠️ No pending media tasks found for', designer?.name);
        return { success: false, error: 'No pending tasks' };
      }

      mediaTask = mediaTask || data.tasks.find(t => t.jobId === job.id && t.templateId === 'media');
      console.log('✓ Found task for job:', job.jobNumber, '-', job.name);

      // Simulate completing media
      const taskIndex = data.tasks.findIndex(t => t.id === mediaTask.id);
      data.tasks[taskIndex] = {
        ...mediaTask,
        status: 'Done',
        completed: true,
        fileUrl: 'launch-video-final.mp4',
        fileType: 'video',
        completedAt: new Date().toISOString()
      };
      console.log('✓ Completed media task with file upload');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ DESIGNER WORKFLOW COMPLETE');
      return { success: true, job, task: data.tasks[taskIndex] };
    }
  },

  // ========================================
  // CD WORKFLOW TEST (Internal Review)
  // ========================================
  cd: {
    name: 'CD Workflow',
    description: 'Review completed work, approve or reject with feedback',

    approve(jobNumber) {
      const data = window.testRoles._getData();
      const cd = window.testRoles._findPerson(data, 'CD');

      console.log('👔 CD WORKFLOW TEST - APPROVE');
      console.log('Testing as:', cd?.name);
      console.log('');

      // Find job ready for internal review (In Progress with completed tasks)
      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j => {
          if (j.status !== 'In Progress') return false;
          const tasks = data.tasks.filter(t => t.jobId === j.id);
          const copyDone = tasks.find(t => t.templateId === 'copy')?.completed;
          const mediaDone = tasks.find(t => t.templateId === 'media')?.completed;
          return copyDone && mediaDone;
        });
      }

      if (!job) {
        console.log('⚠️ No jobs ready for internal review');
        return { success: false, error: 'No jobs ready for review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);

      // Approve - move to In Review (for client)
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        status: 'In Review',
        internalApprovedBy: cd?.id,
        internalApprovedAt: new Date().toISOString()
      };
      console.log('✓ Approved - moved to Client Review');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CD APPROVAL COMPLETE');
      return { success: true, job: data.jobs[jobIndex] };
    },

    reject(jobNumber, feedback = 'Please revise the creative direction') {
      const data = window.testRoles._getData();
      const cd = window.testRoles._findPerson(data, 'CD');
      const copywriter = window.testRoles._findPerson(data, 'Copywriter');
      const designer = window.testRoles._findPerson(data, 'Designer');

      console.log('👔 CD WORKFLOW TEST - REJECT WITH FEEDBACK');
      console.log('Testing as:', cd?.name);
      console.log('');

      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j => {
          if (j.status !== 'In Progress') return false;
          const tasks = data.tasks.filter(t => t.jobId === j.id);
          const copyDone = tasks.find(t => t.templateId === 'copy')?.completed;
          const mediaDone = tasks.find(t => t.templateId === 'media')?.completed;
          return copyDone && mediaDone;
        });
      }

      if (!job) {
        console.log('⚠️ No jobs ready for internal review');
        return { success: false, error: 'No jobs ready for review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);
      console.log('✓ Feedback:', feedback);

      // Reject - mark tasks incomplete with feedback
      const tasks = data.tasks.filter(t => t.jobId === job.id);
      tasks.forEach(task => {
        const idx = data.tasks.findIndex(t => t.id === task.id);
        data.tasks[idx] = {
          ...task,
          completed: false,
          status: 'In Progress',
          internalFeedback: feedback,
          feedbackBy: cd?.id,
          feedbackAt: new Date().toISOString()
        };
      });
      console.log('✓ Tasks marked for revision with feedback');

      // Update job
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        internalFeedback: feedback,
        internalFeedbackBy: cd?.id,
        internalFeedbackAt: new Date().toISOString()
      };

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CD REJECTION COMPLETE - Tasks sent back for revision');
      return { success: true, job: data.jobs[jobIndex], feedback };
    }
  },

  // ========================================
  // CLIENT WORKFLOW TEST (External Review)
  // ========================================
  client: {
    name: 'Client Workflow',
    description: 'Review work in Client Review tab, approve or reject',

    approve(jobNumber) {
      const data = window.testRoles._getData();
      const client = window.testRoles._findPerson(data, 'Client');

      console.log('🤝 CLIENT WORKFLOW TEST - APPROVE');
      console.log('Testing as:', client?.name);
      console.log('');

      // Find job in Client Review
      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j =>
          j.status === 'In Review' &&
          j.assignments?.Client === client?.id
        );
      }

      if (!job) {
        console.log('⚠️ No jobs in Client Review');
        return { success: false, error: 'No jobs in Client Review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);

      // Approve
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        status: 'Approved (External)',
        clientApprovedBy: client?.id,
        clientApprovedAt: new Date().toISOString()
      };
      console.log('✓ Client approved - Job complete!');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CLIENT APPROVAL COMPLETE');
      return { success: true, job: data.jobs[jobIndex] };
    },

    reject(jobNumber, feedback = 'Please adjust the messaging to be more brand-aligned') {
      const data = window.testRoles._getData();
      const client = window.testRoles._findPerson(data, 'Client');
      const cd = window.testRoles._findPerson(data, 'CD');

      console.log('🤝 CLIENT WORKFLOW TEST - REJECT WITH FEEDBACK');
      console.log('Testing as:', client?.name);
      console.log('');

      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j =>
          j.status === 'In Review' &&
          j.assignments?.Client === client?.id
        );
      }

      if (!job) {
        console.log('⚠️ No jobs in Client Review');
        return { success: false, error: 'No jobs in Client Review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);
      console.log('✓ Feedback:', feedback);

      // Reject - assign feedback to CD
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        status: 'In Progress',
        clientFeedback: feedback,
        clientFeedbackDate: new Date().toISOString(),
        clientFeedbackBy: client?.id,
        clientFeedbackAssignedTo: cd?.id,
        clientFeedbackStatus: 'pending'
      };
      console.log('✓ Feedback assigned to CD:', cd?.name);

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CLIENT REJECTION COMPLETE - CD will address feedback');
      return { success: true, job: data.jobs[jobIndex], feedback };
    }
  },

  // ========================================
  // TRAFFIC WORKFLOW TEST
  // ========================================
  traffic: {
    name: 'Traffic Workflow',
    description: 'View all jobs, manage workflow, reassign tasks',

    viewAll() {
      const data = window.testRoles._getData();
      const traffic = window.testRoles._findPerson(data, 'Traffic');

      console.log('🚦 TRAFFIC WORKFLOW TEST - VIEW ALL');
      console.log('Testing as:', traffic?.name);
      console.log('');

      const jobsByStatus = {};
      data.jobs.forEach(job => {
        if (!jobsByStatus[job.status]) jobsByStatus[job.status] = [];
        jobsByStatus[job.status].push(job);
      });

      console.log('Jobs by Status:');
      Object.entries(jobsByStatus).forEach(([status, jobs]) => {
        console.log(`  ${status}: ${jobs.length} jobs`);
      });

      console.log('');
      console.log('✅ TRAFFIC VIEW COMPLETE');
      return { success: true, jobsByStatus };
    },

    reassign(jobNumber, role, newPersonId) {
      const data = window.testRoles._getData();
      const traffic = window.testRoles._findPerson(data, 'Traffic');

      console.log('🚦 TRAFFIC WORKFLOW TEST - REASSIGN');
      console.log('Testing as:', traffic?.name);
      console.log('');

      const job = data.jobs.find(j => j.jobNumber === jobNumber);
      if (!job) {
        console.log('⚠️ Job not found:', jobNumber);
        return { success: false, error: 'Job not found' };
      }

      const newPerson = data.people.find(p => p.id === newPersonId);
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);

      data.jobs[jobIndex] = {
        ...job,
        assignments: {
          ...job.assignments,
          [role]: newPersonId
        }
      };
      console.log('✓ Reassigned', role, 'to', newPerson?.name);

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ TRAFFIC REASSIGN COMPLETE');
      return { success: true, job: data.jobs[jobIndex] };
    }
  },

  // ========================================
  // FULL WORKFLOW TEST - All roles in sequence
  // ========================================
  runAll() {
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('           FULL MULTI-ROLE WORKFLOW TEST');
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('');

    const results = [];

    // Step 1: PM creates brief
    console.log('━━━ STEP 1/6: PM Creates Brief ━━━');
    const pmResult = this.pm.run();
    results.push({ role: 'PM', ...pmResult });
    console.log('');

    if (!pmResult.success) return { success: false, results };

    const jobNumber = pmResult.job.jobNumber;

    // Step 2: Copywriter completes copy
    console.log('━━━ STEP 2/6: Copywriter Completes Copy ━━━');
    const copyResult = this.copywriter.run(jobNumber);
    results.push({ role: 'Copywriter', ...copyResult });
    console.log('');

    // Step 3: Designer completes media
    console.log('━━━ STEP 3/6: Designer Completes Media ━━━');
    const designResult = this.designer.run(jobNumber);
    results.push({ role: 'Designer', ...designResult });
    console.log('');

    // Step 4: CD approves internal review
    console.log('━━━ STEP 4/6: CD Approves Internal Review ━━━');
    const cdResult = this.cd.approve(jobNumber);
    results.push({ role: 'CD', ...cdResult });
    console.log('');

    // Step 5: Client approves
    console.log('━━━ STEP 5/6: Client Approves ━━━');
    const clientResult = this.client.approve(jobNumber);
    results.push({ role: 'Client', ...clientResult });
    console.log('');

    // Step 6: Traffic views all
    console.log('━━━ STEP 6/6: Traffic Views Dashboard ━━━');
    const trafficResult = this.traffic.viewAll();
    results.push({ role: 'Traffic', ...trafficResult });
    console.log('');

    // Summary
    const allPassed = results.every(r => r.success);
    console.log('═══════════════════════════════════════════════════════════════');
    console.log(allPassed ? '✅ ALL ROLE WORKFLOWS PASSED' : '❌ SOME WORKFLOWS FAILED');
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('');
    console.log('Results:');
    results.forEach(r => {
      console.log(`  ${r.success ? '✓' : '✗'} ${r.role}`);
    });

    return { success: allPassed, results, jobNumber };
  },

  // Run rejection flow test
  runRejectionFlow() {
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('           REJECTION FLOW TEST');
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('');

    // Create job and complete tasks
    const pmResult = this.pm.run();
    const jobNumber = pmResult.job.jobNumber;
    this.copywriter.run(jobNumber);
    this.designer.run(jobNumber);

    // CD rejects
    console.log('━━━ CD REJECTS WITH FEEDBACK ━━━');
    this.cd.reject(jobNumber, 'Need more energy in the copy and brighter visuals');

    // Copywriter and Designer revise
    console.log('━━━ TEAM REVISES ━━━');
    this.copywriter.run(jobNumber);
    this.designer.run(jobNumber);

    // CD approves
    console.log('━━━ CD APPROVES REVISION ━━━');
    this.cd.approve(jobNumber);

    // Client rejects
    console.log('━━━ CLIENT REJECTS WITH FEEDBACK ━━━');
    this.client.reject(jobNumber, 'Logo needs to be more prominent');

    console.log('');
    console.log('✅ REJECTION FLOW TEST COMPLETE');
    console.log('Client feedback is now assigned to CD for action');

    return { success: true, jobNumber };
  }
};

// Auto-run tests based on URL parameters
// ?runtest=true     - Basic PM workflow test
// ?roletest=true    - Full multi-role workflow test
// ?roletest=reject  - Rejection flow test
// ?roletest=pm      - PM only
// ?roletest=cd      - CD approval only
// ?roletest=client  - Client approval only
(function() {
  const params = new URLSearchParams(window.location.search);

  if (params.get('runtest') === 'true') {
    setTimeout(() => {
      console.log('🔄 Auto-running PM workflow test...');
      const result = window.testPMWorkflow.full();
      if (result.success) {
        showBanner('✅ PM WORKFLOW TEST PASSED - Job "' + result.job.jobNumber + '" is in Client Review', '#4caf50');
      }
    }, 1000);
  }

  if (params.has('roletest')) {
    setTimeout(() => {
      const testType = params.get('roletest');

      if (testType === 'true' || testType === 'all') {
        console.log('🔄 Auto-running FULL multi-role workflow test...');
        const result = window.testRoles.runAll();
        if (result.success) {
          showBanner('✅ ALL ROLE TESTS PASSED - Job "' + result.jobNumber + '" completed full workflow', '#4caf50');
        } else {
          showBanner('❌ SOME ROLE TESTS FAILED - Check console for details', '#f44336');
        }
      } else if (testType === 'reject') {
        console.log('🔄 Auto-running rejection flow test...');
        const result = window.testRoles.runRejectionFlow();
        showBanner('✅ REJECTION FLOW TEST COMPLETE - Job "' + result.jobNumber + '"', '#ff9800');
      } else if (testType === 'pm') {
        const result = window.testRoles.pm.run();
        showBanner('✅ PM TEST - Created job "' + result.job?.jobNumber + '"', '#3b82f6');
      } else if (testType === 'copywriter') {
        const result = window.testRoles.copywriter.run();
        showBanner(result.success ? '✅ COPYWRITER TEST PASSED' : '⚠️ No pending copy tasks', result.success ? '#4caf50' : '#ff9800');
      } else if (testType === 'designer') {
        const result = window.testRoles.designer.run();
        showBanner(result.success ? '✅ DESIGNER TEST PASSED' : '⚠️ No pending media tasks', result.success ? '#4caf50' : '#ff9800');
      } else if (testType === 'cd') {
        const result = window.testRoles.cd.approve();
        showBanner(result.success ? '✅ CD APPROVAL - Job in Client Review' : '⚠️ No jobs ready for review', result.success ? '#4caf50' : '#ff9800');
      } else if (testType === 'client') {
        const result = window.testRoles.client.approve();
        showBanner(result.success ? '✅ CLIENT APPROVED - Workflow complete!' : '⚠️ No jobs in Client Review', result.success ? '#4caf50' : '#ff9800');
      }
    }, 1000);
  }

  function showBanner(text, color) {
    const banner = document.createElement('div');
    banner.innerHTML = text;
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:' + color + ';color:white;padding:12px;text-align:center;font-weight:bold;z-index:9999;font-family:system-ui;box-shadow:0 2px 8px rgba(0,0,0,0.2)';
    document.body.prepend(banner);
    console.log('');
    console.log(text);
  }
})();

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<App />);
