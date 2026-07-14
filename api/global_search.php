<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
if (mb_strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$viewAll = canViewAll();
$like = '%' . $query . '%';
$results = [];

$can = static fn(string $permission): bool => isAdmin() || hasPermission($permission);
$addRows = static function (array &$results, array $rows, string $type, string $icon, callable $title, callable $subtitle, callable $url): void {
    foreach ($rows as $row) {
        $results[] = ['type' => $type, 'icon' => $icon, 'title' => $title($row), 'subtitle' => $subtitle($row), 'url' => $url($row)];
    }
};
$search = static function (PDO $db, string $sql, array $params): array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$menus = [
    ['Dashboard', 'dashboard.php', 'dashboard', 'fa-chart-line'],
    ['Projects', 'projects.php', 'projects', 'fa-project-diagram'],
    ['Leads', 'leads.php', 'leads', 'fa-filter'],
    ['Clients', 'contacts.php', 'contacts', 'fa-address-book'],
    ['Deals', 'deals.php', 'deals', 'fa-handshake'],
    ['Tasks', 'tasks.php', 'tasks', 'fa-tasks'],
    ['Media', 'media.php', 'media', 'fa-images'],
    ['Invoices', 'invoices.php', 'invoices', 'fa-file-invoice-dollar'],
    ['Reports', 'reports.php', 'reports', 'fa-chart-pie'],
    ['Activity Logs', 'logs.php', 'roles', 'fa-clipboard-list'],
    ['Storage', 'storage.php', 'storage', 'fa-hdd'],
    ['Settings', 'settings.php', 'settings', 'fa-cog'],
];
foreach ($menus as [$label, $url, $permission, $icon]) {
    if ($can($permission) && stripos($label, $query) !== false) $results[] = ['type' => 'Menu', 'icon' => $icon, 'title' => $label, 'subtitle' => 'Open menu', 'url' => $url];
}

if ($can('contacts')) {
    $scope = $viewAll ? '' : ' AND assigned_to = ?';
    $params = [$like, $like, $like, $like];
    if (!$viewAll) $params[] = $userId;
    $rows = $search($db, "SELECT id, first_name, last_name, company, email FROM contacts WHERE (first_name LIKE ? OR last_name LIKE ? OR company LIKE ? OR email LIKE ?)$scope ORDER BY first_name LIMIT 6", $params);
    $addRows($results, $rows, 'Client', 'fa-address-book', fn($r) => trim($r['first_name'].' '.$r['last_name']), fn($r) => $r['company'] ?: ($r['email'] ?: 'Client'), fn($r) => 'contacts.php?action=edit&id='.(int)$r['id']);
}
if ($can('leads')) {
    $scope = $viewAll ? '' : ' AND assigned_to = ?'; $params = [$like, $like, $like]; if (!$viewAll) $params[] = $userId;
    $rows = $search($db, "SELECT id, title, status, priority FROM leads WHERE (title LIKE ? OR description LIKE ? OR source LIKE ?)$scope ORDER BY created_at DESC LIMIT 6", $params);
    $addRows($results, $rows, 'Lead', 'fa-filter', fn($r) => $r['title'], fn($r) => ucfirst($r['status']).' · '.ucfirst($r['priority']), fn($r) => 'leads.php?action=edit&id='.(int)$r['id']);
}
if ($can('deals')) {
    $scope = $viewAll ? '' : ' AND assigned_to = ?'; $params = [$like, $like]; if (!$viewAll) $params[] = $userId;
    $rows = $search($db, "SELECT id, title, stage, value FROM deals WHERE (title LIKE ? OR notes LIKE ?)$scope ORDER BY created_at DESC LIMIT 6", $params);
    $addRows($results, $rows, 'Deal', 'fa-handshake', fn($r) => $r['title'], fn($r) => ucwords(str_replace('_',' ',$r['stage'])), fn($r) => 'deals.php?action=edit&id='.(int)$r['id']);
}
if ($can('projects')) {
    $scope = $viewAll ? '' : ' AND (assigned_to = ? OR created_by = ?)'; $params = [$like, $like]; if (!$viewAll) array_push($params, $userId, $userId);
    $rows = $search($db, "SELECT id, title, status FROM projects WHERE (title LIKE ? OR description LIKE ?)$scope ORDER BY created_at DESC LIMIT 6", $params);
    $addRows($results, $rows, 'Project', 'fa-project-diagram', fn($r) => $r['title'], fn($r) => ucwords(str_replace('_',' ',$r['status'])), fn($r) => 'projects.php?action=edit&id='.(int)$r['id']);
}
if ($can('tasks')) {
    $scope = $viewAll ? '' : ' AND assigned_to = ?'; $params = [$like, $like]; if (!$viewAll) $params[] = $userId;
    $rows = $search($db, "SELECT id, title, status, due_date FROM tasks WHERE (title LIKE ? OR description LIKE ?)$scope ORDER BY created_at DESC LIMIT 6", $params);
    $addRows($results, $rows, 'Task', 'fa-tasks', fn($r) => $r['title'], fn($r) => ucfirst($r['status']), fn($r) => 'tasks.php?action=edit&id='.(int)$r['id']);
}
if ($can('invoices')) {
    $scope = $viewAll ? '' : ' AND i.created_by = ?'; $params = [$like, $like, $like]; if (!$viewAll) $params[] = $userId;
    $rows = $search($db, "SELECT i.id, i.invoice_number, i.status, c.first_name, c.last_name, c.company FROM invoices i LEFT JOIN contacts c ON c.id=i.contact_id WHERE (i.invoice_number LIKE ? OR c.company LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ?)$scope ORDER BY i.created_at DESC LIMIT 6", $params);
    $addRows($results, $rows, 'Invoice', 'fa-file-invoice-dollar', fn($r) => $r['invoice_number'], fn($r) => ($r['company'] ?: trim($r['first_name'].' '.$r['last_name'])).' · '.ucfirst($r['status']), fn($r) => 'invoices.php?action=view&id='.(int)$r['id']);
}
if ($can('media')) {
    $scope = $viewAll ? '' : ' AND uploaded_by = ?'; $params = [$like]; if (!$viewAll) $params[] = $userId;
    $rows = $search($db, "SELECT id, original_name, mime_type FROM media WHERE original_name LIKE ?$scope ORDER BY created_at DESC LIMIT 6", $params);
    $addRows($results, $rows, 'Media', 'fa-file', fn($r) => $r['original_name'], fn($r) => $r['mime_type'] ?: 'Uploaded file', fn($r) => 'media.php');
}
if ($can('users')) {
    $rows = $search($db, "SELECT id, name, email, role FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY name LIMIT 6", [$like, $like]);
    $addRows($results, $rows, 'User', 'fa-user', fn($r) => $r['name'], fn($r) => $r['email'].' · '.ucwords(str_replace('_',' ',$r['role'])), fn($r) => 'settings.php?section=users');
}

echo json_encode(['results' => array_slice($results, 0, 30)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
