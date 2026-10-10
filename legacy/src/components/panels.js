const TaskList = ({
  jobId,
  data,
  dispatch,
  people,
  currentUser
}) => {
  const tasks = (data.tasks || []).filter(t => t.jobId === jobId);
  const [expandedTask, setExpandedTask] = useState(null);

  // #32: Debounce rapid saves -- text inputs use a 300ms trailing debounce
  const debounceTimer = useRef(null);
  const handleTaskUpdateDebounced = (task, updates) => {
    if (debounceTimer.current) clearTimeout(debounceTimer.current);
    debounceTimer.current = setTimeout(() => {
      dispatch({
        type: 'UPDATE_TASK',
        payload: { ...task, ...updates, editedBy: currentUser?.id, editedByRole: currentUser?.role }
      });
    }, 300);
  };

  // BUG-03/13 fix: Check if current user can edit tasks
  // Users with canEditJobs can edit any task; users assigned to the task can mark it done
  const job = data.jobs.find(j => j.id === jobId);
  const canEditTask = task => {
    const permissions = getUserPermissions(currentUser);
    // Admin/manager roles with full job edit access
    if (permissions.canEditJobs) return true;
    // Asset editors (COO)
    if (permissions.canEditAssets) return true;
    // Users assigned to this specific task can toggle its status
    if (task.assignedTo === currentUser?.id) return true;
    // Users assigned to the parent job who have canEditOwnStatus
    if (permissions.canEditOwnStatus && job && Object.values(job.assignments || {}).includes(currentUser?.id)) return true;
    return false;
  };
  const handleTaskStatusChange = (task, newStatus) => {
    const updates = {
      ...task,
      status: newStatus,
      completedAt: newStatus === 'Done' ? new Date().toISOString() : task.completedAt,
      editedBy: currentUser?.id,
      editedByRole: currentUser?.role
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
        ...updates,
        editedBy: currentUser?.id,
        editedByRole: currentUser?.role
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
      key: task.id + '-content',
      defaultValue: task.content || '',
      onChange: e => {
        if (canEdit) {
          handleTaskUpdateDebounced(task, {
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
      key: task.id + '-fileurl',
      defaultValue: task.fileUrl || '',
      onChange: e => {
        if (canEdit) {
          handleTaskUpdateDebounced(task, {
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
    }, "Completed: ", new Date(task.completedAt).toLocaleDateString()), task.internalFeedback && /*#__PURE__*/React.createElement("div", {
      className: "task-feedback-display",
      style: { marginTop: '0.5rem', padding: '0.5rem', borderRadius: '4px', backgroundColor: '#fff3e0', border: '1px solid #ffe0b2' }
    }, /*#__PURE__*/React.createElement("span", {
      style: { fontWeight: 600, fontSize: '0.75rem', color: '#e65100' }
    }, "Internal Feedback:"), /*#__PURE__*/React.createElement("p", {
      style: { margin: '0.25rem 0 0', fontSize: '0.85rem', fontStyle: 'italic' }
    }, task.internalFeedback))));
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
  const [panelError, setPanelError] = useState(null);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  useEffect(() => {
    if (item) {
      setEditedItem({
        ...item
      });
    }
  }, [item]);

  // BUG-01/02 fix: early return if item is null to prevent crash
  if (!isOpen || !item) return null;

  // Permission checks
  const canEditFull = canUserEditItem(currentUser, item, type, 'full');
  const canEditStatus = canUserEditItem(currentUser, item, type, 'status');
  const canDelete = canUserDelete(currentUser, item, type);
  const canAssignRoles = canUserAssignRoles(currentUser);
  const handleSave = () => {
    if (!editedItem) return;

    // Check if user has permission to save
    if (!canEditFull && !canEditStatus) {
      setPanelError('You do not have permission to edit this item.');
      return;
    }
    setPanelError(null);
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
      setPanelError('You do not have permission to delete this item.');
      return;
    }
    setPanelError(null);
    setShowDeleteConfirm(true);
  };
  const handleConfirmDelete = () => {
    setShowDeleteConfirm(false);
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
  if (!editedItem) return null;
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
        }, p.name))))))), (item.clientFeedback || item.internalFeedback) && /*#__PURE__*/React.createElement("div", {
          className: "feedback-banner",
          style: { margin: '0.75rem 0', padding: '0.75rem', borderRadius: '6px', border: '1px solid #e65100', backgroundColor: '#fff3e0' }
        }, /*#__PURE__*/React.createElement("h4", {
          style: { margin: '0 0 0.5rem', fontSize: '0.85rem', color: '#e65100' }
        }, "Feedback"), item.clientFeedback && /*#__PURE__*/React.createElement("div", {
          style: { marginBottom: item.internalFeedback ? '0.5rem' : 0 }
        }, /*#__PURE__*/React.createElement("span", {
          style: { fontWeight: 600, fontSize: '0.8rem', color: '#bf360c' }
        }, "Client: "), /*#__PURE__*/React.createElement("span", {
          style: { fontStyle: 'italic', fontSize: '0.85rem' }
        }, "\"", item.clientFeedback, "\"")), item.internalFeedback && /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("span", {
          style: { fontWeight: 600, fontSize: '0.8rem', color: '#e65100' }
        }, "Internal: "), /*#__PURE__*/React.createElement("span", {
          style: { fontStyle: 'italic', fontSize: '0.85rem' }
        }, "\"", item.internalFeedback, "\""))), /*#__PURE__*/React.createElement("div", {
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
  }, renderFields(), panelError && /*#__PURE__*/React.createElement("p", {
    className: "form-error",
    style: {margin: '1rem 0 0'}
  }, panelError), showDeleteConfirm && /*#__PURE__*/React.createElement("div", {
    className: "delete-confirm-inline"
  }, /*#__PURE__*/React.createElement("p", null, "Are you sure you want to delete this item? This cannot be undone."), /*#__PURE__*/React.createElement("div", {
    className: "delete-confirm-actions"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-danger",
    onClick: handleConfirmDelete
  }, "Yes, Delete"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: () => setShowDeleteConfirm(false)
  }, "Cancel")))), /*#__PURE__*/React.createElement("div", {
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
