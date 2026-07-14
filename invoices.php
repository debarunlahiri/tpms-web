<?php
$pageTitle = 'Invoices';
require_once 'includes/header.php';
requirePermission('invoices');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Auto-migrate invoice tables if they don't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(100) NOT NULL UNIQUE,
        contact_id INT DEFAULT NULL,
        deal_id INT DEFAULT NULL,
        issue_date DATE NOT NULL,
        due_date DATE DEFAULT NULL,
        status ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
        reverse_charge CHAR(1) DEFAULT 'N',
        billing_gstin VARCHAR(30) DEFAULT NULL,
        supply_state VARCHAR(100) DEFAULT NULL,
        state_code VARCHAR(10) DEFAULT NULL,
        place_of_supply VARCHAR(150) DEFAULT NULL,
        shipping_name VARCHAR(200) DEFAULT NULL,
        shipping_address TEXT DEFAULT NULL,
        shipping_gstin VARCHAR(30) DEFAULT NULL,
        shipping_state VARCHAR(100) DEFAULT NULL,
        shipping_state_code VARCHAR(10) DEFAULT NULL,
        subtotal DECIMAL(15,2) DEFAULT 0,
        tax_rate DECIMAL(5,2) DEFAULT 0,
        tax_amount DECIMAL(15,2) DEFAULT 0,
        gst_rate DECIMAL(5,2) DEFAULT 0,
        gst_amount DECIMAL(15,2) DEFAULT 0,
        igst_rate DECIMAL(5,2) DEFAULT 0,
        igst_amount DECIMAL(15,2) DEFAULT 0,
        cgst_rate DECIMAL(5,2) DEFAULT 0,
        cgst_amount DECIMAL(15,2) DEFAULT 0,
        sgst_rate DECIMAL(5,2) DEFAULT 0,
        sgst_amount DECIMAL(15,2) DEFAULT 0,
        discount DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(15,2) DEFAULT 0,
        notes TEXT DEFAULT NULL,
        terms TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
        FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach (['igst_rate' => 'DECIMAL(5,2) DEFAULT 0', 'igst_amount' => 'DECIMAL(15,2) DEFAULT 0', 'cgst_rate' => 'DECIMAL(5,2) DEFAULT 0', 'cgst_amount' => 'DECIMAL(15,2) DEFAULT 0', 'sgst_rate' => 'DECIMAL(5,2) DEFAULT 0', 'sgst_amount' => 'DECIMAL(15,2) DEFAULT 0'] as $column => $definition) {
        $columnCheck = $db->query("SHOW COLUMNS FROM invoices LIKE " . $db->quote($column));
        if (!$columnCheck->fetch()) $db->exec("ALTER TABLE invoices ADD COLUMN `$column` $definition");
    }
    foreach (['reverse_charge' => "CHAR(1) DEFAULT 'N'", 'billing_gstin' => 'VARCHAR(30) DEFAULT NULL', 'supply_state' => 'VARCHAR(100) DEFAULT NULL', 'state_code' => 'VARCHAR(10) DEFAULT NULL', 'place_of_supply' => 'VARCHAR(150) DEFAULT NULL', 'shipping_name' => 'VARCHAR(200) DEFAULT NULL', 'shipping_address' => 'TEXT DEFAULT NULL', 'shipping_gstin' => 'VARCHAR(30) DEFAULT NULL', 'shipping_state' => 'VARCHAR(100) DEFAULT NULL', 'shipping_state_code' => 'VARCHAR(10) DEFAULT NULL'] as $column => $definition) {
        $columnCheck = $db->query("SHOW COLUMNS FROM invoices LIKE " . $db->quote($column));
        if (!$columnCheck->fetch()) $db->exec("ALTER TABLE invoices ADD COLUMN `$column` $definition");
    }
    $db->exec("UPDATE invoices SET cgst_rate = gst_rate / 2, sgst_rate = gst_rate / 2, cgst_amount = gst_amount / 2, sgst_amount = gst_amount / 2 WHERE gst_amount > 0 AND igst_amount = 0 AND cgst_amount = 0 AND sgst_amount = 0");
    foreach (['gstin' => 'VARCHAR(30) DEFAULT NULL', 'state' => 'VARCHAR(100) DEFAULT NULL', 'state_code' => 'VARCHAR(10) DEFAULT NULL', 'shipping_name' => 'VARCHAR(200) DEFAULT NULL', 'shipping_address' => 'TEXT DEFAULT NULL', 'shipping_gstin' => 'VARCHAR(30) DEFAULT NULL', 'shipping_state' => 'VARCHAR(100) DEFAULT NULL', 'shipping_state_code' => 'VARCHAR(10) DEFAULT NULL'] as $column => $definition) {
        $columnCheck = $db->query("SHOW COLUMNS FROM contacts LIKE " . $db->quote($column));
        if (!$columnCheck->fetch()) $db->exec("ALTER TABLE contacts ADD COLUMN `$column` $definition");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        description TEXT NOT NULL,
        sac_code VARCHAR(50) DEFAULT NULL,
        other_charge DECIMAL(15,2) DEFAULT 0,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(15,2) DEFAULT 0,
        amount DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $sacColumnCheck = $db->query("SHOW COLUMNS FROM invoice_items LIKE 'sac_code'");
    if (!$sacColumnCheck->fetch()) $db->exec("ALTER TABLE invoice_items ADD COLUMN sac_code VARCHAR(50) DEFAULT NULL AFTER description");
    $chargeColumnCheck = $db->query("SHOW COLUMNS FROM invoice_items LIKE 'other_charge'");
    if (!$chargeColumnCheck->fetch()) $db->exec("ALTER TABLE invoice_items ADD COLUMN other_charge DECIMAL(15,2) DEFAULT 0 AFTER amount");

    // Ensure invoice settings exist
    $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES
        ('company_address', ''),
        ('company_phone', ''),
        ('company_gstin', ''),
        ('bank_name_branch', 'Canara Bank, Patparganj'),
        ('bank_account_name', 'Techpro IT Solutions'),
        ('bank_account_number', '2756201000509'),
        ('bank_ifsc', 'CNRB0002756'),
        ('invoice_prefix', 'INV-'),
        ('invoice_next_number', '1001'),
        ('invoice_tax_rate', '0'),
        ('invoice_gst_rate', '18'),
        ('invoice_terms', '')
        ON DUPLICATE KEY UPDATE setting_value = setting_value");
    $companyDefaults = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '' OR setting_value = ?, VALUES(setting_value), setting_value)");
    foreach ([['company_name', 'TECHPRO IT SOLUTIONS', 'TPMS'], ['company_email', 'info@techproitsolutions.in', 'info@tpms.com'], ['company_address', '220 PLOT-8, AGGARWAL TOWER, LSC-11, MANDAWALI FAZALPUR, NEW DELHI-92, INDIA', ''], ['company_gstin', '07AIAPB4587B1ZP', '']] as [$key, $value, $legacyValue]) {
        $companyDefaults->execute([$key, $value, $legacyValue]);
    }
} catch (Exception $e) {
    $error = 'Migration error: ' . $e->getMessage();
}

