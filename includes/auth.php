<?php

/**
 * Enterprise CRM - Authentication Handling
 */

require_once __DIR__ . '/functions.php';

const REMEMBER_COOKIE = 'tpms_remember';
const REMEMBER_DAYS = 30;

function rememberCookieOptions($expires)
{
    return ['expires' => $expires, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax'];
}

function ensureRememberTokensTable($db)
{
    $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, selector CHAR(24) NOT NULL UNIQUE, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_remember_user (user_id), INDEX idx_remember_expiry (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function establishUserSession($user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['user_permissions'] = loadUserPermissions($user['id']);
}

function forgetRememberToken($db)
{
    $selector = explode(':', $_COOKIE[REMEMBER_COOKIE] ?? '', 2)[0];
    if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
        $db->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$selector]);
    }
    setcookie(REMEMBER_COOKIE, '', rememberCookieOptions(time() - 3600));
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function issueRememberToken($db, $userId)
{
    ensureRememberTokensTable($db);
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_DAYS * 86400;
    $db->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]);
    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, rememberCookieOptions($expires));
}

if (!isLoggedIn() && !empty($_COOKIE[REMEMBER_COOKIE])) {
    try {
        $db = getDB();
        ensureRememberTokensTable($db);
        [$selector, $validator] = array_pad(explode(':', $_COOKIE[REMEMBER_COOKIE], 2), 2, '');
        if (preg_match('/^[a-f0-9]{24}$/', $selector) && preg_match('/^[a-f0-9]{64}$/', $validator)) {
            $stmt = $db->prepare("SELECT rt.token_hash, u.* FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.selector = ? AND rt.expires_at > NOW() AND u.status = 'active' LIMIT 1");
            $stmt->execute([$selector]);
            $rememberedUser = $stmt->fetch();
            if ($rememberedUser && hash_equals($rememberedUser['token_hash'], hash('sha256', $validator))) {
                establishUserSession($rememberedUser);
                $db->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$selector]);
                issueRememberToken($db, $rememberedUser['id']);
            } else {
                forgetRememberToken($db);
            }
        } else {
            forgetRememberToken($db);
        }
    } catch (Exception $e) {
        setcookie(REMEMBER_COOKIE, '', rememberCookieOptions(time() - 3600));
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rate limiting for login attempts
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = 'login_attempts_' . md5($ip);
    if (!isset($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = ['count' => 0, 'time' => time()];
    }
    if (time() - $_SESSION[$rateKey]['time'] > 900) {
        $_SESSION[$rateKey] = ['count' => 0, 'time' => time()];
    }
    $_SESSION[$rateKey]['count']++;
    if ($_SESSION[$rateKey]['count'] > 5) {
        logActivity('rate_limit', "Login rate limit exceeded for IP: $ip", 'login', null);
        redirect('index.php', 'Too many login attempts. Please try again after 15 minutes.', 'error');
    }

    if (empty($email) || empty($password)) {
        redirect('index.php', 'Please enter email and password.', 'error');
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            establishUserSession($user);
            if (!empty($_POST['remember_me'])) {
                issueRememberToken($db, $user['id']);
            }

            logActivity('login', "User {$user['name']} logged in");
            redirect('dashboard.php', 'Welcome back, ' . $user['name'] . '!', 'success');
        } else {
            redirect('index.php', 'Invalid email or password.', 'error');
        }
    } catch (Exception $e) {
        redirect('index.php', 'Login error: ' . $e->getMessage(), 'error');
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logActivity('logout', "User logged out");
    try {
        $db = getDB();
        ensureRememberTokensTable($db);
        forgetRememberToken($db);
    } catch (Exception $e) {
        setcookie(REMEMBER_COOKIE, '', rememberCookieOptions(time() - 3600));
    }
    session_destroy();
    header('Location: index.php');
    exit;
}
