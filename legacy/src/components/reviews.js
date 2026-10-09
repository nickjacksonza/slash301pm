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
  // WF-08: Inline notes before actioning feedback
  const [actionJobId, setActionJobId] = useState(null);
  const [actionNotes, setActionNotes] = useState('');

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

  // Handle Action & Submit - WF-08: show inline notes form first
  const handleActionAndSubmit = job => {
    setActionJobId(job.id);
    setActionNotes('');
  };

  // Confirm action with optional internal notes, then dispatch
  const handleConfirmAction = job => {
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        status: 'In Review',
        clientFeedbackStatus: 'actioned',
        clientFeedbackActionedBy: currentUser?.id,
        clientFeedbackActionedAt: new Date().toISOString(),
        internalFeedback: actionNotes.trim() || null,
        internalFeedbackBy: actionNotes.trim() ? currentUser?.id : null,
        internalFeedbackAt: actionNotes.trim() ? new Date().toISOString() : null
      }
    });
    setActionedFeedback(prev => new Set([...prev, job.id]));
    setActionJobId(null);
    setActionNotes('');
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

  // BUG-07 fix: Get jobs ready for internal review
  // Jobs where Copy and Media tasks are completed and NOT yet internally approved
  const reviewableJobs = useMemo(() => {
    // Active statuses that should allow internal review (not just 'In Progress')
    const reviewableStatuses = ['In Progress', 'Today', 'This Week', 'In Review'];
    return data.jobs.filter(job => {
      // Must be in an active status and NOT already internally approved
      if (!reviewableStatuses.includes(job.status)) return false;
      if (job.internalApprovedBy) return false; // Already approved internally

      // E2E-08 fix: CD only sees jobs where they are the assigned CD
      if (currentUser?.role === 'CD' && job.assignments?.CD !== currentUser.id) return false;

      // Check if Copy and Media tasks exist and are completed
      const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id);
      const copyTask = jobTasks.find(t => t.templateId === 'copy');
      const mediaTask = jobTasks.find(t => t.templateId === 'media');

      // Both Copy and Media must be completed for internal review
      const copyComplete = copyTask?.status === 'Done';
      const mediaComplete = mediaTask?.status === 'Done';
      return copyComplete && mediaComplete;
    });
  }, [data.jobs, data.tasks, currentUser?.id, currentUser?.role]);

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

  // Shared data-lookup helpers (defined in utils.js)
  const { getJobTasks, getProject, getPerson } = makeDataHelpers(data);

  // BUG-05/06 fix: Handle internal approval - moves to "Approved (Internal)" for client review
  // Also gates on Client assignment existing
  const handleApprove = job => {
    // BUG-05: Block approval if no Client is assigned
    if (!job.assignments?.Client) {
      alert('Cannot approve: No client reviewer is assigned to this job. Please assign a client in the job details before approving.');
      return;
    }
    dispatch({
      type: 'UPDATE_JOB',
      payload: {
        ...job,
        status: 'Approved (Internal)',
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
    }, "\u25C9"), /*#__PURE__*/React.createElement("h2", null, "Access Restricted"), /*#__PURE__*/React.createElement("p", null, "Job Review is available to CDs, ECDs, Producers, PMs, Traffic, and COO roles."));
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
    }, actionJobId === job.id ? /*#__PURE__*/React.createElement("div", {
      className: "action-inline-form"
    }, /*#__PURE__*/React.createElement("label", {
      className: "action-notes-label"
    }, "What did you change? (optional)"), /*#__PURE__*/React.createElement("textarea", {
      className: "action-notes-input",
      value: actionNotes,
      onChange: e => setActionNotes(e.target.value),
      placeholder: "Describe what was actioned (shown as internal notes)...",
      rows: 3
    }), /*#__PURE__*/React.createElement("div", {
      className: "action-inline-buttons"
    }, /*#__PURE__*/React.createElement("button", {
      className: "btn btn-secondary btn-sm",
      onClick: () => { setActionJobId(null); setActionNotes(''); }
    }, "Cancel"), /*#__PURE__*/React.createElement("button", {
      className: "btn feedback-action-btn action-submit",
      onClick: () => handleConfirmAction(job)
    }, /*#__PURE__*/React.createElement("span", {
      className: "btn-icon"
    }, "\u21A9"), "Confirm & Submit to Client"))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
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
    }))))));
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
