<?php
/**
 * TPMS - Attachment Upload/Delete API
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $relatedTo = $_POST['related_to'] ?? '';
    $relatedId = intval($_POST['related_id'] ?? 0);

    if (!in_array($relatedTo, ['contact', 'deal']) || !$relatedId) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Check access to parent record
    $table = $relatedTo === 'contact' ? 'contacts' : 'deals';
    $stmt = $db->prepare("SELECT assigned_to FROM $table WHERE id = ?");
    $stmt->execute([$relatedId]);
    $record = $stmt->fetch();
    if (!$record || (!$viewAll && $record['assigned_to'] != $userId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['file'];
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar'];
    $maxSize = 20 * 1024 * 1024; // 20MB

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 20MB limit']);
        exit;
    }

    $originalName = basename($file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/attachments/' . $relatedTo . '/' . $relatedId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uuidV7() . '.' . $extension;
    $filePath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/attachments/' . $relatedTo . '/' . $relatedId . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO media (filename, original_name, file_path, file_type, file_size, mime_type, related_to, related_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$filename, $originalName, $relativePath, 'attachment', $file['size'], $file['type'], $relatedTo, $relatedId, $userId]);
        $mediaId = $db->lastInsertId();
        logActivity('create', "Uploaded attachment #$mediaId to $relatedTo #$relatedId", 'media', $mediaId);
        echo json_encode(['success' => true, 'message' => 'File uploaded successfully', 'id' => $mediaId]);
    } catch (Exception $e) {
        @unlink($filePath);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_method'] ?? '') === 'DELETE')) {
    $mediaId = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$mediaId) {
        echo json_encode(['success' => false, 'message' => 'Invalid attachment ID']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM media WHERE id = ? AND file_type = 'attachment'");
    $stmt->execute([$mediaId]);
    $media = $stmt->fetch();

    if (!$media) {
        echo json_encode(['success' => false, 'message' => 'Attachment not found']);
        exit;
    }

    // Check access to parent record
    $table = $media['related_to'] === 'contact' ? 'contacts' : 'deals';
    $stmt = $db->prepare("SELECT assigned_to FROM $table WHERE id = ?");
    $stmt->execute([$media['related_id']]);
    $record = $stmt->fetch();
    if (!$record || (!$viewAll && $record['assigned_to'] != $userId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $filePath = __DIR__ . '/../' . $media['file_path'];
    @unlink($filePath);

    $stmt = $db->prepare("DELETE FROM media WHERE id = ?");
    $stmt->execute([$mediaId]);
    logActivity('delete', "Deleted attachment #$mediaId", 'media', $mediaId);
    echo json_encode(['success' => true, 'message' => 'Attachment deleted successfully']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
