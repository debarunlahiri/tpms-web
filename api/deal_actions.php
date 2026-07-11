<?php
/**
 * Enterprise CRM - Deal API Endpoints
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

if ($action === 'update_stage') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $dealId = intval($_POST['deal_id'] ?? 0);
    $stage = $_POST['stage'] ?? '';
    $validStages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];

    if (!$dealId || !in_array($stage, $validStages)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        if (!$viewAll) {
            $stmt = $db->prepare("UPDATE deals SET stage = ?, actual_close_date = CASE WHEN ? IN ('closed_won', 'closed_lost') THEN CURDATE() ELSE actual_close_date END WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$stage, $stage, $dealId, $userId]);
        } else {
            $stmt = $db->prepare("UPDATE deals SET stage = ?, actual_close_date = CASE WHEN ? IN ('closed_won', 'closed_lost') THEN CURDATE() ELSE actual_close_date END WHERE id = ?");
            $stmt->execute([$stage, $stage, $dealId]);
        }

        if ($stmt->rowCount() > 0) {
            logActivity('update', "Updated deal #$dealId stage to $stage", 'deal', $dealId);
            echo json_encode(['success' => true, 'message' => 'Stage updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Deal not found or access denied']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
