<?php
$pageTitle = 'Client Portal';
require_once 'includes/header.php';
requirePermission('client_access');

$db = getDB();
$userId = $_SESSION['user_id'];
$adminPreview = isAdmin();
$portalContacts = [];

if ($adminPreview) {
    $portalContacts = $db->query("SELECT id, first_name, last_name, company FROM contacts ORDER BY first_name, last_name")->fetchAll();
    $selectedContactId = filter_input(INPUT_GET, 'contact_id', FILTER_VALIDATE_INT);

    if (!$selectedContactId && !empty($portalContacts)) {
        $selectedContactId = (int) $portalContacts[0]['id'];
    }

    $contact = false;
    if ($selectedContactId) {
        $stmt = $db->prepare("SELECT c.*, u.name as assigned_name FROM contacts c LEFT JOIN users u ON c.assigned_to = u.id WHERE c.id = ? LIMIT 1");
        $stmt->execute([$selectedContactId]);
        $contact = $stmt->fetch();
    }
} else {
    $contact = $db->prepare("SELECT c.*, u.name as assigned_name FROM contacts c LEFT JOIN users u ON c.assigned_to = u.id WHERE c.user_id = ? LIMIT 1");
    $contact->execute([$userId]);
    $contact = $contact->fetch();
}

$deals = [];
$tasks = [];
$attachments = [];

if ($contact) {
    $stmt = $db->prepare("SELECT d.*, u.name as assigned_name FROM deals d LEFT JOIN users u ON d.assigned_to = u.id WHERE d.contact_id = ? ORDER BY d.created_at DESC");
    $stmt->execute([$contact['id']]);
    $deals = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT t.*, u.name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE (t.related_to = 'contact' AND t.related_id = ?) OR (t.related_to = 'deal' AND t.related_id IN (SELECT id FROM deals WHERE contact_id = ?)) ORDER BY t.due_date ASC");
    $stmt->execute([$contact['id'], $contact['id']]);
    $tasks = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM media WHERE related_to = 'contact' AND related_id = ? AND file_type = 'attachment' ORDER BY created_at DESC");
    $stmt->execute([$contact['id']]);
    $attachments = $stmt->fetchAll();
}

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-6 pt-20">
        <div class="mb-8 animate-fade-in flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Client Portal</h1>
                <p class="text-gray-500 mt-1"><?php echo $adminPreview ? 'Preview the portal as a client contact.' : 'View your linked deals, tasks, and shared files.'; ?></p>
            </div>
            <?php if ($adminPreview && !empty($portalContacts)): ?>
                <form method="GET" action="client_portal.php" class="flex items-end gap-2">
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
                        View Portal For
                        <select name="contact_id" onchange="this.form.submit()" class="w-64 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                            <?php foreach ($portalContacts as $portalContact): ?>
                                <option value="<?php echo (int) $portalContact['id']; ?>" <?php echo $contact && (int) $contact['id'] === (int) $portalContact['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize(trim($portalContact['first_name'] . ' ' . $portalContact['last_name']) . ($portalContact['company'] ? ' — ' . $portalContact['company'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$contact): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-yellow-800 animate-slide-up">
                <i class="fas fa-info-circle mr-2"></i>
                <?php echo $adminPreview ? 'No contacts are available to preview yet.' : 'Your account is not linked to any contact yet. Please contact your account manager.'; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6 animate-slide-up">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-2xl font-bold">
                            <?php echo getInitials($contact['first_name'] . ' ' . $contact['last_name']); ?>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-secondary-900"><?php echo sanitize($contact['first_name'] . ' ' . $contact['last_name']); ?></h2>
                            <p class="text-gray-500"><?php echo sanitize($contact['company']); ?></p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><i class="fas fa-envelope w-5 text-gray-400"></i> <?php echo sanitize($contact['email'] ?: '-'); ?></p>
                        <p><i class="fas fa-phone w-5 text-gray-400"></i> <?php echo sanitize($contact['phone'] ?: '-'); ?></p>
                        <p><i class="fas fa-user-tie w-5 text-gray-400"></i> Manager: <?php echo sanitize($contact['assigned_name'] ?: 'Unassigned'); ?></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Deals -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-secondary-900"><i class="fas fa-handshake text-primary-600 mr-2"></i>Your Deals</h3>
                        <span class="bg-primary-100 text-primary-800 text-xs px-2 py-1 rounded-full"><?php echo count($deals); ?></span>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        <?php if (empty($deals)): ?>
                            <p class="p-4 text-sm text-gray-500 text-center">No deals found.</p>
                        <?php else: ?>
                            <?php foreach ($deals as $deal): ?>
                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex items-start justify-between mb-1">
                                        <p class="font-medium text-secondary-900"><?php echo sanitize($deal['title']); ?></p>
                                        <span class="px-2 py-0.5 text-xs rounded-full <?php echo statusColor($deal['stage']); ?>"><?php echo ucwords(str_replace('_', ' ', $deal['stage'])); ?></span>
                                    </div>
                                    <p class="text-sm font-bold text-secondary-900"><?php echo formatCurrency($deal['value']); ?></p>
                                    <p class="text-xs text-gray-500 mt-1">Close: <?php echo $deal['expected_close_date'] ? formatDate($deal['expected_close_date']) : '-'; ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tasks -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up animate-delay-100">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-secondary-900"><i class="fas fa-tasks text-primary-600 mr-2"></i>Your Tasks</h3>
                        <span class="bg-primary-100 text-primary-800 text-xs px-2 py-1 rounded-full"><?php echo count($tasks); ?></span>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        <?php if (empty($tasks)): ?>
                            <p class="p-4 text-sm text-gray-500 text-center">No tasks found.</p>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex items-start justify-between mb-1">
                                        <p class="font-medium text-secondary-900"><?php echo sanitize($task['title']); ?></p>
                                        <span class="px-2 py-0.5 text-xs rounded-full <?php echo statusColor($task['status']); ?>"><?php echo ucwords(str_replace('_', ' ', $task['status'])); ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500">Due: <?php echo $task['due_date'] ? formatDate($task['due_date']) : '-'; ?> • Priority: <?php echo ucfirst($task['priority']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attachments -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up animate-delay-200">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-secondary-900"><i class="fas fa-paperclip text-primary-600 mr-2"></i>Shared Files</h3>
                        <span class="bg-primary-100 text-primary-800 text-xs px-2 py-1 rounded-full"><?php echo count($attachments); ?></span>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        <?php if (empty($attachments)): ?>
                            <p class="p-4 text-sm text-gray-500 text-center">No shared files.</p>
                        <?php else: ?>
                            <?php foreach ($attachments as $att): ?>
                                <div class="p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <i class="fas fa-file text-gray-400"></i>
                                            <div>
                                                <p class="text-sm font-medium text-secondary-900 truncate"><?php echo sanitize($att['original_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo formatBytes($att['file_size']); ?></p>
                                            </div>
                                        </div>
                                        <a href="<?php echo sanitize($att['file_path']); ?>" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-download text-xs"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>