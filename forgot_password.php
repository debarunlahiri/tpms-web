<?php
require_once 'includes/password_reset.php';

if (isLoggedIn()) redirect('dashboard.php');
$message = '';
$error = '';
$localResetUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif (!checkRateLimit('forgot_password_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 900)) {
        $error = 'Too many requests. Please wait 15 minutes and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $message = 'If an active account matches that email, a password reset link has been sent.';
        if (isValidEmail($email)) {
            $db = getDB();
            ensurePasswordResetTable($db);
            $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            if ($user = $stmt->fetch()) {
                $selector = bin2hex(random_bytes(12));
                $validator = bin2hex(random_bytes(32));
                $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at <= NOW()")->execute([$user['id']]);
                $db->prepare("INSERT INTO password_reset_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL " . PASSWORD_RESET_MINUTES . " MINUTE))")
                    ->execute([$user['id'], $selector, hash('sha256', $validator)]);
                $resetUrl = passwordResetBaseUrl() . '/reset_password.php?selector=' . urlencode($selector) . '&validator=' . urlencode($validator);
                $sent = sendPasswordResetEmail($user['email'], $user['name'], $resetUrl);
                if (!$sent && isLocalPasswordResetRequest()) $localResetUrl = $resetUrl;
            }
        }
    }
}
$pageHeading = 'Forgot password?';
$pageText = 'Enter your account email and we’ll send you a secure reset link.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot Password | TPMS</title>
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
                    },
                    animation: {
                        'slide-up': 'slideUp .8s ease-out',
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(.4,0,.6,1) infinite'
                    },
                    keyframes: {
                        slideUp: {
                            '0%': {
                                opacity: '0',
                                transform: 'translateY(30px)'
                            },
                            '100%': {
                                opacity: '1',
                                transform: 'translateY(0)'
                            }
                        },
                        float: {
                            '0%, 100%': {
                                transform: 'translateY(0)'
                            },
                            '50%': {
                                transform: 'translateY(-20px)'
                            }
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
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-primary-600/20 rounded-full blur-3xl animate-float"></div>
        <div class="absolute top-1/2 -right-40 w-80 h-80 bg-purple-600/20 rounded-full blur-3xl animate-float" style="animation-delay:2s"></div>
        <div class="absolute -bottom-40 left-1/3 w-72 h-72 bg-pink-600/20 rounded-full blur-3xl animate-float" style="animation-delay:4s"></div>
    </div>
    <main class="relative z-10 w-full max-w-md bg-white/10 backdrop-blur-lg rounded-2xl shadow-2xl border border-white/20 p-8 animate-slide-up">
        <div class="text-center mb-8">
            <div class="w-36 h-16 mx-auto rounded-xl bg-white overflow-hidden flex items-center justify-center shadow-lg mb-4 animate-pulse-slow"><img src="assets/images/logo.png" alt="TPMS" class="w-full h-full object-cover scale-[1.6]"></div>
            <h1 class="text-2xl font-bold text-white mb-1"><?php echo $pageHeading; ?></h1>
            <p class="text-gray-400 text-sm"><?php echo $pageText; ?></p>
        </div>
        <?php if ($error): ?><div class="p-3 mb-4 rounded-lg bg-red-500/20 border border-red-400/30 text-red-100 text-sm"><?php echo sanitize($error); ?></div><?php endif; ?><?php if ($message): ?><div class="p-3 mb-4 rounded-lg bg-green-500/20 border border-green-400/30 text-green-100 text-sm"><?php echo sanitize($message); ?></div><?php endif; ?><?php if ($localResetUrl): ?><div class="p-3 mb-4 rounded-lg bg-amber-500/20 border border-amber-400/30 text-amber-100 text-sm"><strong>Local mail is unavailable.</strong> Use this development-only link:<br><a class="underline break-all" href="<?php echo sanitize($localResetUrl); ?>">Reset password</a></div><?php endif; ?>
        <form method="post" class="space-y-5"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <div><label class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                <div class="relative"><i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="email" name="email" required autocomplete="email" class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all" placeholder="you@example.com"></div>
            </div><button class="w-full py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-500 hover:to-primary-600 text-white font-semibold rounded-lg shadow-lg transform hover:-translate-y-0.5 transition-all">Send reset link <i class="fas fa-paper-plane ml-2"></i></button>
        </form><a href="index.php" class="block text-center mt-6 text-sm text-gray-400 hover:text-white transition-colors"><i class="fas fa-arrow-left mr-1"></i> Back to sign in</a>
    </main>
</body>

</html>