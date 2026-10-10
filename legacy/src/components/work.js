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
  // DB-03 fix: Default to "My Work" for non-admin roles (Copywriter, Designer, etc.)
  const workPermissions = getUserPermissions(currentUser);
  const [filterMode, setFilterMode] = useState(workPermissions.canViewAll ? 'all' : 'mine');
  const [selectedProjectId, setSelectedProjectId] = useState(null);
  const [statusFilter, setStatusFilter] = useState('all');
  // E2E-11: Task-level status filter (Jobs sub-tab only)
  const [taskStatusFilter, setTaskStatusFilter] = useState('all');
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

    // E2E-11: Filter by task status (Jobs sub-tab only)
    if (activeSubTab === 'Jobs' && taskStatusFilter !== 'all') {
      items = items.filter(job => {
        const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id);
        return jobTasks.some(t => t.status === taskStatusFilter);
      });
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
    setTaskStatusFilter('all');
  };
  const hasActiveFilters = filterMode !== 'all' || selectedProjectId || statusFilter !== 'all' || taskStatusFilter !== 'all';
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
  }, status))), activeSubTab === 'Jobs' && /*#__PURE__*/React.createElement("select", {
    className: "filter-select",
    value: taskStatusFilter,
    onChange: e => setTaskStatusFilter(e.target.value),
    title: "Filter by task status"
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Task Statuses"), ['Not Started', 'To Do', 'In Progress', 'In Review', 'Waiting', 'Done', 'On Hold'].map(s => /*#__PURE__*/React.createElement("option", {
    key: s,
    value: s
  }, "Task: ", s))), hasActiveFilters && /*#__PURE__*/React.createElement("button", {
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
