<?php
$pageTitle = 'Deals';
require_once 'includes/header.php';
requirePermission('deals');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$canAssign = hasPermission('assign_records') || isAdmin();

$action = $_GET['action'] ?? 'pipeline';
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
            'lead_id' => $_POST['lead_id'] ?: null,
            'stage' => $_POST['stage'] ?? 'prospecting',
            'value' => floatval($_POST['value'] ?? 0),
            'probability' => intval($_POST['probability'] ?? 0),
            'expected_close_date' => $_POST['expected_close_date'] ?: null,
            'assigned_to' => $_POST['assigned_to'] ?: $userId,
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        try {
            if (isset($_POST['id']) && $_POST['id']) {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign deals to yourself.';
                } else {
                    $stmt = $db->prepare("UPDATE deals SET title=?, contact_id=?, lead_id=?, stage=?, value=?, probability=?, expected_close_date=?, assigned_to=?, notes=? WHERE id=?");
                    $stmt->execute(array_values(array_merge($data, ['id' => $_POST['id']])));
                    logActivity('update', "Updated deal: {$data['title']}", 'deal', $_POST['id']);
                    redirect('deals.php', 'Deal updated successfully.', 'success');
                }
            } else {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign deals to yourself.';
                } else {
                    $stmt = $db->prepare("INSERT INTO deals (title, contact_id, lead_id, stage, value, probability, expected_close_date, assigned_to, created_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$data['title'], $data['contact_id'], $data['lead_id'], $data['stage'], $data['value'], $data['probability'], $data['expected_close_date'], $data['assigned_to'], $userId, $data['notes']]);
                    $dealId = $db->lastInsertId();
                    logActivity('create', "Created deal: {$data['title']}", 'deal', $dealId);
                    if ((int) $data['assigned_to'] !== (int) $userId) {
                        createNotification($data['assigned_to'], 'New deal assigned', "You were assigned the deal: {$data['title']}", 'info', "deals.php?action=edit&id=$dealId");
                    }
                    redirect('deals.php', 'Deal created successfully.', 'success');
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
            $stmt = $db->prepare("DELETE FROM deals WHERE id=? AND assigned_to=?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM deals WHERE id=?");
            $stmt->execute([$id]);
        }
        if ($stmt->rowCount() > 0) {
            logActivity('delete', "Deleted deal #$id", 'deal', $id);
            redirect('deals.php', 'Deal deleted successfully.', 'success');
        } else {
            $error = 'Deal not found or access denied.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting deal: ' . $e->getMessage();
    }
}

// Get deal for edit
$editDeal = null;
$dealAttachments = [];
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM deals WHERE id=? " . ($viewAll ? '' : 'AND assigned_to=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $editDeal = $stmt->fetch();
    if (!$editDeal) {
        $error = 'Deal not found.';
        $action = 'pipeline';
    } else {
        $stmt = $db->prepare("SELECT * FROM media WHERE related_to = 'deal' AND related_id = ? AND file_type = 'attachment' ORDER BY created_at DESC");
        $stmt->execute([$editDeal['id']]);
        $dealAttachments = $stmt->fetchAll();
    }
}

