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

  // #28-29/#30: Inline validation errors + no-client confirmation state
  const [errors, setErrors] = useState({});
  const [showNoClientConfirm, setShowNoClientConfirm] = useState(false);

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
    // #28-29/#30: Collect all validation errors upfront (no alert/confirm)
    const newErrors = {};

    if (!name.trim()) {
      newErrors.name = 'Job name is required';
    } else if (name.trim().length > 200) {
      newErrors.name = 'Job name must be 200 characters or less';
    }

    if (!dueDate) {
      newErrors.dueDate = 'Delivery date is required';
    } else if (briefDate && dueDate < briefDate) {
      newErrors.dueDate = 'Delivery date must be on or after the brief date';
    }

    if (!projectId && newProjectName && newProjectName.trim().length > 100) {
      newErrors.newProjectName = 'Project name must be 100 characters or less';
    }

    if (hoursEstimate !== '' && (isNaN(parseFloat(hoursEstimate)) || parseFloat(hoursEstimate) < 0)) {
      newErrors.hoursEstimate = 'Hours estimate must be a positive number';
    }

    // BUG-05/14 fix: Validate required team assignments
    const requiredRoles = ['PM', 'Copywriter', 'Designer', 'CD'];
    const missingRequired = requiredRoles.filter(role => !assignments[role]);
    if (missingRequired.length > 0) {
      newErrors.assignments = `Please assign: ${missingRequired.join(', ')}`;
    }

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    setErrors({});

    // Warn about missing Client inline (no browser confirm)
    if (!assignments['Client'] && !showNoClientConfirm) {
      setShowNoClientConfirm(true);
      return;
    }
    setShowNoClientConfirm(false);

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
    if (!targetProjectId) {
      setErrors({ project: 'Please select or create a project' });
      return;
    }

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
    setErrors({});
    setShowNoClientConfirm(false);
  };
  if (!isOpen) return null;

  // Key roles for quick assignment (BUG-14: Client added; DB-04: QA added)
  const KEY_ROLES = ['PM', 'Traffic', 'Copywriter', 'Designer', 'CD', 'QA', 'Client', 'ECD'];
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
    onChange: e => { setName(e.target.value); if (errors.name) setErrors(prev => ({...prev, name: null})); },
    placeholder: "e.g., Summer Campaign Video"
  }), errors.name && /*#__PURE__*/React.createElement("p", {className: "form-error"}, errors.name))), /*#__PURE__*/React.createElement("div", {
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
    onChange: e => { setDueDate(e.target.value); if (errors.dueDate) setErrors(prev => ({...prev, dueDate: null})); }
  }), errors.dueDate && /*#__PURE__*/React.createElement("p", {className: "form-error"}, errors.dueDate)))), /*#__PURE__*/React.createElement(CollapsibleSection, {
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
  }, p.name)))))))), errors.assignments && /*#__PURE__*/React.createElement("p", {className: "form-error form-error-section"}, errors.assignments), /*#__PURE__*/React.createElement(CollapsibleSection, {
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
  }, errors.project && /*#__PURE__*/React.createElement("p", {className: "form-error form-error-section"}, errors.project), showNoClientConfirm ? /*#__PURE__*/React.createElement("div", {
    className: "form-warning-banner"
  }, /*#__PURE__*/React.createElement("p", null, "No Client reviewer assigned. The review workflow requires a Client."), /*#__PURE__*/React.createElement("div", {
    className: "form-warning-actions"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary btn-sm",
    onClick: () => setShowNoClientConfirm(false)
  }, "Go Back"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-warning btn-sm",
    onClick: handleSubmit
  }, "Continue Anyway"))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSubmit
  }, "Create Brief")))));
};

// ============================================================================
// TASK LIST COMPONENT
// ============================================================================
