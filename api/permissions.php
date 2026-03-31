<?php
// ============================================================================
// permissions.php -- Server-side authorization, input validation, output encoding
// Phase 3.4: Fixes #14 (no server-side permission enforcement)
// ============================================================================

// Role tiers (mirrors constants.js ROLE_PERMISSIONS)
define('ADMIN_ROLES', ['COO', 'ECD']);
define('MANAGER_ROLES', ['AM', 'PM', 'Traffic', 'Producer']);
define('CREATIVE_ROLES', ['CD', 'Copywriter', 'Designer', 'QA', 'Developer', 'SEO', 'Social']);
define('CLIENT_ROLES', ['Client']);

// All valid roles (must match db.php CHECK constraint)
define('ALL_ROLES', array_merge(ADMIN_ROLES, MANAGER_ROLES, CREATIVE_ROLES, CLIENT_ROLES));

// ============================================================================
// AUTHORIZATION
// ============================================================================

/**
 * Check if user role is in the admin tier.
 */
function isAdmin(?array $user): bool {
    return $user && in_array($user['role'], ADMIN_ROLES, true);
}

/**
 * Check if user role is in the manager tier.
 */
function isManager(?array $user): bool {
    return $user && in_array($user['role'], MANAGER_ROLES, true);
}

/**
 * Check if user role is in the creative tier.
 */
function isCreative(?array $user): bool {
    return $user && in_array($user['role'], CREATIVE_ROLES, true);
}

/**
 * Check if user role is Client.
 */
function isClient(?array $user): bool {
    return $user && $user['role'] === 'Client';
}

/**
 * Check if user is admin or manager (can perform management actions).
 */
function isAdminOrManager(?array $user): bool {
    return isAdmin($user) || isManager($user);
}

/**
 * Authorize a user to perform an action on an entity.
 * Returns true if authorized, or calls jsonError() and exits if not.
 *
 * Actions:
 *   'create_job'    -- Admin, Manager
 *   'update_job'    -- Admin, Manager (any); Creative (only if assigned); Client (never)
 *   'update_task'   -- Admin, Manager (any); Creative (only if assigned_to matches); Client (never)
 *   'update_asset'  -- Admin (any); others restricted
 *   'add_user'      -- Admin only (COO)
 *   'update_wiki'   -- Admin, Manager with canEditWiki; Client/Creative denied
 *   'approve_internal' -- Admin, CD/ECD
 *   'approve_client'   -- Client (own brand only), Admin
 *   'reject_feedback'  -- Admin, CD/ECD (internal), Client (external, own brand)
 *   'read_job'      -- Admin/Manager/Creative (assigned); Client (own brand)
 *   'read_jobs'     -- Enforced via query filter, not here
 */
function authorize(array $user, string $action, ?array $entity = null): bool {
    $role = $user['role'];

    // Admins can do everything
    if (isAdmin($user)) {
        return true;
    }

    switch ($action) {
        case 'create_job':
            if (isAdminOrManager($user)) return true;
            jsonError('You do not have permission to create jobs', 403);

        case 'update_job':
            if (isManager($user)) return true;
            // Creative: must be assigned to the job
            if (isCreative($user) && $entity) {
                if (isUserAssignedToJob($user['id'], $entity['id'])) return true;
            }
            jsonError('You do not have permission to edit this job', 403);

        case 'update_task':
            if (isAdminOrManager($user)) return true;
            // Creative: must be the assigned_to on this task
            if (isCreative($user) && $entity) {
                if (isset($entity['assigned_to']) && $entity['assigned_to'] === $user['id']) return true;
            }
            jsonError('You can only edit tasks assigned to you', 403);

        case 'update_asset':
            if (isAdminOrManager($user)) return true;
            // Creative: must be assigned to the asset
            if (isCreative($user) && $entity) {
                if (isset($entity['assigned_to']) && $entity['assigned_to'] === $user['id']) return true;
            }
            jsonError('You do not have permission to edit this asset', 403);

        case 'add_user':
            // Only COO
            if ($role === 'COO') return true;
            jsonError('Only COO can add users', 403);

        case 'update_wiki':
            if (isAdminOrManager($user)) return true;
            jsonError('You do not have permission to edit wiki pages', 403);

        case 'approve_internal':
            // CD, ECD can approve internally
            if (in_array($role, ['CD', 'ECD'], true)) return true;
            jsonError('Only CD/ECD can approve internally', 403);

        case 'approve_client':
            // Client: must belong to the job's brand
            if (isClient($user) && $entity) {
                if (isClientBrandMatch($user, $entity)) return true;
            }
            jsonError('You can only approve jobs for your brand', 403);

        case 'reject_feedback':
            // Internal: CD/ECD
            // External: Client for their brand
            if (in_array($role, ['CD', 'ECD'], true)) return true;
            if (isClient($user) && $entity && isClientBrandMatch($user, $entity)) return true;
            if (isAdminOrManager($user)) return true;
            jsonError('You do not have permission to reject this job', 403);

        case 'read_job':
            if (isAdminOrManager($user)) return true;
            if (isCreative($user) && $entity) {
                if (isUserAssignedToJob($user['id'], $entity['id'])) return true;
            }
            if (isClient($user) && $entity) {
                if (isClientBrandMatch($user, $entity)) return true;
            }
            jsonError('You do not have permission to view this job', 403);

        default:
            jsonError('Unknown authorization action', 400);
    }
    return false;
}

