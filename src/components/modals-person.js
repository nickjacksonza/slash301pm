const AddPersonModal = ({
  isOpen,
  onClose,
  dispatch
}) => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState('');
  const [color, setColor] = useState('#3b82f6');
  const [formError, setFormError] = useState('');
  const handleSubmit = () => {
    if (!name || !email || !role) {
      setFormError('Please fill in all required fields.');
      return;
    }
    setFormError('');
    const person = {
      id: generateId(),
      name,
      email,
      role,
      color
    };
    dispatch({
      type: 'ADD_PERSON',
      payload: person
    });
    setName('');
    setEmail('');
    setRole('');
    setColor('#3b82f6');
    onClose();
  };
  if (!isOpen) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "modal-overlay",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-content",
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-header"
  }, /*#__PURE__*/React.createElement("h2", null, "Add Person"), /*#__PURE__*/React.createElement("button", {
    className: "modal-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "modal-body"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Name *"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: name,
    onChange: e => setName(e.target.value),
    placeholder: "Full name"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Email *"), /*#__PURE__*/React.createElement("input", {
    type: "email",
    value: email,
    onChange: e => setEmail(e.target.value),
    placeholder: "email@example.com"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Role *"), /*#__PURE__*/React.createElement("select", {
    value: role,
    onChange: e => setRole(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "Select a role"), ROLES.map(r => /*#__PURE__*/React.createElement("option", {
    key: r,
    value: r
  }, r)))), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Color"), /*#__PURE__*/React.createElement("input", {
    type: "color",
    value: color,
    onChange: e => setColor(e.target.value)
  })), formError && /*#__PURE__*/React.createElement("p", {
    className: "form-error"
  }, formError)), /*#__PURE__*/React.createElement("div", {
    className: "modal-footer"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSubmit
  }, "Add Person"))));
};

// ============================================================================
// USER SELECTOR COMPONENT
// ============================================================================

const UserSelector = ({
  people,
  currentUser,
  onUserChange
}) => {
  const [isOpen, setIsOpen] = useState(false);
  if (!currentUser) return null;
  const permissions = getUserPermissions(currentUser);
  const permissionLabel = permissions.level === 'superadmin' ? 'COO' : permissions.level === 'admin' ? 'Admin' : permissions.level === 'manager' ? 'Manager' : 'User';
  return /*#__PURE__*/React.createElement("div", {
    className: "user-selector"
  }, /*#__PURE__*/React.createElement("div", {
    className: "user-selector-trigger",
    onClick: () => setIsOpen(!isOpen)
  }, /*#__PURE__*/React.createElement(PersonAvatar, {
    person: currentUser,
    size: "small"
  }), /*#__PURE__*/React.createElement("div", {
    className: "user-selector-info"
  }, /*#__PURE__*/React.createElement("span", {
    className: "user-selector-name"
  }, currentUser.name), /*#__PURE__*/React.createElement("span", {
    className: "user-selector-role"
  }, permissionLabel)), /*#__PURE__*/React.createElement("span", {
    className: "user-selector-arrow"
  }, isOpen ? '▲' : '▼')), isOpen && /*#__PURE__*/React.createElement("div", {
    className: "user-selector-dropdown"
  }, /*#__PURE__*/React.createElement("div", {
    className: "user-selector-header"
  }, "Switch User"), people.map(person => {
    const perms = getUserPermissions(person);
    const label = perms.level === 'superadmin' ? 'COO' : perms.level === 'admin' ? 'Admin' : perms.level === 'manager' ? 'Manager' : 'User';
    return /*#__PURE__*/React.createElement("div", {
      key: person.id,
      className: `user-selector-option ${person.id === currentUser.id ? 'active' : ''}`,
      onClick: () => {
        onUserChange(person.id);
        setIsOpen(false);
      }
    }, /*#__PURE__*/React.createElement(PersonAvatar, {
      person: person,
      size: "small"
    }), /*#__PURE__*/React.createElement("div", {
      className: "user-option-info"
    }, /*#__PURE__*/React.createElement("span", {
      className: "user-option-name"
    }, person.name), /*#__PURE__*/React.createElement("span", {
      className: "user-option-role"
    }, person.role, " \u2022 ", label)));
  })));
};

// ============================================================================
// OPERATIONS DASHBOARD COMPONENT
// ============================================================================

// Check if user can access Operations dashboard (COO, PM, Traffic, ECD, CD, Producer)
