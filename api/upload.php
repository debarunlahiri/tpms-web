<?php
/**
 * TPMS - File Upload Handler
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requirePermission('media');

header('Content-Type: application/json');

// Rate limiting for uploads
$uploadKey = 'upload_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!isset($_SESSION[$uploadKey])) {
    $_SESSION[$uploadKey] = ['count' => 0, 'time' => time()];
}
if (time() - $_SESSION[$uploadKey]['time'] > 3600) {
    $_SESSION[$uploadKey] = ['count' => 0, 'time' => time()];
}
$_SESSION[$uploadKey]['count']++;
if ($_SESSION[$uploadKey]['count'] > 100) {
    echo json_encode(['success' => false, 'message' => 'Upload rate limit exceeded. Try again later.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = 'Upload failed';
    if (isset($_FILES['file']['error'])) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        $error = $errors[$_FILES['file']['error']] ?? 'Upload failed';
    }
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

$file = $_FILES['file'];
$originalName = basename($file['name']);
$tmpName = $file['tmp_name'];
$fileSize = $file['size'];

// Max file size: 100MB
$maxSize = 100 * 1024 * 1024;
if ($fileSize > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 100MB.']);
    exit;
}

// Validate mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpName);
finfo_close($finfo);

$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'];
$allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images and videos are allowed.']);
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'ogg', 'ogv', 'mov', 'avi'];
if (!in_array($ext, $allowedExts)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file extension.']);
    exit;
}

// Determine file type category
$fileType = in_array($mimeType, $allowedImageTypes) ? 'image' : 'video';

// Store under a time-sortable, globally unique UUIDv7 filename.
$safeName = uuidV7() . '.' . $ext;
$dateFolder = date('Y/m');
$uploadDir = __DIR__ . '/../uploads/' . $dateFolder;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$targetPath = $uploadDir . '/' . $safeName;

if (!move_uploaded_file($tmpName, $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

// Store in database
$relativePath = 'uploads/' . $dateFolder . '/' . $safeName;
$relatedTo = $_POST['related_to'] ?? 'general';
$relatedId = $_POST['related_id'] ?? null;

try {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO media (filename, original_name, file_path, file_type, file_size, mime_type, related_to, related_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$safeName, $originalName, $relativePath, $fileType, $fileSize, $mimeType, $relatedTo, $relatedId, $_SESSION['user_id']]);
    $mediaId = $db->lastInsertId();
    
    logActivity('upload', "Uploaded $fileType: $originalName", 'media', $mediaId);
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'media' => [
            'id' => $mediaId,
            'filename' => $safeName,
            'original_name' => $originalName,
            'file_path' => $relativePath,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'url' => $relativePath
        ]
    ]);
} catch (Exception $e) {
    // Clean up file if DB insert fails
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
