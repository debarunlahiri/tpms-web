<?php
/**
 * TPMS - CSV Export API
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$type = $_GET['type'] ?? '';

$allowedTypes = ['leads', 'contacts', 'deals'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    echo 'Invalid export type.';
    exit;
}

// Check permission
$permMap = ['leads' => 'leads', 'contacts' => 'contacts', 'deals' => 'deals'];
if (!hasPermission($permMap[$type])) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterAssigned = $_GET['assigned_to'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterStage = $_GET['stage'] ?? '';

$filename = 'tpms_' . $type . '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

function buildWhere($db, $viewAll, $userId, $baseTable, $filters) {
    $conditions = [];
    if (!$viewAll) {
        $conditions[] = "$baseTable.assigned_to = $userId";
    }
    if (!empty($filters['status'])) {
        $conditions[] = "$baseTable.status = " . $db->quote($filters['status']);
    }
    if (!empty($filters['stage'])) {
        $conditions[] = "$baseTable.stage = " . $db->quote($filters['stage']);
    }
    if (!empty($filters['assigned_to']) && is_numeric($filters['assigned_to'])) {
        $conditions[] = "$baseTable.assigned_to = " . intval($filters['assigned_to']);
    }
    if (!empty($filters['date_from'])) {
        $conditions[] = "DATE($baseTable.created_at) >= " . $db->quote($filters['date_from']);
    }
    if (!empty($filters['date_to'])) {
        $conditions[] = "DATE($baseTable.created_at) <= " . $db->quote($filters['date_to']);
    }
    return $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
}

$filters = [
    'status' => $filterStatus,
    'assigned_to' => $filterAssigned,
    'date_from' => $filterDateFrom,
    'date_to' => $filterDateTo,
    'stage' => $filterStage,
];

if ($type === 'leads') {
    $where = buildWhere($db, $viewAll, $userId, 'l', $filters);
    $stmt = $db->query("SELECT l.title, l.description, c.first_name, c.last_name, c.company, c.email, c.phone, l.status, l.priority, l.source, l.estimated_value, u.name as assigned_name, l.created_at 
                        FROM leads l 
                        LEFT JOIN contacts c ON l.contact_id = c.id 
                        LEFT JOIN users u ON l.assigned_to = u.id 
                        $where 
                        ORDER BY l.created_at DESC");
    fputcsv($output, ['Title', 'Description', 'Contact First Name', 'Contact Last Name', 'Company', 'Email', 'Phone', 'Status', 'Priority', 'Source', 'Estimated Value', 'Assigned To', 'Created At']);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['title'],
            $row['description'],
            $row['first_name'],
            $row['last_name'],
            $row['company'],
            $row['email'],
            $row['phone'],
            $row['status'],
            $row['priority'],
            $row['source'],
            $row['estimated_value'],
            $row['assigned_name'],
            $row['created_at']
        ]);
    }
} elseif ($type === 'contacts') {
    $where = buildWhere($db, $viewAll, $userId, 'c', $filters);
    $stmt = $db->query("SELECT c.first_name, c.last_name, c.email, c.phone, c.company, c.job_title, c.industry, c.address, c.city, c.country, c.source, c.status, u.name as assigned_name, c.notes, c.created_at 
                        FROM contacts c 
                        LEFT JOIN users u ON c.assigned_to = u.id 
                        $where 
                        ORDER BY c.created_at DESC");
    fputcsv($output, ['First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Job Title', 'Industry', 'Address', 'City', 'Country', 'Source', 'Status', 'Assigned To', 'Notes', 'Created At']);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['company'],
            $row['job_title'],
            $row['industry'],
            $row['address'],
            $row['city'],
            $row['country'],
            $row['source'],
            $row['status'],
            $row['assigned_name'],
            $row['notes'],
            $row['created_at']
        ]);
    }
} elseif ($type === 'deals') {
    $where = buildWhere($db, $viewAll, $userId, 'd', $filters);
    $stmt = $db->query("SELECT d.title, d.notes, c.first_name, c.last_name, c.company, d.stage, d.value, d.probability, d.expected_close_date, u.name as assigned_name, d.created_at 
                        FROM deals d 
                        LEFT JOIN contacts c ON d.contact_id = c.id 
                        LEFT JOIN users u ON d.assigned_to = u.id 
                        $where 
                        ORDER BY d.created_at DESC");
    fputcsv($output, ['Title', 'Notes', 'Contact First Name', 'Contact Last Name', 'Company', 'Stage', 'Value', 'Probability', 'Expected Close Date', 'Assigned To', 'Created At']);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['title'],
            $row['notes'],
            $row['first_name'],
            $row['last_name'],
            $row['company'],
            $row['stage'],
            $row['value'],
            $row['probability'],
            $row['expected_close_date'],
            $row['assigned_name'],
            $row['created_at']
        ]);
    }
}

fclose($output);
exit;
