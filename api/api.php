<?php
// ============================================================================
// api.php -- Single-file API router for Slash 301 PM
// All endpoints via ?action=...
// ============================================================================

// CORS headers -- same-origin in production, but needed for dev
header('Access-Control-Allow-Origin: https://projects.slash301.com');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// auth.php includes db.php, starts session, sets security headers
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

$action = $_GET['action'] ?? '';

// Action whitelist
$validActions = [
    // Auth (no session required)
    'login', 'check_session', 'logout', 'csrf_token',
    // Demo mode
    'demo_login', 'toggle_demo_mode',
    // Read (session required)
    'get_brands', 'get_users', 'get_jobs', 'get_job', 'get_campaigns',
    'get_wiki_pages', 'get_wiki_page',
    // Write (session + CSRF required)
    'add_job', 'update_job', 'update_task', 'add_user',
    'update_asset', 'update_wiki_page',
    // Workflow (session + CSRF required)
    'approve_internal', 'approve_client', 'reject_with_feedback',
    // Utility
    'batch', 'health',
];

if (!in_array($action, $validActions, true)) {
    jsonError("Unknown action: {$action}", 400);
}

$db = getDb();

// Parse JSON body for POST requests
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $input = json_decode($raw, true) ?? [];
    }
}

// ============================================================================
// AUTHENTICATION & AUTHORIZATION ENFORCEMENT
// ============================================================================

// Actions that don't require a session
$publicActions = ['health', 'login', 'check_session', 'logout', 'csrf_token', 'demo_login'];

// Actions that require CSRF token (all POST write actions)
$csrfRequired = [
    'add_job', 'update_job', 'update_task', 'add_user',
    'update_asset', 'update_wiki_page',
    'approve_internal', 'approve_client', 'reject_with_feedback',
    'batch', 'toggle_demo_mode',
];

// Check session for protected actions
$sessionUser = null;
$demoMode = isDemoMode();

if (!in_array($action, $publicActions, true)) {
    $sessionUser = getSessionUser();

    // In demo mode, allow demo_login to set a temporary session user
    if (!$sessionUser && $demoMode && !empty($_SESSION['demo_user_id'])) {
        // Demo mode: load user from session demo_user_id
        $stmt = $db->prepare('SELECT id, username, name, email, role, color, brand_id FROM users WHERE id = :id AND is_active = 1');
        $stmt->bindValue(':id', $_SESSION['demo_user_id'], SQLITE3_TEXT);
        $result = $stmt->execute();
        $sessionUser = $result->fetchArray(SQLITE3_ASSOC);
        if ($sessionUser && $sessionUser['role'] === 'Client' && $sessionUser['brand_id']) {
            $bStmt = $db->prepare('SELECT name FROM brands WHERE id = :id');
            $bStmt->bindValue(':id', $sessionUser['brand_id'], SQLITE3_TEXT);
            $bResult = $bStmt->execute();
            $brand = $bResult->fetchArray(SQLITE3_ASSOC);
            $sessionUser['brand'] = $brand ? $brand['name'] : null;
        }
    }

    if (!$sessionUser) {
        jsonError('Authentication required', 401);
    }
}

