const WysiwygEditor = ({
  content,
  onChange,
  onSave
}) => {
  const editorRef = React.useRef(null);
  const [showLinkModal, setShowLinkModal] = useState(false);
  const [linkUrl, setLinkUrl] = useState('');
  const execCommand = (command, value = null) => {
    document.execCommand(command, false, value);
    editorRef.current?.focus();
  };
  const handleInsertLink = () => {
    if (linkUrl) {
      execCommand('createLink', linkUrl);
      setLinkUrl('');
    }
    setShowLinkModal(false);
  };
  const handleInput = () => {
    if (editorRef.current) {
      onChange(editorRef.current.innerHTML);
    }
  };
  useEffect(() => {
    if (editorRef.current && editorRef.current.innerHTML !== content) {
      editorRef.current.innerHTML = content || '';
    }
  }, []);
  return /*#__PURE__*/React.createElement("div", {
    className: "wysiwyg-editor"
  }, /*#__PURE__*/React.createElement("div", {
    className: "editor-toolbar"
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('bold'),
    title: "Bold"
  }, /*#__PURE__*/React.createElement("b", null, "B")), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('italic'),
    title: "Italic"
  }, /*#__PURE__*/React.createElement("i", null, "I")), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('underline'),
    title: "Underline"
  }, /*#__PURE__*/React.createElement("u", null, "U")), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('strikeThrough'),
    title: "Strikethrough"
  }, /*#__PURE__*/React.createElement("s", null, "S")), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'h1'),
    title: "Heading 1"
  }, "H1"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'h2'),
    title: "Heading 2"
  }, "H2"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'h3'),
    title: "Heading 3"
  }, "H3"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'p'),
    title: "Paragraph"
  }, "P"), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('insertUnorderedList'),
    title: "Bullet List"
  }, "\u2022"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('insertOrderedList'),
    title: "Numbered List"
  }, "1."), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => setShowLinkModal(true),
    title: "Insert Link"
  }, "\uD83D\uDD17"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'blockquote'),
    title: "Quote"
  }, "\u201C"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('formatBlock', 'pre'),
    title: "Code"
  }, "</>"), /*#__PURE__*/React.createElement("span", {
    className: "toolbar-divider"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: () => execCommand('removeFormat'),
    title: "Clear Formatting"
  }, "\u2716")), /*#__PURE__*/React.createElement("div", {
    ref: editorRef,
    className: "editor-content",
    contentEditable: true,
    onInput: handleInput,
    onBlur: handleInput,
    suppressContentEditableWarning: true
  }), showLinkModal && /*#__PURE__*/React.createElement("div", {
    className: "link-modal"
  }, /*#__PURE__*/React.createElement("input", {
    type: "url",
    placeholder: "Enter URL...",
    value: linkUrl,
    onChange: e => setLinkUrl(e.target.value),
    onKeyDown: e => e.key === 'Enter' && handleInsertLink()
  }), /*#__PURE__*/React.createElement("button", {
    onClick: handleInsertLink
  }, "Insert"), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowLinkModal(false)
  }, "Cancel")));
};

// Wiki Tree Node Component
const WikiTreeNode = ({
  page,
  pages,
  level = 0,
  selectedId,
  onSelect,
  onToggle,
  expanded
}) => {
  const children = pages.filter(p => p.parentId === page.id).sort((a, b) => a.order - b.order);
  const hasChildren = children.length > 0;
  const isExpanded = expanded[page.id];
  const isSelected = selectedId === page.id;
  const typeIcons = {
    client: '&#128188;',
    campaign: '&#128200;',
    award: '&#127942;',
    report: '&#128202;',
    general: '&#128196;'
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-tree-node"
  }, /*#__PURE__*/React.createElement("div", {
    className: `tree-item ${isSelected ? 'selected' : ''}`,
    style: {
      paddingLeft: `${level * 16 + 8}px`
    },
    onClick: () => onSelect(page)
  }, hasChildren && /*#__PURE__*/React.createElement("span", {
    className: "tree-toggle",
    onClick: e => {
      e.stopPropagation();
      onToggle(page.id);
    }
  }, isExpanded ? '\u25BC' : '\u25B6'), !hasChildren && /*#__PURE__*/React.createElement("span", {
    className: "tree-spacer"
  }), /*#__PURE__*/React.createElement("span", {
    className: "tree-icon",
    dangerouslySetInnerHTML: {
      __html: typeIcons[page.type] || typeIcons.general
    }
  }), /*#__PURE__*/React.createElement("span", {
    className: "tree-title"
  }, page.title)), hasChildren && isExpanded && /*#__PURE__*/React.createElement("div", {
    className: "tree-children"
  }, children.map(child => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: child.id,
    page: child,
    pages: pages,
    level: level + 1,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: onToggle,
    expanded: expanded
  }))));
};

