<?php
$unreadNotifications = 0;
$recentNotifications = [];
try {
    $db = getDB();
    ensureNotificationsTable($db);
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadNotifications = (int) $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $recentNotifications = $stmt->fetchAll();
} catch (Exception $e) {
    $unreadNotifications = 0;
}
?>
<header class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-white border-b border-gray-200 z-30 flex items-center justify-between px-4">
    <button id="sidebar-toggle" class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
        <i class="fas fa-bars text-gray-600 text-xl"></i>
    </button>
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
            <i class="fas fa-bolt text-white text-sm"></i>
        </div>
        <span class="font-bold text-secondary-900">TPMS</span>
    </div>
    <a href="notifications.php" class="relative w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center text-gray-600" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($unreadNotifications > 0): ?><span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?php echo $unreadNotifications > 99 ? '99+' : $unreadNotifications; ?></span><?php endif; ?>
    </a>
</header>

<div class="hidden lg:flex fixed top-0 right-0 left-64 h-16 bg-white/80 backdrop-blur-md border-b border-gray-200 z-20 items-center justify-between px-6">
    <div class="flex items-center gap-4">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" data-search=".searchable-row" placeholder="Search anything..." 
                   class="pl-10 pr-4 py-2 w-64 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
        </div>
    </div>
    
    <div class="flex items-center gap-4">
        <div class="relative">
            <button type="button" id="notification-toggle" class="relative w-10 h-10 rounded-xl bg-gray-50 hover:bg-gray-100 flex items-center justify-center transition-colors" aria-label="Notifications" aria-expanded="false">
                <i class="fas fa-bell text-gray-600"></i>
                <?php if ($unreadNotifications > 0): ?><span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?php echo $unreadNotifications > 99 ? '99+' : $unreadNotifications; ?></span><?php endif; ?>
            </button>
            <div id="notification-menu" class="hidden absolute right-0 mt-3 w-96 max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden z-50">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div><h3 class="font-bold text-secondary-900">Notifications</h3><p class="text-xs text-gray-500"><?php echo $unreadNotifications; ?> unread</p></div>
                    <?php if ($unreadNotifications): ?><a href="notifications.php?action=read_all" class="text-xs font-medium text-primary-600 hover:text-primary-700">Mark all read</a><?php endif; ?>
                </div>
                <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                    <?php if (!$recentNotifications): ?>
                    <div class="p-8 text-center"><i class="far fa-bell text-3xl text-gray-300 mb-3"></i><p class="text-sm text-gray-500">You're all caught up.</p></div>
                    <?php else: foreach ($recentNotifications as $notification): ?>
                    <a href="notifications.php?action=open&id=<?php echo (int) $notification['id']; ?>" class="flex gap-3 p-4 hover:bg-gray-50 transition-colors <?php echo !$notification['read_at'] ? 'bg-blue-50/50' : ''; ?>">
                        <span class="mt-1 w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center <?php echo $notification['type'] === 'success' ? 'bg-green-100 text-green-600' : ($notification['type'] === 'warning' ? 'bg-yellow-100 text-yellow-600' : 'bg-blue-100 text-blue-600'); ?>"><i class="fas fa-<?php echo $notification['type'] === 'success' ? 'check' : ($notification['type'] === 'warning' ? 'exclamation' : 'info'); ?> text-sm"></i></span>
                        <span class="min-w-0"><span class="block text-sm font-semibold text-secondary-900"><?php echo sanitize($notification['title']); ?></span><span class="block text-xs text-gray-500 mt-0.5 line-clamp-2"><?php echo sanitize($notification['message']); ?></span><span class="block text-[11px] text-gray-400 mt-1"><?php echo formatDate($notification['created_at'], 'M d, H:i'); ?></span></span>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
                <a href="notifications.php" class="block p-3 text-center text-sm font-medium text-primary-600 bg-gray-50 hover:bg-gray-100">View all notifications</a>
            </div>
        </div>
        
        <a href="profile.php" class="flex items-center gap-3 pl-4 border-l border-gray-200 rounded-r-xl hover:bg-gray-50 pr-2 py-1 transition-colors" data-tooltip="View profile">
            <div class="text-right hidden md:block">
                <p class="text-sm font-medium text-secondary-900"><?php echo sanitize($_SESSION['user_name']); ?></p>
                <p class="text-xs text-gray-500 capitalize"><?php echo str_replace('_', ' ', $_SESSION['user_role']); ?></p>
            </div>
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold text-sm overflow-hidden ring-2 ring-white shadow-sm">
                <?php if (!empty($_SESSION['user_avatar'])): ?><img src="<?php echo sanitize($_SESSION['user_avatar']); ?>" alt="Profile" class="w-full h-full object-cover"><?php else: ?><?php echo getInitials($_SESSION['user_name']); ?><?php endif; ?>
            </div>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('notification-toggle');
    const menu = document.getElementById('notification-menu');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        menu.classList.toggle('hidden');
        toggle.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
    });
    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target)) menu.classList.add('hidden');
    });
});
</script>
