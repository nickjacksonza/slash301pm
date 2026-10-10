const PersonSelector = ({
  people,
  currentIndex,
  onNavigate
}) => {
  const person = people[currentIndex];
  if (!person) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "person-selector"
  }, /*#__PURE__*/React.createElement("button", {
    className: "nav-arrow nav-arrow-left",
    onClick: () => onNavigate(-1),
    disabled: currentIndex === 0
  }, "\u2039"), /*#__PURE__*/React.createElement("div", {
    className: "person-info"
  }, /*#__PURE__*/React.createElement(PersonAvatar, {
    person: person,
    size: "medium"
  }), /*#__PURE__*/React.createElement("div", {
    className: "person-details"
  }, /*#__PURE__*/React.createElement("span", {
    className: "person-name"
  }, person.name), /*#__PURE__*/React.createElement("span", {
    className: "person-roles"
  }, person.role))), /*#__PURE__*/React.createElement("button", {
    className: "nav-arrow nav-arrow-right",
    onClick: () => onNavigate(1),
    disabled: currentIndex === people.length - 1
  }, "\u203A"));
};

// Capacity progress bar
const CapacityBar = ({
  current,
  max,
  label
}) => {
  const percentage = Math.min(current / max * 100, 100);
  const isOverCapacity = current > max;
  return /*#__PURE__*/React.createElement("div", {
    className: "capacity-bar-container"
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-bar-label"
  }, /*#__PURE__*/React.createElement("span", null, label), /*#__PURE__*/React.createElement("span", {
    className: `capacity-hours ${isOverCapacity ? 'over-capacity' : ''}`
  }, current.toFixed(1), "h / ", max, "h")), /*#__PURE__*/React.createElement("div", {
    className: "capacity-bar"
  }, /*#__PURE__*/React.createElement("div", {
    className: `capacity-bar-fill ${isOverCapacity ? 'over-capacity' : ''}`,
    style: {
      width: `${percentage}%`
    }
  })));
};

// Job card for capacity view
const CapacityJobCard = ({
  job,
  project,
  isOverflow
}) => {
  return /*#__PURE__*/React.createElement("div", {
    className: `capacity-job-card ${isOverflow ? 'overflow' : ''}`
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-card-header"
  }, /*#__PURE__*/React.createElement("span", {
    className: "capacity-job-number"
  }, job.jobNumber), /*#__PURE__*/React.createElement("span", {
    className: "capacity-job-name"
  }, job.name)), /*#__PURE__*/React.createElement("div", {
    className: "capacity-card-meta"
  }, project?.client, " / ", project?.name), /*#__PURE__*/React.createElement("div", {
    className: "capacity-card-footer"
  }, /*#__PURE__*/React.createElement(StatusBadge, {
    status: job.status
  }), /*#__PURE__*/React.createElement("span", {
    className: "capacity-hours-badge"
  }, job.hours, "h"), /*#__PURE__*/React.createElement("span", {
    className: "capacity-due"
  }, "Due: ", formatDate(job.dueDate))), isOverflow && /*#__PURE__*/React.createElement("div", {
    className: "overflow-warning"
  }, "Overflows to next week"));
};

// Section grouping jobs
const CapacitySection = ({
  title,
  jobs,
  totalHours,
  data,
  isOverflow = false
}) => {
  if (jobs.length === 0) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: `capacity-section ${isOverflow ? 'overflow-section' : ''}`
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-section-header"
  }, /*#__PURE__*/React.createElement("h3", null, title), /*#__PURE__*/React.createElement("span", {
    className: "section-hours"
  }, totalHours.toFixed(1), " hours")), /*#__PURE__*/React.createElement("div", {
    className: "capacity-jobs-list"
  }, jobs.map(job => {
    const project = data.projects.find(p => p.id === job.projectId);
    return /*#__PURE__*/React.createElement(CapacityJobCard, {
      key: job.id,
      job: job,
      project: project,
      isOverflow: isOverflow
    });
  })));
};

