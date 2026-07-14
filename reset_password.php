<?php
require_once 'includes/password_reset.php';

if (isLoggedIn()) redirect('dashboard.php');
$db = getDB();
ensurePasswordResetTable($db);
$selector = $_POST['selector'] ?? $_GET['selector'] ?? '';
$validator = $_POST['validator'] ?? $_GET['validator'] ?? '';
$reset = findValidPasswordReset($db, $selector, $validator);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif (!$reset) {
        $error = 'This reset link is invalid or has expired.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['password_confirmation'] ?? '';
        if (strlen($password) < 8) $error = 'Password must be at least 8 characters.';
        elseif ($password !== $confirmation) $error = 'Passwords do not match.';
        else {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
                $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")->execute([$reset['reset_id']]);
                $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND id <> ?")->execute([$reset['user_id'], $reset['reset_id']]);
                $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$reset['user_id']]);
                $db->commit();
                redirect('index.php', 'Password reset successfully. You can now sign in.', 'success');
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Password could not be reset. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reset Password | TPMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        },
                        secondary: {
                            900: '#0f172a'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-secondary-900 min-h-screen flex items-center justify-center overflow-hidden relative p-6">
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-primary-600/20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-40 w-80 h-80 bg-purple-600/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 left-1/3 w-72 h-72 bg-pink-600/20 rounded-full blur-3xl"></div>
    </div>
    <main class="relative z-10 w-full max-w-md bg-white/10 backdrop-blur-lg rounded-2xl shadow-2xl border border-white/20 p-8">
        <div class="text-center mb-8">
            <div class="w-36 h-16 mx-auto rounded-xl bg-white overflow-hidden flex items-center justify-center shadow-lg mb-4"><img src="assets/images/logo.png" alt="TPMS" class="w-full h-full object-cover scale-[1.6]"></div>
            <h1 class="text-2xl font-bold text-white">Create new password</h1>
        </div>
        <?php if (!$reset): ?><div class="p-4 mb-5 rounded-lg bg-red-500/20 border border-red-400/30 text-red-100">This reset link is invalid, expired, or has already been used.</div><a href="forgot_password.php" class="block w-full text-center py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-lg">Request another link</a><?php else: ?><p class="text-gray-400 text-sm text-center -mt-6 mb-6">Use at least 8 characters for your new password.</p><?php if ($error): ?><div class="p-3 mb-4 rounded-lg bg-red-500/20 border border-red-400/30 text-red-100 text-sm"><?php echo sanitize($error); ?></div><?php endif; ?><form method="post" class="space-y-5"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>"><input type="hidden" name="selector" value="<?php echo sanitize($selector); ?>"><input type="hidden" name="validator" value="<?php echo sanitize($validator); ?>">
                <div><label class="block text-sm font-medium text-gray-300 mb-2">New Password</label>
                    <div class="relative"><i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="password" name="password" minlength="8" required autocomplete="new-password" class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
                    <div class="relative"><i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"></div>
                </div><button class="w-full py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-500 hover:to-primary-600 text-white font-semibold rounded-lg shadow-lg">Reset password</button>
            </form><?php endif; ?><a href="index.php" class="block text-center mt-6 text-sm text-gray-400 hover:text-white transition-colors"><i class="fas fa-arrow-left mr-1"></i> Back to sign in</a>
    </main>
</body>

</html>