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
    className: "ops-metric-icon",
    role: "img",
    "aria-label": "Clients"
  }, "\u25CB"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-content"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-value"
  }, metrics.clientCount), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-label"
  }, "Clients"))), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-card campaigns"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-icon",
    role: "img",
    "aria-label": "Campaigns"
  }, "\u25CE"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-content"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-value"
  }, metrics.campaignCount), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-label"
  }, "Campaigns"))), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-card jobs"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-icon",
    role: "img",
    "aria-label": "Jobs"
  }, "\u25C8"), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-content"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-value"
  }, metrics.jobCount), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-label"
  }, "Jobs"))), /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-card hours"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ops-metric-icon",
    role: "img",
    "aria-label": "Hours"
  }, "\u25D4"), /*#__PURE__*/React.createElement("div", {
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
  }, metrics.roleCapacity[role].count, metrics.roleCapacity[role].count === 1 ? " person" : " people")), /*#__PURE__*/React.createElement("div", {
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

// Check if user can access Client Review (Client role + Traffic managers for oversight)
const canAccessClientReview = user => {
  if (!user || !user.role) return false;
  const clientReviewRoles = ['Client', 'Traffic', 'COO'];
  return clientReviewRoles.includes(user.role);
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

  // BUG-06 fix: Get jobs pending client review
  // Clients see only their assigned jobs; Traffic/COO see all jobs in client review
  const pendingReviewJobs = useMemo(() => {
    if (!currentUser) return [];
    const clientReviewStatuses = ['Approved (Internal)', 'In Review'];
    if (currentUser.role === 'Client') {
      // DB-05 fix: Scope by assignment AND by brand if the client has a brand set
      return data.jobs.filter(job => {
        if (!clientReviewStatuses.includes(job.status)) return false;
        if (job.assignments?.Client !== currentUser.id) return false;
        // Brand safety net: if client has a brand, only show jobs from matching projects
        if (currentUser.brand) {
          const project = data.projects.find(p => p.id === job.projectId);
          if (project && project.client !== currentUser.brand) return false;
        }
        return true;
      });
    }
    // Traffic/COO can see all jobs pending client review
    return data.jobs.filter(job => clientReviewStatuses.includes(job.status));
  }, [data.jobs, data.projects, currentUser?.id, currentUser?.role, currentUser?.brand]);

  // Shared data-lookup helpers (defined in utils.js)
  const { getJobTasks, getProject } = makeDataHelpers(data);

  // Handle approval
  const handleApprove = job => {
    api.approveClient(job.id).then(() => api.refreshInto(dispatch)).then(() => {
      setApprovedJobs(prev => new Set([...prev, job.id]));
    }).catch(err => {
      alert('Approval failed: ' + (err.message || 'unknown error'));
    });
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
    // WF-07 fix: Reset Copy/Media tasks to In Progress on rejection too
    const jobTasks = getJobTasks(job.id);
    const copyTask = jobTasks.find(t => t.templateId === 'copy');
    const mediaTask = jobTasks.find(t => t.templateId === 'media');
    if (copyTask && copyTask.status === 'Done') {
      dispatch({ type: 'UPDATE_TASK', payload: { ...copyTask, status: 'In Progress' } });
    }
    if (mediaTask && mediaTask.status === 'Done') {
      dispatch({ type: 'UPDATE_TASK', payload: { ...mediaTask, status: 'In Progress' } });
    }
    setRejectedJobs(prev => new Set([...prev, job.id]));
  };

  // Handle rejection with feedback - assigns to CD
  const handleRejectWithFeedback = job => {
    // Find the CD assigned to this job, or fall back to any CD in the system
    const assignedCD = job.assignments?.CD;
    const fallbackCD = data.people.find(p => p.role === 'CD')?.id;
    const feedbackAssignee = assignedCD || fallbackCD;
    api.rejectWithFeedback(job.id, feedbackText, false).then(() => api.refreshInto(dispatch)).then(fresh => {
      // The server stores the feedback and resets the job and tasks; the CD assignment is legacy-only
      const freshJob = (fresh.jobs || []).find(j => j.id === job.id);
      if (freshJob && feedbackAssignee) {
        dispatch({
          type: 'UPDATE_JOB',
          payload: { ...freshJob, clientFeedbackAssignedTo: feedbackAssignee }
        });
      }
      setRejectedJobs(prev => new Set([...prev, job.id]));
      setFeedbackJobId(null);
      setFeedbackText('');
    }).catch(err => {
      alert('Rejection failed: ' + (err.message || 'unknown error'));
    });
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
  }, /*#__PURE__*/React.createElement("h1", null, "Client Review"), /*#__PURE__*/React.createElement("p", {
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

// Check if user can access Job Review (internal reviewers + Traffic for oversight)
const canAccessJobReview = user => {
  if (!user || !user.role) return false;
  const reviewerRoles = ['CD', 'ECD', 'Producer', 'PM', 'COO', 'Traffic'];
  return reviewerRoles.includes(user.role);
};