// Wiki Sidebar Component
const WikiSidebar = ({
  pages,
  selectedId,
  onSelect,
  onNewPage,
  searchQuery,
  onSearchChange
}) => {
  const [expanded, setExpanded] = useState({});
  const toggleExpand = id => {
    setExpanded(prev => ({
      ...prev,
      [id]: !prev[id]
    }));
  };

  // Get root pages (no parent)
  const rootPages = pages.filter(p => !p.parentId).sort((a, b) => a.order - b.order);

  // Group by type
  const clientPages = rootPages.filter(p => p.type === 'client');
  const awardPages = rootPages.filter(p => p.type === 'award');
  const reportPages = rootPages.filter(p => p.type === 'report');
  const generalPages = rootPages.filter(p => p.type === 'general');

  // Filter by search
  const filteredPages = searchQuery ? pages.filter(p => p.title.toLowerCase().includes(searchQuery.toLowerCase())) : null;
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-sidebar"
  }, /*#__PURE__*/React.createElement("div", {
    className: "sidebar-search"
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Search wiki...",
    value: searchQuery,
    onChange: e => onSearchChange(e.target.value)
  })), filteredPages ? /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Search Results"), filteredPages.map(page => /*#__PURE__*/React.createElement("div", {
    key: page.id,
    className: `tree-item ${selectedId === page.id ? 'selected' : ''}`,
    onClick: () => onSelect(page)
  }, /*#__PURE__*/React.createElement("span", {
    className: "tree-title"
  }, page.title))), filteredPages.length === 0 && /*#__PURE__*/React.createElement("div", {
    className: "empty-text"
  }, "No results found")) : /*#__PURE__*/React.createElement(React.Fragment, null, clientPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Clients"), clientPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  }))), awardPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Award Submissions"), awardPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  }))), reportPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "Reports"), reportPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  }))), generalPages.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "sidebar-section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "section-title"
  }, "General"), generalPages.map(page => /*#__PURE__*/React.createElement(WikiTreeNode, {
    key: page.id,
    page: page,
    pages: pages,
    selectedId: selectedId,
    onSelect: onSelect,
    onToggle: toggleExpand,
    expanded: expanded
  })))), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary sidebar-new-btn",
    onClick: onNewPage
  }, "+ New Page"));
};

// Wiki Breadcrumb Component
const WikiBreadcrumb = ({
  page,
  pages,
  onNavigate
}) => {
  const getBreadcrumbPath = currentPage => {
    const path = [currentPage];
    let parent = pages.find(p => p.id === currentPage.parentId);
    while (parent) {
      path.unshift(parent);
      parent = pages.find(p => p.id === parent.parentId);
    }
    return path;
  };
  if (!page) return null;
  const path = getBreadcrumbPath(page);
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-breadcrumb"
  }, /*#__PURE__*/React.createElement("span", {
    className: "breadcrumb-item",
    onClick: () => onNavigate(null)
  }, "Wiki"), path.map((p, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: p.id
  }, /*#__PURE__*/React.createElement("span", {
    className: "breadcrumb-sep"
  }, "/"), /*#__PURE__*/React.createElement("span", {
    className: `breadcrumb-item ${i === path.length - 1 ? 'current' : ''}`,
    onClick: () => onNavigate(p)
  }, p.title))));
};

