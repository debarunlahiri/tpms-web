<?php
$pageTitle = 'Storage Management';
require_once 'includes/header.php';
requirePermission('storage');

$db = getDB();

// Handle media delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        $stmt = $db->prepare("SELECT * FROM media WHERE id=?");
        $stmt->execute([$id]);
        $media = $stmt->fetch();
        
        if ($media) {
            $filePath = __DIR__ . '/' . $media['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $db->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
            logActivity('delete', "Admin deleted media: {$media['original_name']}", 'media', $id);
            redirect('storage.php', 'Media deleted successfully.', 'success');
        }
    } catch (Exception $e) {
        redirect('storage.php', 'Error deleting media: ' . $e->getMessage(), 'error');
    }
}

// Storage statistics
$totalFiles = $db->query("SELECT COUNT(*) FROM media")->fetchColumn();
$totalSize = $db->query("SELECT COALESCE(SUM(file_size), 0) FROM media")->fetchColumn();
$imageSize = $db->query("SELECT COALESCE(SUM(file_size), 0) FROM media WHERE file_type='image'")->fetchColumn();
$videoSize = $db->query("SELECT COALESCE(SUM(file_size), 0) FROM media WHERE file_type='video'")->fetchColumn();

// User-wise breakdown
$userStats = $db->query("SELECT u.id, u.name, COUNT(m.id) as file_count, COALESCE(SUM(m.file_size), 0) as total_size 
                         FROM users u 
                         LEFT JOIN media m ON u.id = m.uploaded_by 
                         GROUP BY u.id, u.name 
                         ORDER BY total_size DESC")->fetchAll();

// Recent media
$media = $db->query("SELECT m.*, u.name as uploader_name FROM media m LEFT JOIN users u ON m.uploaded_by = u.id ORDER BY m.created_at DESC LIMIT 50")->fetchAll();

// Disk usage
$uploadsDir = __DIR__ . '/uploads';
$diskTotal = disk_total_space($uploadsDir);
$diskFree = disk_free_space($uploadsDir);
$diskUsed = $diskTotal - $diskFree;
$diskUsedPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0;

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="mb-8 animate-fade-in">
            <h1 class="text-3xl font-bold text-secondary-900">Storage Management</h1>
            <p class="text-gray-500 mt-1">Monitor server storage and manage uploaded media</p>
        </div>

        <!-- Storage Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Files</p>
                        <h3 class="text-3xl font-bold text-secondary-900"><?php echo number_format($totalFiles); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center"><i class="fas fa-images text-blue-600 text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Media Storage</p>
                        <h3 class="text-3xl font-bold text-secondary-900"><?php echo formatBytes($totalSize); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center"><i class="fas fa-hdd text-green-600 text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Server Disk Used</p>
                        <h3 class="text-3xl font-bold text-secondary-900"><?php echo formatBytes($diskUsed); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-orange-50 flex items-center justify-center"><i class="fas fa-server text-orange-600 text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Disk Usage</p>
                        <h3 class="text-3xl font-bold text-secondary-900"><?php echo $diskUsedPercent; ?>%</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center"><i class="fas fa-chart-pie text-red-600 text-xl"></i></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Disk Usage Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-100">
                <h2 class="text-lg font-bold text-secondary-900 mb-4">Disk Usage</h2>
                <div class="h-4 bg-gray-100 rounded-full overflow-hidden mb-4">
                    <div class="h-full bg-gradient-to-r from-green-500 to-red-500 rounded-full transition-all duration-1000" style="width: 0%" data-width="<?php echo $diskUsedPercent; ?>"></div>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-xs text-gray-500">Total</p>
                        <p class="font-semibold text-secondary-900"><?php echo formatBytes($diskTotal); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Used</p>
                        <p class="font-semibold text-secondary-900"><?php echo formatBytes($diskUsed); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Free</p>
                        <p class="font-semibold text-secondary-900"><?php echo formatBytes($diskFree); ?></p>
                    </div>
                </div>
            </div>

            <!-- Media Type Breakdown -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-200">
                <h2 class="text-lg font-bold text-secondary-900 mb-4">Media Type Breakdown</h2>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700">Images</span>
                            <span class="text-gray-500"><?php echo formatBytes($imageSize); ?></span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $totalSize > 0 ? round(($imageSize / $totalSize) * 100) : 0; ?>"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700">Videos</span>
                            <span class="text-gray-500"><?php echo formatBytes($videoSize); ?></span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-red-500 rounded-full" style="width: <?php echo $totalSize > 0 ? round(($videoSize / $totalSize) * 100) : 0; ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User-wise Storage -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8 animate-slide-up animate-delay-300">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-lg font-bold text-secondary-900">User-wise Storage Usage</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Files</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Storage Used</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($userStats as $user): 
                            $percent = $totalSize > 0 ? round(($user['total_size'] / $totalSize) * 100, 1) : 0;
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-secondary-900"><?php echo sanitize($user['name']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($user['file_count']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo formatBytes($user['total_size']); ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden max-w-[150px]">
                                        <div class="h-full bg-primary-500 rounded-full" style="width: <?php echo $percent; ?>"></div>
                                    </div>
                                    <span class="text-sm text-gray-600 w-12"><?php echo $percent; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- All Media -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up animate-delay-400">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-lg font-bold text-secondary-900">All Uploaded Media</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Preview</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">File</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Uploaded By</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($media as $item): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <?php if ($item['file_type'] === 'image'): ?>
                                <img src="<?php echo sanitize($item['file_path']); ?>" alt="" class="w-12 h-12 rounded object-cover">
                                <?php else: ?>
                                <div class="w-12 h-12 rounded bg-gray-800 flex items-center justify-center"><i class="fas fa-video text-white"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-secondary-900 truncate max-w-[200px]"><?php echo sanitize($item['original_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo sanitize($item['file_path']); ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 capitalize"><?php echo $item['file_type']; ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo formatBytes($item['file_size']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($item['uploader_name'] ?? 'Unknown'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo formatDate($item['created_at']); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?php echo sanitize($item['file_path']); ?>" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-eye"></i></a>
                                    <a href="<?php echo sanitize($item['file_path']); ?>" download class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"><i class="fas fa-download"></i></a>
                                    <a href="storage.php?delete=<?php echo $item['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-confirm="Delete this media file? This cannot be undone."><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.querySelectorAll('[data-width]').forEach(bar => {
            bar.style.width = bar.dataset.width + '%';
        });
    }, 500);
});
</script>

<?php include 'includes/footer.php'; ?>
