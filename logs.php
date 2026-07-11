<?php
$pageTitle = 'Activity Logs';
require_once 'includes/header.php';
requirePermission(['settings', 'users', 'roles']);

$db = getDB();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

$where = '';
$params = [];

if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $where .= ($where ? ' AND ' : 'WHERE ') . 'a.user_id = ?';
    $params[] = $_GET['user_id'];
}

if (!empty($_GET['action'])) {
    $where .= ($where ? ' AND ' : 'WHERE ') . 'a.action = ?';
    $params[] = $_GET['action'];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM activities a $where");
foreach ($params as $i => $param) {
    $countStmt->bindValue($i + 1, $param);
}
$countStmt->execute();
$total = $countStmt->fetchColumn();
$offset = ($page - 1) * $perPage;

$logStmt = $db->prepare("SELECT a.*, u.name as user_name, u.email as user_email 
                      FROM activities a 
                      LEFT JOIN users u ON a.user_id = u.id 
                      $where 
                      ORDER BY a.created_at DESC 
                      LIMIT ? OFFSET ?");
$paramIndex = 1;
foreach ($params as $param) {
    $logStmt->bindValue($paramIndex++, $param);
}
$logStmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$logStmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$logStmt->execute();
$logs = $logStmt->fetchAll();

$users = $db->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
$actions = $db->query("SELECT DISTINCT action FROM activities ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$totalPages = max(1, ceil($total / $perPage));

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="mb-8 animate-fade-in">
            <h1 class="text-3xl font-bold text-secondary-900">Activity Logs</h1>
            <p class="text-gray-500 mt-1">Track every action performed by users including admins</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up">
            <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row gap-4">
                <form method="GET" action="" class="flex flex-wrap items-end gap-3">
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">User
                    <select name="user_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($_GET['user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>><?php echo sanitize($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Action
                    <select name="action" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $actionType): ?>
                        <option value="<?php echo $actionType; ?>" <?php echo ($_GET['action'] ?? '') === $actionType ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $actionType)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 transition-colors">Filter</button>
                    <a href="logs.php" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">Reset</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Time</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap"><?php echo formatDate($log['created_at'], 'Y-m-d H:i:s'); ?></td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-secondary-900"><?php echo sanitize($log['user_name'] ?? 'System'); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo sanitize($log['user_email'] ?? ''); ?></p>
                                </td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full bg-gray-100 text-gray-700 capitalize"><?php echo str_replace('_', ' ', $log['action']); ?></span></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($log['description']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500 font-mono"><?php echo sanitize($log['ip_address'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="p-4 border-t border-gray-100 flex items-center justify-between">
                <p class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $total); ?> of <?php echo $total; ?></p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&user_id=<?php echo $_GET['user_id'] ?? ''; ?>&action=<?php echo $_GET['action'] ?? ''; ?>" class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-50 text-sm">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&user_id=<?php echo $_GET['user_id'] ?? ''; ?>&action=<?php echo $_GET['action'] ?? ''; ?>" class="px-3 py-1 border border-gray-200 rounded hover:bg-gray-50 text-sm">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
