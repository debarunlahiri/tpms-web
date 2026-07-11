<?php
/**
 * TPMS - Project API Endpoints
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'update_status') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $projectId = intval($_POST['project_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $validStatuses = ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    if (!$projectId || !in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        if (!$viewAll) {
            $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$status, $projectId, $userId]);
        } else {
            $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ?");
            $stmt->execute([$status, $projectId]);
        }

        if ($stmt->rowCount() > 0) {
            logActivity('update', "Updated project #$projectId status to $status", 'project', $projectId);
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Project not found or access denied']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