// Email action buttons
const EmailActions = ({
  person,
  categorizedJobs,
  data,
  dispatch
}) => {
  const [showScheduleConfirm, setShowScheduleConfirm] = useState(false);
  const [showSendConfirm, setShowSendConfirm] = useState(false);
  const handleSendEmail = () => {
    const {
      subject,
      body
    } = generateCapacityEmailContent(person, categorizedJobs, data);
    const mailtoLink = `mailto:${person.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    window.location.href = mailtoLink;
    setShowSendConfirm(true);
    setTimeout(() => setShowSendConfirm(false), 3000);
  };
  const handleScheduleEmail = () => {
    const scheduledFor = getNextWeekdayAt9am();
    const {
      subject,
      body
    } = generateCapacityEmailContent(person, categorizedJobs, data);
    const scheduledEmail = {
      id: generateId(),
      personId: person.id,
      personEmail: person.email,
      scheduledFor: scheduledFor.toISOString(),
      subject,
      content: body,
      createdAt: new Date().toISOString()
    };
    dispatch({
      type: 'ADD_SCHEDULED_EMAIL',
      payload: scheduledEmail
    });
    setShowScheduleConfirm(true);
    setTimeout(() => setShowScheduleConfirm(false), 3000);
  };
  const nextWeekday = getNextWeekdayAt9am();
  const scheduleLabel = nextWeekday.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric'
  });
  return /*#__PURE__*/React.createElement("div", {
    className: "email-actions"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary email-btn",
    onClick: handleSendEmail
  }, /*#__PURE__*/React.createElement("span", {
    className: "email-icon"
  }, "\u2709"), "Send by Email"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary email-btn",
    onClick: handleScheduleEmail
  }, /*#__PURE__*/React.createElement("span", {
    className: "email-icon"
  }, "\uD83D\uDD53"), "Schedule Email (9am ", scheduleLabel, ")"), showSendConfirm && /*#__PURE__*/React.createElement("div", {
    className: "schedule-confirm"
  }, "Email client opened for ", person.name), showScheduleConfirm && /*#__PURE__*/React.createElement("div", {
    className: "schedule-confirm"
  }, "Email scheduled for 9am on ", scheduleLabel));
};

// ============================================================================
// CAPACITY CALENDAR VIEW
// ============================================================================

const CapacityCalendar = ({
  person,
  jobs,
  data
}) => {
  // Get current week's dates (Monday to Friday)
  const getWeekDates = () => {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const monday = new Date(today);
    monday.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
    const dates = [];
    for (let i = 0; i < 5; i++) {
      const date = new Date(monday);
      date.setDate(monday.getDate() + i);
      dates.push(date);
    }
    return dates;
  };
  const weekDates = getWeekDates();

  // Get day name and date string
  const formatDayHeader = date => {
    const dayName = date.toLocaleDateString('en-US', {
      weekday: 'short'
    });
    const dateNum = date.getDate();
    return {
      dayName,
      dateNum
    };
  };

  // Check if a date is today
  const isToday = date => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
  };

  // Get jobs for a specific day based on status and due date
  const getJobsForDay = date => {
    const dateStr = date.toISOString().split('T')[0];
    const today = new Date().toISOString().split('T')[0];
    return jobs.filter(job => {
      // Jobs due on this date
      if (job.dueDate === dateStr) return true;

      // "Today" status jobs on today's date
      if (dateStr === today && (job.status === 'Today' || job.status === 'In Progress')) {
        return true;
      }
      return false;
    });
  };

  // Calculate hours for a day
  const getDayHours = dayJobs => {
    return dayJobs.reduce((sum, job) => {
      const hours = calculateJobHours(job, data.assets);
      return sum + hours;
    }, 0);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "capacity-calendar"
  }, /*#__PURE__*/React.createElement("div", {
    className: "calendar-header"
  }, /*#__PURE__*/React.createElement("h3", null, "Week View"), /*#__PURE__*/React.createElement("span", {
    className: "calendar-week-label"
  }, weekDates[0].toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  }), " - ", weekDates[4].toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  }))), /*#__PURE__*/React.createElement("div", {
    className: "calendar-grid"
  }, weekDates.map((date, index) => {
    const {
      dayName,
      dateNum
    } = formatDayHeader(date);
    const dayJobs = getJobsForDay(date);
    const dayHours = getDayHours(dayJobs);
    const isOverCapacity = dayHours > DAILY_CAPACITY;
    return /*#__PURE__*/React.createElement("div", {
      key: index,
      className: `calendar-day ${isToday(date) ? 'is-today' : ''}`
    }, /*#__PURE__*/React.createElement("div", {
      className: "calendar-day-header"
    }, /*#__PURE__*/React.createElement("span", {
      className: "day-name"
    }, dayName), /*#__PURE__*/React.createElement("span", {
      className: "day-date"
    }, dateNum), /*#__PURE__*/React.createElement("span", {
      className: `day-hours ${isOverCapacity ? 'over-capacity' : ''}`
    }, dayHours.toFixed(1), "h")), /*#__PURE__*/React.createElement("div", {
      className: "calendar-day-content"
    }, dayJobs.length === 0 ? /*#__PURE__*/React.createElement("div", {
      className: "calendar-empty-day"
    }, "No jobs") : dayJobs.map(job => {
      const project = data.projects.find(p => p.id === job.projectId);
      const hours = calculateJobHours(job, data.assets);
      return /*#__PURE__*/React.createElement("div", {
        key: job.id,
        className: "calendar-job-block",
        style: {
          backgroundColor: STATUS_COLORS[job.status] + '20',
          borderLeftColor: STATUS_COLORS[job.status]
        }
      }, /*#__PURE__*/React.createElement("span", {
        className: "job-block-number"
      }, job.jobNumber), /*#__PURE__*/React.createElement("span", {
        className: "job-block-name"
      }, job.name), /*#__PURE__*/React.createElement("span", {
        className: "job-block-client"
      }, project?.client), /*#__PURE__*/React.createElement("span", {
        className: "job-block-hours"
      }, hours.toFixed(1), "h"));
    })), /*#__PURE__*/React.createElement("div", {
      className: "calendar-day-capacity"
    }, /*#__PURE__*/React.createElement("div", {
      className: `capacity-indicator ${isOverCapacity ? 'over' : ''}`,
      style: {
        width: `${Math.min(dayHours / DAILY_CAPACITY * 100, 100)}%`
      }
    })));
  })));
};

// ============================================================================
// TEAM OVERVIEW CARD (for Team Overview mode)
// ============================================================================

const TeamMemberCard = ({
  person,
  data,
  onSelect
}) => {
  const personJobs = getPersonJobs(person.id, data.jobs);
  const categorizedJobs = categorizeJobsByCapacity(personJobs, data.assets);
  const weekUtilization = categorizedJobs.weekHours / WEEKLY_CAPACITY * 100;
  const isOverCapacity = weekUtilization > 100;
  const utilizationColor = isOverCapacity ? 'var(--status-red-mid)' : weekUtilization > 80 ? 'var(--status-orange-mid)' : 'var(--status-green-mid)';
  return /*#__PURE__*/React.createElement("div", {
    className: "team-member-card",
    onClick: () => onSelect(person)
  }, /*#__PURE__*/React.createElement("div", {
    className: "team-card-header"
  }, /*#__PURE__*/React.createElement(PersonAvatar, {
    person: person,
    size: "small"
  }), /*#__PURE__*/React.createElement("div", {
    className: "team-card-info"
  }, /*#__PURE__*/React.createElement("span", {
    className: "team-card-name"
  }, person.name), /*#__PURE__*/React.createElement("span", {
    className: "team-card-role"
  }, person.role)), /*#__PURE__*/React.createElement("div", {
    className: "team-card-utilization",
    style: {
      color: utilizationColor
    }
  }, weekUtilization.toFixed(0), "%")), /*#__PURE__*/React.createElement("div", {
    className: "team-card-capacity"
  }, /*#__PURE__*/React.createElement("div", {
    className: "mini-capacity-bar"
  }, /*#__PURE__*/React.createElement("div", {
    className: `mini-capacity-fill ${isOverCapacity ? 'over' : ''}`,
    style: {
      width: `${Math.min(weekUtilization, 100)}%`
    }
  })), /*#__PURE__*/React.createElement("div", {
    className: "team-card-stats"
  }, /*#__PURE__*/React.createElement("span", null, categorizedJobs.weekHours.toFixed(1), "h / ", WEEKLY_CAPACITY, "h"), /*#__PURE__*/React.createElement("span", null, personJobs.length, " jobs"))), categorizedJobs.overflow.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "team-card-overflow"
  }, categorizedJobs.overflow.length, " overflow"));
};

// Main Capacity Tab Component
const CapacityTab = ({
  data,
  dispatch,
  people
}) => {
  const [currentPersonIndex, setCurrentPersonIndex] = useState(0);
  const [viewMode, setViewMode] = useState('team'); // 'team', 'list', or 'calendar'
  const [sortBy, setSortBy] = useState('name'); // 'name', 'role', 'capacity', 'jobs'
  const [filterRole, setFilterRole] = useState('all');

  // Filter to only people with agency roles (not Client)
  const agencyPeople = people.filter(p => p.role !== 'Client');

  // Get capacity data for sorting
  const getPeopleWithCapacity = () => {
    return agencyPeople.map(person => {
      const personJobs = getPersonJobs(person.id, data.jobs);
      const categorizedJobs = categorizeJobsByCapacity(personJobs, data.assets);
      return {
        ...person,
        jobCount: personJobs.length,
        weekHours: categorizedJobs.weekHours,
        utilization: categorizedJobs.weekHours / WEEKLY_CAPACITY * 100
      };
    });
  };

  // Sort people
  const sortPeople = peopleList => {
    return [...peopleList].sort((a, b) => {
      switch (sortBy) {
        case 'name':
          return a.name.localeCompare(b.name);
        case 'role':
          return a.role.localeCompare(b.role);
        case 'capacity':
          return b.utilization - a.utilization;
        // High to low
        case 'jobs':
          return b.jobCount - a.jobCount;
        // High to low
        default:
          return 0;
      }
    });
  };

  // Filter people by role
  const filterPeople = peopleList => {
    if (filterRole === 'all') return peopleList;
    return peopleList.filter(p => p.role === filterRole);
  };
  const processedPeople = sortPeople(filterPeople(getPeopleWithCapacity()));

  // Get unique roles for filter dropdown
  const uniqueRoles = [...new Set(agencyPeople.map(p => p.role))].sort();
  const currentPerson = agencyPeople[currentPersonIndex];
  const handleNavigate = direction => {
    const newIndex = currentPersonIndex + direction;
    if (newIndex >= 0 && newIndex < agencyPeople.length) {
      setCurrentPersonIndex(newIndex);
    }
  };
  const handleSelectPerson = person => {
    const index = agencyPeople.findIndex(p => p.id === person.id);
    if (index !== -1) {
      setCurrentPersonIndex(index);
      setViewMode('list');
    }
  };
  if (agencyPeople.length === 0) {
    return /*#__PURE__*/React.createElement("div", {
      className: "capacity-empty"
    }, /*#__PURE__*/React.createElement("div", {
      className: "empty-icon"
    }, "\u25CB"), /*#__PURE__*/React.createElement("h2", null, "No team members found"), /*#__PURE__*/React.createElement("p", null, "Add people to see their capacity."));
  }

  // Get jobs assigned to current person (for individual view)
  const personJobs = currentPerson ? getPersonJobs(currentPerson.id, data.jobs) : [];
  const categorizedJobs = currentPerson ? categorizeJobsByCapacity(personJobs, data.assets) : null;
  const scheduledEmails = currentPerson ? (data.scheduledEmails || []).filter(e => e.personId === currentPerson.id) : [];
  return /*#__PURE__*/React.createElement("div", {
    className: "capacity-tab"
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-header"
  }, /*#__PURE__*/React.createElement("div", {
    className: "capacity-view-toggle"
  }, /*#__PURE__*/React.createElement("button", {
    className: `toggle-btn ${viewMode === 'team' ? 'active' : ''}`,
    onClick: () => setViewMode('team'),
    title: "Team Overview"
  }, "\u25CE Team"), /*#__PURE__*/React.createElement("button", {
    className: `toggle-btn ${viewMode === 'list' ? 'active' : ''}`,
    onClick: () => setViewMode('list'),
    title: "Individual List"
  }, "\u2630 List"), /*#__PURE__*/React.createElement("button", {
    className: `toggle-btn ${viewMode === 'calendar' ? 'active' : ''}`,
    onClick: () => setViewMode('calendar'),
    title: "Individual Calendar"
  }, "\u25A6 Calendar")), viewMode === 'team' && /*#__PURE__*/React.createElement("div", {
    className: "capacity-filters"
  }, /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Sort by:"), /*#__PURE__*/React.createElement("select", {
    value: sortBy,
    onChange: e => setSortBy(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "name"
  }, "Name"), /*#__PURE__*/React.createElement("option", {
    value: "role"
  }, "Role"), /*#__PURE__*/React.createElement("option", {
    value: "capacity"
  }, "Capacity (High \u2192 Low)"), /*#__PURE__*/React.createElement("option", {
    value: "jobs"
  }, "Jobs (High \u2192 Low)"))), /*#__PURE__*/React.createElement("div", {
    className: "filter-group"
  }, /*#__PURE__*/React.createElement("label", null, "Role:"), /*#__PURE__*/React.createElement("select", {
    value: filterRole,
    onChange: e => setFilterRole(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: "all"
  }, "All Roles"), uniqueRoles.map(role => /*#__PURE__*/React.createElement("option", {
    key: role,
    value: role
  }, role))))), (viewMode === 'list' || viewMode === 'calendar') && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(PersonSelector, {
    people: agencyPeople,
    currentIndex: currentPersonIndex,
    onNavigate: handleNavigate
  }), categorizedJobs && /*#__PURE__*/React.createElement("div", {
    className: "capacity-bars"
  }, /*#__PURE__*/React.createElement(CapacityBar, {
    current: categorizedJobs.todayHours,
    max: DAILY_CAPACITY,
    label: "Today"
  }), /*#__PURE__*/React.createElement(CapacityBar, {
    current: categorizedJobs.weekHours,
    max: WEEKLY_CAPACITY,
    label: "This Week"
  })))), viewMode === 'team' && /*#__PURE__*/React.createElement("div", {
    className: "team-overview"
  }, /*#__PURE__*/React.createElement("div", {
    className: "team-summary"
  }, /*#__PURE__*/React.createElement("div", {
    className: "summary-stat"
  }, /*#__PURE__*/React.createElement("span", {
    className: "stat-value"
  }, processedPeople.length), /*#__PURE__*/React.createElement("span", {
    className: "stat-label"
  }, "Team Members")), /*#__PURE__*/React.createElement("div", {
    className: "summary-stat"
  }, /*#__PURE__*/React.createElement("span", {
    className: "stat-value"
  }, processedPeople.filter(p => p.utilization > 100).length), /*#__PURE__*/React.createElement("span", {
    className: "stat-label"
  }, "Over Capacity")), /*#__PURE__*/React.createElement("div", {
    className: "summary-stat"
  }, /*#__PURE__*/React.createElement("span", {
    className: "stat-value"
  }, processedPeople.filter(p => p.utilization < 50).length), /*#__PURE__*/React.createElement("span", {
    className: "stat-label"
  }, "Under 50%"))), /*#__PURE__*/React.createElement("div", {
    className: "team-grid"
  }, processedPeople.map(person => /*#__PURE__*/React.createElement(TeamMemberCard, {
    key: person.id,
    person: person,
    data: data,
    onSelect: handleSelectPerson
  }))), processedPeople.length === 0 && /*#__PURE__*/React.createElement("div", {
    className: "capacity-empty-jobs"
  }, /*#__PURE__*/React.createElement("span", {
    className: "empty-icon"
  }, "\u25CC"), /*#__PURE__*/React.createElement("p", null, "No team members match the selected filter"))), viewMode === 'calendar' && currentPerson && /*#__PURE__*/React.createElement(CapacityCalendar, {
    person: currentPerson,
    jobs: personJobs,
    data: data
  }), viewMode === 'list' && currentPerson && /*#__PURE__*/React.createElement("div", {
    className: "capacity-content"
  }, /*#__PURE__*/React.createElement(CapacitySection, {
    title: "TODAY",
    jobs: categorizedJobs.today,
    totalHours: categorizedJobs.todayHours,
    data: data
  }), /*#__PURE__*/React.createElement(CapacitySection, {
    title: "THIS WEEK",
    jobs: categorizedJobs.thisWeek,
    totalHours: categorizedJobs.thisWeek.reduce((sum, j) => sum + j.hours, 0),
    data: data
  }), categorizedJobs.overflow.length > 0 && /*#__PURE__*/React.createElement(CapacitySection, {
    title: "OVERFLOW (Over Capacity)",
    jobs: categorizedJobs.overflow,
    totalHours: categorizedJobs.overflowHours,
    data: data,
    isOverflow: true
  }), personJobs.length === 0 && /*#__PURE__*/React.createElement("div", {
    className: "capacity-empty-jobs"
  }, /*#__PURE__*/React.createElement("span", {
    className: "empty-icon"
  }, "\u25CC"), /*#__PURE__*/React.createElement("p", null, "No jobs assigned to ", currentPerson.name))), (viewMode === 'list' || viewMode === 'calendar') && currentPerson && categorizedJobs && /*#__PURE__*/React.createElement("div", {
    className: "capacity-footer"
  }, /*#__PURE__*/React.createElement(EmailActions, {
    person: currentPerson,
    categorizedJobs: categorizedJobs,
    data: data,
    dispatch: dispatch
  }), scheduledEmails.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "scheduled-emails-indicator"
  }, scheduledEmails.length, " email(s) scheduled")));
};

// ============================================================================
// ADD PERSON MODAL
// ============================================================================
