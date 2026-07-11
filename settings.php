<?php
$pageTitle = 'Settings';
require_once 'includes/header.php';
requirePermission(['settings', 'users', 'roles']);

$db = getDB();
$error = '';
$success = '';
$section = $_GET['section'] ?? '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $settings = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'company_email' => trim($_POST['company_email'] ?? ''),
            'company_address' => trim($_POST['company_address'] ?? ''),
            'company_phone' => trim($_POST['company_phone'] ?? ''),
            'company_gstin' => trim($_POST['company_gstin'] ?? ''),
            'currency' => trim($_POST['currency'] ?? '₹'),
            'currency_code' => trim($_POST['currency_code'] ?? 'INR'),
            'currency_conversion_rate' => trim($_POST['currency_conversion_rate'] ?? '1'),
            'timezone' => trim($_POST['timezone'] ?? 'UTC'),
            'date_format' => trim($_POST['date_format'] ?? 'Y-m-d'),
            'invoice_prefix' => trim($_POST['invoice_prefix'] ?? 'INV-'),
            'invoice_next_number' => trim($_POST['invoice_next_number'] ?? '1001'),
            'invoice_tax_rate' => trim($_POST['invoice_tax_rate'] ?? '0'),
            'invoice_gst_rate' => trim($_POST['invoice_gst_rate'] ?? '18'),
            'invoice_terms' => trim($_POST['invoice_terms'] ?? ''),
        ];
        try {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            logActivity('update', 'Updated system settings');
            redirect('settings.php?section=general', 'Settings updated successfully.', 'success');
        } catch (Exception $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim($_POST['user_name'] ?? '');
        $email = trim($_POST['user_email'] ?? '');
        $password = $_POST['user_password'] ?? '';
        $role = $_POST['user_role'] ?? 'sales_rep';
        $phone = trim($_POST['user_phone'] ?? '');
        
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill all required fields.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hash, $role, $phone]);
                logActivity('create', "Created user: $name", 'user', $db->lastInsertId());
                redirect('settings.php?section=users', 'User created successfully.', 'success');
            } catch (Exception $e) {
                $error = 'Error creating user: ' . $e->getMessage();
            }
        }
    }
}

// Handle user status toggle
if (isset($_GET['toggle_user']) && is_numeric($_GET['toggle_user'])) {
    try {
        $stmt = $db->prepare("UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = ? AND id != ?");
        $stmt->execute([$_GET['toggle_user'], $_SESSION['user_id']]);
        logActivity('update', "Toggled user status #" . $_GET['toggle_user'], 'user', $_GET['toggle_user']);
        redirect('settings.php?section=users', 'User status updated.', 'success');
    } catch (Exception $e) {
        $error = 'Error updating user: ' . $e->getMessage();
    }
}

// Handle role creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $roleName = trim($_POST['role_name'] ?? '');
        $displayName = trim($_POST['role_display_name'] ?? '');
        $description = trim($_POST['role_description'] ?? '');
        $permissions = $_POST['role_permissions'] ?? [];
        $roleId = $_POST['role_id'] ?? '';

        if (empty($roleName) || empty($displayName)) {
            $error = 'Role name and display name are required.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $roleName)) {
            $error = 'Role name can only contain lowercase letters, numbers and underscores.';
        } else {
            try {
                $permsJson = json_encode(array_values($permissions));
                if ($roleId) {
                    // Update existing role
                    $check = $db->prepare("SELECT is_system FROM roles WHERE id = ?");
                    $check->execute([$roleId]);
                    $roleInfo = $check->fetch();
                    if ($roleInfo && $roleInfo['is_system']) {
                        $error = 'System roles cannot be edited.';
                    } else {
                        $stmt = $db->prepare("UPDATE roles SET display_name = ?, description = ?, permissions = ? WHERE id = ? AND is_system = 0");
                        $stmt->execute([$displayName, $description, $permsJson, $roleId]);
                        logActivity('update', "Updated role: $displayName", 'role', $roleId);
                        redirect('settings.php?section=roles', 'Role updated successfully.', 'success');
                    }
                } else {
                    // Create new role
                    $stmt = $db->prepare("INSERT INTO roles (name, display_name, description, permissions, is_system) VALUES (?, ?, ?, ?, 0)");
                    $stmt->execute([$roleName, $displayName, $description, $permsJson]);
                    logActivity('create', "Created role: $displayName", 'role', $db->lastInsertId());
                    redirect('settings.php?section=roles', 'Role created successfully.', 'success');
                }
            } catch (Exception $e) {
                $error = 'Error saving role: ' . $e->getMessage();
            }
        }
    }
}