// Wiki Page View Component
const WikiPageView = ({
  page,
  pages,
  data,
  people,
  onEdit,
  onDelete,
  onNavigate,
  onLinkItem
}) => {
  if (!page) {
    return /*#__PURE__*/React.createElement("div", {
      className: "wiki-page-empty"
    }, /*#__PURE__*/React.createElement("span", {
      className: "empty-icon"
    }, "\u25A4"), /*#__PURE__*/React.createElement("h2", null, "Welcome to the Wiki"), /*#__PURE__*/React.createElement("p", null, "Select a page from the sidebar or create a new one to get started."));
  }
  const linkedJobs = (page.linkedJobs || []).map(id => data.jobs.find(j => j.id === id)).filter(Boolean);
  const linkedProjects = (page.linkedProjects || []).map(id => data.projects.find(p => p.id === id)).filter(Boolean);
  const author = people.find(p => p.id === page.createdBy);
  const template = WIKI_TEMPLATES.find(t => t.id === page.templateId);
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-page-view"
  }, /*#__PURE__*/React.createElement(WikiBreadcrumb, {
    page: page,
    pages: pages,
    onNavigate: onNavigate
  }), /*#__PURE__*/React.createElement("div", {
    className: "page-header"
  }, /*#__PURE__*/React.createElement("h1", null, page.title), /*#__PURE__*/React.createElement("div", {
    className: "page-actions"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onEdit
  }, "Edit"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onLinkItem
  }, "Link Item"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-danger",
    onClick: onDelete
  }, "Delete"))), /*#__PURE__*/React.createElement("div", {
    className: "page-meta"
  }, template && /*#__PURE__*/React.createElement("span", {
    className: "meta-template"
  }, template.name), /*#__PURE__*/React.createElement("span", {
    className: "meta-type"
  }, page.type), author && /*#__PURE__*/React.createElement("span", {
    className: "meta-author"
  }, "by ", author.name), /*#__PURE__*/React.createElement("span", {
    className: "meta-date"
  }, "Updated ", formatDate(page.updatedAt))), page.tags && page.tags.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "page-tags"
  }, page.tags.map(tag => /*#__PURE__*/React.createElement("span", {
    key: tag,
    className: "tag"
  }, tag))), /*#__PURE__*/React.createElement("div", {
    className: "page-content",
    dangerouslySetInnerHTML: {
      __html: typeof DOMPurify !== 'undefined' ? DOMPurify.sanitize(page.content) : page.content
    }
  }), (linkedJobs.length > 0 || linkedProjects.length > 0) && /*#__PURE__*/React.createElement("div", {
    className: "page-links"
  }, /*#__PURE__*/React.createElement("h3", null, "Linked Items"), linkedProjects.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "link-group"
  }, /*#__PURE__*/React.createElement("h4", null, "Projects"), linkedProjects.map(proj => /*#__PURE__*/React.createElement("div", {
    key: proj.id,
    className: "link-item"
  }, /*#__PURE__*/React.createElement("span", {
    className: "link-icon"
  }, "\uD83D\uDCC1"), /*#__PURE__*/React.createElement("span", null, proj.name), /*#__PURE__*/React.createElement(StatusBadge, {
    status: proj.status
  })))), linkedJobs.length > 0 && /*#__PURE__*/React.createElement("div", {
    className: "link-group"
  }, /*#__PURE__*/React.createElement("h4", null, "Jobs"), linkedJobs.map(job => /*#__PURE__*/React.createElement("div", {
    key: job.id,
    className: "link-item"
  }, /*#__PURE__*/React.createElement("span", {
    className: "link-icon"
  }, "\uD83D\uDCBC"), /*#__PURE__*/React.createElement("span", null, job.jobNumber, " - ", job.name), /*#__PURE__*/React.createElement(StatusBadge, {
    status: job.status
  }))))));
};

