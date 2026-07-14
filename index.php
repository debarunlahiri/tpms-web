<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Clear stale session cookies when access is denied to break redirect loops
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    try {
        $db = getDB();
        ensureRememberTokensTable($db);
        forgetRememberToken($db);
    } catch (Exception $e) {
        setcookie(REMEMBER_COOKIE, '', rememberCookieOptions(time() - 3600));
    }
    $_SESSION = [];
    session_destroy();
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'] ?? '/', $params['domain'] ?? '');
}

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$flash = flash();

// Handle URL error messages (e.g., access denied after session destroyed)
if (isset($_GET['error']) && $_GET['error'] === 'access_denied' && !$flash) {
    $flash = ['message' => 'Access denied. You do not have permission to view this page.', 'type' => 'error'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TPMS</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            800: '#1e293b',
                            900: '#0f172a'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.8s ease-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: '0'
                            },
                            '100%': {
                                opacity: '1'
                            }
                        },
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

<body class="bg-secondary-900 min-h-screen flex items-center justify-center overflow-hidden relative">
    <!-- Animated Background -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-primary-600/20 rounded-full blur-3xl animate-float"></div>
        <div class="absolute top-1/2 -right-40 w-80 h-80 bg-purple-600/20 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute -bottom-40 left-1/3 w-72 h-72 bg-pink-600/20 rounded-full blur-3xl animate-float" style="animation-delay: 4s;"></div>
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=" 60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" %3E%3Cg fill="none" fill-rule="evenodd" %3E%3Cg fill="%23ffffff" fill-opacity="0.03" %3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z" /%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-20"></div>
    </div>

    <?php if ($flash): ?>
        <div class="fixed top-4 right-4 z-50 animate-slide-up">
            <div class="rounded-lg px-6 py-4 shadow-lg flex items-center gap-3 <?php echo $flash['type'] === 'error' ? 'bg-red-500 text-white' : ($flash['type'] === 'success' ? 'bg-green-500 text-white' : 'bg-blue-500 text-white'); ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle'); ?>"></i>
                <span><?php echo sanitize($flash['message']); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="relative z-10 w-full max-w-md px-6">
        <div class="bg-white/10 backdrop-blur-lg rounded-2xl shadow-2xl border border-white/20 p-8 animate-slide-up">
            <div class="text-center mb-8">
                <div class="w-36 h-16 mx-auto rounded-xl bg-white overflow-hidden flex items-center justify-center px-3 shadow-lg mb-4">
                    <img src="assets/images/logo.png" alt="TPMS" class="w-full h-full object-cover scale-[1.25]">
                </div>
                <h1 class="text-2xl font-bold text-white mb-1">Welcome Back</h1>
                <p class="text-gray-400 text-sm">Sign in to access TPMS</p>
            </div>

            <form method="POST" action="" class="space-y-5">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="email" name="email" required
                            class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                            placeholder="you@example.com" autocomplete="email">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="password" name="password" required
                            class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                            placeholder="••••••••" autocomplete="current-password">
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center text-gray-400 cursor-pointer hover:text-white transition-colors">
                        <input type="checkbox" name="remember_me" value="1" class="mr-2 rounded border-white/20 bg-white/5 text-primary-600 focus:ring-primary-500">
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="text-white hover:text-gray-200 transition-colors">Forgot password?</a>
                </div>

                <button type="submit"
                    class="w-full py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-500 hover:to-primary-600 text-white font-semibold rounded-lg shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">
                    Sign In <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
        </div>

        <div class="mt-6 text-center">
            <p class="text-gray-500 text-sm">&copy; <?php echo date('Y'); ?> TPMS. All rights reserved.</p>
        </div>
    </div>
</body>

</html>