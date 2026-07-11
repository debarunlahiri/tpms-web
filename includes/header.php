<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$flash = flash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = currentUser();

// Log page views for authenticated users (except login page)
if (isLoggedIn() && $currentPage !== 'index') {
    logPageView($currentPage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' | ' : ''; ?>TPMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 900: '#1e3a8a' },
                        secondary: { 50: '#f8fafc', 100: '#f1f5f9', 800: '#1e293b', 900: '#0f172a' }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'slide-in-right': 'slideInRight 0.4s ease-out',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'bounce-slow': 'bounce 2s infinite',
                        'spin-slow': 'spin 3s linear infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideInRight: {
                            '0%': { opacity: '0', transform: 'translateX(20px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' }
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-secondary-50 text-secondary-800 font-sans antialiased">
    <?php if ($flash): ?>
    <div id="flash-message" class="fixed top-4 right-4 z-50 animate-slide-in-right">
        <div class="rounded-lg px-6 py-4 shadow-lg flex items-center gap-3 <?php echo $flash['type'] === 'error' ? 'bg-red-500 text-white' : ($flash['type'] === 'success' ? 'bg-green-500 text-white' : 'bg-blue-500 text-white'); ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle'); ?>"></i>
            <span><?php echo sanitize($flash['message']); ?></span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 hover:opacity-75"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            const flash = document.getElementById('flash-message');
            if (flash) flash.style.display = 'none';
        }, 5000);
    </script>
    <?php endif; ?>
