<?php
$pageTitle = 'Media';
require_once 'includes/header.php';
requirePermission('media');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();

$recordWhere = $viewAll ? '' : ' WHERE assigned_to = ? OR created_by = ?';
$stmt = $db->prepare("SELECT id, title FROM projects$recordWhere ORDER BY title");
$stmt->execute($viewAll ? [] : [$userId, $userId]);
$projects = $stmt->fetchAll();
$stmt = $db->prepare("SELECT id, title FROM tasks$recordWhere ORDER BY title");
$stmt->execute($viewAll ? [] : [$userId, $userId]);
$tasks = $stmt->fetchAll();

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        $stmt = $db->prepare("SELECT * FROM media WHERE id=? " . ($viewAll ? '' : 'AND uploaded_by=?'));
        $stmt->execute($viewAll ? [$id] : [$id, $userId]);
        $media = $stmt->fetch();

        if ($media) {
            $filePath = __DIR__ . '/' . $media['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $db->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
            logActivity('delete', "Deleted media: {$media['original_name']}", 'media', $id);
            $success = 'Media deleted successfully.';
        } else {
            $error = 'Media not found or access denied.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting media: ' . $e->getMessage();
    }
}

$where = $viewAll ? '' : "WHERE m.uploaded_by = $userId";
$media = $db->query("SELECT m.*, u.name as uploader_name FROM media m LEFT JOIN users u ON m.uploaded_by = u.id $where ORDER BY m.created_at DESC")->fetchAll();

