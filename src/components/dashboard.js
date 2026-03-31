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
  // DB-01/DB-02 fix: COO/Traffic/Producer see all jobs, others see assigned only
  const permissions = getUserPermissions(currentUser);
  const myJobs = permissions.canViewAll
    ? jobs.filter(job => job.status !== 'Done' && job.status !== 'Archived')
    : jobs.filter(job => Object.values(job.assignments || {}).includes(currentUser?.id));

  // Group by status priority
  const todayJobs = myJobs.filter(j => j.status === 'Today' || j.status === 'In Progress');
  const thisWeekJobs = myJobs.filter(j => j.status === 'This Week');
  const inboxJobs = myJobs.filter(j => j.status === 'Inbox');

  // BUG-10 fix: Show approved jobs for Traffic/Admin roles
  const approvedStatuses = ['Approved (Internal)', 'Approved (External)'];
  const approvedJobs = (permissions.canViewAll)
    ? jobs.filter(j => approvedStatuses.includes(j.status))
    : myJobs.filter(j => approvedStatuses.includes(j.status));

  // WF-10 fix: Catch-all for jobs not in any of the above sections
  const categorizedIds = new Set([...todayJobs, ...thisWeekJobs, ...inboxJobs, ...approvedJobs].map(j => j.id));
  const otherJobs = myJobs.filter(j => !categorizedIds.has(j.id));

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
  })))), approvedJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "work-section approved",
    style: { borderLeft: '3px solid #4caf50' }
  }, /*#__PURE__*/React.createElement("h3", null, "Ready to Schedule (", approvedJobs.length, ")"), /*#__PURE__*/React.createElement("div", {
    className: "work-cards"
  }, approvedJobs.map(job => /*#__PURE__*/React.createElement(DashboardJobCard, {
    key: job.id,
    job: job,
    data: data,
    onClick: onJobClick
  })))), otherJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "work-section",
    style: { borderLeft: '3px solid #9e9e9e' }
  }, /*#__PURE__*/React.createElement("h3", null, "Other (", otherJobs.length, ")"), /*#__PURE__*/React.createElement("div", {
    className: "work-cards"
  }, otherJobs.map(job => /*#__PURE__*/React.createElement(DashboardJobCard, {
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
  // BUG-09 fix: Get task statuses for this job
  const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id);
  const copyTask = jobTasks.find(t => t.templateId === 'copy');
  const mediaTask = jobTasks.find(t => t.templateId === 'media');
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
  }, job.name), jobTasks.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "job-card-tasks",
    style: { display: 'flex', gap: '0.5rem', fontSize: '0.75rem', color: '#666', marginTop: '0.25rem' }
  }, copyTask && /*#__PURE__*/React.createElement("span", {
    style: { color: copyTask.status === 'Done' ? '#4caf50' : '#ff9800' }
  }, "Copy: ", copyTask.status), mediaTask && /*#__PURE__*/React.createElement("span", {
    style: { color: mediaTask.status === 'Done' ? '#4caf50' : '#ff9800' }
  }, "Media: ", mediaTask.status)), /*#__PURE__*/React.createElement("div", {
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