// CSRF validation for write actions
if (in_array($action, $csrfRequired, true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Skip CSRF check in demo mode (no real session tokens)
    if (!$demoMode && !validateCsrfToken()) {
        jsonError('Invalid or missing CSRF token', 403);
    }
}

// ============================================================================
// ROUTE TO ACTION
// ============================================================================

switch ($action) {

    // ========================================================================
    // HEALTH CHECK
    // ========================================================================
    case 'health':
        jsonResponse(['status' => 'ok', 'timestamp' => date('c'), 'demo_mode' => $demoMode]);

    // ========================================================================
    // AUTH ENDPOINTS
    // ========================================================================

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            jsonError('Username and password required');
        }

        $result = handleLogin($username, $password);

        if ($result['success']) {
            jsonResponse([
                'user' => $result['user'],
                'csrf_token' => $result['csrf_token'],
            ]);
        } else {
            $status = isset($result['retry_after']) ? 429 : 401;
            jsonResponse(['error' => $result['error']], $status);
        }

    case 'check_session':
        $user = getSessionUser();

        // In demo mode, also check demo session
        if (!$user && $demoMode && !empty($_SESSION['demo_user_id'])) {
            $stmt = $db->prepare('SELECT id, username, name, email, role, color, brand_id FROM users WHERE id = :id AND is_active = 1');
            $stmt->bindValue(':id', $_SESSION['demo_user_id'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            if ($user && $user['role'] === 'Client' && $user['brand_id']) {
                $bStmt = $db->prepare('SELECT name FROM brands WHERE id = :id');
                $bStmt->bindValue(':id', $user['brand_id'], SQLITE3_TEXT);
                $bResult = $bStmt->execute();
                $brand = $bResult->fetchArray(SQLITE3_ASSOC);
                $user['brand'] = $brand ? $brand['name'] : null;
            }
        }

        if (!$user) {
            $response = ['authenticated' => false, 'demo_mode' => $demoMode];
            // In demo mode, include user list so login screen can show user-switcher
            if ($demoMode) {
                $usersQuery = $db->query('
                    SELECT u.id, u.name, u.role, u.color, u.brand_id, b.name as brand_name
                    FROM users u
                    LEFT JOIN brands b ON u.brand_id = b.id
                    WHERE u.is_active = 1
                    ORDER BY u.role, u.name
                ');
                $demoUsers = [];
                while ($row = $usersQuery->fetchArray(SQLITE3_ASSOC)) {
                    $demoUsers[] = $row;
                }
                $response['users'] = $demoUsers;
            }
            jsonResponse($response);
        }

        jsonResponse([
            'authenticated' => true,
            'user' => $user,
            'csrf_token' => getCsrfToken(),
            'demo_mode' => $demoMode,
        ]);

    case 'logout':
        handleLogout();
        jsonResponse(['success' => true]);

    case 'csrf_token':
        $user = getSessionUser();
        if (!$user) {
            jsonError('Not authenticated', 401);
        }
        jsonResponse(['csrf_token' => getCsrfToken()]);

    // ========================================================================
    // DEMO MODE ENDPOINTS
    // ========================================================================

    case 'demo_login':
        // In demo mode, allow switching users without password
        if (!$demoMode) {
            jsonError('Demo mode is not enabled', 403);
        }

        $userId = $input['user_id'] ?? $_GET['user_id'] ?? null;
        if (!$userId) {
            jsonError('Missing user_id');
        }

        $stmt = $db->prepare('SELECT id, username, name, email, role, color, brand_id FROM users WHERE id = :id AND is_active = 1');
        $stmt->bindValue(':id', $userId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        if (!$user) {
            jsonError('User not found', 404);
        }

        // Set demo session
        $_SESSION['demo_user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_brand_id'] = $user['brand_id'];
        $_SESSION['last_activity'] = time();

        // Add brand name for clients
        if ($user['role'] === 'Client' && $user['brand_id']) {
            $bStmt = $db->prepare('SELECT name FROM brands WHERE id = :id');
            $bStmt->bindValue(':id', $user['brand_id'], SQLITE3_TEXT);
            $bResult = $bStmt->execute();
            $brand = $bResult->fetchArray(SQLITE3_ASSOC);
            $user['brand'] = $brand ? $brand['name'] : null;
        }

        jsonResponse(['user' => $user, 'demo_mode' => true]);

    case 'toggle_demo_mode':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        // Only COO can toggle demo mode
        if (!$sessionUser || $sessionUser['role'] !== 'COO') {
            jsonError('Only COO can toggle demo mode', 403);
        }

        $enabled = $input['enabled'] ?? !isDemoMode();
        setDemoMode((bool) $enabled);
        jsonResponse(['success' => true, 'demo_mode' => isDemoMode()]);

    // ========================================================================
    // READ ENDPOINTS
    // ========================================================================

    case 'get_brands':
        $results = [];
        $query = $db->query('SELECT * FROM brands ORDER BY name');
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
        }
        jsonResponse(['brands' => $results]);

    case 'get_users':
        $results = [];
        // Phase 3.4: Explicit column selection -- never SELECT * for users
        $query = $db->query('
            SELECT u.id, u.username, u.name, u.email, u.role, u.color,
                   u.brand_id, u.is_active, u.created_at,
                   b.name as brand_name
            FROM users u
            LEFT JOIN brands b ON u.brand_id = b.id
            WHERE u.is_active = 1
            ORDER BY u.role, u.name
        ');
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
        }
        jsonResponse(['users' => $results]);

    case 'get_campaigns':
        $brandId = $_GET['brand_id'] ?? null;
        if ($brandId) {
            $stmt = $db->prepare('
                SELECT c.*, b.name as brand_name
                FROM campaigns c
                JOIN brands b ON c.brand_id = b.id
                WHERE c.brand_id = :brand_id
                ORDER BY c.created_at DESC
            ');
            $stmt->bindValue(':brand_id', $brandId, SQLITE3_TEXT);
            $query = $stmt->execute();
        } else {
            $query = $db->query('
                SELECT c.*, b.name as brand_name
                FROM campaigns c
                JOIN brands b ON c.brand_id = b.id
                ORDER BY c.created_at DESC
            ');
        }
        $results = [];
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
        }
        jsonResponse(['campaigns' => $results]);

    case 'get_jobs':
        // Optional filters
        $status = $_GET['status'] ?? null;
        $campaignId = $_GET['campaign_id'] ?? null;

        // Base query: jobs with campaign + brand info
        $sql = '
            SELECT j.*,
                   c.name as campaign_name, c.brand_id,
                   b.name as brand_name, b.prefix as brand_prefix
            FROM jobs j
            LEFT JOIN campaigns c ON j.campaign_id = c.id
            LEFT JOIN brands b ON c.brand_id = b.id
        ';

        $where = [];
        $params = [];

        // Phase 3.4: Server-side client brand isolation
        // Clients can ONLY see jobs for their own brand -- enforced regardless of query params
        if (isClient($sessionUser)) {
            $where[] = 'c.brand_id = :brand_id';
            $params[':brand_id'] = $sessionUser['brand_id'];
        } elseif ($campaignId) {
            $where[] = 'j.campaign_id = :campaign_id';
            $params[':campaign_id'] = $campaignId;
        }

        if ($status) {
            $where[] = 'j.status = :status';
            $params[':status'] = $status;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY j.sort_order, j.created_at DESC';

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, SQLITE3_TEXT);
        }
        $query = $stmt->execute();

        $jobs = [];
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            // Attach assignments
            $aStmt = $db->prepare('
                SELECT ja.role_on_job, ja.user_id, u.name as user_name
                FROM job_assignments ja
                JOIN users u ON ja.user_id = u.id
                WHERE ja.job_id = :job_id
            ');
            $aStmt->bindValue(':job_id', $row['id'], SQLITE3_TEXT);
            $aResult = $aStmt->execute();
            $assignments = [];
            while ($a = $aResult->fetchArray(SQLITE3_ASSOC)) {
                $assignments[$a['role_on_job']] = $a['user_id'];
            }
            $row['assignments'] = $assignments;

            // Attach tasks summary
            $tStmt = $db->prepare('
                SELECT id, type, status, assigned_to
                FROM tasks
                WHERE job_id = :job_id
                ORDER BY sort_order
            ');
            $tStmt->bindValue(':job_id', $row['id'], SQLITE3_TEXT);
            $tResult = $tStmt->execute();
            $tasks = [];
            while ($t = $tResult->fetchArray(SQLITE3_ASSOC)) {
                $tasks[] = $t;
            }
            $row['tasks'] = $tasks;

            $jobs[] = $row;
        }
        jsonResponse(['jobs' => $jobs]);

    case 'get_job':
        $jobId = $_GET['id'] ?? null;
        if (!$jobId) {
            jsonError('Missing id parameter');
        }

        $stmt = $db->prepare('
            SELECT j.*,
                   c.name as campaign_name, c.brand_id, c.description as campaign_description,
                   b.name as brand_name, b.prefix as brand_prefix
            FROM jobs j
            LEFT JOIN campaigns c ON j.campaign_id = c.id
            LEFT JOIN brands b ON c.brand_id = b.id
            WHERE j.id = :id
        ');
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $job = $result->fetchArray(SQLITE3_ASSOC);

        if (!$job) {
            jsonError('Job not found', 404);
        }

        // Phase 3.4: IDOR prevention -- client can only see their own brand's jobs
        if (isClient($sessionUser)) {
            if ($job['brand_id'] !== $sessionUser['brand_id']) {
                jsonError('Job not found', 404);  // 404 not 403 to avoid leaking existence
            }
        }

        // Denormalize: include assignments with person details
        $aStmt = $db->prepare('
            SELECT ja.role_on_job, ja.user_id, u.name as user_name, u.email as user_email, u.color as user_color
            FROM job_assignments ja
            JOIN users u ON ja.user_id = u.id
            WHERE ja.job_id = :job_id
        ');
        $aStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
        $aResult = $aStmt->execute();
        $assignments = [];
        while ($a = $aResult->fetchArray(SQLITE3_ASSOC)) {
            $assignments[$a['role_on_job']] = [
                'user_id' => $a['user_id'],
                'name' => $a['user_name'],
                'email' => $a['user_email'],
                'color' => $a['user_color'],
            ];
        }
        $job['assignments'] = $assignments;

        // Denormalize: include full tasks
        $tStmt = $db->prepare('
            SELECT t.*, u.name as assigned_to_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.job_id = :job_id
            ORDER BY t.sort_order
        ');
        $tStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
        $tResult = $tStmt->execute();
        $tasks = [];
        while ($t = $tResult->fetchArray(SQLITE3_ASSOC)) {
            $tasks[] = $t;
        }
        $job['tasks'] = $tasks;

        // Denormalize: include assets
        $asStmt = $db->prepare('
            SELECT a.*, u.name as assigned_to_name
            FROM assets a
            LEFT JOIN users u ON a.assigned_to = u.id
            WHERE a.job_id = :job_id
            ORDER BY a.sort_order
        ');
        $asStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
        $asResult = $asStmt->execute();
        $assets = [];
        while ($as = $asResult->fetchArray(SQLITE3_ASSOC)) {
            $assets[] = $as;
        }
        $job['assets'] = $assets;

        jsonResponse(['job' => $job]);

    case 'get_wiki_pages':
        $results = [];
        $query = $db->query('
            SELECT id, title, slug, template_id, parent_id, type, tags, sort_order, created_at, updated_at
            FROM wiki_pages
            ORDER BY sort_order, title
        ');
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            // Attach links
            $lStmt = $db->prepare('SELECT entity_type, entity_id FROM wiki_page_links WHERE wiki_page_id = :id');
            $lStmt->bindValue(':id', $row['id'], SQLITE3_TEXT);
            $lResult = $lStmt->execute();
            $linkedJobs = [];
            $linkedProjects = [];
            while ($l = $lResult->fetchArray(SQLITE3_ASSOC)) {
                if ($l['entity_type'] === 'job') $linkedJobs[] = $l['entity_id'];
                if ($l['entity_type'] === 'project') $linkedProjects[] = $l['entity_id'];
            }
            $row['linkedJobs'] = $linkedJobs;
            $row['linkedProjects'] = $linkedProjects;
            $results[] = $row;
        }
        jsonResponse(['wikiPages' => $results]);

    case 'get_wiki_page':
        $wikiId = $_GET['id'] ?? null;
        if (!$wikiId) {
            jsonError('Missing id parameter');
        }
        $stmt = $db->prepare('SELECT * FROM wiki_pages WHERE id = :id');
        $stmt->bindValue(':id', $wikiId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $page = $result->fetchArray(SQLITE3_ASSOC);
        if (!$page) {
            jsonError('Wiki page not found', 404);
        }
        // Attach links
        $lStmt = $db->prepare('SELECT entity_type, entity_id FROM wiki_page_links WHERE wiki_page_id = :id');
        $lStmt->bindValue(':id', $page['id'], SQLITE3_TEXT);
        $lResult = $lStmt->execute();
        $linkedJobs = [];
        $linkedProjects = [];
        while ($l = $lResult->fetchArray(SQLITE3_ASSOC)) {
            if ($l['entity_type'] === 'job') $linkedJobs[] = $l['entity_id'];
            if ($l['entity_type'] === 'project') $linkedProjects[] = $l['entity_id'];
        }
        $page['linkedJobs'] = $linkedJobs;
        $page['linkedProjects'] = $linkedProjects;
        jsonResponse(['wikiPage' => $page]);

    // ========================================================================
    // WRITE ENDPOINTS
    // ========================================================================

    case 'add_job':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        // Phase 3.4: Authorization -- only Admin/Manager can create jobs
        authorize($sessionUser, 'create_job');

        // Phase 3.4: Input validation
        $validated = validateInput($input, [
            'title'              => 'required|string|max:500',
            'campaign_id'        => 'required|id',
            'description'        => 'string|max:5000',
            'status'             => 'string|in:Inbox,Brief,To Do,In Progress,Today,This Week,Waiting,On Hold',
            'creative_direction' => 'string|max:5000',
            'brief_date'         => 'date',
            'delivery_date'      => 'date',
            'hours_estimate'     => 'float',
        ]);

        $required = ['title', 'campaign_id'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonError("Missing required field: {$field}");
            }
        }

        // Verify campaign exists and get brand prefix for job number
        $cStmt = $db->prepare('
            SELECT c.id, c.brand_id, b.prefix
            FROM campaigns c
            JOIN brands b ON c.brand_id = b.id
            WHERE c.id = :id
        ');
        $cStmt->bindValue(':id', $input['campaign_id'], SQLITE3_TEXT);
        $cResult = $cStmt->execute();
        $campaign = $cResult->fetchArray(SQLITE3_ASSOC);
        if (!$campaign) {
            jsonError('Campaign not found');
        }

        // Generate job number: PREFIX-NNN
        $countStmt = $db->prepare('
            SELECT COUNT(*) as cnt FROM jobs j
            JOIN campaigns c ON j.campaign_id = c.id
            WHERE c.brand_id = :brand_id
        ');
        $countStmt->bindValue(':brand_id', $campaign['brand_id'], SQLITE3_TEXT);
        $countResult = $countStmt->execute();
        $count = $countResult->fetchArray(SQLITE3_ASSOC)['cnt'];
        $jobNumber = $campaign['prefix'] . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        $jobId = generateId();

        $db->exec('BEGIN TRANSACTION');

        $stmt = $db->prepare('
            INSERT INTO jobs (id, job_number, campaign_id, title, description, status, creative_direction, brief_date, delivery_date, hours_estimate, sort_order, created_by)
            VALUES (:id, :job_number, :campaign_id, :title, :description, :status, :creative_direction, :brief_date, :delivery_date, :hours_estimate, :sort_order, :created_by)
        ');
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $stmt->bindValue(':job_number', $jobNumber, SQLITE3_TEXT);
        $stmt->bindValue(':campaign_id', $input['campaign_id'], SQLITE3_TEXT);
        $stmt->bindValue(':title', $input['title'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $input['description'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':status', $input['status'] ?? 'Inbox', SQLITE3_TEXT);
        $stmt->bindValue(':creative_direction', $input['creative_direction'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':brief_date', $input['brief_date'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':delivery_date', $input['delivery_date'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':hours_estimate', $input['hours_estimate'] ?? null, SQLITE3_FLOAT);
        $stmt->bindValue(':sort_order', $input['sort_order'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':created_by', $input['created_by'] ?? null, SQLITE3_TEXT);
        $stmt->execute();

        // Insert assignments if provided
        if (!empty($input['assignments']) && is_array($input['assignments'])) {
            foreach ($input['assignments'] as $role => $userId) {
                if ($userId === null) continue;
                $aStmt = $db->prepare('
                    INSERT INTO job_assignments (id, job_id, user_id, role_on_job)
                    VALUES (:id, :job_id, :user_id, :role_on_job)
                ');
                $aStmt->bindValue(':id', generateId(), SQLITE3_TEXT);
                $aStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
                $aStmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
                $aStmt->bindValue(':role_on_job', $role, SQLITE3_TEXT);
                $aStmt->execute();
            }
        }

        // Auto-create default tasks (copy + media) if none provided
        if (empty($input['tasks'])) {
            $copywriter = $input['assignments']['Copywriter'] ?? null;
            $designer = $input['assignments']['Designer'] ?? null;

            $tStmt = $db->prepare('INSERT INTO tasks (id, job_id, type, status, assigned_to, sort_order) VALUES (:id, :job_id, :type, :status, :assigned_to, :sort_order)');

            $tStmt->bindValue(':id', generateId(), SQLITE3_TEXT);
            $tStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
            $tStmt->bindValue(':type', 'copy', SQLITE3_TEXT);
            $tStmt->bindValue(':status', 'Not Started', SQLITE3_TEXT);
            $tStmt->bindValue(':assigned_to', $copywriter, SQLITE3_TEXT);
            $tStmt->bindValue(':sort_order', 0, SQLITE3_INTEGER);
            $tStmt->execute();

            $tStmt->reset();
            $tStmt->bindValue(':id', generateId(), SQLITE3_TEXT);
            $tStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
            $tStmt->bindValue(':type', 'media', SQLITE3_TEXT);
            $tStmt->bindValue(':status', 'Not Started', SQLITE3_TEXT);
            $tStmt->bindValue(':assigned_to', $designer, SQLITE3_TEXT);
            $tStmt->bindValue(':sort_order', 1, SQLITE3_INTEGER);
            $tStmt->execute();
        }

        $db->exec('COMMIT');

        // Return the full job via get_job logic
        jsonResponse(['job' => ['id' => $jobId, 'job_number' => $jobNumber]], 201);

    case 'update_job':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        $jobId = $input['id'] ?? null;
        if (!$jobId) jsonError('Missing id');

        // Phase 3.4: Validate job ID format
        validateInput(['id' => $jobId], ['id' => 'required|id']);

        // Verify job exists
        $stmt = $db->prepare('SELECT id FROM jobs WHERE id = :id');
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $result = $stmt->execute();
        if (!$result->fetchArray()) jsonError('Job not found', 404);

        // Phase 3.4: Authorization -- Admin/Manager: any; Creative: only if assigned; Client: denied
        authorize($sessionUser, 'update_job', ['id' => $jobId]);

        // Allowed fields for update
        $allowedFields = [
            'title', 'description', 'status', 'creative_direction',
            'brief_date', 'delivery_date', 'hours_estimate',
            'all_tasks_completed_at', 'sort_order',
            'client_feedback', 'client_feedback_by', 'client_feedback_at',
            'client_feedback_status', 'client_feedback_assigned_to',
            'client_feedback_assigned_role', 'client_feedback_actioned_by',
            'client_feedback_actioned_at',
            'internal_feedback', 'internal_feedback_by', 'internal_feedback_at',
        ];

        $sets = [];
        $params = [':id' => $jobId];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }

        if (empty($sets) && empty($input['assignments'])) {
            jsonError('No fields to update');
        }

        $db->exec('BEGIN TRANSACTION');

        if (!empty($sets)) {
            $sql = 'UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
        }

        // Update assignments if provided
        if (!empty($input['assignments']) && is_array($input['assignments'])) {
            foreach ($input['assignments'] as $role => $userId) {
                if ($userId === null) {
                    // Remove assignment
                    $dStmt = $db->prepare('DELETE FROM job_assignments WHERE job_id = :job_id AND role_on_job = :role');
                    $dStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
                    $dStmt->bindValue(':role', $role, SQLITE3_TEXT);
                    $dStmt->execute();
                } else {
                    // Upsert assignment
                    $dStmt = $db->prepare('DELETE FROM job_assignments WHERE job_id = :job_id AND role_on_job = :role');
                    $dStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
                    $dStmt->bindValue(':role', $role, SQLITE3_TEXT);
                    $dStmt->execute();

                    $iStmt = $db->prepare('INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :job_id, :user_id, :role_on_job)');
                    $iStmt->bindValue(':id', generateId(), SQLITE3_TEXT);
                    $iStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
                    $iStmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
                    $iStmt->bindValue(':role_on_job', $role, SQLITE3_TEXT);
                    $iStmt->execute();
                }
            }
        }

        $db->exec('COMMIT');
        jsonResponse(['success' => true]);

    case 'update_task':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        $taskId = $input['id'] ?? null;
        if (!$taskId) jsonError('Missing id');

        // Phase 3.4: Validate task ID format
        validateInput(['id' => $taskId], ['id' => 'required|id']);

        // Verify task exists (fetch assigned_to for authorization)
        $stmt = $db->prepare('SELECT id, job_id, assigned_to FROM tasks WHERE id = :id');
        $stmt->bindValue(':id', $taskId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $task = $result->fetchArray(SQLITE3_ASSOC);
        if (!$task) jsonError('Task not found', 404);

        // Phase 3.4: Authorization -- Creative can only edit tasks assigned to them
        authorize($sessionUser, 'update_task', $task);

        $allowedFields = [
            'status', 'content', 'assigned_to', 'character_count',
            'file_url', 'file_type', 'internal_feedback', 'feedback_by',
            'feedback_at', 'sort_order', 'completed_at', 'completed_by',
        ];

        $sets = [];
        $params = [':id' => $taskId];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }

        if (empty($sets)) jsonError('No fields to update');

        $sql = 'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        jsonResponse(['success' => true, 'job_id' => $task['job_id']]);

    case 'add_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        // Phase 3.4: Authorization -- only COO can add users
        authorize($sessionUser, 'add_user');

        // Phase 3.4: Input validation
        $validRolesStr = implode(',', ALL_ROLES);
        validateInput($input, [
            'name'     => 'required|string|min:2|max:100',
            'role'     => "required|string|in:{$validRolesStr}",
            'username' => 'required|string|min:3|max:100',
            'password' => 'required|string|min:12|max:128',
            'email'    => 'string|max:200',
            'color'    => 'string|max:20',
            'brand_id' => 'id',
        ]);

        $required = ['name', 'role', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                jsonError("Missing required field: {$field}");
            }
        }

        // Check username uniqueness
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->bindValue(':username', $input['username'], SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            jsonError('Username already taken');
        }

        $userId = generateId();
        $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);

        $stmt = $db->prepare('
            INSERT INTO users (id, username, password_hash, name, email, role, color, brand_id)
            VALUES (:id, :username, :password_hash, :name, :email, :role, :color, :brand_id)
        ');
        $stmt->bindValue(':id', $userId, SQLITE3_TEXT);
        $stmt->bindValue(':username', $input['username'], SQLITE3_TEXT);
        $stmt->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
        $stmt->bindValue(':name', $input['name'], SQLITE3_TEXT);
        $stmt->bindValue(':email', $input['email'] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':role', $input['role'], SQLITE3_TEXT);
        $stmt->bindValue(':color', $input['color'] ?? '#3b82f6', SQLITE3_TEXT);
        $stmt->bindValue(':brand_id', $input['brand_id'] ?? null, SQLITE3_TEXT);
        $stmt->execute();

        jsonResponse(['user' => ['id' => $userId]], 201);

    case 'update_asset':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        $assetId = $input['id'] ?? null;
        if (!$assetId) jsonError('Missing id');

        validateInput(['id' => $assetId], ['id' => 'required|id']);

        $stmt = $db->prepare('SELECT id, assigned_to FROM assets WHERE id = :id');
        $stmt->bindValue(':id', $assetId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $asset = $result->fetchArray(SQLITE3_ASSOC);
        if (!$asset) jsonError('Asset not found', 404);

        // Phase 3.4: Authorization
        authorize($sessionUser, 'update_asset', $asset);

        $allowedFields = ['name', 'type', 'template_id', 'status', 'assigned_to', 'due_date', 'sort_order'];
        $sets = [];
        $params = [':id' => $assetId];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }
        if (empty($sets)) jsonError('No fields to update');

        $sql = 'UPDATE assets SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        jsonResponse(['success' => true]);

    case 'update_wiki_page':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        // Phase 3.4: Authorization -- Admin/Manager only
        authorize($sessionUser, 'update_wiki');

        $pageId = $input['id'] ?? null;
        if (!$pageId) jsonError('Missing id');

        validateInput(['id' => $pageId], ['id' => 'required|id']);

        $stmt = $db->prepare('SELECT id FROM wiki_pages WHERE id = :id');
        $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
        $result = $stmt->execute();
        if (!$result->fetchArray()) jsonError('Wiki page not found', 404);

        $allowedFields = ['title', 'slug', 'content', 'template_id', 'parent_id', 'type', 'tags', 'sort_order'];
        $sets = [];
        $params = [':id' => $pageId];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }
        if (empty($sets) && empty($input['linkedJobs']) && empty($input['linkedProjects'])) {
            jsonError('No fields to update');
        }

        $db->exec('BEGIN TRANSACTION');

        if (!empty($sets)) {
            $sql = 'UPDATE wiki_pages SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
        }

        // Update links if provided
        if (array_key_exists('linkedJobs', $input) || array_key_exists('linkedProjects', $input)) {
            $db->prepare('DELETE FROM wiki_page_links WHERE wiki_page_id = :id')
               ->bindValue(':id', $pageId, SQLITE3_TEXT);
            // Re-delete properly
            $delStmt = $db->prepare('DELETE FROM wiki_page_links WHERE wiki_page_id = :id');
            $delStmt->bindValue(':id', $pageId, SQLITE3_TEXT);
            $delStmt->execute();

            if (!empty($input['linkedJobs'])) {
                foreach ($input['linkedJobs'] as $jobId) {
                    $lStmt = $db->prepare('INSERT INTO wiki_page_links (wiki_page_id, entity_type, entity_id) VALUES (:wiki_id, :type, :eid)');
                    $lStmt->bindValue(':wiki_id', $pageId, SQLITE3_TEXT);
                    $lStmt->bindValue(':type', 'job', SQLITE3_TEXT);
                    $lStmt->bindValue(':eid', $jobId, SQLITE3_TEXT);
                    $lStmt->execute();
                }
            }
            if (!empty($input['linkedProjects'])) {
                foreach ($input['linkedProjects'] as $projId) {
                    $lStmt = $db->prepare('INSERT INTO wiki_page_links (wiki_page_id, entity_type, entity_id) VALUES (:wiki_id, :type, :eid)');
                    $lStmt->bindValue(':wiki_id', $pageId, SQLITE3_TEXT);
                    $lStmt->bindValue(':type', 'project', SQLITE3_TEXT);
                    $lStmt->bindValue(':eid', $projId, SQLITE3_TEXT);
                    $lStmt->execute();
                }
            }
        }

        $db->exec('COMMIT');
        jsonResponse(['success' => true]);

    // ========================================================================
    // WORKFLOW ENDPOINTS
    // ========================================================================

    case 'approve_internal':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        // Phase 3.4: Authorization -- Admin, CD/ECD only
        authorize($sessionUser, 'approve_internal');

        $jobId = $input['job_id'] ?? null;
        $approvedBy = $input['approved_by'] ?? $sessionUser['id'];
        if (!$jobId) jsonError('Missing job_id');

        // Validate current status: all tasks must be Done, job status allows internal approval
        $stmt = $db->prepare('SELECT status, all_tasks_completed_at FROM jobs WHERE id = :id');
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $job = $result->fetchArray(SQLITE3_ASSOC);
        if (!$job) jsonError('Job not found', 404);

        // Check tasks are all done
        $tStmt = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN status = \'Done\' THEN 1 ELSE 0 END) as done FROM tasks WHERE job_id = :job_id');
        $tStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
        $tResult = $tStmt->execute();
        $taskCounts = $tResult->fetchArray(SQLITE3_ASSOC);

        if ($taskCounts['total'] > 0 && $taskCounts['done'] < $taskCounts['total']) {
            jsonError('Cannot approve internally: not all tasks are Done');
        }

        // Valid source statuses for internal approval
        $validFrom = ['In Progress', 'In Review', 'Today', 'This Week'];
        if (!in_array($job['status'], $validFrom, true)) {
            jsonError("Cannot approve internally from status: {$job['status']}");
        }

        $stmt = $db->prepare('
            UPDATE jobs SET
                status = \'Approved (Internal)\',
                internal_approved_by = :approved_by,
                internal_approved_at = datetime(\'now\')
            WHERE id = :id
        ');
        $stmt->bindValue(':approved_by', $approvedBy, SQLITE3_TEXT);
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $stmt->execute();

        jsonResponse(['success' => true, 'new_status' => 'Approved (Internal)']);

    case 'approve_client':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        $jobId = $input['job_id'] ?? null;
        $approvedBy = $input['approved_by'] ?? $sessionUser['id'];
        if (!$jobId) jsonError('Missing job_id');

        $stmt = $db->prepare('SELECT j.id, j.status, c.brand_id FROM jobs j LEFT JOIN campaigns c ON j.campaign_id = c.id WHERE j.id = :id');
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $job = $result->fetchArray(SQLITE3_ASSOC);
        if (!$job) jsonError('Job not found', 404);

        // Phase 3.4: Authorization -- Client (own brand) or Admin
        authorize($sessionUser, 'approve_client', $job);

        // Must be internally approved first
        if ($job['status'] !== 'Approved (Internal)') {
            jsonError("Cannot approve by client from status: {$job['status']}. Must be 'Approved (Internal)' first.");
        }

        $stmt = $db->prepare('
            UPDATE jobs SET
                status = \'Approved (External)\',
                client_approved_by = :approved_by,
                client_approved_at = datetime(\'now\')
            WHERE id = :id
        ');
        $stmt->bindValue(':approved_by', $approvedBy, SQLITE3_TEXT);
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $stmt->execute();

        jsonResponse(['success' => true, 'new_status' => 'Approved (External)']);

    case 'reject_with_feedback':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        $jobId = $input['job_id'] ?? null;
        $feedback = $input['feedback'] ?? null;
        $feedbackBy = $input['feedback_by'] ?? $sessionUser['id'];
        $isInternal = $input['is_internal'] ?? false;
        if (!$jobId || !$feedback) {
            jsonError('Missing job_id or feedback');
        }

        // Phase 3.4: Validate feedback length
        validateInput(['feedback' => $feedback], ['feedback' => 'required|string|max:10000']);

        $stmt = $db->prepare('SELECT j.id, j.status, c.brand_id FROM jobs j LEFT JOIN campaigns c ON j.campaign_id = c.id WHERE j.id = :id');
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $job = $result->fetchArray(SQLITE3_ASSOC);
        if (!$job) jsonError('Job not found', 404);

        // Phase 3.4: Authorization
        authorize($sessionUser, 'reject_feedback', $job);

        $db->exec('BEGIN TRANSACTION');

        if ($isInternal) {
            // Internal rejection: CD/ECD rejecting tasks back
            $stmt = $db->prepare('
                UPDATE jobs SET
                    status = \'In Progress\',
                    internal_feedback = :feedback,
                    internal_feedback_by = :feedback_by,
                    internal_feedback_at = datetime(\'now\'),
                    internal_approved_by = NULL,
                    internal_approved_at = NULL,
                    all_tasks_completed_at = NULL
                WHERE id = :id
            ');
        } else {
            // Client rejection
            $stmt = $db->prepare('
                UPDATE jobs SET
                    status = \'In Progress\',
                    client_feedback = :feedback,
                    client_feedback_by = :feedback_by,
                    client_feedback_at = datetime(\'now\'),
                    client_feedback_status = \'pending\',
                    client_approved_by = NULL,
                    client_approved_at = NULL,
                    internal_approved_by = NULL,
                    internal_approved_at = NULL,
                    all_tasks_completed_at = NULL
                WHERE id = :id
            ');
        }
        $stmt->bindValue(':feedback', $feedback, SQLITE3_TEXT);
        $stmt->bindValue(':feedback_by', $feedbackBy, SQLITE3_TEXT);
        $stmt->bindValue(':id', $jobId, SQLITE3_TEXT);
        $stmt->execute();

        // Reset task statuses back to In Progress
        $tStmt = $db->prepare("UPDATE tasks SET status = 'In Progress', completed_at = NULL, completed_by = NULL WHERE job_id = :job_id AND status = 'Done'");
        $tStmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
        $tStmt->execute();

        $db->exec('COMMIT');

        jsonResponse(['success' => true, 'new_status' => 'In Progress']);

    // ========================================================================
    // BATCH ENDPOINT
    // ========================================================================
    case 'batch':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

        $operations = $input['operations'] ?? [];
        if (empty($operations) || !is_array($operations)) {
            jsonError('Missing or empty operations array');
        }

        // Limit batch size
        if (count($operations) > 20) {
            jsonError('Batch too large (max 20 operations)');
        }

        $db->exec('BEGIN TRANSACTION');
        $results = [];

        foreach ($operations as $i => $op) {
            $opAction = $op['action'] ?? null;
            $opInput = $op['data'] ?? [];

            // Only allow write actions in batch
            $batchAllowed = ['update_job', 'update_task', 'update_asset'];
            if (!in_array($opAction, $batchAllowed, true)) {
                $db->exec('ROLLBACK');
                jsonError("Batch operation {$i}: action '{$opAction}' not allowed in batch");
            }

            // Execute inline (simplified -- re-uses same logic)
            try {
                switch ($opAction) {
                    case 'update_job':
                        $jId = $opInput['id'] ?? null;
                        if (!$jId) throw new Exception("Missing id in operation {$i}");
                        $allowedFields = ['title','description','status','sort_order','all_tasks_completed_at',
                            'client_feedback','client_feedback_by','client_feedback_at','client_feedback_status',
                            'internal_feedback','internal_feedback_by','internal_feedback_at'];
                        $sets = [];
                        $params = [':id' => $jId];
                        foreach ($allowedFields as $f) {
                            if (array_key_exists($f, $opInput)) {
                                $sets[] = "{$f} = :{$f}";
                                $params[":{$f}"] = $opInput[$f];
                            }
                        }
                        if (!empty($sets)) {
                            $sql = 'UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE id = :id';
                            $s = $db->prepare($sql);
                            foreach ($params as $k => $v) $s->bindValue($k, $v);
                            $s->execute();
                        }
                        $results[] = ['success' => true, 'action' => $opAction];
                        break;

                    case 'update_task':
                        $tId = $opInput['id'] ?? null;
                        if (!$tId) throw new Exception("Missing id in operation {$i}");
                        $allowedFields = ['status','content','assigned_to','character_count','file_url','file_type',
                            'internal_feedback','feedback_by','feedback_at','sort_order','completed_at','completed_by'];
                        $sets = [];
                        $params = [':id' => $tId];
                        foreach ($allowedFields as $f) {
                            if (array_key_exists($f, $opInput)) {
                                $sets[] = "{$f} = :{$f}";
                                $params[":{$f}"] = $opInput[$f];
                            }
                        }
                        if (!empty($sets)) {
                            $sql = 'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = :id';
                            $s = $db->prepare($sql);
                            foreach ($params as $k => $v) $s->bindValue($k, $v);
                            $s->execute();
                        }
                        $results[] = ['success' => true, 'action' => $opAction];
                        break;

                    case 'update_asset':
                        $aId = $opInput['id'] ?? null;
                        if (!$aId) throw new Exception("Missing id in operation {$i}");
                        $allowedFields = ['name','type','template_id','status','assigned_to','due_date','sort_order'];
                        $sets = [];
                        $params = [':id' => $aId];
                        foreach ($allowedFields as $f) {
                            if (array_key_exists($f, $opInput)) {
                                $sets[] = "{$f} = :{$f}";
                                $params[":{$f}"] = $opInput[$f];
                            }
                        }
                        if (!empty($sets)) {
                            $sql = 'UPDATE assets SET ' . implode(', ', $sets) . ' WHERE id = :id';
                            $s = $db->prepare($sql);
                            foreach ($params as $k => $v) $s->bindValue($k, $v);
                            $s->execute();
                        }
                        $results[] = ['success' => true, 'action' => $opAction];
                        break;
                }
            } catch (Exception $e) {
                $db->exec('ROLLBACK');
                jsonError("Batch operation {$i} failed: " . $e->getMessage());
            }
        }

        $db->exec('COMMIT');
        jsonResponse(['success' => true, 'results' => $results]);

    default:
        jsonError("Unhandled action: {$action}", 400);
}
