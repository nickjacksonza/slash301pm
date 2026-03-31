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