function generateInvoiceNumber($db) {
    $prefix = setting('invoice_prefix', 'INV-');
    $next = intval(setting('invoice_next_number', '1001'));
    $number = $prefix . $next;
    // Ensure unique
    $check = $db->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
    $check->execute([$number]);
    while ($check->fetchColumn() > 0) {
        $next++;
        $number = $prefix . $next;
        $check->execute([$number]);
    }
    // Update next number
    $upd = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('invoice_next_number', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $upd->execute([strval($next + 1)]);
    return $number;
}

function calculateInvoiceTotals($items, $igstRate, $cgstRate, $sgstRate, $discount) {
    $subtotal = 0;
    foreach ($items as $item) {
        $qty = floatval($item['quantity'] ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $amount = $qty * $price;
        $subtotal += $amount + floatval($item['other_charge'] ?? 0);
    }
    $afterDiscount = max(0, $subtotal - floatval($discount));
    $igstAmount = $afterDiscount * (floatval($igstRate) / 100);
    $cgstAmount = $afterDiscount * (floatval($cgstRate) / 100);
    $sgstAmount = $afterDiscount * (floatval($sgstRate) / 100);
    $total = $afterDiscount + $igstAmount + $cgstAmount + $sgstAmount;
    return [
        'subtotal' => round($subtotal, 2),
        'igst_amount' => round($igstAmount, 2),
        'cgst_amount' => round($cgstAmount, 2),
        'sgst_amount' => round($sgstAmount, 2),
        'total' => round($total, 2),
    ];
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        if (!$viewAll) {
            $stmt = $db->prepare("DELETE FROM invoices WHERE id=? AND created_by=?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM invoices WHERE id=?");
            $stmt->execute([$id]);
        }
        if ($stmt->rowCount() > 0) {
            logActivity('delete', "Deleted invoice #$id", 'invoice', $id);
            redirect('invoices.php', 'Invoice deleted successfully.', 'success');
        } else {
            $error = 'Invoice not found or access denied.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting invoice: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $invoiceId = $_POST['invoice_id'] ?? '';
        $contactId = $_POST['contact_id'] ?: null;
        $dealId = $_POST['deal_id'] ?: null;
        $issueDate = $_POST['issue_date'];
        $dueDate = $_POST['due_date'] ?: null;
        $status = $_POST['status'] ?? 'draft';
        $reverseCharge = ($_POST['reverse_charge'] ?? 'N') === 'Y' ? 'Y' : 'N';
        $billingGstin = trim($_POST['billing_gstin'] ?? '');
        $supplyState = trim($_POST['supply_state'] ?? '');
        $stateCode = trim($_POST['state_code'] ?? '');
        $placeOfSupply = trim($_POST['place_of_supply'] ?? '');
        $shippingName = trim($_POST['shipping_name'] ?? '');
        $shippingAddress = trim($_POST['shipping_address'] ?? '');
        $shippingGstin = trim($_POST['shipping_gstin'] ?? '');
        $shippingState = trim($_POST['shipping_state'] ?? '');
        $shippingStateCode = trim($_POST['shipping_state_code'] ?? '');
        $igstRate = floatval($_POST['igst_rate'] ?? 0);
        $cgstRate = floatval($_POST['cgst_rate'] ?? 0);
        $sgstRate = floatval($_POST['sgst_rate'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $terms = trim($_POST['terms'] ?? '');
        $itemDescriptions = $_POST['item_description'] ?? [];
        $itemSacCodes = $_POST['item_sac_code'] ?? [];
        $itemOtherCharges = $_POST['item_other_charge'] ?? [];
        $itemQuantities = $_POST['item_quantity'] ?? [];
        $itemPrices = $_POST['item_unit_price'] ?? [];

        $items = [];
        for ($i = 0; $i < count($itemDescriptions); $i++) {
            $desc = trim($itemDescriptions[$i]);
            if ($desc === '') continue;
            $items[] = [
                'description' => $desc,
                'sac_code' => trim($itemSacCodes[$i] ?? ''),
                'other_charge' => floatval($itemOtherCharges[$i] ?? 0),
                'quantity' => floatval($itemQuantities[$i] ?? 1),
                'unit_price' => floatval($itemPrices[$i] ?? 0),
            ];
        }

        if (empty($contactId)) {
            $error = 'Please select a contact.';
        } elseif (empty($items)) {
            $error = 'Please add at least one invoice item.';
        } elseif (empty($issueDate)) {
            $error = 'Issue date is required.';
        } else {
            $totals = calculateInvoiceTotals($items, $igstRate, $cgstRate, $sgstRate, $discount);

            try {
                if ($invoiceId) {
                    // Update
                    $stmt = $db->prepare("UPDATE invoices SET contact_id=?, deal_id=?, issue_date=?, due_date=?, status=?, subtotal=?, igst_rate=?, igst_amount=?, cgst_rate=?, cgst_amount=?, sgst_rate=?, sgst_amount=?, tax_rate=0, tax_amount=0, gst_rate=?, gst_amount=?, discount=?, total=?, notes=?, terms=? WHERE id=?");
                    $combinedGstRate = $cgstRate + $sgstRate;
                    $combinedGstAmount = $totals['cgst_amount'] + $totals['sgst_amount'];
                    $stmt->execute([$contactId, $dealId, $issueDate, $dueDate, $status, $totals['subtotal'], $igstRate, $totals['igst_amount'], $cgstRate, $totals['cgst_amount'], $sgstRate, $totals['sgst_amount'], $combinedGstRate, $combinedGstAmount, $discount, $totals['total'], $notes, $terms, $invoiceId]);
                    $extraStmt = $db->prepare("UPDATE invoices SET reverse_charge=?, billing_gstin=?, supply_state=?, state_code=?, place_of_supply=?, shipping_name=?, shipping_address=?, shipping_gstin=?, shipping_state=?, shipping_state_code=? WHERE id=?");
                    $extraStmt->execute([$reverseCharge, $billingGstin, $supplyState, $stateCode, $placeOfSupply, $shippingName, $shippingAddress, $shippingGstin, $shippingState, $shippingStateCode, $invoiceId]);

                    // Delete old items and insert new
                    $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoiceId]);
                    $itemStmt = $db->prepare("INSERT INTO invoice_items (invoice_id, description, sac_code, quantity, unit_price, amount, other_charge) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($items as $item) {
                        $amount = $item['quantity'] * $item['unit_price'];
                        $itemStmt->execute([$invoiceId, $item['description'], $item['sac_code'], $item['quantity'], $item['unit_price'], $amount, $item['other_charge']]);
                    }
                    logActivity('update', "Updated invoice #$invoiceId", 'invoice', $invoiceId);
                    redirect('invoices.php', 'Invoice updated successfully.', 'success');
                } else {
                    // Create
                    $invoiceNumber = generateInvoiceNumber($db);
                    $stmt = $db->prepare("INSERT INTO invoices (invoice_number, contact_id, deal_id, issue_date, due_date, status, subtotal, igst_rate, igst_amount, cgst_rate, cgst_amount, sgst_rate, sgst_amount, gst_rate, gst_amount, discount, total, notes, terms, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$invoiceNumber, $contactId, $dealId, $issueDate, $dueDate, $status, $totals['subtotal'], $igstRate, $totals['igst_amount'], $cgstRate, $totals['cgst_amount'], $sgstRate, $totals['sgst_amount'], $cgstRate + $sgstRate, $totals['cgst_amount'] + $totals['sgst_amount'], $discount, $totals['total'], $notes, $terms, $userId]);
                    $newInvoiceId = $db->lastInsertId();
                    $extraStmt = $db->prepare("UPDATE invoices SET reverse_charge=?, billing_gstin=?, supply_state=?, state_code=?, place_of_supply=?, shipping_name=?, shipping_address=?, shipping_gstin=?, shipping_state=?, shipping_state_code=? WHERE id=?");
                    $extraStmt->execute([$reverseCharge, $billingGstin, $supplyState, $stateCode, $placeOfSupply, $shippingName, $shippingAddress, $shippingGstin, $shippingState, $shippingStateCode, $newInvoiceId]);

                    $itemStmt = $db->prepare("INSERT INTO invoice_items (invoice_id, description, sac_code, quantity, unit_price, amount, other_charge) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($items as $item) {
                        $amount = $item['quantity'] * $item['unit_price'];
                        $itemStmt->execute([$newInvoiceId, $item['description'], $item['sac_code'], $item['quantity'], $item['unit_price'], $amount, $item['other_charge']]);
                    }
                    logActivity('create', "Created invoice $invoiceNumber", 'invoice', $newInvoiceId);
                    redirect('invoices.php', 'Invoice created successfully.', 'success');
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get invoice for edit
$editInvoice = null;
$editItems = [];
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM invoices WHERE id=? " . ($viewAll ? '' : 'AND created_by=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $editInvoice = $stmt->fetch();
    if (!$editInvoice) {
        $error = 'Invoice not found.';
        $action = 'list';
    } else {
        $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
        $stmt->execute([$editInvoice['id']]);
        $editItems = $stmt->fetchAll();
    }
}

// Get invoice for view
$viewInvoice = null;
$viewItems = [];
$viewContact = null;
if ($action === 'view' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT i.*, c.first_name, c.last_name, c.company as contact_company, c.email as contact_email, c.phone as contact_phone, c.address as contact_address, c.city as contact_city, c.country as contact_country, u.name as created_by_name FROM invoices i LEFT JOIN contacts c ON i.contact_id = c.id LEFT JOIN users u ON i.created_by = u.id WHERE i.id=? " . ($viewAll ? '' : 'AND i.created_by=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $viewInvoice = $stmt->fetch();
    if (!$viewInvoice) {
        $error = 'Invoice not found.';
        $action = 'list';
    } else {
        $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
        $stmt->execute([$viewInvoice['id']]);
        $viewItems = $stmt->fetchAll();
    }
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterContact = $_GET['contact_id'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Build where clause
$conditions = [];
if (!$viewAll) {
    $conditions[] = "i.created_by = $userId";
}
if ($filterStatus) {
    $conditions[] = "i.status = " . $db->quote($filterStatus);
}
if ($filterContact && is_numeric($filterContact)) {
    $conditions[] = "i.contact_id = " . intval($filterContact);
}
if ($filterDateFrom) {
    $conditions[] = "DATE(i.issue_date) >= " . $db->quote($filterDateFrom);
}
if ($filterDateTo) {
    $conditions[] = "DATE(i.issue_date) <= " . $db->quote($filterDateTo);
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$invoices = [];
$contacts = [];
$deals = [];
$statuses = ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'];

try {
    $invoices = $db->query("SELECT i.*, c.first_name, c.last_name, c.company FROM invoices i LEFT JOIN contacts c ON i.contact_id = c.id $where ORDER BY i.created_at DESC")->fetchAll();
    $contacts = $db->query("SELECT id, first_name, last_name, company, address, city, country, gstin, state, state_code, shipping_name, shipping_address, shipping_gstin, shipping_state, shipping_state_code FROM contacts ORDER BY first_name, last_name")->fetchAll();
    $deals = $db->query("SELECT id, title FROM deals ORDER BY title")->fetchAll();
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage() . '. Please run setup.php to create invoice tables.';
}

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Invoices</h1>
                <p class="text-gray-500 mt-1">Create and manage customizable invoices</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="invoices.php?action=add" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-medium">
                    <i class="fas fa-plus"></i> Create Invoice
                </a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $editInvoice)): ?>
        <!-- Invoice Form -->
        <div class="animate-slide-up">
            <!-- Form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-secondary-900 mb-6"><?php echo $action === 'edit' ? 'Edit Invoice' : 'Create Invoice'; ?></h2>
                <form method="POST" action="invoices.php" class="space-y-6" id="invoice-form">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <?php if ($editInvoice): ?>
                <input type="hidden" name="invoice_id" value="<?php echo $editInvoice['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client *</label>
                        <select name="contact_id" id="invoice-contact" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <option value="">-- Select Client --</option>
                            <?php foreach ($contacts as $contact): ?>
                            <option value="<?php echo $contact['id']; ?>" <?php echo ($editInvoice['contact_id'] ?? '') == $contact['id'] ? 'selected' : ''; ?>><?php echo sanitize($contact['first_name'] . ' ' . $contact['last_name'] . ' - ' . $contact['company']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Linked Deal</label>
                        <select name="deal_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <option value="">-- None --</option>
                            <?php foreach ($deals as $deal): ?>
                            <option value="<?php echo $deal['id']; ?>" <?php echo ($editInvoice['deal_id'] ?? '') == $deal['id'] ? 'selected' : ''; ?>><?php echo sanitize($deal['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                            <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($editInvoice['status'] ?? 'draft') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Issue Date *</label>
                        <input type="date" name="issue_date" required value="<?php echo $editInvoice['issue_date'] ?? date('Y-m-d'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date of Supply</label>
                        <input type="date" name="due_date" value="<?php echo $editInvoice['due_date'] ?? ''; ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>
                </div>

                <div class="border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-secondary-900 mb-4">Tax &amp; Supply Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Reverse Charge</label><select name="reverse_charge" class="w-full px-3 py-2 border border-gray-200 rounded-lg"><option value="N" <?php echo ($editInvoice['reverse_charge'] ?? 'N') === 'N' ? 'selected' : ''; ?>>No</option><option value="Y" <?php echo ($editInvoice['reverse_charge'] ?? 'N') === 'Y' ? 'selected' : ''; ?>>Yes</option></select></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Billing GSTIN</label><input type="text" name="billing_gstin" value="<?php echo sanitize($editInvoice['billing_gstin'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg" placeholder="Customer GSTIN"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">State</label><input type="text" name="supply_state" value="<?php echo sanitize($editInvoice['supply_state'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg" placeholder="Delhi"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">State Code</label><input type="text" name="state_code" value="<?php echo sanitize($editInvoice['state_code'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg" placeholder="07"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Place of Supply</label><input type="text" name="place_of_supply" value="<?php echo sanitize($editInvoice['place_of_supply'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg" placeholder="Delhi"></div>
                    </div>
                    <h3 class="font-semibold text-secondary-900 mt-5 mb-3">Ship to Party</h3>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Shipping Name</label><input type="text" name="shipping_name" value="<?php echo sanitize($editInvoice['shipping_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg"></div>
                        <div class="md:col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">Shipping Address</label><input type="text" name="shipping_address" value="<?php echo sanitize($editInvoice['shipping_address'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Shipping GSTIN</label><input type="text" name="shipping_gstin" value="<?php echo sanitize($editInvoice['shipping_gstin'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">Shipping State / Code</label><div class="flex gap-2"><input type="text" name="shipping_state" value="<?php echo sanitize($editInvoice['shipping_state'] ?? ''); ?>" class="min-w-0 w-full px-3 py-2 border border-gray-200 rounded-lg"><input type="text" name="shipping_state_code" value="<?php echo sanitize($editInvoice['shipping_state_code'] ?? ''); ?>" class="w-16 px-3 py-2 border border-gray-200 rounded-lg" placeholder="07"></div></div>
                    </div>
                </div>

                <!-- Line Items -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-gray-700">Invoice Items</label>
                        <button type="button" onclick="addInvoiceItem()" class="text-sm text-primary-600 hover:text-primary-700 font-medium"><i class="fas fa-plus mr-1"></i> Add Item</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-32">SAC Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-32">Qty</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-40">Unit Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-32">Other Charge</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-32">Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-16">Action</th>
                                </tr>
                            </thead>
                            <tbody id="invoice-items-body" class="divide-y divide-gray-100">
                                <?php if (empty($editItems)): ?>
                                <tr class="invoice-item-row">
                                    <td class="px-4 py-3"><input type="text" name="item_description[]" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500" placeholder="Item description"></td>
                                    <td class="px-4 py-3"><input type="text" name="item_sac_code[]" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500" placeholder="e.g. 998313"></td>
                                    <td class="px-4 py-3"><input type="number" step="0.01" name="item_quantity[]" value="1" min="0.01" required onchange="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500"></td>
                                    <td class="px-4 py-3"><input type="number" step="0.01" name="item_unit_price[]" value="0" min="0" required onchange="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500"></td>
                                    <td class="px-4 py-3"><input type="number" step="0.01" name="item_other_charge[]" value="0" min="0" oninput="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"></td>
                                    <td class="px-4 py-3 text-sm font-medium item-amount">0.00</td>
                                    <td class="px-4 py-3 text-center"><button type="button" onclick="removeInvoiceItem(this)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i class="fas fa-trash text-xs"></i></button></td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($editItems as $item): ?>
                                    <tr class="invoice-item-row">
                                        <td class="px-4 py-3"><input type="text" name="item_description[]" required value="<?php echo sanitize($item['description']); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500" placeholder="Item description"></td>
                                        <td class="px-4 py-3"><input type="text" name="item_sac_code[]" value="<?php echo sanitize($item['sac_code'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500" placeholder="e.g. 998313"></td>
                                        <td class="px-4 py-3"><input type="number" step="0.01" name="item_quantity[]" value="<?php echo $item['quantity']; ?>" min="0.01" required onchange="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500"></td>
                                        <td class="px-4 py-3"><input type="number" step="0.01" name="item_unit_price[]" value="<?php echo $item['unit_price']; ?>" min="0" required onchange="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500"></td>
                                        <td class="px-4 py-3"><input type="number" step="0.01" name="item_other_charge[]" value="<?php echo $item['other_charge'] ?? 0; ?>" min="0" oninput="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"></td>
                                        <td class="px-4 py-3 text-sm font-medium item-amount"><?php echo number_format((float)$item['amount'] + (float)($item['other_charge'] ?? 0), 2); ?></td>
                                        <td class="px-4 py-3 text-center"><button type="button" onclick="removeInvoiceItem(this)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i class="fas fa-trash text-xs"></i></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Totals -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($editInvoice['notes'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Terms & Conditions</label>
                            <textarea name="terms" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($editInvoice['terms'] ?? setting('invoice_terms', '')); ?></textarea>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-6 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium" id="display-subtotal"><?php echo formatCurrency($editInvoice['subtotal'] ?? 0); ?></span>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">IGST %</label>
                                <input type="number" step="0.01" min="0" name="igst_rate" id="igst-rate" value="<?php echo $editInvoice['igst_rate'] ?? 0; ?>" oninput="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">CGST %</label>
                                <input type="number" step="0.01" min="0" name="cgst_rate" id="cgst-rate" value="<?php echo $editInvoice['cgst_rate'] ?? 9; ?>" oninput="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">SGST %</label>
                                <input type="number" step="0.01" min="0" name="sgst_rate" id="sgst-rate" value="<?php echo $editInvoice['sgst_rate'] ?? 9; ?>" oninput="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500">
                            </div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">IGST Amount</span>
                            <span class="font-medium" id="display-igst"><?php echo formatCurrency($editInvoice['igst_amount'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">CGST Amount</span>
                            <span class="font-medium" id="display-cgst"><?php echo formatCurrency($editInvoice['cgst_amount'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">SGST Amount</span>
                            <span class="font-medium" id="display-sgst"><?php echo formatCurrency($editInvoice['sgst_amount'] ?? 0); ?></span>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Discount</label>
                            <input type="number" step="0.01" name="discount" id="discount" value="<?php echo $editInvoice['discount'] ?? 0; ?>" onchange="calculateInvoice()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500">
                        </div>
                        <div class="flex justify-between text-lg font-bold pt-3 border-t border-gray-200">
                            <span class="text-secondary-900">Total</span>
                            <span class="text-primary-600" id="display-total"><?php echo formatCurrency($editInvoice['total'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium"><?php echo $action === 'edit' ? 'Update Invoice' : 'Create Invoice'; ?></button>
                    <button type="button" onclick="openInvoicePreview()" class="px-6 py-2.5 border border-primary-200 text-primary-600 rounded-lg hover:bg-primary-50 transition-colors font-medium"><i class="fas fa-eye mr-2"></i>Preview Invoice</button>
                    <a href="invoices.php" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                </div>
            </form>
            </div>
        </div>

        <?php elseif ($action === 'view' && $viewInvoice): ?>
        <!-- Invoice View -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-secondary-900">Invoice <?php echo sanitize($viewInvoice['invoice_number']); ?></h2>
                    <p class="text-gray-500 mt-1">Status: <span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($viewInvoice['status']); ?>"><?php echo $statuses[$viewInvoice['status']] ?? ucfirst($viewInvoice['status']); ?></span></p>
                </div>
                <div class="mt-4 sm:mt-0 flex gap-2">
                    <a href="invoice_print.php?id=<?php echo $viewInvoice['id']; ?>" target="_blank" class="px-4 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"><i class="fas fa-print mr-2"></i>Print</a>
                    <a href="invoices.php?action=edit&id=<?php echo $viewInvoice['id']; ?>" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors"><i class="fas fa-edit mr-2"></i>Edit</a>
                    <a href="invoices.php" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Back</a>
                </div>
            </div>

            <iframe src="invoice_print.php?id=<?php echo $viewInvoice['id']; ?>&embedded=1" class="w-full h-[800px] border border-gray-200 rounded-lg bg-gray-50" title="Invoice View"></iframe>
        </div>

        <?php else: ?>
        <!-- Invoice List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up">
            <div class="p-4 border-b border-gray-100 flex flex-col gap-4">
                <div class="relative w-full sm:w-80">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" data-search=".invoice-row" placeholder="Search invoices..." class="pl-10 pr-4 py-2 w-full border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>
                <form method="GET" action="invoices.php" class="flex flex-wrap items-end gap-2">
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
                        Status
                        <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
                        Client
                        <select name="contact_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                            <option value="">All Clients</option>
                            <?php foreach ($contacts as $contact): ?>
                            <option value="<?php echo $contact['id']; ?>" <?php echo $filterContact == $contact['id'] ? 'selected' : ''; ?>><?php echo sanitize($contact['first_name'] . ' ' . $contact['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
                        From Date
                        <input type="date" name="date_from" value="<?php echo sanitize($filterDateFrom); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">
                        To Date
                        <input type="date" name="date_to" value="<?php echo sanitize($filterDateTo); ?>" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                    </label>
                    <button type="submit" class="px-3 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 transition-colors"><i class="fas fa-filter mr-1"></i> Filter</button>
                    <a href="invoices.php" class="px-3 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm transition-colors">Reset</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice #</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Issue Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">No invoices found. <a href="invoices.php?action=add" class="text-primary-600 hover:underline">Create your first invoice</a>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $index => $invoice): ?>
                            <tr class="invoice-row searchable-row hover:bg-gray-50 transition-colors table-row-animate" style="animation-delay: <?php echo $index * 50; ?>ms">
                                <td class="px-6 py-4 font-medium text-secondary-900"><?php echo sanitize($invoice['invoice_number']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? '') . ' - ' . ($invoice['company'] ?? '')); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo formatDate($invoice['issue_date']); ?></td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($invoice['status']); ?>"><?php echo $statuses[$invoice['status']] ?? ucfirst($invoice['status']); ?></span></td>
                                <td class="px-6 py-4 text-sm font-bold text-secondary-900 text-right"><?php echo formatCurrency($invoice['total']); ?></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="invoices.php?action=view&id=<?php echo $invoice['id']; ?>" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors" data-tooltip="View"><i class="fas fa-eye"></i></a>
                                        <a href="invoice_print.php?id=<?php echo $invoice['id']; ?>" target="_blank" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors" data-tooltip="Print"><i class="fas fa-print"></i></a>
                                        <a href="invoices.php?action=edit&id=<?php echo $invoice['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" data-tooltip="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="invoices.php?delete=<?php echo $invoice['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-confirm="Are you sure you want to delete this invoice?" data-tooltip="Delete"><i class="fas fa-trash"></i></a>
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

<meta name="csrf-token" content="<?php echo csrfToken(); ?>">

<script>
const invoiceContacts = <?php echo json_encode(array_column($contacts, null, 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function fillInvoiceFromContact(contactId) {
    const contact = invoiceContacts[contactId];
    if (!contact) return;
    const setValue = (name, value) => {
        const field = document.querySelector(`[name="${name}"]`);
        if (field) field.value = value || '';
    };
    const displayName = contact.company || `${contact.first_name || ''} ${contact.last_name || ''}`.trim();
    const billingAddress = [contact.address, contact.city, contact.state, contact.country].filter(Boolean).join(', ');
    const billingState = contact.state || contact.city || '';
    setValue('billing_gstin', contact.gstin);
    setValue('supply_state', billingState);
    setValue('state_code', contact.state_code);
    setValue('place_of_supply', billingState);
    setValue('shipping_name', contact.shipping_name || displayName);
    setValue('shipping_address', contact.shipping_address || billingAddress);
    setValue('shipping_gstin', contact.shipping_gstin || contact.gstin);
    setValue('shipping_state', contact.shipping_state || billingState);
    setValue('shipping_state_code', contact.shipping_state_code || contact.state_code);
}

// Prevent duplicate form submissions
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    });
});

function addInvoiceItem() {
    const tbody = document.getElementById('invoice-items-body');
    const row = document.createElement('tr');
    row.className = 'invoice-item-row';
    row.innerHTML = `
        <td class="px-4 py-3"><input type="text" name="item_description[]" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500" placeholder="Item description"></td>
        <td class="px-4 py-3"><input type="text" name="item_sac_code[]" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500" placeholder="e.g. 998313"></td>
        <td class="px-4 py-3"><input type="number" step="0.01" name="item_quantity[]" value="1" min="0.01" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500"></td>
        <td class="px-4 py-3"><input type="number" step="0.01" name="item_unit_price[]" value="0" min="0" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500"></td>
        <td class="px-4 py-3"><input type="number" step="0.01" name="item_other_charge[]" value="0" min="0" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"></td>
        <td class="px-4 py-3 text-sm font-medium item-amount">0.00</td>
        <td class="px-4 py-3 text-center"><button type="button" onclick="removeInvoiceItem(this)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i class="fas fa-trash text-xs"></i></button></td>
    `;
    tbody.appendChild(row);
    calculateInvoice();
}

function removeInvoiceItem(btn) {
    const rows = document.querySelectorAll('.invoice-item-row');
    if (rows.length <= 1) {
        showAlert('Invoice must have at least one item.', 'Warning', 'warning');
        return;
    }
    btn.closest('tr').remove();
    calculateInvoice();
}

function calculateInvoice() {
    let subtotal = 0;
    const rows = document.querySelectorAll('.invoice-item-row');
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('[name="item_quantity[]"]').value) || 0;
        const price = parseFloat(row.querySelector('[name="item_unit_price[]"]').value) || 0;
        const otherCharge = parseFloat(row.querySelector('[name="item_other_charge[]"]').value) || 0;
        const amount = (qty * price) + otherCharge;
        row.querySelector('.item-amount').textContent = amount.toFixed(2);
        subtotal += amount;
    });

    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const afterDiscount = Math.max(0, subtotal - discount);
    const igstRate = parseFloat(document.getElementById('igst-rate').value) || 0;
    const cgstRate = parseFloat(document.getElementById('cgst-rate').value) || 0;
    const sgstRate = parseFloat(document.getElementById('sgst-rate').value) || 0;
    const igstAmount = afterDiscount * (igstRate / 100);
    const cgstAmount = afterDiscount * (cgstRate / 100);
    const sgstAmount = afterDiscount * (sgstRate / 100);
    const total = afterDiscount + igstAmount + cgstAmount + sgstAmount;

    const currency = '<?php echo setting('currency', '₹'); ?>';
    const conversionRate = <?php echo json_encode((float)setting('currency_conversion_rate', '1')); ?> || 1;
    const converted = amount => currency + (amount * conversionRate).toFixed(2);
    document.getElementById('display-subtotal').textContent = converted(subtotal);
    document.getElementById('display-igst').textContent = converted(igstAmount);
    document.getElementById('display-cgst').textContent = converted(cgstAmount);
    document.getElementById('display-sgst').textContent = converted(sgstAmount);
    document.getElementById('display-total').textContent = converted(total);

}

async function openInvoicePreview() {
    const form = document.getElementById('invoice-form');
    if (!form) return;

    const previewWindow = window.open('', 'invoice-preview', 'width=1100,height=800,resizable=yes,scrollbars=yes');
    if (!previewWindow) {
        await showAlert('Please allow pop-ups in your browser to preview the invoice.', 'Preview blocked', 'warning');
        return;
    }

    previewWindow.document.write('<!DOCTYPE html><html><head><title>Loading Invoice Preview...</title></head><body style="font-family: sans-serif; padding: 2rem; color: #475569;">Loading invoice preview...</body></html>');
    previewWindow.document.close();

    const formData = new FormData(form);
    try {
        const response = await fetch('invoice_preview.php', {
            method: 'POST',
            body: formData
        });
        const html = await response.text();
        if (!response.ok) throw new Error(html || 'Unable to load preview.');
        previewWindow.document.open();
        previewWindow.document.write(html);
        previewWindow.document.close();
        previewWindow.focus();
    } catch (error) {
        console.error('Preview update failed:', error);
        previewWindow.document.open();
        previewWindow.document.write('<p style="font-family: sans-serif; padding: 2rem; color: #dc2626;">Unable to load the invoice preview. Please try again.</p>');
        previewWindow.document.close();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('invoice-form');
    if (form) {
        const contactSelect = document.getElementById('invoice-contact');
        if (contactSelect) contactSelect.addEventListener('change', () => fillInvoiceFromContact(contactSelect.value));
        form.addEventListener('input', function(e) {
            if (e.target.name === 'item_quantity[]' || e.target.name === 'item_unit_price[]' || e.target.name === 'item_other_charge[]') {
                calculateInvoice();
            }
        });
        form.addEventListener('change', function(e) {
            calculateInvoice();
        });
    }
    calculateInvoice();
});
</script>

<?php include 'includes/footer.php'; ?>
