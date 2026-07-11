<?php
$pageTitle = 'Leads';
require_once 'includes/header.php';
requirePermission('leads');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$canAssign = hasPermission('assign_records') || isAdmin();

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'contact_id' => $_POST['contact_id'] ?: null,
            'description' => trim($_POST['description'] ?? ''),
            'status' => $_POST['status'] ?? 'new',
            'priority' => $_POST['priority'] ?? 'medium',
            'source' => $_POST['source'] ?? 'other',
            'estimated_value' => floatval($_POST['estimated_value'] ?? 0),
            'assigned_to' => $_POST['assigned_to'] ?: $userId,
        ];

        try {
            if (isset($_POST['id']) && $_POST['id']) {
                // Update
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign leads to yourself.';
                } else {
                    $stmt = $db->prepare("UPDATE leads SET title=?, contact_id=?, description=?, status=?, priority=?, source=?, estimated_value=?, assigned_to=? WHERE id=?");
                    $stmt->execute(array_values(array_merge($data, ['id' => $_POST['id']])));
                    logActivity('update', "Updated lead: {$data['title']}", 'lead', $_POST['id']);
                    redirect('leads.php', 'Lead updated successfully.', 'success');
                }
            } else {
                // Create
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign leads to yourself.';
                } else {
                    $stmt = $db->prepare("INSERT INTO leads (title, contact_id, description, status, priority, source, estimated_value, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$data['title'], $data['contact_id'], $data['description'], $data['status'], $data['priority'], $data['source'], $data['estimated_value'], $data['assigned_to'], $userId]);
                    $leadId = $db->lastInsertId();
                    logActivity('create', "Created lead: {$data['title']}", 'lead', $leadId);
                    if ((int) $data['assigned_to'] !== (int) $userId) {
                        createNotification($data['assigned_to'], 'New lead assigned', "You were assigned the lead: {$data['title']}", 'info', "leads.php?action=edit&id=$leadId");
                    }
                    redirect('leads.php', 'Lead created successfully.', 'success');
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        if (!$viewAll) {
            $stmt = $db->prepare("DELETE FROM leads WHERE id=? AND assigned_to=?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM leads WHERE id=?");
            $stmt->execute([$id]);
        }
        if ($stmt->rowCount() > 0) {
            logActivity('delete', "Deleted lead #$id", 'lead', $id);
            redirect('leads.php', 'Lead deleted successfully.', 'success');
        } else {
            $error = 'Lead not found or access denied.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting lead: ' . $e->getMessage();
    }
}

// Get lead for edit
$editLead = null;
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM leads WHERE id=? " . ($viewAll ? '' : 'AND assigned_to=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $editLead = $stmt->fetch();
    if (!$editLead) {
        $error = 'Lead not found.';
        $action = 'list';
    }
}

