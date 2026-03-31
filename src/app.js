// ============================================================================
// APP ENTRY POINT (Phase 3.3 - Auth Gate + 5-Tab Navigation)
// ============================================================================
//
// Auth flow:
// 1. On mount, call check_session to see if session exists
// 2. If no session (and not demo mode), show LoginScreen
// 3. On login success, load data from API and show the app
// 4. Logout destroys session and returns to login screen
//
// Navigation structure:
// 1. DASHBOARD - Role-based landing page
// 2. WORK - Unified Projects/Jobs/Assets view
// 3. CAPACITY - Traffic/PM workload view
// 4. REVIEWS - Unified Internal/Client review
// 5. MORE (dropdown) - People, Wiki, Operations
// ============================================================================

const {
  useRef
} = React;

// BUG-02 fix: Error Boundary to catch render crashes and show fallback UI
class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }
  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }
  componentDidCatch(error, errorInfo) {
    console.error('ErrorBoundary caught:', error, errorInfo);
  }
  render() {
    if (this.state.hasError) {
      return /*#__PURE__*/React.createElement("div", {
        style: { padding: '2rem', textAlign: 'center', color: '#666' }
      }, /*#__PURE__*/React.createElement("h2", null, "Something went wrong"),
      /*#__PURE__*/React.createElement("p", null, "An error occurred while rendering this view."),
      /*#__PURE__*/React.createElement("button", {
        className: "btn btn-primary",
        onClick: () => this.setState({ hasError: false, error: null }),
        style: { marginTop: '1rem' }
      }, "Try Again"),
      /*#__PURE__*/React.createElement("button", {
        className: "btn btn-secondary",
        onClick: () => window.location.reload(),
        style: { marginTop: '1rem', marginLeft: '0.5rem' }
      }, "Reload Page"));
    }
    return this.props.children;
  }
}