// Handle role deletion
if (isset($_GET['delete_role']) && is_numeric($_GET['delete_role'])) {
    try {
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ? AND is_system = 0");
        $stmt->execute([$_GET['delete_role']]);
        if ($stmt->rowCount() > 0) {
            logActivity('delete', "Deleted role #" . $_GET['delete_role'], 'role', $_GET['delete_role']);
            redirect('settings.php?section=roles', 'Role deleted successfully.', 'success');
        } else {
            $error = 'Role not found or cannot delete system role.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting role: ' . $e->getMessage();
    }
}

$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$roles = $db->query("SELECT * FROM roles ORDER BY is_system DESC, display_name ASC")->fetchAll();
$roleMap = [];
foreach ($roles as $role) {
    $roleMap[$role['name']] = $role;
}
$allPermissions = $GLOBALS['ALL_PERMISSIONS'];

// Get role for editing
$editRole = null;
if (isset($_GET['edit_role']) && is_numeric($_GET['edit_role'])) {
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$_GET['edit_role']]);
    $editRole = $stmt->fetch();
}

$validSections = ['general', 'users', 'roles'];
if (!in_array($section, $validSections)) {
    $section = '';
}

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="mb-8 animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-secondary-900">Settings</h1>
                    <p class="text-gray-500 mt-1">Configure your CRM system and users</p>
                </div>
                <?php if ($section): ?>
                <a href="settings.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                    <i class="fas fa-arrow-left text-sm"></i>
                    <span>Back to Settings</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
        <?php endif; ?>

        <?php if (!$section): ?>
        <!-- Settings Menu -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="settings.php?section=general" class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:border-primary-200 transition-all animate-slide-up">
                <div class="w-14 h-14 rounded-xl bg-primary-50 flex items-center justify-center mb-4 group-hover:bg-primary-100 transition-colors">
                    <i class="fas fa-cog text-primary-600 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-secondary-900 mb-2">General Settings</h2>
                <p class="text-gray-500 text-sm">Company info, currency, timezone, date format and system preferences.</p>
            </a>

            <a href="settings.php?section=users" class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:border-primary-200 transition-all animate-slide-up animate-delay-100">
                <div class="w-14 h-14 rounded-xl bg-blue-50 flex items-center justify-center mb-4 group-hover:bg-blue-100 transition-colors">
                    <i class="fas fa-users text-blue-600 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-secondary-900 mb-2">Users Management</h2>
                <p class="text-gray-500 text-sm">Create new users, assign roles and activate or deactivate accounts.</p>
            </a>

            <a href="settings.php?section=roles" class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:border-primary-200 transition-all animate-slide-up animate-delay-200">
                <div class="w-14 h-14 rounded-xl bg-purple-50 flex items-center justify-center mb-4 group-hover:bg-purple-100 transition-colors">
                    <i class="fas fa-user-shield text-purple-600 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-secondary-900 mb-2">Roles & Permissions</h2>
                <p class="text-gray-500 text-sm">Manage custom roles and control access permissions for each role.</p>
            </a>
        </div>

        <?php elseif ($section === 'general'): ?>
        <!-- General Settings -->
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                <h2 class="text-xl font-bold text-secondary-900 mb-6 flex items-center gap-2"><i class="fas fa-cog text-primary-600"></i> General Settings</h2>
                <form method="POST" action="settings.php?section=general" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="update_settings" value="1">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                        <input type="text" name="company_name" value="<?php echo sanitize($settings['company_name'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Email</label>
                        <input type="email" name="company_email" value="<?php echo sanitize($settings['company_email'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Phone</label>
                        <input type="text" name="company_phone" value="<?php echo sanitize($settings['company_phone'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Address</label>
                        <textarea name="company_address" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($settings['company_address'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company GSTIN</label>
                        <input type="text" name="company_gstin" value="<?php echo sanitize($settings['company_gstin'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency Symbol</label>
                        <input type="text" name="currency" value="<?php echo sanitize($settings['currency'] ?? '₹'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency Code</label>
                        <input type="text" name="currency_code" value="<?php echo sanitize($settings['currency_code'] ?? 'INR'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all" placeholder="INR">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Conversion Rate</label>
                        <input type="number" step="0.0001" name="currency_conversion_rate" value="<?php echo sanitize($settings['currency_conversion_rate'] ?? '1'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <p class="text-xs text-gray-500 mt-1">Multiplier applied to all amounts (1 = no conversion)</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                        <select name="timezone" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                            <option value="America/Chicago" <?php echo ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>America/Chicago</option>
                            <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>America/Los_Angeles</option>
                            <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                            <option value="Asia/Kolkata" <?php echo ($settings['timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata</option>
                            <option value="Asia/Tokyo" <?php echo ($settings['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Asia/Tokyo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Format</label>
                        <select name="date_format" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                            <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                            <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                            <option value="F j, Y" <?php echo ($settings['date_format'] ?? '') === 'F j, Y' ? 'selected' : ''; ?>>Month DD, YYYY</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 pt-4 border-t border-gray-100">
                        <h3 class="text-lg font-bold text-secondary-900 mb-4 flex items-center gap-2"><i class="fas fa-file-invoice-dollar text-primary-600"></i> Invoice Settings</h3>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" value="<?php echo sanitize($settings['invoice_prefix'] ?? 'INV-'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Next Invoice Number</label>
                        <input type="number" name="invoice_next_number" value="<?php echo sanitize($settings['invoice_next_number'] ?? '1001'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Tax Rate (%)</label>
                        <input type="number" step="0.01" name="invoice_tax_rate" value="<?php echo sanitize($settings['invoice_tax_rate'] ?? '0'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Default GST Rate (%)</label>
                        <input type="number" step="0.01" name="invoice_gst_rate" value="<?php echo sanitize($settings['invoice_gst_rate'] ?? '18'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Terms & Conditions</label>
                        <textarea name="invoice_terms" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($settings['invoice_terms'] ?? ''); ?></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($section === 'users'): ?>
        <!-- Users Management -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                <h2 class="text-xl font-bold text-secondary-900 mb-6 flex items-center gap-2"><i class="fas fa-user-plus text-primary-600"></i> Add User</h2>
                <form method="POST" action="settings.php?section=users" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="add_user" value="1">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" name="user_name" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" name="user_email" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                        <input type="password" name="user_password" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select name="user_role" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo sanitize($role['name']); ?>"><?php echo sanitize($role['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input type="tel" name="user_phone" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>

                    <button type="submit" class="btn-primary w-full py-2.5 text-white rounded-lg font-medium">Create User</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up animate-delay-100">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-xl font-bold text-secondary-900 flex items-center gap-2"><i class="fas fa-users text-primary-600"></i> Users Management</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($users as $index => $user): ?>
                            <tr class="hover:bg-gray-50 transition-colors table-row-animate" style="animation-delay: <?php echo $index * 50; ?>ms">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo getInitials($user['name']); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-secondary-900"><?php echo sanitize($user['name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo sanitize($user['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full bg-gray-100 text-gray-800"><?php echo sanitize($roleMap[$user['role']]['display_name'] ?? ucwords(str_replace('_', ' ', $user['role']))); ?></span></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($user['phone'] ?: '-'); ?></td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($user['status']); ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo formatDate($user['created_at']); ?></td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="settings.php?section=users&toggle_user=<?php echo $user['id']; ?>" class="px-3 py-1.5 text-xs rounded-lg <?php echo $user['status'] === 'active' ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100'; ?> transition-colors" data-confirm="Are you sure you want to <?php echo $user['status'] === 'active' ? 'deactivate' : 'activate'; ?> this user?">
                                        <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($section === 'roles'): ?>
        <!-- Roles & Permissions Management -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                <h2 class="text-xl font-bold text-secondary-900 mb-6 flex items-center gap-2"><i class="fas fa-user-shield text-primary-600"></i> <?php echo $editRole ? 'Edit Role' : 'Add Role'; ?></h2>
                <form method="POST" action="settings.php?section=roles" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="save_role" value="1">
                    <?php if ($editRole): ?>
                    <input type="hidden" name="role_id" value="<?php echo $editRole['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role Key *</label>
                        <input type="text" name="role_name" required <?php echo $editRole ? 'readonly' : ''; ?> value="<?php echo sanitize($editRole['name'] ?? ''); ?>" 
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all <?php echo $editRole ? 'bg-gray-100' : ''; ?>"
                               placeholder="e.g., senior_sales">
                        <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, underscores</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Display Name *</label>
                        <input type="text" name="role_display_name" required value="<?php echo sanitize($editRole['display_name'] ?? ''); ?>" 
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                               placeholder="e.g., Senior Sales">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="role_description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($editRole['description'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Permissions</label>
                        <div class="space-y-2 max-h-80 overflow-y-auto p-2 border border-gray-100 rounded-lg">
                            <?php 
                            $editPerms = $editRole ? json_decode($editRole['permissions'], true) : [];
                            foreach ($allPermissions as $permKey => $permLabel): 
                            ?>
                            <label class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="role_permissions[]" value="<?php echo $permKey; ?>" <?php echo in_array($permKey, $editPerms ?? []) ? 'checked' : ''; ?> 
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700"><?php echo sanitize($permLabel); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary flex-1 py-2.5 text-white rounded-lg font-medium"><?php echo $editRole ? 'Update Role' : 'Create Role'; ?></button>
                        <?php if ($editRole): ?>
                        <a href="settings.php?section=roles" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up animate-delay-100">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-xl font-bold text-secondary-900 flex items-center gap-2"><i class="fas fa-user-shield text-primary-600"></i> Roles & Permissions</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Permissions</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($roles as $role): 
                                $rolePerms = json_decode($role['permissions'], true) ?: [];
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-secondary-900"><?php echo sanitize($role['display_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo sanitize($role['name']); ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($role['description'] ?: '-'); ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($rolePerms as $perm): ?>
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-blue-50 text-blue-700"><?php echo sanitize($allPermissions[$perm] ?? $perm); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo $role['is_system'] ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>"><?php echo $role['is_system'] ? 'System' : 'Custom'; ?></span></td>
                                <td class="px-6 py-4 text-right">
                                    <?php if (!$role['is_system']): ?>
                                    <a href="settings.php?section=roles&edit_role=<?php echo $role['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors mr-1"><i class="fas fa-edit"></i></a>
                                    <a href="settings.php?section=roles&delete_role=<?php echo $role['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-confirm="Are you sure you want to delete this role?"><i class="fas fa-trash"></i></a>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">System</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

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