// Wiki Page Modal (Create/Edit)
const WikiPageModal = ({
  isOpen,
  onClose,
  page,
  pages,
  data,
  dispatch
}) => {
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [type, setType] = useState('general');
  const [templateId, setTemplateId] = useState('');
  const [parentId, setParentId] = useState('');
  const [tags, setTags] = useState('');
  useEffect(() => {
    if (page) {
      setTitle(page.title);
      setContent(page.content);
      setType(page.type);
      setTemplateId(page.templateId || '');
      setParentId(page.parentId || '');
      setTags((page.tags || []).join(', '));
    } else {
      setTitle('');
      setContent('');
      setType('general');
      setTemplateId('');
      setParentId('');
      setTags('');
    }
  }, [page, isOpen]);
  const handleTemplateChange = newTemplateId => {
    setTemplateId(newTemplateId);
    if (newTemplateId && !page) {
      const template = WIKI_TEMPLATES.find(t => t.id === newTemplateId);
      if (template) {
        setContent(template.content);
        setType(template.type);
      }
    }
  };
  const handleSubmit = () => {
    if (!title) return alert('Title is required');
    const pageData = {
      id: page?.id || generateId(),
      title,
      slug: title.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, ''),
      content,
      templateId: templateId || null,
      parentId: parentId || null,
      type,
      linkedJobs: page?.linkedJobs || [],
      linkedProjects: page?.linkedProjects || [],
      tags: tags.split(',').map(t => t.trim()).filter(Boolean),
      createdAt: page?.createdAt || new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      createdBy: page?.createdBy || 'p1',
      order: page?.order || pages.filter(p => p.parentId === parentId).length
    };
    if (page) {
      dispatch({
        type: 'UPDATE_WIKI_PAGE',
        payload: pageData
      });
    } else {
      dispatch({
        type: 'ADD_WIKI_PAGE',
        payload: pageData
      });
    }
    onClose(pageData);
  };
  if (!isOpen) return null;

  // Get potential parent pages (excluding self and descendants)
  const getDescendantIds = pageId => {
    const descendants = new Set();
    const addDescendants = id => {
      pages.filter(p => p.parentId === id).forEach(p => {
        descendants.add(p.id);
        addDescendants(p.id);
      });
    };
    addDescendants(pageId);
    return descendants;
  };
  const excludeIds = page ? new Set([page.id, ...getDescendantIds(page.id)]) : new Set();
  const parentOptions = pages.filter(p => !excludeIds.has(p.id));
  return /*#__PURE__*/React.createElement("div", {
    className: "modal-overlay",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-content wiki-modal",
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-header"
  }, /*#__PURE__*/React.createElement("h2", null, page ? 'Edit Page' : 'New Wiki Page'), /*#__PURE__*/React.createElement("button", {
    className: "modal-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "modal-body"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Title *"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: title,
    onChange: e => setTitle(e.target.value),
    placeholder: "Page title"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Type"), /*#__PURE__*/React.createElement("select", {
    value: type,
    onChange: e => setType(e.target.value)
  }, WIKI_TYPES.map(t => /*#__PURE__*/React.createElement("option", {
    key: t,
    value: t
  }, t.charAt(0).toUpperCase() + t.slice(1)))))), /*#__PURE__*/React.createElement("div", {
    className: "form-row"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Template"), /*#__PURE__*/React.createElement("select", {
    value: templateId,
    onChange: e => handleTemplateChange(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- No Template --"), WIKI_TEMPLATES.map(t => /*#__PURE__*/React.createElement("option", {
    key: t.id,
    value: t.id
  }, t.name)))), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Parent Page"), /*#__PURE__*/React.createElement("select", {
    value: parentId,
    onChange: e => setParentId(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "-- None (Root Level) --"), parentOptions.map(p => /*#__PURE__*/React.createElement("option", {
    key: p.id,
    value: p.id
  }, p.title))))), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Tags (comma separated)"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: tags,
    onChange: e => setTags(e.target.value),
    placeholder: "e.g., client, brand, 2024"
  })), /*#__PURE__*/React.createElement("div", {
    className: "form-group"
  }, /*#__PURE__*/React.createElement("label", null, "Content"), /*#__PURE__*/React.createElement(WysiwygEditor, {
    content: content,
    onChange: setContent
  }))), /*#__PURE__*/React.createElement("div", {
    className: "modal-footer"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSubmit
  }, page ? 'Save Changes' : 'Create Page'))));
};

