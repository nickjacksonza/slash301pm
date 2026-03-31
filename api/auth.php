<?php
// ============================================================================
// auth.php -- Session management, CSRF protection, login rate limiting
// Phase 3.3: Real authentication replacing user-switcher
// ============================================================================

// Disable error display (log only)
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// HTTPS enforcement
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    // Allow non-HTTPS in CLI mode for testing
    if (php_sapi_name() !== 'cli') {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
}

// Custom session save path for shared hosting security
$sessionDir = dirname(__DIR__) . '/data/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}

// Protect session directory with .htaccess
$sessionHtaccess = $sessionDir . '/.htaccess';
if (!file_exists($sessionHtaccess)) {
    file_put_contents($sessionHtaccess, "Deny from all\n");
}

ini_set('session.save_path', $sessionDir);

// Start session with hardened configuration
session_start([
    'name'                   => 'SLASH301PM_SID',
    'cookie_lifetime'        => 0,              // Session cookie (browser close)
    'cookie_path'            => '/slash301pm/',
    'cookie_domain'          => 'projects.slash301.com',
    'cookie_secure'          => true,           // HTTPS only
    'cookie_httponly'        => true,           // No JavaScript access
    'cookie_samesite'        => 'Lax',          // CSRF mitigation layer
    'use_strict_mode'        => true,           // Reject uninitialized IDs
    'use_only_cookies'       => true,           // No session ID in URLs
    'sid_length'             => 48,
    'gc_maxlifetime'         => 3600,           // 1 hour timeout
]);

// Security headers on every response
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/db.php';

// ============================================================================
// SESSION HELPERS
// ============================================================================

/**
 * Check if a valid session exists.
 * Returns the user array if authenticated, null otherwise.
 */
function getSessionUser(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    // Check session timeout (1 hour of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        destroySession();
        return null;
    }

    // Update last activity
    $_SESSION['last_activity'] = time();

    $db = getDb();
    $stmt = $db->prepare('SELECT id, username, name, email, role, color, brand_id FROM users WHERE id = :id AND is_active = 1');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        destroySession();
        return null;
    }

    // For clients, include brand name
    if ($user['role'] === 'Client' && $user['brand_id']) {
        $bStmt = $db->prepare('SELECT name FROM brands WHERE id = :id');
        $bStmt->bindValue(':id', $user['brand_id'], SQLITE3_TEXT);
        $bResult = $bStmt->execute();
        $brand = $bResult->fetchArray(SQLITE3_ASSOC);
        $user['brand'] = $brand ? $brand['name'] : null;
    }

    return $user;
}

/**
 * Destroy the current session completely.
 */
function destroySession(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Generate or retrieve the CSRF token for the current session.
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token from the X-CSRF-Token header.
 * Returns true if valid, false otherwise.
 */
function validateCsrfToken(): bool {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($token) || empty($sessionToken)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

// ============================================================================
// LOGIN RATE LIMITING
// ============================================================================

/**
 * Check if an IP is rate-limited (10 failed attempts in 15 minutes).
 * Returns seconds until retry is allowed, or 0 if not limited.
 */
function checkRateLimit(string $ip): int {
    $db = getDb();
    $windowStart = time() - 900; // 15 minutes

    // Clean up old attempts
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < " . (time() - 900));

    // Count recent failures
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM login_attempts WHERE ip = :ip AND attempted_at > :since');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':since', $windowStart, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['cnt'];

    if ($count >= 10) {
        // Find when the oldest attempt in the window expires
        $stmt = $db->prepare('SELECT MIN(attempted_at) as oldest FROM login_attempts WHERE ip = :ip AND attempted_at > :since');
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':since', $windowStart, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $oldest = $result->fetchArray(SQLITE3_ASSOC)['oldest'];
        return ($oldest + 900) - time();
    }

    return 0;
}

/**
 * Record a failed login attempt.
 */
function recordFailedAttempt(string $ip): void {
    $db = getDb();
    $stmt = $db->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (:ip, :time)');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

/**
 * Clear login attempts for an IP (on successful login).
 */
function clearAttempts(string $ip): void {
    $db = getDb();
    $stmt = $db->prepare('DELETE FROM login_attempts WHERE ip = :ip');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->execute();
}

/**
 * Get the client IP address.
 */
function getClientIp(): string {
    // On shared hosting behind proxy, check forwarded headers
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ============================================================================
// LOGIN / LOGOUT HANDLERS
// ============================================================================

/**
 * Handle login attempt.
 * Returns ['success' => true, 'user' => [...], 'csrf_token' => '...'] on success,
 * or ['success' => false, 'error' => '...'] on failure.
 */
function handleLogin(string $username, string $password): array {
    $ip = getClientIp();

    // Check rate limiting
    $retryAfter = checkRateLimit($ip);
    if ($retryAfter > 0) {
        http_response_code(429);
        header("Retry-After: {$retryAfter}");
        return ['success' => false, 'error' => 'Too many login attempts. Try again later.', 'retry_after' => $retryAfter];
    }

    // Validate password length
    if (strlen($password) < 12 || strlen($password) > 128) {
        recordFailedAttempt($ip);
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    $db = getDb();

    // Look up user by username
    $stmt = $db->prepare('SELECT id, username, password_hash, name, email, role, color, brand_id FROM users WHERE username = :username AND is_active = 1');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // Timing-safe: always call password_verify even if user not found
    $hash = $user ? $user['password_hash'] : '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012';
    $valid = password_verify($password, $hash);

    if (!$user || !$valid) {
        recordFailedAttempt($ip);
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    // Success: regenerate session ID to prevent fixation
    session_regenerate_id(true);

    // Store user info in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_brand_id'] = $user['brand_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_at'] = time();

    // Generate fresh CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Clear failed attempts
    clearAttempts($ip);

    // Strip password_hash before returning
    unset($user['password_hash']);

    // Add brand name for clients
    if ($user['role'] === 'Client' && $user['brand_id']) {
        $bStmt = $db->prepare('SELECT name FROM brands WHERE id = :id');
        $bStmt->bindValue(':id', $user['brand_id'], SQLITE3_TEXT);
        $bResult = $bStmt->execute();
        $brand = $bResult->fetchArray(SQLITE3_ASSOC);
        $user['brand'] = $brand ? $brand['name'] : null;
    }

    return [
        'success' => true,
        'user' => $user,
        'csrf_token' => $_SESSION['csrf_token'],
    ];
}

/**
 * Handle logout.
 */
function handleLogout(): array {
    destroySession();
    return ['success' => true];
}

// ============================================================================
// DEMO MODE
// ============================================================================

/**
 * Check if demo mode is enabled.
 * In demo mode, the user-switcher is shown instead of login.
 * Only COO can toggle demo mode.
 */
function isDemoMode(): bool {
    $db = getDb();
    // Check if a settings row exists (simple key-value in a lightweight way)
    // We'll use a file-based flag for simplicity
    $flagFile = dirname(__DIR__) . '/data/.demo_mode';
    return file_exists($flagFile);
}

/**
 * Toggle demo mode on/off.
 */
function setDemoMode(bool $enabled): void {
    $flagFile = dirname(__DIR__) . '/data/.demo_mode';
    if ($enabled) {
        file_put_contents($flagFile, '1');
    } else {
        if (file_exists($flagFile)) {
            unlink($flagFile);
        }
    }
}