function App() {
  const [data, dispatch] = useReducer(dataReducer, null, loadFromStorage);
  const [activeTab, setActiveTab] = useState('Dashboard');
  const [viewMode, setViewMode] = useState('table'); // 'table' or 'kanban'
  const [searchQuery, setSearchQuery] = useState('');
  const [briefModalOpen, setBriefModalOpen] = useState(false);
  const [addPersonModalOpen, setAddPersonModalOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);
  const [detailPanelOpen, setDetailPanelOpen] = useState(false);
  const [storageError, setStorageError] = useState(null);
  const [importToast, setImportToast] = useState(null);
  const importToastTimer = useRef(null);

  // Phase 3.3: Auth state
  const [authState, setAuthState] = useState('checking'); // 'checking' | 'logged_in' | 'logged_out'
  const [authUser, setAuthUser] = useState(null); // User object from session
  const [demoMode, setDemoMode] = useState(false);
  const [apiLoaded, setApiLoaded] = useState(false);

  // Phase 3.3: Check session on mount
  useEffect(() => {
    if (typeof api === 'undefined') {
      // No API module -- fallback to localStorage (dev mode)
      setAuthState('logged_in');
      setApiLoaded(true);
      return;
    }

    // Register auth failure callback
    api._onAuthFailure = () => {
      setAuthState('logged_out');
      setAuthUser(null);
    };

    api.checkSession()
      .then(result => {
        if (result.authenticated && result.user) {
          setAuthUser(result.user);
          setAuthState('logged_in');
          setDemoMode(result.demo_mode || false);
          // Load data from API
          return api.loadAllData().then(apiData => {
            dispatch({ type: 'SET_DATA', payload: apiData });
            setApiLoaded(true);
            console.log('Data loaded from API (session restored)');
          });
        } else {
          setDemoMode(result.demo_mode || false);
          setAuthState('logged_out');

          // In demo mode, check_session returns the user list for the demo selector
          if (result.demo_mode && result.users) {
            const people = result.users.map(u => ({
              id: u.id, name: u.name, role: u.role,
              color: u.color, brand: u.brand_name || null,
            }));
            dispatch({ type: 'SET_DATA', payload: { ...loadFromStorage(), people } });
          }
        }
      })
      .catch(err => {
        console.warn('Session check failed:', err);
        setAuthState('logged_out');
      });
  }, []);

  // Handle login success
  const handleLoginSuccess = (user) => {
    setAuthUser(user);
    setAuthState('logged_in');

    // Load data from API after login
    if (typeof api !== 'undefined') {
      api.loadAllData()
        .then(apiData => {
          dispatch({ type: 'SET_DATA', payload: apiData });
          setApiLoaded(true);
          console.log('Data loaded from API (after login)');
        })
        .catch(err => {
          console.warn('API load failed after login:', err);
          setApiLoaded(true);
        });
    }
  };

  // Handle logout
  const handleLogout = async () => {
    if (typeof api !== 'undefined') {
      try {
        await api.logout();
      } catch (err) {
        console.warn('Logout API call failed:', err);
      }
    }
    setAuthUser(null);
    setAuthState('logged_out');
    setApiLoaded(false);
  };

  // Check for storage errors after data changes
  useEffect(() => {
    const error = getLastStorageError();
    if (error) {
      setStorageError(error);
      clearLastStorageError();
    }
  }, [data]);

  // Current user: in auth mode, use authUser. In demo mode, allow switching.
  const [currentUserId, setCurrentUserId] = useState(null);

  // Sync currentUserId with authUser
  useEffect(() => {
    if (authUser) {
      setCurrentUserId(authUser.id);
    }
  }, [authUser]);

  // BUG-16 fix: Memoize currentUser to provide stable reference across renders.
  const currentUser = useMemo(() => {
    if (!data?.people) return authUser;
    return data.people.find(p => p.id === currentUserId) || data.people[0];
  }, [data?.people, currentUserId, authUser]);

  // In demo mode, user switching goes through demo_login API
  const handleUserChange = async (userId) => {
    if (demoMode && typeof api !== 'undefined') {
      try {
        const result = await api.demoLogin(userId);
        if (result.user) {
          setAuthUser(result.user);
          setCurrentUserId(result.user.id);
          // Reload data for new user context
          const apiData = await api.loadAllData();
          dispatch({ type: 'SET_DATA', payload: apiData });
        }
      } catch (err) {
        console.error('Demo user switch failed:', err);
      }
    } else {
      setCurrentUserId(userId);
    }
  };

  // Show loading while checking auth
  if (authState === 'checking') {
    return React.createElement('div', { className: 'auth-loading' }, 'Loading...');
  }

  // Show login screen if not authenticated
  if (authState === 'logged_out') {
    return React.createElement(LoginScreen, {
      onLogin: handleLoginSuccess,
      demoMode: demoMode,
      people: data?.people || [],
    });
  }

  // Get permissions for current user
  const userPermissions = getUserPermissions(currentUser);
  const handleRowClick = item => {
    setSelectedItem(item);
    setDetailPanelOpen(true);
  };
  const showToast = (type, text) => {
    setImportToast({ type, text });
    if (importToastTimer.current) clearTimeout(importToastTimer.current);
    importToastTimer.current = setTimeout(() => setImportToast(null), 4000);
  };
  const handleExport = () => {
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], {
      type: 'application/json'
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `slash301pm-export-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
  };
  const handleImport = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = e => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = event => {
        try {
          const imported = JSON.parse(event.target.result);
          if (imported.people && imported.projects && imported.jobs && imported.assets) {
            dispatch({
              type: 'SET_DATA',
              payload: imported
            });
            showToast('success', 'Data imported successfully!');
          } else {
            showToast('error', 'Invalid file format');
          }
        } catch (err) {
          showToast('error', 'Failed to parse file: ' + err.message);
        }
      };
      reader.readAsText(file);
    };
    input.click();
  };

  // Check if user can access Reviews tab
  const canAccessReviews = canAccessJobReview(currentUser) || canAccessClientReview(currentUser);

  // Get tab counts for badges
  const getTabCount = tab => {
    switch (tab) {
      case 'Dashboard':
        return null;
      // No count for dashboard
      case 'Work':
        return data.jobs.length;
      case 'Capacity':
        return data.people.filter(p => p.role !== 'Client').length;
      case 'Reviews':
        {
        // BUG-06/07 fix: Count internal review items + client review items (broadened filters)
        const reviewableStatuses = ['In Progress', 'Today', 'This Week', 'In Review'];
        const internalCount = data.jobs.filter(j => {
          if (!reviewableStatuses.includes(j.status)) return false;
          if (j.internalApprovedBy) return false;
          const jobTasks = (data.tasks || []).filter(t => t.jobId === j.id);
          const copyComplete = jobTasks.find(t => t.templateId === 'copy')?.status === 'Done';
          const mediaComplete = jobTasks.find(t => t.templateId === 'media')?.status === 'Done';
          return copyComplete && mediaComplete;
        }).length;
        const clientReviewStatuses = ['Approved (Internal)', 'In Review'];
        // WF-11 fix: Traffic/COO see all client review jobs; Clients see only their assigned jobs
        const clientCount = (currentUser?.role === 'Traffic' || currentUser?.role === 'COO')
          ? data.jobs.filter(j => clientReviewStatuses.includes(j.status)).length
          : data.jobs.filter(j => clientReviewStatuses.includes(j.status) && j.assignments?.Client === currentUser?.id).length;
        return internalCount + clientCount;
        }
      default:
        return null;
    }
  };

  // Filter main tabs based on permissions
  const getVisibleMainTabs = () => {
    return MAIN_TABS.filter(tab => {
      // Hide Reviews if user has no access
      if (tab === 'Reviews' && !canAccessReviews) {
        return false;
      }
      // Hide Capacity from non-manager roles (optional - can be removed if you want all to see)
      // if (tab === 'Capacity' && !['COO', 'PM', 'Traffic', 'ECD', 'CD', 'Producer'].includes(currentUser?.role)) {
      //   return false;
      // }
      return true;
    });
  };

  // Check if active tab is in "More" menu
  const isMoreMenuActive = MORE_MENU_ITEMS.includes(activeTab);

  // Determine the detail panel type based on active tab
  // #22: Use explicit type field if present, then reliable structural checks
  // (avoids fragile projectId && !jobNumber pattern -- jobs also have projectId)
  const getDetailPanelType = () => {
    if (activeTab === 'Work') {
      if (!selectedItem) return 'Jobs';
      // explicit _type stamp takes priority (future-proofing)
      if (selectedItem._type) return selectedItem._type;
      // Jobs: only entity with a jobNumber
      if (selectedItem.jobNumber) return 'Jobs';
      // Assets: have displayName (set during creation) or a typed templateId+jobId combo
      if (selectedItem.displayName !== undefined) return 'Assets';
      // Projects: have a client field at top level (jobs/assets don't)
      if (selectedItem.client !== undefined) return 'Projects';
      return 'Jobs';
    }
    return activeTab;
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "app-container"
  }, /*#__PURE__*/React.createElement(StorageWarning, {
    error: storageError,
    onDismiss: () => setStorageError(null),
    onExport: handleExport
  }), /*#__PURE__*/React.createElement("header", {
    className: "app-header"
  }, /*#__PURE__*/React.createElement("div", {
    className: "header-left"
  }, /*#__PURE__*/React.createElement("h1", {
    className: "app-title"
  }, "Slash 301 PM")), /*#__PURE__*/React.createElement("div", {
    className: "header-center"
  }, /*#__PURE__*/React.createElement("div", {
    className: "search-box"
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Search...",
    value: searchQuery,
    onChange: e => setSearchQuery(e.target.value)
  }))), /*#__PURE__*/React.createElement("div", {
    className: "header-right"
  }, activeTab === 'Work' && /*#__PURE__*/React.createElement("div", {
    className: "view-toggle"
  }, /*#__PURE__*/React.createElement("button", {
    className: viewMode === 'table' ? 'active' : '',
    onClick: () => setViewMode('table'),
    title: "Table View"
  }, /*#__PURE__*/React.createElement("span", {
    className: "icon-table"
  }, "\u2630")), /*#__PURE__*/React.createElement("button", {
    className: viewMode === 'kanban' ? 'active' : '',
    onClick: () => setViewMode('kanban'),
    title: "Kanban View"
  }, /*#__PURE__*/React.createElement("span", {
    className: "icon-kanban"
  }, "\u25A6"))), userPermissions.level === 'superadmin' && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-icon",
    onClick: handleExport,
    title: "Export JSON"
  }, "\u2193"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-icon",
    onClick: handleImport,
    title: "Import JSON"
  }, "\u2191")), activeTab === 'People' && userPermissions.canEditPeople && /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: () => setAddPersonModalOpen(true)
  }, "+ Add Person"),
  // Phase 3.3: Show UserSelector in demo mode, logged-in user display otherwise
  demoMode ? /*#__PURE__*/React.createElement(UserSelector, {
    people: data.people,
    currentUser: currentUser,
    onUserChange: handleUserChange
  }) : /*#__PURE__*/React.createElement(React.Fragment, null,
    currentUser && /*#__PURE__*/React.createElement("div", {
      className: "user-selector",
      style: { cursor: 'default' }
    }, /*#__PURE__*/React.createElement("div", {
      className: "user-avatar",
      style: { backgroundColor: currentUser.color || '#3b82f6' }
    }, currentUser.name ? currentUser.name.split(' ').map(function(n){ return n[0]; }).join('') : '?'),
    /*#__PURE__*/React.createElement("div", {
      className: "user-info"
    }, /*#__PURE__*/React.createElement("div", {
      className: "user-name"
    }, currentUser.name), /*#__PURE__*/React.createElement("div", {
      className: "user-role"
    }, currentUser.role))),
    /*#__PURE__*/React.createElement("button", {
      className: "btn-logout",
      onClick: handleLogout,
      title: "Sign out"
    }, "Logout")
  ))), /*#__PURE__*/React.createElement("nav", {
    className: "tab-bar"
  }, getVisibleMainTabs().map(tab => /*#__PURE__*/React.createElement("button", {
    key: tab,
    className: `tab ${activeTab === tab ? 'active' : ''}`,
    onClick: () => setActiveTab(tab)
  }, /*#__PURE__*/React.createElement("span", {
    className: "tab-icon"
  }, TAB_ICONS[tab]), tab, getTabCount(tab) !== null && /*#__PURE__*/React.createElement("span", {
    className: "tab-count"
  }, getTabCount(tab)))), /*#__PURE__*/React.createElement(MoreMenu, {
    data: data,
    currentUser: currentUser,
    activeItem: isMoreMenuActive ? activeTab : null,
    onItemSelect: setActiveTab
  })), /*#__PURE__*/React.createElement("main", {
    className: "main-content"
  }, activeTab === 'Dashboard' && /*#__PURE__*/React.createElement(ErrorBoundary, null, /*#__PURE__*/React.createElement(Dashboard, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser,
    onNewBrief: () => setBriefModalOpen(true),
    onCheckCapacity: () => setActiveTab('Capacity'),
    onJobClick: handleRowClick
  })), activeTab === 'Work' && /*#__PURE__*/React.createElement(ErrorBoundary, null, /*#__PURE__*/React.createElement(WorkTab, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser,
    viewMode: viewMode,
    searchQuery: searchQuery,
    onRowClick: handleRowClick,
    onNewBrief: () => setBriefModalOpen(true)
  })), activeTab === 'Capacity' && /*#__PURE__*/React.createElement(ErrorBoundary, null, /*#__PURE__*/React.createElement(CapacityTab, {
    data: data,
    dispatch: dispatch,
    people: data.people
  })), activeTab === 'Reviews' && /*#__PURE__*/React.createElement(ErrorBoundary, null, /*#__PURE__*/React.createElement(ReviewsTab, {
    key: currentUserId,
    data: data,
    dispatch: dispatch,
    currentUser: currentUser
  })), activeTab === 'People' && /*#__PURE__*/React.createElement(ErrorBoundary, null, /*#__PURE__*/React.createElement(TableView, {
    tab: "People",
    data: data,
    dispatch: dispatch,
    people: data.people,
    projects: data.projects,
    onRowClick: handleRowClick,
    searchQuery: searchQuery,
    currentUser: currentUser
  })), activeTab === 'Wiki' && /*#__PURE__*/React.createElement(ErrorBoundary, null, /*#__PURE__*/React.createElement(WikiTab, {
    data: data,
    dispatch: dispatch,
    people: data.people
  })), activeTab === 'Operations' && /*#__PURE__*/React.createElement(ErrorBoundary, null, /*#__PURE__*/React.createElement(OperationsDashboard, {
    data: data,
    currentUser: currentUser
  }))), /*#__PURE__*/React.createElement(BriefModal, {
    isOpen: briefModalOpen,
    onClose: () => setBriefModalOpen(false),
    data: data,
    dispatch: dispatch
  }), /*#__PURE__*/React.createElement(AddPersonModal, {
    isOpen: addPersonModalOpen,
    onClose: () => setAddPersonModalOpen(false),
    dispatch: dispatch
  }), /*#__PURE__*/React.createElement(DetailPanel, {
    item: selectedItem,
    type: getDetailPanelType(),
    isOpen: detailPanelOpen,
    onClose: () => {
      setDetailPanelOpen(false);
      setSelectedItem(null);
    },
    data: data,
    dispatch: dispatch,
    people: data.people,
    currentUser: currentUser
  }), importToast && /*#__PURE__*/React.createElement("div", {
    className: "app-toast app-toast-" + importToast.type,
    onClick: () => setImportToast(null)
  }, importToast.text));
}

// Render the app
ReactDOM.createRoot(document.getElementById('root')).render(/*#__PURE__*/React.createElement(App, null));
