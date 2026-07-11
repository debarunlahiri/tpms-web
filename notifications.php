<?php
$pageTitle = 'Notifications';
require_once 'includes/header.php';
requireLogin();

$db = getDB();
ensureNotificationsTable($db);
$userId = (int) $_SESSION['user_id'];

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'open') {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare("SELECT url FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $notification = $stmt->fetch();
    if ($notification) {
        $db->prepare("UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        $url = $notification['url'] ?: 'notifications.php';
        if (preg_match('/^(?!https?:|\/\/)[a-zA-Z0-9_\-]+\.php(?:[?#].*)?$/', $url)) {
            header('Location: ' . $url);
            exit;
        }
    }
    header('Location: notifications.php');
    exit;
}

if (($_GET['action'] ?? '') === 'read_all') {
    $db->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL")->execute([$userId]);
    header('Location: notifications.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['mark_read'])) {
        $db->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?")->execute([(int) $_POST['mark_read'], $userId]);
    } elseif (isset($_POST['delete'])) {
        $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([(int) $_POST['delete'], $userId]);
    } elseif (isset($_POST['clear_read'])) {
        $db->prepare("DELETE FROM notifications WHERE user_id = ? AND read_at IS NOT NULL")->execute([$userId]);
    }
    header('Location: notifications.php');
    exit;
}

$filter = ($_GET['filter'] ?? 'all') === 'unread' ? 'unread' : 'all';
$whereUnread = $filter === 'unread' ? ' AND read_at IS NULL' : '';
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ?$whereUnread ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
$stmt->execute([$userId]);
$unreadCount = (int) $stmt->fetchColumn();

include 'includes/sidebar.php';
?>
<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8 animate-fade-in">
            <div><h1 class="text-3xl font-bold text-secondary-900">Notifications</h1><p class="text-gray-500 mt-1">Updates about your assigned CRM work.</p></div>
            <div class="flex flex-wrap gap-2">
                <?php if ($unreadCount): ?><a href="notifications.php?action=read_all" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700"><i class="fas fa-check-double mr-2"></i>Mark all read</a><?php endif; ?>
                <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>"><button name="clear_read" value="1" data-confirm="Delete all read notifications?" data-confirm-title="Clear notifications" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-broom mr-2"></i>Clear read</button></form>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up">
            <div class="p-4 border-b border-gray-100 flex items-center gap-2">
                <a href="notifications.php" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $filter === 'all' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">All</a>
                <a href="notifications.php?filter=unread" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $filter === 'unread' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">Unread <span class="ml-1 opacity-75"><?php echo $unreadCount; ?></span></a>
            </div>
            <div class="divide-y divide-gray-100">
                <?php if (!$notifications): ?><div class="py-20 text-center"><div class="w-16 h-16 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-4"><i class="far fa-bell text-2xl text-gray-400"></i></div><h3 class="font-semibold text-secondary-900">No notifications</h3><p class="text-sm text-gray-500 mt-1">New updates will appear here.</p></div>
                <?php else: foreach ($notifications as $notification): ?>
                <div class="p-5 flex items-start gap-4 hover:bg-gray-50 <?php echo !$notification['read_at'] ? 'bg-blue-50/40' : ''; ?>">
                    <div class="w-11 h-11 rounded-full flex-shrink-0 flex items-center justify-center <?php echo $notification['type'] === 'success' ? 'bg-green-100 text-green-600' : ($notification['type'] === 'warning' ? 'bg-yellow-100 text-yellow-600' : 'bg-blue-100 text-blue-600'); ?>"><i class="fas fa-bell"></i></div>
                    <div class="flex-1 min-w-0"><div class="flex items-center gap-2"><h3 class="font-semibold text-secondary-900"><?php echo sanitize($notification['title']); ?></h3><?php if (!$notification['read_at']): ?><span class="w-2 h-2 bg-primary-600 rounded-full"></span><?php endif; ?></div><p class="text-sm text-gray-600 mt-1"><?php echo sanitize($notification['message']); ?></p><p class="text-xs text-gray-400 mt-2"><?php echo formatDate($notification['created_at'], 'M d, Y H:i'); ?></p></div>
                    <div class="flex items-center gap-1">
                        <?php if ($notification['url']): ?><a href="notifications.php?action=open&id=<?php echo (int) $notification['id']; ?>" class="p-2 text-primary-600 hover:bg-primary-50 rounded-lg" data-tooltip="Open"><i class="fas fa-arrow-right"></i></a><?php endif; ?>
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>"><?php if (!$notification['read_at']): ?><button name="mark_read" value="<?php echo (int) $notification['id']; ?>" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" data-tooltip="Mark read"><i class="fas fa-check"></i></button><?php endif; ?><button name="delete" value="<?php echo (int) $notification['id']; ?>" class="p-2 text-red-500 hover:bg-red-50 rounded-lg" data-tooltip="Delete"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
