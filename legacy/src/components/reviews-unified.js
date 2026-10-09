// ============================================================================
// UNIFIED REVIEWS TAB (Phase 2 - Merges Job Review + Client Review)
// ============================================================================
//
// Consolidates the previous Job Review and Client Review tabs into a single
// "Reviews" view with Internal/Client sub-tabs.
//
// Features:
// - Sub-tabs: Internal (for CD/ECD/PM/COO), Client (for Client role)
// - Role-based visibility - only shows tabs the user can access
// - Unified UI with consistent styling
// ============================================================================

const ReviewsTab = ({
  data,
  dispatch,
  currentUser
}) => {
  // Determine which sub-tabs the user can access
  const canAccessInternal = canAccessJobReview(currentUser);
  const canAccessClient = canAccessClientReview(currentUser);

  // Set default active sub-tab based on access
  const getDefaultSubTab = () => {
    if (canAccessInternal) return 'internal';
    if (canAccessClient) return 'client';
    return 'internal'; // Fallback
  };
  const [activeSubTab, setActiveSubTab] = useState(getDefaultSubTab());

  // Reset active sub-tab when user changes (component doesn't unmount on user switch)
  useEffect(() => {
    if (canAccessInternal) {
      setActiveSubTab('internal');
    } else if (canAccessClient) {
      setActiveSubTab('client');
    }
  }, [currentUser?.id]);

  // If user has no access to either, show restricted message
  if (!canAccessInternal && !canAccessClient) {
    return /*#__PURE__*/React.createElement("div", {
      className: "reviews-restricted"
    }, /*#__PURE__*/React.createElement("span", {
      className: "empty-icon"
    }, "\u25C9"), /*#__PURE__*/React.createElement("h2", null, "Access Restricted"), /*#__PURE__*/React.createElement("p", null, "Reviews are available to CDs, ECDs, Producers, PMs, Traffic, COO, and Clients."));
  }

  // BUG-07 fix: Get counts for sub-tabs (broadened to match reviewableJobs filter)
  const getInternalCount = () => {
    const reviewableStatuses = ['In Progress', 'Today', 'This Week', 'In Review'];
    return data.jobs.filter(job => {
      if (!reviewableStatuses.includes(job.status)) return false;
      if (job.internalApprovedBy) return false;
      const jobTasks = (data.tasks || []).filter(t => t.jobId === job.id);
      const copyComplete = jobTasks.find(t => t.templateId === 'copy')?.status === 'Done';
      const mediaComplete = jobTasks.find(t => t.templateId === 'media')?.status === 'Done';
      return copyComplete && mediaComplete;
    }).length;
  };
  // BUG-06 fix: match both 'Approved (Internal)' and legacy 'In Review'
  // Traffic/COO see all jobs in client review; Clients see only their assigned jobs
  const getClientCount = () => {
    if (!currentUser) return 0;
    const clientReviewStatuses = ['Approved (Internal)', 'In Review'];
    if (currentUser.role === 'Client') {
      return data.jobs.filter(job => clientReviewStatuses.includes(job.status) && job.assignments?.Client === currentUser.id).length;
    }
    return data.jobs.filter(job => clientReviewStatuses.includes(job.status)).length;
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "reviews-tab"
  }, canAccessInternal && canAccessClient && /*#__PURE__*/React.createElement("div", {
    className: "reviews-sub-nav"
  }, /*#__PURE__*/React.createElement("button", {
    className: `review-sub-tab ${activeSubTab === 'internal' ? 'active' : ''}`,
    onClick: () => setActiveSubTab('internal')
  }, "Internal Review", /*#__PURE__*/React.createElement("span", {
    className: "sub-tab-count"
  }, getInternalCount())), /*#__PURE__*/React.createElement("button", {
    className: `review-sub-tab ${activeSubTab === 'client' ? 'active' : ''}`,
    onClick: () => setActiveSubTab('client')
  }, "Client Review", /*#__PURE__*/React.createElement("span", {
    className: "sub-tab-count"
  }, getClientCount()))), (!canAccessInternal || !canAccessClient) && /*#__PURE__*/React.createElement("div", {
    className: "reviews-single-header"
  }, /*#__PURE__*/React.createElement("h1", null, canAccessInternal ? 'Internal Review' : 'Client Review')), activeSubTab === 'internal' && canAccessInternal && /*#__PURE__*/React.createElement(JobReviewTab, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser
  }), activeSubTab === 'client' && canAccessClient && /*#__PURE__*/React.createElement(ClientReviewTab, {
    data: data,
    dispatch: dispatch,
    currentUser: currentUser
  }));
};
