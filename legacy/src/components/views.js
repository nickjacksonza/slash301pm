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