// Get filter params
$filterStatus = $_GET['status'] ?? '';
$filterAssigned = $_GET['assigned_to'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Build where clause
$conditions = [];
if (!$viewAll) {
    $conditions[] = "l.assigned_to = $userId";
}
if ($filterStatus) {
    $conditions[] = "l.status = " . $db->quote($filterStatus);
}
if ($filterAssigned && is_numeric($filterAssigned)) {
    $conditions[] = "l.assigned_to = " . intval($filterAssigned);
}
if ($filterDateFrom) {
    $conditions[] = "DATE(l.created_at) >= " . $db->quote($filterDateFrom);
}
if ($filterDateTo) {
    $conditions[] = "DATE(l.created_at) <= " . $db->quote($filterDateTo);
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$leads = $db->query("SELECT l.*, c.first_name, c.last_name, c.company, u.name as assigned_name 
                     FROM leads l 
                     LEFT JOIN contacts c ON l.contact_id = c.id 
                     LEFT JOIN users u ON l.assigned_to = u.id 
                     $where 
                     ORDER BY l.created_at DESC")->fetchAll();

// Get contacts and users for forms
$contacts = $db->query("SELECT id, first_name, last_name, company FROM contacts ORDER BY first_name, last_name")->fetchAll();
$users = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Leads</h1>
                <p class="text-gray-500 mt-1">Manage and track your sales leads</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="leads.php?action=add" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-medium">
                    <i class="fas fa-plus"></i> Add Lead
                </a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in">
            <i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $editLead)): ?>
        <!-- Lead Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
            <h2 class="text-xl font-bold text-secondary-900 mb-6"><?php echo $action === 'edit' ? 'Edit Lead' : 'Add New Lead'; ?></h2>
            <form method="POST" action="leads.php" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <?php if ($editLead): ?>
                <input type="hidden" name="id" value="<?php echo $editLead['id']; ?>">
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lead Title *</label>
                    <input type="text" name="title" required value="<?php echo sanitize($editLead['title'] ?? ''); ?>"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                           placeholder="e.g., Enterprise Software Deal">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact</label>
                    <select name="contact_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <option value="">-- Select Contact --</option>
                        <?php foreach ($contacts as $contact): ?>
                        <option value="<?php echo $contact['id']; ?>" <?php echo ($editLead['contact_id'] ?? '') == $contact['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($contact['first_name'] . ' ' . $contact['last_name'] . ' - ' . $contact['company']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Value</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"><?php echo setting('currency', '$'); ?></span>
                        <input type="number" step="0.01" name="estimated_value" value="<?php echo $editLead['estimated_value'] ?? '0.00'; ?>"
                               class="w-full pl-8 pr-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach (['new','contacted','qualified','proposal','negotiation','won','lost'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo ($editLead['status'] ?? 'new') === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select name="priority" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach (['low','medium','high'] as $priority): ?>
                        <option value="<?php echo $priority; ?>" <?php echo ($editLead['priority'] ?? 'medium') === $priority ? 'selected' : ''; ?>><?php echo ucfirst($priority); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                    <select name="source" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach (['website','referral','social','email','call','other'] as $source): ?>
                        <option value="<?php echo $source; ?>" <?php echo ($editLead['source'] ?? 'other') === $source ? 'selected' : ''; ?>><?php echo ucfirst($source); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                    <select name="assigned_to" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($editLead['assigned_to'] ?? $userId) == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="4" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                              placeholder="Add notes about this lead..."><?php echo sanitize($editLead['description'] ?? ''); ?></textarea>
                </div>

                <div class="md:col-span-2 flex items-center gap-3">
                    <button type="submit" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium">
                        <?php echo $action === 'edit' ? 'Update Lead' : 'Create Lead'; ?>
                    </button>
                    <a href="leads.php" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- Leads List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up">
            <div class="p-4 border-b border-gray-100 flex flex-col gap-4">
                <div class="relative w-full sm:w-80">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" data-search=".lead-row" placeholder="Search leads..." 
                           class="pl-10 pr-4 py-2 w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>
                <form method="GET" action="leads.php" class="flex flex-wrap items-end gap-2">
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Status
                    <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                        <option value="">All Statuses</option>
                        <?php foreach (['new','contacted','qualified','proposal','negotiation','won','lost'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo $filterStatus === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Assigned To
                    <select name="assigned_to" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filterAssigned == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">From Date
                    <input type="date" name="date_from" value="<?php echo sanitize($filterDateFrom); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">To Date
                    <input type="date" name="date_to" value="<?php echo sanitize($filterDateTo); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                    </label>
                    <button type="submit" class="px-3 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 transition-colors"><i class="fas fa-filter mr-1"></i> Filter</button>
                    <a href="leads.php" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm transition-colors">Reset</a>
                    <a href="api/export.php?type=leads<?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?><?php echo $filterAssigned ? '&assigned_to=' . urlencode($filterAssigned) : ''; ?><?php echo $filterDateFrom ? '&date_from=' . urlencode($filterDateFrom) : ''; ?><?php echo $filterDateTo ? '&date_to=' . urlencode($filterDateTo) : ''; ?>" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm transition-colors"><i class="fas fa-download mr-1"></i> CSV</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Value</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assigned</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($leads)): ?>
                            <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No leads found. <a href="leads.php?action=add" class="text-primary-600 hover:underline">Create your first lead</a>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($leads as $index => $lead): ?>
                            <tr class="lead-row searchable-row hover:bg-gray-50 transition-colors table-row-animate" data-status="<?php echo $lead['status']; ?>" style="animation-delay: <?php echo $index * 50; ?>ms">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-secondary-900"><?php echo sanitize($lead['title']); ?></p>
                                    <p class="text-xs text-gray-500 capitalize"><i class="fas fa-<?php echo $lead['source'] === 'email' ? 'envelope' : ($lead['source'] === 'call' ? 'phone' : 'globe'); ?> mr-1"></i><?php echo $lead['source']; ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo $lead['first_name'] ? sanitize($lead['first_name'] . ' ' . $lead['last_name']) : '<span class="text-gray-400">-</span>'; ?></td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($lead['status']); ?>"><?php echo ucfirst($lead['status']); ?></span></td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($lead['priority']); ?>"><?php echo ucfirst($lead['priority']); ?></span></td>
                                <td class="px-6 py-4 text-sm font-medium text-secondary-900"><?php echo formatCurrency($lead['estimated_value']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($lead['assigned_name'] ?? 'Unassigned'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo formatDate($lead['created_at']); ?></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="leads.php?action=edit&id=<?php echo $lead['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" data-tooltip="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="leads.php?delete=<?php echo $lead['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-confirm="Are you sure you want to delete this lead?" data-tooltip="Delete"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
function filterByStatus(status, rowSelector, dataAttr) {
    document.querySelectorAll('.' + rowSelector).forEach(row => {
        if (!status || row.dataset[dataAttr] === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<script>
// Prevent duplicate form submissions by disabling submit buttons on click
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