/**
 * Check if a user is assigned to a job (via job_assignments table).
 */
function isUserAssignedToJob(string $userId, string $jobId): bool {
    $db = getDb();
    $stmt = $db->prepare('SELECT 1 FROM job_assignments WHERE job_id = :job_id AND user_id = :user_id LIMIT 1');
    $stmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $userId, SQLITE3_TEXT);
    $result = $stmt->execute();
    return $result->fetchArray() !== false;
}

/**
 * Check if a client user's brand matches the job's brand.
 * The entity must have a brand_id (from campaign join).
 */
function isClientBrandMatch(array $user, array $entity): bool {
    if (empty($user['brand_id'])) return false;
    // Entity might have brand_id directly or via campaign
    $brandId = $entity['brand_id'] ?? null;
    if (!$brandId && !empty($entity['id'])) {
        // Look up brand_id via job -> campaign -> brand
        $db = getDb();
        $stmt = $db->prepare('
            SELECT c.brand_id FROM jobs j
            JOIN campaigns c ON j.campaign_id = c.id
            WHERE j.id = :job_id
        ');
        $stmt->bindValue(':job_id', $entity['id'], SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $brandId = $row ? $row['brand_id'] : null;
    }
    return $brandId === $user['brand_id'];
}

/**
 * Get the brand_id for a job (via its campaign).
 */
function getJobBrandId(string $jobId): ?string {
    $db = getDb();
    $stmt = $db->prepare('
        SELECT c.brand_id FROM jobs j
        JOIN campaigns c ON j.campaign_id = c.id
        WHERE j.id = :job_id
    ');
    $stmt->bindValue(':job_id', $jobId, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['brand_id'] : null;
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================

/**
 * Validate input data against rules.
 * Rules format: ['field' => 'type|max:N|min:N|required|in:a,b,c']
 *
 * Returns validated/sanitized data or calls jsonError() on failure.
 */
function validateInput(array $data, array $rules): array {
    $validated = [];
    $errors = [];

    foreach ($rules as $field => $ruleStr) {
        $ruleParts = explode('|', $ruleStr);
        $value = $data[$field] ?? null;
        $isRequired = in_array('required', $ruleParts, true);

        // Check required
        if ($isRequired && ($value === null || $value === '')) {
            $errors[] = "Missing required field: {$field}";
            continue;
        }

        // Skip optional empty fields
        if ($value === null || $value === '') {
            $validated[$field] = null;
            continue;
        }

        foreach ($ruleParts as $rule) {
            if ($rule === 'required') continue;

            if ($rule === 'string') {
                if (!is_string($value)) {
                    $errors[] = "{$field} must be a string";
                    break;
                }
            }

            if ($rule === 'int') {
                if (!is_numeric($value) || (int)$value != $value) {
                    $errors[] = "{$field} must be an integer";
                    break;
                }
                $value = (int)$value;
            }

            if ($rule === 'float') {
                if (!is_numeric($value)) {
                    $errors[] = "{$field} must be a number";
                    break;
                }
                $value = (float)$value;
            }

            if ($rule === 'bool') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    $errors[] = "{$field} must be a boolean";
                    break;
                }
            }

            if ($rule === 'date') {
                // Validate YYYY-MM-DD format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $errors[] = "{$field} must be a valid date (YYYY-MM-DD)";
                    break;
                }
            }

            if ($rule === 'id') {
                // IDs are hex strings, 1-64 chars
                if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $value)) {
                    $errors[] = "{$field} contains invalid characters";
                    break;
                }
            }

            if (str_starts_with($rule, 'max:')) {
                $max = (int)substr($rule, 4);
                if (is_string($value) && mb_strlen($value) > $max) {
                    $errors[] = "{$field} exceeds maximum length of {$max}";
                    break;
                }
            }

            if (str_starts_with($rule, 'min:')) {
                $min = (int)substr($rule, 4);
                if (is_string($value) && mb_strlen($value) < $min) {
                    $errors[] = "{$field} must be at least {$min} characters";
                    break;
                }
            }

            if (str_starts_with($rule, 'in:')) {
                $allowed = explode(',', substr($rule, 3));
                if (!in_array($value, $allowed, true)) {
                    $errors[] = "{$field} must be one of: " . implode(', ', $allowed);
                    break;
                }
            }
        }

        $validated[$field] = $value;
    }

    if (!empty($errors)) {
        jsonError(implode('; ', $errors), 400);
    }

    return $validated;
}

// ============================================================================
// OUTPUT ENCODING (XSS PREVENTION)
// ============================================================================

/**
 * Sanitize a single value for safe JSON output.
 * Encodes HTML entities in strings to prevent stored XSS.
 */
function sanitizeValue($value): mixed {
    if (is_string($value)) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return $value;
}

/**
 * Recursively sanitize an array for safe JSON output.
 * Applies htmlspecialchars to all string values.
 */
function sanitizeOutput(array $data): array {
    $result = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $result[$key] = sanitizeOutput($value);
        } else {
            $result[$key] = sanitizeValue($value);
        }
    }
    return $result;
}
