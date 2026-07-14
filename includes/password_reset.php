<?php

require_once __DIR__ . '/functions.php';

const PASSWORD_RESET_MINUTES = 30;

function ensurePasswordResetTable($db)
{
    $db->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, selector CHAR(24) NOT NULL UNIQUE, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_password_reset_user (user_id), INDEX idx_password_reset_expiry (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function passwordResetBaseUrl()
{
    if (defined('APP_URL') && APP_URL !== '') return rtrim(APP_URL, '/');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $secure ? 'https' : 'http';
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $port = (int) ($_SERVER['SERVER_PORT'] ?? ($secure ? 443 : 80));
    if (($secure && $port !== 443) || (!$secure && $port !== 80)) $host .= ':' . $port;
    $directory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($directory === '' ? '' : $directory);
}

function isLocalPasswordResetRequest()
{
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function sendPasswordResetEmail($email, $name, $resetUrl)
{
    $company = setting('company_name', 'TPMS');
    $from = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : setting('company_email', 'no-reply@localhost');
    $subject = $company . ' password reset';
    $message = "Hello $name,\n\nWe received a request to reset your password.\n\nReset your password: $resetUrl\n\nThis link expires in " . PASSWORD_RESET_MINUTES . " minutes and can only be used once. If you did not request this, ignore this email.\n";
    $headers = [
        'From: ' . $company . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    return @mail($email, $subject, $message, implode("\r\n", $headers));
}

function findValidPasswordReset($db, $selector, $validator)
{
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) return null;
    $stmt = $db->prepare("SELECT pr.id AS reset_id, pr.token_hash, pr.user_id, u.email FROM password_reset_tokens pr JOIN users u ON u.id = pr.user_id WHERE pr.selector = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.status = 'active' LIMIT 1");
    $stmt->execute([$selector]);
    $reset = $stmt->fetch();
    return $reset && hash_equals($reset['token_hash'], hash('sha256', $validator)) ? $reset : null;
}
