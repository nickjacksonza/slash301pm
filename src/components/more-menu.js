// ============================================================================
// MORE MENU COMPONENT (Phase 2 - Overflow Menu for Secondary Items)
// ============================================================================
//
// A dropdown menu that contains secondary navigation items:
// - People directory
// - Wiki
// - Operations dashboard (role-restricted)
// - Settings (future)
//
// Features:
// - Dropdown behavior with click-outside-to-close
// - Role-based visibility for Operations
// - Active state indicator
// ============================================================================

const MoreMenu = ({
  data,
  currentUser,
  activeItem,
  onItemSelect
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [dropdownPos, setDropdownPos] = useState({ top: 0, left: 0 });
  const menuRef = useRef(null);
  const triggerRef = useRef(null);

  // Close menu when clicking outside
  useEffect(() => {
    const handleClickOutside = event => {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Menu items with role-based visibility
  const menuItems = [{
    id: 'People',
    label: 'People',
    icon: '○',
    count: data.people.length,
    visible: true
  }, {
    id: 'Wiki',
    label: 'Wiki',
    icon: '▤',
    count: (data.wikiPages || []).length,
    visible: true
  }, {
    id: 'Operations',
    label: 'Operations',
    icon: '▦',
    count: null,
    visible: canAccessOperations(currentUser)
  }];
  const visibleItems = menuItems.filter(item => item.visible);
  const isActiveInMore = visibleItems.some(item => item.id === activeItem);
  const handleItemClick = itemId => {
    onItemSelect(itemId);
    setIsOpen(false);
  };
  const handleToggle = () => {
    if (!isOpen && triggerRef.current) {
      const rect = triggerRef.current.getBoundingClientRect();
      setDropdownPos({ top: rect.bottom + 4, left: rect.left });
    }
    setIsOpen(!isOpen);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "more-menu",
    ref: menuRef
  }, /*#__PURE__*/React.createElement("button", {
    className: `more-menu-trigger ${isOpen ? 'open' : ''} ${isActiveInMore ? 'active' : ''}`,
    ref: triggerRef,
    onClick: handleToggle
  }, /*#__PURE__*/React.createElement("span", {
    className: "more-icon"
  }, "\u22EF"), "More", /*#__PURE__*/React.createElement("span", {
    className: "more-arrow"
  }, isOpen ? '▲' : '▼')), isOpen && /*#__PURE__*/React.createElement("div", {
    className: "more-menu-dropdown",
    style: { position: 'fixed', top: dropdownPos.top, left: dropdownPos.left }
  }, visibleItems.map(item => /*#__PURE__*/React.createElement("button", {
    key: item.id,
    className: `more-menu-item ${activeItem === item.id ? 'active' : ''}`,
    onClick: () => handleItemClick(item.id)
  }, /*#__PURE__*/React.createElement("span", {
    className: "menu-item-icon"
  }, item.icon), /*#__PURE__*/React.createElement("span", {
    className: "menu-item-label"
  }, item.label), item.count !== null && /*#__PURE__*/React.createElement("span", {
    className: "menu-item-count"
  }, item.count)))));
};
