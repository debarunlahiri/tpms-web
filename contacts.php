<?php
$pageTitle = 'Clients';
require_once 'includes/header.php';
requirePermission('contacts');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$canAssign = hasPermission('assign_records') || isAdmin();

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

try {
    foreach (['gstin' => 'VARCHAR(30) DEFAULT NULL', 'state' => 'VARCHAR(100) DEFAULT NULL', 'state_code' => 'VARCHAR(10) DEFAULT NULL', 'shipping_name' => 'VARCHAR(200) DEFAULT NULL', 'shipping_address' => 'TEXT DEFAULT NULL', 'shipping_gstin' => 'VARCHAR(30) DEFAULT NULL', 'shipping_state' => 'VARCHAR(100) DEFAULT NULL', 'shipping_state_code' => 'VARCHAR(10) DEFAULT NULL'] as $column => $definition) {
        $columnCheck = $db->query("SHOW COLUMNS FROM contacts LIKE " . $db->quote($column));
        if (!$columnCheck->fetch()) $db->exec("ALTER TABLE contacts ADD COLUMN `$column` $definition");
    }
} catch (Exception $e) {
    $error = 'Contact migration error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'company' => trim($_POST['company'] ?? ''),
            'job_title' => trim($_POST['job_title'] ?? ''),
            'industry' => trim($_POST['industry'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'gstin' => trim($_POST['gstin'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'state_code' => trim($_POST['state_code'] ?? ''),
            'shipping_name' => trim($_POST['shipping_name'] ?? ''),
            'shipping_address' => trim($_POST['shipping_address'] ?? ''),
            'shipping_gstin' => trim($_POST['shipping_gstin'] ?? ''),
            'shipping_state' => trim($_POST['shipping_state'] ?? ''),
            'shipping_state_code' => trim($_POST['shipping_state_code'] ?? ''),
            'source' => $_POST['source'] ?? 'other',
            'status' => $_POST['status'] ?? 'prospect',
            'assigned_to' => $_POST['assigned_to'] ?: $userId,
            'user_id' => $_POST['user_id'] ?: null,
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        try {
            if (isset($_POST['id']) && $_POST['id']) {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign contacts to yourself.';
                } else {
                    $stmt = $db->prepare("UPDATE contacts SET first_name=?, last_name=?, email=?, phone=?, company=?, job_title=?, industry=?, address=?, city=?, country=?, gstin=?, state=?, state_code=?, shipping_name=?, shipping_address=?, shipping_gstin=?, shipping_state=?, shipping_state_code=?, source=?, status=?, assigned_to=?, user_id=?, notes=? WHERE id=?");
                    $stmt->execute(array_values(array_merge($data, ['id' => $_POST['id']])));
                    logActivity('update', "Updated contact: {$data['first_name']} {$data['last_name']}", 'contact', $_POST['id']);
                    redirect('contacts.php', 'Client updated successfully.', 'success');
                }
            } else {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign contacts to yourself.';
                } else {
                    $stmt = $db->prepare("INSERT INTO contacts (first_name, last_name, email, phone, company, job_title, industry, address, city, country, gstin, state, state_code, shipping_name, shipping_address, shipping_gstin, shipping_state, shipping_state_code, source, status, assigned_to, user_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute(array_values($data));
                    $contactId = $db->lastInsertId();
                    logActivity('create', "Created contact: {$data['first_name']} {$data['last_name']}", 'contact', $contactId);
                    redirect('contacts.php', 'Client created successfully.', 'success');
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
            $stmt = $db->prepare("DELETE FROM contacts WHERE id=? AND assigned_to=?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM contacts WHERE id=?");
            $stmt->execute([$id]);
        }
        if ($stmt->rowCount() > 0) {
            logActivity('delete', "Deleted contact #$id", 'contact', $id);
            redirect('contacts.php', 'Client deleted successfully.', 'success');
        } else {
            $error = 'Client not found or access denied.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting contact: ' . $e->getMessage();
    }
}

// Get contact for edit
$editContact = null;
$contactAttachments = [];
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM contacts WHERE id=? " . ($viewAll ? '' : 'AND assigned_to=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $editContact = $stmt->fetch();
    if (!$editContact) {
        $error = 'Client not found.';
        $action = 'list';
    } else {
        $stmt = $db->prepare("SELECT * FROM media WHERE related_to = 'contact' AND related_id = ? AND file_type = 'attachment' ORDER BY created_at DESC");
        $stmt->execute([$editContact['id']]);
        $contactAttachments = $stmt->fetchAll();
    }
}

// Get filter params
$filterStatus = $_GET['status'] ?? '';
$filterAssigned = $_GET['assigned_to'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Build where clause
$isClient = hasPermission('client_access') && !hasPermission('leads');
$conditions = [];
if ($isClient) {
    $conditions[] = "c.user_id = $userId";
} elseif (!$viewAll) {
    $conditions[] = "c.assigned_to = $userId";
}
if ($filterStatus) {
    $conditions[] = "c.status = " . $db->quote($filterStatus);
}
if ($filterAssigned && is_numeric($filterAssigned)) {
    $conditions[] = "c.assigned_to = " . intval($filterAssigned);
}
if ($filterDateFrom) {
    $conditions[] = "DATE(c.created_at) >= " . $db->quote($filterDateFrom);
}
if ($filterDateTo) {
    $conditions[] = "DATE(c.created_at) <= " . $db->quote($filterDateTo);
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$contacts = $db->query("SELECT c.*, u.name as assigned_name FROM contacts c LEFT JOIN users u ON c.assigned_to = u.id $where ORDER BY c.created_at DESC")->fetchAll();

$users = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();
$clientUsers = $db->query("SELECT u.id, u.name, r.display_name as role_name FROM users u JOIN roles r ON u.role = r.name WHERE u.status='active' AND (r.permissions LIKE '%client_access%' OR r.name='client') ORDER BY u.name")->fetchAll();

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Clients</h1>
                <p class="text-gray-500 mt-1">Manage your customers and prospects</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="contacts.php?action=add" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-medium">
                    <i class="fas fa-plus"></i> Add Client
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $editContact)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                <h2 class="text-xl font-bold text-secondary-900 mb-6"><?php echo $action === 'edit' ? 'Edit Client' : 'Add New Client'; ?></h2>
                <form method="POST" action="contacts.php" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <?php if ($editContact): ?>
                        <input type="hidden" name="id" value="<?php echo $editContact['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                        <input type="text" name="first_name" required value="<?php echo sanitize($editContact['first_name'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                        <input type="text" name="last_name" required value="<?php echo sanitize($editContact['last_name'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" value="<?php echo sanitize($editContact['email'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input type="tel" name="phone" value="<?php echo sanitize($editContact['phone'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company</label>
                        <input type="text" name="company" value="<?php echo sanitize($editContact['company'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Job Title</label>
                        <input type="text" name="job_title" value="<?php echo sanitize($editContact['job_title'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Industry</label>
                        <input type="text" name="industry" value="<?php echo sanitize($editContact['industry'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                        <select name="source" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <?php foreach (['website', 'referral', 'social', 'email', 'call', 'other'] as $source): ?>
                                <option value="<?php echo $source; ?>" <?php echo ($editContact['source'] ?? 'other') === $source ? 'selected' : ''; ?>><?php echo ucfirst($source); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <?php foreach (['active', 'inactive', 'prospect'] as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo ($editContact['status'] ?? 'prospect') === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                        <select name="assigned_to" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($editContact['assigned_to'] ?? $userId) == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Linked Client User</label>
                        <select name="user_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <option value="">-- None --</option>
                            <?php foreach ($clientUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($editContact['user_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name'] . ' (' . $u['role_name'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Link this contact to a client/employee user account</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                        <input type="text" name="city" value="<?php echo sanitize($editContact['city'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                        <input type="text" name="country" value="<?php echo sanitize($editContact['country'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <input type="text" name="address" value="<?php echo sanitize($editContact['address'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div class="md:col-span-2 border-t border-gray-100 pt-5">
                        <h3 class="text-lg font-bold text-secondary-900">GST &amp; Shipping Details</h3>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">GSTIN</label><input type="text" name="gstin" value="<?php echo sanitize($editContact['gstin'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg" placeholder="Customer GSTIN"></div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-2">State</label><input type="text" name="state" value="<?php echo sanitize($editContact['state'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Code</label><input type="text" name="state_code" value="<?php echo sanitize($editContact['state_code'] ?? ''); ?>" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg" placeholder="07"></div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Shipping Name</label><input type="text" name="shipping_name" value="<?php echo sanitize($editContact['shipping_name'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg" placeholder="Defaults to company/contact name"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Shipping GSTIN</label><input type="text" name="shipping_gstin" value="<?php echo sanitize($editContact['shipping_gstin'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg" placeholder="Defaults to billing GSTIN"></div>
                    <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-2">Shipping Address</label><input type="text" name="shipping_address" value="<?php echo sanitize($editContact['shipping_address'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg" placeholder="Defaults to billing address"></div>
                    <div class="grid grid-cols-3 gap-3 md:col-span-2">
                        <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-2">Shipping State</label><input type="text" name="shipping_state" value="<?php echo sanitize($editContact['shipping_state'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Code</label><input type="text" name="shipping_state_code" value="<?php echo sanitize($editContact['shipping_state_code'] ?? ''); ?>" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg" placeholder="07"></div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($editContact['notes'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($editContact): ?>
                        <div class="md:col-span-2 bg-gray-50 rounded-xl p-6 border border-gray-100">
                            <h3 class="text-lg font-bold text-secondary-900 mb-4 flex items-center gap-2"><i class="fas fa-paperclip text-primary-600"></i> Attachments</h3>
                            <div id="contact-attachments" class="space-y-2 mb-4">
                                <?php if (empty($contactAttachments)): ?>
                                    <p class="text-sm text-gray-500" id="no-attachments-msg">No attachments yet.</p>
                                <?php else: ?>
                                    <?php foreach ($contactAttachments as $att): ?>
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
                                                <button type="button" onclick="deleteAttachment(<?php echo $att['id']; ?>, 'contact-attachments')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="file" id="contact-attachment-file" class="text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                                <button type="button" onclick="uploadAttachment('contact', <?php echo $editContact['id']; ?>, 'contact-attachment-file', 'contact-attachments')" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 text-sm transition-colors">Upload</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="md:col-span-2 flex items-center gap-3">
                        <button type="submit" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium"><?php echo $action === 'edit' ? 'Update Client' : 'Create Client'; ?></button>
                        <a href="contacts.php" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Clients Grid -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 animate-slide-up">
                <div class="flex flex-col gap-4 mb-4">
                    <div class="relative w-full sm:w-80">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" data-search=".contact-card" placeholder="Search contacts..."
                            class="pl-10 pr-4 py-2 w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>
                    <form method="GET" action="contacts.php" class="flex flex-wrap items-end gap-2">
                        <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Status
                            <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                                <option value="">All Statuses</option>
                                <?php foreach (['active', 'inactive', 'prospect'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $filterStatus === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php if (!$isClient): ?>
                            <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Assigned To
                                <select name="assigned_to" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo $filterAssigned == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>
                        <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">From Date
                            <input type="date" name="date_from" value="<?php echo sanitize($filterDateFrom); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                        </label>
                        <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">To Date
                            <input type="date" name="date_to" value="<?php echo sanitize($filterDateTo); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                        </label>
                        <button type="submit" class="px-3 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 transition-colors"><i class="fas fa-filter mr-1"></i> Filter</button>
                        <a href="contacts.php" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm transition-colors">Reset</a>
                        <a href="api/export.php?type=contacts<?php echo $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?><?php echo $filterAssigned ? '&assigned_to=' . urlencode($filterAssigned) : ''; ?><?php echo $filterDateFrom ? '&date_from=' . urlencode($filterDateFrom) : ''; ?><?php echo $filterDateTo ? '&date_to=' . urlencode($filterDateTo) : ''; ?>" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm transition-colors"><i class="fas fa-download mr-1"></i> CSV</a>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <?php if (empty($contacts)): ?>
                    <div class="py-12 text-center text-gray-500">No clients found. <a href="contacts.php?action=add" class="text-primary-600 hover:underline">Create your first client</a>.</div>
                    <?php else: ?>
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assigned</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($contacts as $index => $contact): ?>
                                    <tr class="contact-row searchable-row hover:bg-gray-50 transition-colors table-row-animate" data-status="<?php echo $contact['status']; ?>" style="animation-delay: <?php echo $index * 50; ?>ms">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold text-sm">
                                                    <?php echo getInitials($contact['first_name'] . ' ' . $contact['last_name']); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-secondary-900"><?php echo sanitize($contact['first_name'] . ' ' . $contact['last_name']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo sanitize($contact['job_title'] ?: '-'); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($contact['company'] ?: '-'); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $contact['email'] ? sanitize($contact['email']) : '<span class="text-gray-400">-</span>'; ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $contact['phone'] ? sanitize($contact['phone']) : '<span class="text-gray-400">-</span>'; ?></td>
                                        <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($contact['status']); ?>"><?php echo ucfirst($contact['status']); ?></span></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($contact['assigned_name'] ?? 'Unassigned'); ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="contacts.php?action=edit&id=<?php echo $contact['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" data-tooltip="Edit"><i class="fas fa-edit"></i></a>
                                                <a href="contacts.php?delete=<?php echo $contact['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-confirm="Are you sure you want to delete this contact?" data-tooltip="Delete"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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