$totalSize = $db->query("SELECT COALESCE(SUM(file_size), 0) FROM media $where")->fetchColumn();

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Media Gallery</h1>
                <p class="text-gray-500 mt-1">Upload and manage documents, images, and videos</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <button onclick="openUploadModal()" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-medium">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Media
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Files</p>
                        <h3 class="text-3xl font-bold text-secondary-900"><?php echo count($media); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center"><i class="fas fa-images text-blue-600 text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Size</p>
                        <h3 class="text-3xl font-bold text-secondary-900"><?php echo formatBytes($totalSize); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center"><i class="fas fa-hdd text-green-600 text-xl"></i></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
            <?php if (empty($media)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-cloud-upload-alt text-6xl text-gray-200 mb-4"></i>
                    <p class="text-gray-500">No media uploaded yet.</p>
                    <button onclick="openUploadModal()" class="mt-4 text-primary-600 hover:underline font-medium">Upload your first file</button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($media as $item): ?>
                        <div class="group bg-gray-50 rounded-xl overflow-hidden hover:shadow-lg transition-all duration-300">
                            <div class="aspect-video bg-gray-200 relative overflow-hidden cursor-pointer" onclick="openMediaViewer(<?php echo $item['id']; ?>)">
                                <?php if ($item['file_type'] === 'image'): ?>
                                    <img src="<?php echo sanitize($item['file_path']); ?>" alt="<?php echo sanitize($item['original_name']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <?php elseif ($item['file_type'] === 'video'): ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-800">
                                        <i class="fas fa-play-circle text-white text-5xl opacity-80"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-slate-100">
                                        <i class="fas fa-file-alt text-slate-500 text-5xl"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute top-2 right-2 px-2 py-1 text-xs rounded-full bg-black/60 text-white">
                                    <?php echo strtoupper(pathinfo($item['original_name'], PATHINFO_EXTENSION)); ?>
                                </div>
                            </div>
                            <div class="p-4">
                                <p class="font-medium text-secondary-900 truncate" title="<?php echo sanitize($item['original_name']); ?>"><?php echo sanitize($item['original_name']); ?></p>
                                <p class="text-xs text-gray-500 mb-2"><?php echo formatBytes($item['file_size']); ?> &bull; <?php echo sanitize($item['uploader_name'] ?? 'Unknown'); ?></p>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-400"><?php echo formatDate($item['created_at']); ?></span>
                                    <div class="flex gap-1">
                                        <a href="<?php echo sanitize($item['file_path']); ?>" download class="p-1.5 text-gray-600 hover:bg-gray-200 rounded transition-colors" title="Download"><i class="fas fa-download text-xs"></i></a>
                                        <a href="media.php?delete=<?php echo $item['id']; ?>" class="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors" data-confirm="Delete this media file?" title="Delete"><i class="fas fa-trash text-xs"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Upload Modal -->
<div id="upload-modal" class="fixed inset-0 z-[100] hidden">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity opacity-0 upload-backdrop"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-lg opacity-0 scale-95 upload-panel">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-secondary-900 mb-4">Upload Media</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                        <div>
                            <label for="media-related-to" class="block text-sm font-medium text-gray-700 mb-1">Related to</label>
                            <select id="media-related-to" class="w-full rounded-lg border-gray-300 text-sm" onchange="updateMediaTargets()">
                                <option value="general">General</option>
                                <option value="task">Task</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                        <div id="media-target-wrap" class="hidden">
                            <label for="media-related-id" class="block text-sm font-medium text-gray-700 mb-1">Select record</label>
                            <select id="media-related-id" class="w-full rounded-lg border-gray-300 text-sm"></select>
                        </div>
                    </div>
                    <div id="drop-zone" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary-500 transition-colors cursor-pointer">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600 mb-2">Drag & drop files here or click to browse</p>
                        <p class="text-xs text-gray-400">Documents and images up to 5 MB · Videos up to 10 MB</p>
                        <input type="file" id="upload-file" class="hidden" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" multiple>
                    </div>
                    <div id="upload-progress" class="mt-4 hidden">
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-primary-600 rounded-full transition-all" style="width: 0%"></div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2 text-center">Uploading...</p>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end gap-2">
                    <button onclick="closeUploadModal()" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Media Viewer Modal -->
<div id="media-viewer" class="fixed inset-0 z-[100] hidden">
    <div class="fixed inset-0 bg-black/90 backdrop-blur-sm" onclick="closeMediaViewer()"></div>
    <div class="fixed inset-0 z-10 flex items-center justify-center p-4">
        <button onclick="closeMediaViewer()" class="absolute top-4 right-4 text-white hover:text-gray-300 text-2xl z-20"><i class="fas fa-times"></i></button>
        <div id="viewer-content" class="max-w-5xl max-h-screen"></div>
    </div>
</div>

<script>
    const mediaFiles = <?php echo json_encode($media); ?>;
    const mediaTargets = {
        task: <?php echo json_encode($tasks); ?>,
        project: <?php echo json_encode($projects); ?>
    };

    function updateMediaTargets() {
        const relatedTo = document.getElementById('media-related-to').value;
        const wrap = document.getElementById('media-target-wrap');
        const select = document.getElementById('media-related-id');
        if (relatedTo === 'general') {
            wrap.classList.add('hidden');
            select.innerHTML = '';
            return;
        }
        wrap.classList.remove('hidden');
        const records = mediaTargets[relatedTo] || [];
        select.innerHTML = records.length ?
            records.map(record => `<option value="${record.id}">${record.title}</option>`).join('') :
            '<option value="">No available records</option>';
    }

    function openUploadModal() {
        const modal = document.getElementById('upload-modal');
        modal.classList.remove('hidden');
        requestAnimationFrame(() => {
            modal.querySelector('.upload-backdrop').classList.remove('opacity-0');
            modal.querySelector('.upload-panel').classList.remove('opacity-0', 'scale-95');
            modal.querySelector('.upload-panel').classList.add('opacity-100', 'scale-100');
        });
    }

    function closeUploadModal() {
        const modal = document.getElementById('upload-modal');
        modal.querySelector('.upload-backdrop').classList.add('opacity-0');
        modal.querySelector('.upload-panel').classList.remove('opacity-100', 'scale-100');
        modal.querySelector('.upload-panel').classList.add('opacity-0', 'scale-95');
        setTimeout(() => modal.classList.add('hidden'), 200);
    }

    function openMediaViewer(id) {
        const item = mediaFiles.find(m => m.id == id);
        if (!item) return;

        const viewer = document.getElementById('media-viewer');
        const content = document.getElementById('viewer-content');

        if (item.file_type === 'image') {
            content.innerHTML = `<img src="${item.file_path}" alt="${item.original_name}" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl">`;
        } else if (item.file_type === 'video') {
            content.innerHTML = `
            <div class="custom-video-player rounded-lg overflow-hidden shadow-2xl bg-black">
                <video controls autoplay class="max-w-full max-h-[90vh]">
                    <source src="${item.file_path}" type="${item.mime_type}">
                    Your browser does not support the video tag.
                </video>
            </div>`;
        } else {
            content.innerHTML = `<div class="bg-white rounded-xl p-8 text-center shadow-2xl"><i class="fas fa-file-alt text-6xl text-slate-400 mb-4"></i><p class="font-medium text-slate-800 mb-4">${item.original_name}</p><a href="${item.file_path}" target="_blank" rel="noopener" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg"><i class="fas fa-external-link-alt"></i> Open document</a></div>`;
        }

        viewer.classList.remove('hidden');
    }

    function closeMediaViewer() {
        const viewer = document.getElementById('media-viewer');
        const content = document.getElementById('viewer-content');
        viewer.classList.add('hidden');
        content.innerHTML = '';
    }

    // Upload handling
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('upload-file');
    const progressDiv = document.getElementById('upload-progress');
    const progressBar = progressDiv.querySelector('div > div');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-primary-500', 'bg-primary-50');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-primary-500', 'bg-primary-50');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-primary-500', 'bg-primary-50');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
    });

    function handleFiles(files) {
        if (!files.length) return;

        const relatedTo = document.getElementById('media-related-to').value;
        const relatedId = document.getElementById('media-related-id').value;
        if (relatedTo !== 'general' && !relatedId) {
            showAlert(`Please select a ${relatedTo}.`, 'Select Record', 'warning');
            return;
        }

        const selectedFiles = Array.from(files);
        const invalidFile = selectedFiles.find(file => {
            const isVideo = file.type.startsWith('video/');
            const isImage = file.type.startsWith('image/');
            const isDocument = !isVideo && !isImage;
            return (isVideo && file.size > 10 * 1024 * 1024) || ((isImage || isDocument) && file.size > 5 * 1024 * 1024);
        });
        if (invalidFile) {
            const limit = invalidFile.type.startsWith('video/') ? '10 MB' : '5 MB';
            showAlert(`${invalidFile.name} exceeds the ${limit} upload limit.`, 'File Too Large', 'warning');
            fileInput.value = '';
            return;
        }

        progressDiv.classList.remove('hidden');
        let uploaded = 0;
        let successfulUploads = 0;

        selectedFiles.forEach(file => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', '<?php echo csrfToken(); ?>');
            formData.append('related_to', relatedTo);
            if (relatedId) formData.append('related_id', relatedId);

            fetch('api/upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    uploaded++;
                    const progress = (uploaded / files.length) * 100;
                    progressBar.style.width = progress + '%';

                    if (!data.success) {
                        showAlert(data.message || `Could not upload ${file.name}.`, 'Upload Failed', 'danger');
                    } else {
                        successfulUploads++;
                    }
                    if (uploaded === selectedFiles.length && successfulUploads > 0) {
                        setTimeout(() => window.location.reload(), 500);
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    uploaded++;
                    showAlert(`Could not upload ${file.name}.`, 'Upload Failed', 'danger');
                });
        });
    }
</script>

<?php include 'includes/footer.php'; ?>