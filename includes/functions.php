<?php
/**
 * TPMS - Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

// Session settings - using PHP defaults for maximum localhost/XAMPP compatibility
// For production, enable secure cookies via php.ini or .htaccess
ini_set('session.gc_maxlifetime', 7200);

session_start();

// Available permissions in the system
$GLOBALS['ALL_PERMISSIONS'] = [
    'dashboard' => 'View Dashboard',
    'leads' => 'Manage Leads',
    'contacts' => 'Manage Contacts',
    'deals' => 'Manage Deals',
    'tasks' => 'Manage Tasks',
    'projects' => 'Manage Projects',
    'reports' => 'View Reports',
    'settings' => 'Manage Settings',
    'users' => 'Manage Users',
    'roles' => 'Manage Roles & Permissions',
    'media' => 'Upload & Manage Media',
    'storage' => 'Manage Server Storage',
    'invoices' => 'Manage Invoices',
    'client_access' => 'Client Access (limited to own records)',
    'view_all' => 'View All Records (override ownership)',
    'assign_records' => 'Assign records to other users'
];

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login, redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Load role permissions into session
 */
function loadUserPermissions($userId = null) {
    if ($userId === null) $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) return [];
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT r.permissions FROM users u JOIN roles r ON u.role = r.name WHERE u.id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetch();
        if ($role && $role['permissions']) {
            $perms = json_decode($role['permissions'], true);
            return is_array($perms) ? $perms : [];
        }
    } catch (Exception $e) {
        // Silent fail
    }
    return [];
}

/**
 * Check if current user has a permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) return false;
    if (!isset($_SESSION['user_permissions'])) {
        $_SESSION['user_permissions'] = loadUserPermissions();
    }
    return in_array($permission, $_SESSION['user_permissions'] ?? []);
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] === 'admin' || in_array('admin', $_SESSION['user_permissions'] ?? []));
}

/**
 * Check if user can view all records or only own records
 */
function canViewAll() {
    return hasPermission('view_all') || isAdmin();
}

/**
 * Require specific permission
 */
function requirePermission($permissions) {
    requireLogin();
    if (isAdmin()) return true;
    if (!is_array($permissions)) $permissions = [$permissions];
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) return true;
    }
    // User is logged in but lacks permission - log them out to prevent redirect loops
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?error=access_denied');
    exit;
}

/**
 * Legacy role check (now uses permissions)
 * Kept for backward compatibility
 */
function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($_SESSION['user_role'], $roles) && !isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Get current user
 */
function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'avatar' => $_SESSION['user_avatar'] ?? null
    ];
}

/**
 * Sanitize input against XSS
 */
function sanitize($input) {
    if ($input === null) return '';
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate a time-ordered RFC 9562 UUID version 7.
 */
function uuidV7() {
    $timestamp = (int) floor(microtime(true) * 1000);
    $hex = str_pad(dechex($timestamp), 12, '0', STR_PAD_LEFT) . bin2hex(random_bytes(10));
    $hex[12] = '7';
    $variants = ['8', '9', 'a', 'b'];
    $hex[16] = $variants[hexdec($hex[16]) & 3];
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function ensureNotificationsTable($db = null) {
    $db = $db ?: getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(150) NOT NULL, message TEXT NOT NULL, type VARCHAR(30) DEFAULT 'info', url VARCHAR(500) DEFAULT NULL, read_at DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_notification_user_read (user_id, read_at), INDEX idx_notification_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function createNotification($userId, $title, $message, $type = 'info', $url = null) {
    if (!$userId) return false;
    try {
        $db = getDB();
        ensureNotificationsTable($db);
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, url) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $type, $url]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Rate limit check
 */
function checkRateLimit($key, $maxAttempts = 10, $window = 60) {
    $sessionKey = 'rate_' . $key;
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 0, 'time' => time()];
    }
    if (time() - $_SESSION[$sessionKey]['time'] > $window) {
        $_SESSION[$sessionKey] = ['count' => 0, 'time' => time()];
    }
    $_SESSION[$sessionKey]['count']++;
    return $_SESSION[$sessionKey]['count'] <= $maxAttempts;
}

/**
 * Redirect with message
 */
function redirect($url, $message = '', $type = 'info') {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header("Location: $url");
    exit;
}

/**
 * Show flash message
 */
function flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format date
 */
function formatDate($date, $format = null) {
    if (!$date) return '-';
    if (!$format) $format = setting('date_format', 'Y-m-d');
    return date($format, strtotime($date));
}

/**
 * Get setting value
 */
function setting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Log activity with IP and user agent
 */
function logActivity($action, $description = '', $related_to = null, $related_id = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activities (user_id, action, description, related_to, related_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $description,
            $related_to,
            $related_id,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * Log page view
 */
function logPageView($page) {
    if (isLoggedIn()) {
        logActivity('page_view', "Viewed page: $page");
    }
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= 1024 ** $pow;
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format currency with conversion
 */
function formatCurrency($amount, $convert = true) {
    $currency = setting('currency', '₹');
    $rate = floatval(setting('currency_conversion_rate', '1'));
    if ($convert && $rate > 0) {
        $amount = (float)$amount * $rate;
    }
    return $currency . number_format((float)$amount, 2);
}

/**
 * Convert amount to base currency
 */
function convertCurrency($amount) {
    $rate = floatval(setting('currency_conversion_rate', '1'));
    if ($rate > 0) {
        return (float)$amount * $rate;
    }
    return (float)$amount;
}

/**
 * Get status badge color
 */
function statusColor($status) {
    $colors = [
        'new' => 'bg-blue-100 text-blue-800',
        'contacted' => 'bg-indigo-100 text-indigo-800',
        'qualified' => 'bg-purple-100 text-purple-800',
        'proposal' => 'bg-yellow-100 text-yellow-800',
        'negotiation' => 'bg-orange-100 text-orange-800',
        'won' => 'bg-green-100 text-green-800',
        'lost' => 'bg-red-100 text-red-800',
        'active' => 'bg-green-100 text-green-800',
        'inactive' => 'bg-gray-100 text-gray-800',
        'prospect' => 'bg-blue-100 text-blue-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'in_progress' => 'bg-blue-100 text-blue-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'prospecting' => 'bg-gray-100 text-gray-800',
        'qualification' => 'bg-blue-100 text-blue-800',
        'closed_won' => 'bg-green-100 text-green-800',
        'closed_lost' => 'bg-red-100 text-red-800',
        'low' => 'bg-green-100 text-green-800',
        'medium' => 'bg-yellow-100 text-yellow-800',
        'high' => 'bg-red-100 text-red-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get initials from name
 */
function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $part) {
        if (!empty($part)) $initials .= strtoupper($part[0]);
        if (strlen($initials) >= 2) break;
    }
    return $initials;
}

/**
 * Generate CSRF token
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Pagination helper
 */
function paginate($page, $perPage, $total) {
    $page = max(1, (int)$page);
    $perPage = max(1, (int)$perPage);
    $totalPages = max(1, ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'offset' => $offset
    ];
}