// Get filter params
$filterStage = $_GET['stage'] ?? '';
$filterAssigned = $_GET['assigned_to'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Build where clause
$conditions = [];
if (!$viewAll) {
    $conditions[] = "d.assigned_to = $userId";
}
if ($filterStage) {
    $conditions[] = "d.stage = " . $db->quote($filterStage);
}
if ($filterAssigned && is_numeric($filterAssigned)) {
    $conditions[] = "d.assigned_to = " . intval($filterAssigned);
}
if ($filterDateFrom) {
    $conditions[] = "DATE(d.created_at) >= " . $db->quote($filterDateFrom);
}
if ($filterDateTo) {
    $conditions[] = "DATE(d.created_at) <= " . $db->quote($filterDateTo);
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$deals = $db->query("SELECT d.*, c.first_name, c.last_name, c.company, u.name as assigned_name, l.title as lead_title 
                     FROM deals d 
                     LEFT JOIN contacts c ON d.contact_id = c.id 
                     LEFT JOIN users u ON d.assigned_to = u.id 
                     LEFT JOIN leads l ON d.lead_id = l.id
                     $where 
                     ORDER BY d.created_at DESC")->fetchAll();

// Group deals by stage
$stages = [
    'prospecting' => ['label' => 'Prospecting', 'color' => 'gray'],
    'qualification' => ['label' => 'Qualification', 'color' => 'blue'],
    'proposal' => ['label' => 'Proposal', 'color' => 'yellow'],
    'negotiation' => ['label' => 'Negotiation', 'color' => 'orange'],
    'closed_won' => ['label' => 'Closed Won', 'color' => 'green'],
    'closed_lost' => ['label' => 'Closed Lost', 'color' => 'red'],
];
$dealsByStage = [];
foreach ($stages as $key => $info) {
    $dealsByStage[$key] = array_filter($deals, fn($d) => $d['stage'] === $key);
}

$contacts = $db->query("SELECT id, first_name, last_name, company FROM contacts ORDER BY first_name, last_name")->fetchAll();
$leads = $db->query("SELECT id, title FROM leads ORDER BY title")->fetchAll();
$users = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Deals</h1>
                <p class="text-gray-500 mt-1">Manage your sales pipeline and opportunities</p>
            </div>
            <div class="mt-4 sm:mt-0 flex gap-2">
                <a href="deals.php?action=list" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors <?php echo $action === 'list' ? 'bg-gray-100' : ''; ?>"><i class="fas fa-list mr-2"></i>List</a>
                <a href="deals.php" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors <?php echo $action === 'pipeline' ? 'bg-gray-100' : ''; ?>"><i class="fas fa-columns mr-2"></i>Pipeline</a>
                <a href="api/export.php?type=deals" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors"><i class="fas fa-download mr-2"></i> CSV</a>
                <a href="deals.php?action=add" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-medium"><i class="fas fa-plus"></i> Add Deal</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $editDeal)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up max-w-4xl">
                <h2 class="text-xl font-bold text-secondary-900 mb-6"><?php echo $action === 'edit' ? 'Edit Deal' : 'Add New Deal'; ?></h2>
                <form method="POST" action="deals.php" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <?php if ($editDeal): ?>
                        <input type="hidden" name="id" value="<?php echo $editDeal['id']; ?>">
                    <?php endif; ?>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Deal Title *</label>
                        <input type="text" name="title" required value="<?php echo sanitize($editDeal['title'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact</label>
                        <select name="contact_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <option value="">-- Select Contact --</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?php echo $contact['id']; ?>" <?php echo ($editDeal['contact_id'] ?? '') == $contact['id'] ? 'selected' : ''; ?>><?php echo sanitize($contact['first_name'] . ' ' . $contact['last_name'] . ' - ' . $contact['company']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Related Lead</label>
                        <select name="lead_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <option value="">-- Select Lead --</option>
                            <?php foreach ($leads as $lead): ?>
                                <option value="<?php echo $lead['id']; ?>" <?php echo ($editDeal['lead_id'] ?? '') == $lead['id'] ? 'selected' : ''; ?>><?php echo sanitize($lead['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Value</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"><?php echo setting('currency', '$'); ?></span>
                            <input type="number" step="0.01" name="value" value="<?php echo $editDeal['value'] ?? '0.00'; ?>" class="w-full pl-8 pr-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Probability (%)</label>
                        <input type="number" min="0" max="100" name="probability" value="<?php echo $editDeal['probability'] ?? '0'; ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stage</label>
                        <select name="stage" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <?php foreach ($stages as $key => $info): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($editDeal['stage'] ?? 'prospecting') === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expected Close Date</label>
                        <input type="date" name="expected_close_date" value="<?php echo $editDeal['expected_close_date'] ?? ''; ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                        <select name="assigned_to" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($editDeal['assigned_to'] ?? $userId) == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" rows="4" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($editDeal['notes'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($editDeal): ?>
                        <div class="md:col-span-2 bg-gray-50 rounded-xl p-6 border border-gray-100">
                            <h3 class="text-lg font-bold text-secondary-900 mb-4 flex items-center gap-2"><i class="fas fa-paperclip text-primary-600"></i> Attachments</h3>
                            <div id="deal-attachments" class="space-y-2 mb-4">
                                <?php if (empty($dealAttachments)): ?>
                                    <p class="text-sm text-gray-500" id="no-attachments-msg">No attachments yet.</p>
                                <?php else: ?>
                                    <?php foreach ($dealAttachments as $att): ?>
                                        <div class="flex items-center justify-between bg-white p-3 rounded-lg border border-gray-200" data-attachment-id="<?php echo $att['id']; ?>">
                                            <div class="flex items-center gap-3">
                                                <i class="fas fa-file text-gray-400"></i>
                                                <div>
                                                    <p class="text-sm font-medium text-secondary-900"><?php echo sanitize($att['original_name']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo formatBytes($att['file_size']); ?> • <?php echo formatDate($att['created_at']); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="<?php echo sanitize($att['file_path']); ?>" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Download"><i class="fas fa-download text-xs"></i></a>
                                                <button type="button" onclick="deleteAttachment(<?php echo $att['id']; ?>, 'deal-attachments')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="file" id="deal-attachment-file" class="text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                                <button type="button" onclick="uploadAttachment('deal', <?php echo $editDeal['id']; ?>, 'deal-attachment-file', 'deal-attachments')" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 text-sm transition-colors">Upload</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <button type="submit" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium"><?php echo $action === 'edit' ? 'Update Deal' : 'Create Deal'; ?></button>
                        <a href="deals.php" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'list'): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up">
                <div class="p-4 border-b border-gray-100 flex flex-col gap-4">
                    <div class="relative w-full sm:w-80">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" data-search=".deal-row" placeholder="Search deals..." class="pl-10 pr-4 py-2 w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>
                    <form method="GET" action="deals.php" class="flex flex-wrap items-end gap-2">
                        <input type="hidden" name="action" value="list">
                        <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Stage
                            <select name="stage" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                                <option value="">All Stages</option>
                                <?php foreach ($stages as $stageKey => $stageInfo): ?>
                                    <option value="<?php echo $stageKey; ?>" <?php echo $filterStage === $stageKey ? 'selected' : ''; ?>><?php echo $stageInfo['label']; ?></option>
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
                        <a href="deals.php?action=list" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm transition-colors">Reset</a>
                        <a href="api/export.php?type=deals<?php echo $filterStage ? '&stage=' . urlencode($filterStage) : ''; ?><?php echo $filterAssigned ? '&assigned_to=' . urlencode($filterAssigned) : ''; ?><?php echo $filterDateFrom ? '&date_from=' . urlencode($filterDateFrom) : ''; ?><?php echo $filterDateTo ? '&date_to=' . urlencode($filterDateTo) : ''; ?>" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm transition-colors"><i class="fas fa-download mr-1"></i> CSV</a>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Deal</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Stage</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Probability</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Close Date</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($deals)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">No deals found. <a href="deals.php?action=add" class="text-primary-600 hover:underline">Create your first deal</a>.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deals as $index => $deal): ?>
                                    <tr class="deal-row searchable-row hover:bg-gray-50 transition-colors table-row-animate" style="animation-delay: <?php echo $index * 50; ?>ms">
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-secondary-900"><?php echo sanitize($deal['title']); ?></p>
                                            <?php if ($deal['lead_title']): ?><p class="text-xs text-gray-500">Lead: <?php echo sanitize($deal['lead_title']); ?></p><?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $deal['first_name'] ? sanitize($deal['first_name'] . ' ' . $deal['last_name']) : '-'; ?></td>
                                        <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($deal['stage']); ?>"><?php echo ucwords(str_replace('_', ' ', $deal['stage'])); ?></span></td>
                                        <td class="px-6 py-4 text-sm font-medium text-secondary-900"><?php echo formatCurrency($deal['value']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $deal['probability']; ?>%</td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $deal['expected_close_date'] ? formatDate($deal['expected_close_date']) : '-'; ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="deals.php?action=edit&id=<?php echo $deal['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-edit"></i></a>
                                                <a href="deals.php?delete=<?php echo $deal['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-confirm="Are you sure you want to delete this deal?"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- Pipeline View -->
            <div class="overflow-x-auto pb-4 animate-slide-up">
                <div class="flex gap-4 min-w-max">
                    <?php foreach ($stages as $stageKey => $stageInfo):
                        $stageDeals = $dealsByStage[$stageKey];
                        $stageValue = array_sum(array_column($stageDeals, 'value'));
                    ?>
                        <div class="w-80 flex-shrink-0">
                            <div class="bg-gray-100 rounded-t-lg p-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-<?php echo $stageInfo['color']; ?>-500"></span>
                                    <h3 class="font-semibold text-sm text-secondary-900"><?php echo $stageInfo['label']; ?></h3>
                                    <span class="bg-white text-gray-600 text-xs px-2 py-0.5 rounded-full"><?php echo count($stageDeals); ?></span>
                                </div>
                                <span class="text-xs font-medium text-gray-600"><?php echo formatCurrency($stageValue); ?></span>
                            </div>
                            <div class="pipeline-column bg-gray-50 rounded-b-lg p-3 space-y-3 border-2 border-dashed border-transparent"
                                data-stage="<?php echo $stageKey; ?>"
                                ondrop="drop(event)"
                                ondragover="allowDrop(event)"
                                ondragleave="dragLeave(event)">
                                <?php foreach ($stageDeals as $deal): ?>
                                    <div class="deal-card bg-white rounded-lg p-4 shadow-sm border border-gray-200" draggable="true" ondragstart="drag(event)" data-deal-id="<?php echo $deal['id']; ?>">
                                        <div class="flex items-start justify-between mb-2">
                                            <h4 class="font-semibold text-sm text-secondary-900"><?php echo sanitize($deal['title']); ?></h4>
                                            <span class="text-xs font-medium text-primary-600"><?php echo $deal['probability']; ?>%</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mb-3"><?php echo sanitize($deal['company'] ?? ($deal['first_name'] . ' ' . $deal['last_name']) ?? 'No Contact'); ?></p>
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-bold text-secondary-900"><?php echo formatCurrency($deal['value']); ?></span>
                                            <div class="flex gap-1">
                                                <a href="deals.php?action=edit&id=<?php echo $deal['id']; ?>" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors"><i class="fas fa-edit text-xs"></i></a>
                                                <a href="deals.php?delete=<?php echo $deal['id']; ?>" class="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors" data-confirm="Delete this deal?"><i class="fas fa-trash text-xs"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<meta name="csrf-token" content="<?php echo csrfToken(); ?>">

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

    async function uploadAttachment(relatedTo, relatedId, fileInputId, containerId) {
        const fileInput = document.getElementById(fileInputId);
        if (!fileInput || !fileInput.files.length) {
            await showAlert('Please select a file to upload.', 'Upload Attachment', 'warning');
            return;
        }
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('related_to', relatedTo);
        formData.append('related_id', relatedId);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('api/attachment.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            } else {
                await showAlert(data.message || 'Upload failed', 'Error', 'danger');
            }
        } catch (error) {
            await showAlert('An error occurred while uploading.', 'Error', 'danger');
        }
    }

    async function deleteAttachment(attachmentId, containerId) {
        const confirmed = await showConfirm('Are you sure you want to delete this attachment?', 'Delete Attachment');
        if (!confirmed) return;

        try {
            const response = await fetch('api/attachment.php?id=' + attachmentId, {
                method: 'DELETE'
            });
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            } else {
                await showAlert(data.message || 'Delete failed', 'Error', 'danger');
            }
        } catch (error) {
            await showAlert('An error occurred while deleting.', 'Error', 'danger');
        }
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }
</script>

<?php include 'includes/footer.php'; ?>