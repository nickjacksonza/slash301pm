// ============================================================================
// API CLIENT -- Fetch wrappers for Slash 301 PM API
// Phase 3.3: Auth + CSRF token management
// ============================================================================

const API_BASE = 'api/api.php';

const api = {
  // CSRF token stored in memory (received on login/check_session)
  _csrfToken: null,
  // Callback for auth failures (set by app.js)
  _onAuthFailure: null,

  /**
   * Make a GET request to the API.
   * @param {string} action - The API action
   * @param {Object} params - Additional query parameters
   * @returns {Promise<Object>} The JSON response
   */
  async get(action, params = {}) {
    const url = new URL(API_BASE, window.location.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== null && v !== undefined) url.searchParams.set(k, v);
    });

    const response = await fetch(url.toString(), {
      credentials: 'include', // Send session cookie
    });
    const data = await response.json();

    if (response.status === 401 && this._onAuthFailure) {
      this._onAuthFailure();
      throw new Error('Session expired');
    }

    if (!response.ok) {
      throw new Error(data.error || `API error: ${response.status}`);
    }
    return data;
  },

  /**
   * Make a POST request to the API.
   * Automatically includes CSRF token.
   * @param {string} action - The API action
   * @param {Object} body - The JSON body
   * @returns {Promise<Object>} The JSON response
   */
  async post(action, body = {}) {
    const url = new URL(API_BASE, window.location.href);
    url.searchParams.set('action', action);

    const headers = { 'Content-Type': 'application/json' };
    if (this._csrfToken) {
      headers['X-CSRF-Token'] = this._csrfToken;
    }

    const response = await fetch(url.toString(), {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
      credentials: 'include', // Send session cookie
    });
    const data = await response.json();

    if (response.status === 401 && this._onAuthFailure) {
      this._onAuthFailure();
      throw new Error('Session expired');
    }

    if (!response.ok) {
      throw new Error(data.error || `API error: ${response.status}`);
    }
    return data;
  },

  // ========================================================================
  // AUTH ENDPOINTS
  // ========================================================================

  /** Check if a session exists, returns user + csrf_token if authenticated */
  async checkSession() {
    const data = await this.get('check_session');
    if (data.authenticated && data.csrf_token) {
      this._csrfToken = data.csrf_token;
    }
    return data;
  },

  /** Login with username and password */
  async login(username, password) {
    const url = new URL(API_BASE, window.location.href);
    url.searchParams.set('action', 'login');

    const response = await fetch(url.toString(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
      credentials: 'include',
    });
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Login failed');
    }

    // Store CSRF token from login response
    if (data.csrf_token) {
      this._csrfToken = data.csrf_token;
    }
    return data;
  },

  /** Logout and destroy session */
  async logout() {
    const data = await this.get('logout');
    this._csrfToken = null;
    return data;
  },

  /** Demo mode: switch user without password */
  async demoLogin(userId) {
    const url = new URL(API_BASE, window.location.href);
    url.searchParams.set('action', 'demo_login');
    url.searchParams.set('user_id', userId);

    const response = await fetch(url.toString(), {
      credentials: 'include',
    });
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Demo login failed');
    }
    return data;
  },

  // ========================================================================
  // READ ENDPOINTS
  // ========================================================================

  /** Fetch all brands */
  getBrands() { return this.get('get_brands'); },

  /** Fetch all users (with brand names) */
  getUsers() { return this.get('get_users'); },

  /** Fetch all campaigns, optionally filtered by brand */
  getCampaigns(brandId) { return this.get('get_campaigns', { brand_id: brandId }); },

  /**
   * Fetch jobs list with optional filters.
   * @param {Object} opts - { user_id, user_role, brand_id, status, campaign_id }
   */
  getJobs(opts = {}) { return this.get('get_jobs', opts); },

  /** Fetch a single job with denormalized tasks, assignments, and assets */
  getJob(id) { return this.get('get_job', { id }); },

  /** Fetch all wiki pages (without content) */
  getWikiPages() { return this.get('get_wiki_pages'); },

  /** Fetch a single wiki page with content */
  getWikiPage(id) { return this.get('get_wiki_page', { id }); },

  // ========================================================================
  // WRITE ENDPOINTS
  // ========================================================================

  /** Create a new job */
  addJob(data) { return this.post('add_job', data); },

  /** Update job fields and/or assignments */
  updateJob(data) { return this.post('update_job', data); },

  /** Update task fields */
  updateTask(data) { return this.post('update_task', data); },

  /** Create a new user */
  addUser(data) { return this.post('add_user', data); },

  /** Update asset fields */
  updateAsset(data) { return this.post('update_asset', data); },

  /** Update wiki page */
  updateWikiPage(data) { return this.post('update_wiki_page', data); },

  // ========================================================================
  // WORKFLOW ENDPOINTS
  // ========================================================================

  /** Approve job internally */
  approveInternal(jobId, approvedBy) {
    return this.post('approve_internal', { job_id: jobId, approved_by: approvedBy });
  },

  /** Approve job by client */
  approveClient(jobId, approvedBy) {
    return this.post('approve_client', { job_id: jobId, approved_by: approvedBy });
  },

  /** Reject job with feedback */
  rejectWithFeedback(jobId, feedback, feedbackBy, isInternal = false) {
    return this.post('reject_with_feedback', {
      job_id: jobId,
      feedback,
      feedback_by: feedbackBy,
      is_internal: isInternal,
    });
  },

  /** Batch multiple operations in one transaction */
  batch(operations) { return this.post('batch', { operations }); },

  // ========================================================================
  // FULL DATA LOAD (for app init)
  // ========================================================================

  /**
   * Load all data from the API and return it in the frontend state shape.
   * Maps from the DB schema back to the frontend's flat data structure.
   * @returns {Promise<Object>} State object matching createInitialData() shape
   */
  async loadAllData() {
    // Fetch all data in parallel
    const [brandsRes, usersRes, campaignsRes, jobsRes, wikiRes] = await Promise.all([
      this.getBrands(),
      this.getUsers(),
      this.getCampaigns(),
      this.getJobs(),
      this.getWikiPages(),
    ]);

    const brands = brandsRes.brands;
    const dbUsers = usersRes.users;
    const campaigns = campaignsRes.campaigns;
    const dbJobs = jobsRes.jobs;
    const wikiPages = wikiRes.wikiPages;

    // Build brand lookup for client users
    const brandLookup = {};
    brands.forEach(b => { brandLookup[b.id] = b.name; });

    // Map DB users -> frontend people shape
    const people = dbUsers.map(u => ({
      id: u.id,
      name: u.name,
      email: u.email,
      role: u.role,
      color: u.color,
      brand: u.brand_id ? (brandLookup[u.brand_id] || null) : null,
    }));

    // Map DB campaigns -> frontend projects shape
    const projects = campaigns.map(c => ({
      id: c.id,
      name: c.name,
      client: brandLookup[c.brand_id] || '',
      description: c.description,
      status: c.status === 'active' ? 'In Progress' : c.status,
      jobCount: dbJobs.filter(j => j.campaign_id === c.id).length,
      createdAt: c.created_at,
      // clientColors not stored in DB yet -- use defaults
      clientColors: { primary: '#3b82f6', secondary: '#93c5fd' },
    }));

    // Map DB jobs -> frontend jobs shape
    const allTasks = [];
    const allAssets = [];

    const jobs = dbJobs.map(j => {
      // Collect tasks from the job's tasks array (already attached by get_jobs)
      if (j.tasks) {
        j.tasks.forEach(t => {
          allTasks.push({
            id: t.id,
            templateId: t.type, // DB 'type' -> frontend 'templateId'
            jobId: j.id,
            status: t.status,
            assignedTo: t.assigned_to,
            characterCount: t.character_count,
            content: t.content,
            fileUrl: t.file_url,
            fileType: t.file_type,
            completedAt: t.completed_at,
            order: t.sort_order,
          });
        });
      }

      return {
        id: j.id,
        jobNumber: j.job_number,
        name: j.title,
        description: j.description,
        projectId: j.campaign_id,
        status: j.status,
        assignments: j.assignments || {},
        dueDate: j.delivery_date,
        order: j.sort_order,
        createdAt: j.created_at,
        allTasksCompletedAt: j.all_tasks_completed_at,
        internalApprovedBy: j.internal_approved_by,
        internalApprovedAt: j.internal_approved_at,
        clientApprovedBy: j.client_approved_by,
        clientApprovedAt: j.client_approved_at,
        clientFeedback: j.client_feedback,
        clientFeedbackBy: j.client_feedback_by,
        clientFeedbackAt: j.client_feedback_at,
        clientFeedbackStatus: j.client_feedback_status,
        clientFeedbackAssignedTo: j.client_feedback_assigned_to,
        clientFeedbackAssignedRole: j.client_feedback_assigned_role,
        clientFeedbackActionedBy: j.client_feedback_actioned_by,
        clientFeedbackActionedAt: j.client_feedback_actioned_at,
        internalFeedback: j.internal_feedback,
        internalFeedbackBy: j.internal_feedback_by,
        internalFeedbackAt: j.internal_feedback_at,
      };
    });

    // We need assets too -- get_jobs doesn't include them, so fetch individually for jobs with assets
    // For now, get all assets by fetching each job that has assets
    // Actually, assets come from get_job (individual), not get_jobs (list)
    // Let's fetch them separately -- but for now we'll handle this via the DB
    // The assets are already seeded and will be loaded when get_job is called

    // For initial load, we need assets. Let's add a get_assets endpoint or just
    // accept that assets load when job detail is opened. For the dashboard/list views,
    // assets aren't critical.

    // Map wiki pages
    const mappedWikiPages = wikiPages.map(w => ({
      id: w.id,
      title: w.title,
      slug: w.slug,
      content: w.content || '',
      templateId: w.template_id,
      parentId: w.parent_id,
      type: w.type,
      linkedJobs: w.linkedJobs || [],
      linkedProjects: w.linkedProjects || [],
      tags: w.tags ? w.tags.split(',') : [],
      createdAt: w.created_at,
      updatedAt: w.updated_at,
      createdBy: w.created_by,
      order: w.sort_order,
    }));

    return {
      _schemaVersion: 1,
      _source: 'api', // Mark that data came from API
      people,
      projects,
      jobs,
      assets: [], // Assets loaded on-demand via get_job
      tasks: allTasks,
      wikiPages: mappedWikiPages,
      scheduledEmails: [],
    };
  },

  /**
   * Sync a single action to the API.
   * Called after reducer updates local state.
   * Fire-and-forget with error logging.
   *
   * @param {string} actionType - The reducer action type
   * @param {*} payload - The action payload
   */
  async syncAction(actionType, payload) {
    try {
      switch (actionType) {
        case 'UPDATE_JOB': {
          // Map frontend job shape -> API shape
          const job = payload;
          const apiData = { id: job.id };

          // Map field names
          if (job.name !== undefined) apiData.title = job.name;
          if (job.description !== undefined) apiData.description = job.description;
          if (job.status !== undefined) apiData.status = job.status;
          if (job.dueDate !== undefined) apiData.delivery_date = job.dueDate;
          if (job.order !== undefined) apiData.sort_order = job.order;
          if (job.allTasksCompletedAt !== undefined) apiData.all_tasks_completed_at = job.allTasksCompletedAt;
          if (job.internalApprovedBy !== undefined) apiData.internal_approved_by = job.internalApprovedBy;
          if (job.internalApprovedAt !== undefined) apiData.internal_approved_at = job.internalApprovedAt;
          if (job.clientApprovedBy !== undefined) apiData.client_approved_by = job.clientApprovedBy;
          if (job.clientApprovedAt !== undefined) apiData.client_approved_at = job.clientApprovedAt;
          if (job.clientFeedback !== undefined) apiData.client_feedback = job.clientFeedback;
          if (job.clientFeedbackBy !== undefined) apiData.client_feedback_by = job.clientFeedbackBy;
          if (job.clientFeedbackAt !== undefined) apiData.client_feedback_at = job.clientFeedbackAt;
          if (job.clientFeedbackStatus !== undefined) apiData.client_feedback_status = job.clientFeedbackStatus;
          if (job.internalFeedback !== undefined) apiData.internal_feedback = job.internalFeedback;
          if (job.internalFeedbackBy !== undefined) apiData.internal_feedback_by = job.internalFeedbackBy;
          if (job.internalFeedbackAt !== undefined) apiData.internal_feedback_at = job.internalFeedbackAt;
          if (job.assignments !== undefined) apiData.assignments = job.assignments;

          await this.updateJob(apiData);
          break;
        }

        case 'UPDATE_TASK': {
          const task = payload;
          const apiData = {
            id: task.id,
            status: task.status,
            content: task.content,
            assigned_to: task.assignedTo,
            character_count: task.characterCount,
            file_url: task.fileUrl,
            file_type: task.fileType,
            completed_at: task.completedAt,
            sort_order: task.order,
          };
          const result = await this.updateTask(apiData);

          // If the reducer also changed the job (e.g. auto-routing),
          // we need to sync that too. The reducer modifies the job in
          // the UPDATE_TASK handler, but we handle that separately --
          // the job sync will be triggered by the SET_DATA from re-fetch.
          break;
        }

        case 'UPDATE_ASSET': {
          const asset = payload;
          await this.updateAsset({
            id: asset.id,
            name: asset.name,
            type: asset.type,
            template_id: asset.templateId,
            status: asset.status,
            assigned_to: asset.assignedTo,
            due_date: asset.dueDate,
            sort_order: asset.order,
          });
          break;
        }

        case 'UPDATE_PERSON': {
          // Users are managed via add_user; no update_user endpoint yet
          // For now, skip
          console.log('API sync: UPDATE_PERSON not yet implemented');
          break;
        }

        case 'ADD_JOB': {
          // Job already created via addJob() in the brief form component
          // The frontend-created job ID won't match the DB ID, so we
          // need to handle this differently. For now, skip -- the brief
          // form should call api.addJob() directly.
          break;
        }

        case 'UPDATE_WIKI_PAGE': {
          const page = payload;
          await this.updateWikiPage({
            id: page.id,
            title: page.title,
            slug: page.slug,
            content: page.content,
            template_id: page.templateId,
            parent_id: page.parentId,
            type: page.type,
            tags: Array.isArray(page.tags) ? page.tags.join(',') : page.tags,
            sort_order: page.order,
            linkedJobs: page.linkedJobs,
            linkedProjects: page.linkedProjects,
          });
          break;
        }

        // Reorder actions: batch update sort_order
        case 'REORDER_JOBS': {
          const ops = payload.map((j, i) => ({
            action: 'update_job',
            data: { id: j.id, sort_order: i },
          }));
          if (ops.length > 0 && ops.length <= 20) {
            await this.batch(ops);
          }
          break;
        }

        // Actions we don't sync (or sync differently):
        case 'SET_DATA':
        case 'ADD_PROJECT':
        case 'UPDATE_PROJECT':
        case 'DELETE_PROJECT':
        case 'DELETE_JOB':
        case 'ADD_ASSET':
        case 'DELETE_ASSET':
        case 'REORDER_ASSETS':
        case 'ADD_PERSON':
        case 'DELETE_PERSON':
        case 'ADD_WIKI_PAGE':
        case 'DELETE_WIKI_PAGE':
        case 'ADD_TASK':
        case 'DELETE_TASK':
        case 'ADD_SCHEDULED_EMAIL':
        case 'DELETE_SCHEDULED_EMAIL':
          // These will be implemented as needed
          break;

        default:
          console.log('API sync: unhandled action', actionType);
      }
    } catch (err) {
      console.error(`API sync failed for ${actionType}:`, err);
      // Don't throw -- we're fire-and-forget for now
      // Phase 3.3+ will add rollback/retry logic
    }
  },
};