// Wiki Link Modal
const WikiLinkModal = ({
  isOpen,
  onClose,
  page,
  data,
  dispatch
}) => {
  const [selectedJobs, setSelectedJobs] = useState([]);
  const [selectedProjects, setSelectedProjects] = useState([]);
  useEffect(() => {
    if (page) {
      setSelectedJobs(page.linkedJobs || []);
      setSelectedProjects(page.linkedProjects || []);
    }
  }, [page, isOpen]);
  const handleSave = () => {
    if (page) {
      dispatch({
        type: 'UPDATE_WIKI_PAGE',
        payload: {
          ...page,
          linkedJobs: selectedJobs,
          linkedProjects: selectedProjects,
          updatedAt: new Date().toISOString()
        }
      });
    }
    onClose();
  };
  const toggleJob = jobId => {
    setSelectedJobs(prev => prev.includes(jobId) ? prev.filter(id => id !== jobId) : [...prev, jobId]);
  };
  const toggleProject = projectId => {
    setSelectedProjects(prev => prev.includes(projectId) ? prev.filter(id => id !== projectId) : [...prev, projectId]);
  };
  if (!isOpen || !page) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "modal-overlay",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-content",
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    className: "modal-header"
  }, /*#__PURE__*/React.createElement("h2", null, "Link Items to \"", page.title, "\""), /*#__PURE__*/React.createElement("button", {
    className: "modal-close",
    onClick: onClose
  }, "\xD7")), /*#__PURE__*/React.createElement("div", {
    className: "modal-body"
  }, /*#__PURE__*/React.createElement("div", {
    className: "form-section"
  }, /*#__PURE__*/React.createElement("h3", null, "Projects"), /*#__PURE__*/React.createElement("div", {
    className: "link-checkbox-list"
  }, data.projects.map(proj => /*#__PURE__*/React.createElement("label", {
    key: proj.id,
    className: "checkbox-label"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: selectedProjects.includes(proj.id),
    onChange: () => toggleProject(proj.id)
  }), proj.name, " (", proj.client, ")")))), /*#__PURE__*/React.createElement("div", {
    className: "form-section"
  }, /*#__PURE__*/React.createElement("h3", null, "Jobs"), /*#__PURE__*/React.createElement("div", {
    className: "link-checkbox-list"
  }, data.jobs.map(job => /*#__PURE__*/React.createElement("label", {
    key: job.id,
    className: "checkbox-label"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: selectedJobs.includes(job.id),
    onChange: () => toggleJob(job.id)
  }), job.jobNumber, " - ", job.name))))), /*#__PURE__*/React.createElement("div", {
    className: "modal-footer"
  }, /*#__PURE__*/React.createElement("button", {
    className: "btn btn-secondary",
    onClick: onClose
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    className: "btn btn-primary",
    onClick: handleSave
  }, "Save Links"))));
};

// Main Wiki Tab Component
const WikiTab = ({
  data,
  dispatch,
  people
}) => {
  const [selectedPage, setSelectedPage] = useState(null);
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [linkModalOpen, setLinkModalOpen] = useState(false);
  const [editingPage, setEditingPage] = useState(null);
  const [wikiSearchQuery, setWikiSearchQuery] = useState('');
  const wikiPages = data.wikiPages || [];
  const handleSelectPage = page => {
    setSelectedPage(page);
  };
  const handleNewPage = () => {
    setEditingPage(null);
    setEditModalOpen(true);
  };
  const handleEditPage = () => {
    setEditingPage(selectedPage);
    setEditModalOpen(true);
  };
  const handleDeletePage = () => {
    if (!selectedPage) return;
    if (!confirm(`Delete "${selectedPage.title}"? This cannot be undone.`)) return;
    dispatch({
      type: 'DELETE_WIKI_PAGE',
      payload: selectedPage.id
    });
    setSelectedPage(null);
  };
  const handleModalClose = savedPage => {
    setEditModalOpen(false);
    if (savedPage && savedPage.id) {
      setSelectedPage(savedPage);
    }
  };
  const handleNavigate = page => {
    setSelectedPage(page);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "wiki-container"
  }, /*#__PURE__*/React.createElement(WikiSidebar, {
    pages: wikiPages,
    selectedId: selectedPage?.id,
    onSelect: handleSelectPage,
    onNewPage: handleNewPage,
    searchQuery: wikiSearchQuery,
    onSearchChange: setWikiSearchQuery
  }), /*#__PURE__*/React.createElement("div", {
    className: "wiki-main"
  }, /*#__PURE__*/React.createElement(WikiPageView, {
    page: selectedPage,
    pages: wikiPages,
    data: data,
    people: people,
    onEdit: handleEditPage,
    onDelete: handleDeletePage,
    onNavigate: handleNavigate,
    onLinkItem: () => setLinkModalOpen(true)
  })), /*#__PURE__*/React.createElement(WikiPageModal, {
    isOpen: editModalOpen,
    onClose: handleModalClose,
    page: editingPage,
    pages: wikiPages,
    data: data,
    dispatch: dispatch
  }), /*#__PURE__*/React.createElement(WikiLinkModal, {
    isOpen: linkModalOpen,
    onClose: () => setLinkModalOpen(false),
    page: selectedPage,
    data: data,
    dispatch: dispatch
  }));
};

// ============================================================================
// CAPACITY TAB COMPONENTS
// ============================================================================

// Person Selector with carousel navigation
