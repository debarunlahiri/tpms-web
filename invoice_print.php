<?php
require_once 'includes/functions.php';
requireLogin();
requirePermission('invoices');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid invoice ID.');
}

$stmt = $db->prepare("SELECT i.*, c.first_name, c.last_name, c.company as contact_company, c.email as contact_email, c.phone as contact_phone, c.address as contact_address, c.city as contact_city, c.country as contact_country, u.name as created_by_name FROM invoices i LEFT JOIN contacts c ON i.contact_id = c.id LEFT JOIN users u ON i.created_by = u.id WHERE i.id=? " . ($viewAll ? '' : 'AND i.created_by=?'));
$stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found or access denied.');
}

$stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
$stmt->execute([$invoice['id']]);
$items = $stmt->fetchAll();

$companyName = setting('company_name', 'TPMS');
$companyEmail = setting('company_email', '');
$companyPhone = setting('company_phone', '');
$companyAddress = setting('company_address', '');
$companyGstin = setting('company_gstin', '');
$currency = setting('currency', '₹');

$statuses = ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'];
$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo sanitize($invoice['invoice_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .print-container { box-shadow: none; border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Toolbar -->
        <div class="no-print flex justify-end gap-2 mb-4">
            <button onclick="window.print()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"><i class="fas fa-print mr-2"></i>Print</button>
            <a href="invoices.php?action=view&id=<?php echo $invoice['id']; ?>" class="px-4 py-2 border border-gray-300 bg-white text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Back</a>
        </div>

        <!-- Invoice -->
        <div class="print-container bg-white rounded-xl shadow-lg border border-gray-200 p-8 md:p-12">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-secondary-900"><?php echo sanitize($companyName); ?></h1>
                    <?php if ($companyAddress): ?><p class="text-sm text-gray-600 mt-1 whitespace-pre-line"><?php echo sanitize($companyAddress); ?></p><?php endif; ?>
                    <?php if ($companyEmail): ?><p class="text-sm text-gray-600"><i class="fas fa-envelope mr-1"></i><?php echo sanitize($companyEmail); ?></p><?php endif; ?>
                    <?php if ($companyPhone): ?><p class="text-sm text-gray-600"><i class="fas fa-phone mr-1"></i><?php echo sanitize($companyPhone); ?></p><?php endif; ?>
                    <?php if ($companyGstin): ?><p class="text-sm text-gray-600"><i class="fas fa-id-card mr-1"></i>GSTIN: <?php echo sanitize($companyGstin); ?></p><?php endif; ?>
                </div>
                <div class="mt-6 md:mt-0 text-left md:text-right">
                    <h2 class="text-2xl font-bold text-primary-600">INVOICE</h2>
                    <p class="text-lg font-semibold text-secondary-900 mt-1"><?php echo sanitize($invoice['invoice_number']); ?></p>
                    <p class="text-sm text-gray-600 mt-1">Status: <span class="px-2 py-0.5 text-xs rounded-full <?php echo statusColor($invoice['status']); ?>"><?php echo $statuses[$invoice['status']] ?? ucfirst($invoice['status']); ?></span></p>
                </div>
            </div>

            <!-- Bill To & Dates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10 border-b border-gray-200 pb-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Bill To</h3>
                    <p class="text-lg font-bold text-secondary-900"><?php echo sanitize(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? '')); ?></p>
                    <p class="text-sm text-gray-600"><?php echo sanitize($invoice['contact_company'] ?? ''); ?></p>
                    <?php if ($invoice['contact_address']): ?><p class="text-sm text-gray-600 whitespace-pre-line"><?php echo sanitize($invoice['contact_address']); ?><?php echo $invoice['contact_city'] ? ', ' . sanitize($invoice['contact_city']) : ''; ?><?php echo $invoice['contact_country'] ? ', ' . sanitize($invoice['contact_country']) : ''; ?></p><?php endif; ?>
                    <?php if ($invoice['contact_email']): ?><p class="text-sm text-gray-600"><i class="fas fa-envelope mr-1"></i><?php echo sanitize($invoice['contact_email']); ?></p><?php endif; ?>
                    <?php if ($invoice['contact_phone']): ?><p class="text-sm text-gray-600"><i class="fas fa-phone mr-1"></i><?php echo sanitize($invoice['contact_phone']); ?></p><?php endif; ?>
                </div>
                <div class="md:text-right">
                    <div class="grid grid-cols-2 gap-4 md:justify-end">
                        <div class="text-left md:text-right">
                            <p class="text-sm text-gray-500">Issue Date</p>
                            <p class="font-medium"><?php echo formatDate($invoice['issue_date']); ?></p>
                        </div>
                        <div class="text-left md:text-right">
                            <p class="text-sm text-gray-500">Due Date</p>
                            <p class="font-medium"><?php echo $invoice['due_date'] ? formatDate($invoice['due_date']) : '-'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="mb-10">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">#</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Description</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Qty</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Unit Price</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td class="px-4 py-4 text-sm text-gray-600"><?php echo $index + 1; ?></td>
                            <td class="px-4 py-4 text-sm text-gray-900"><?php echo nl2br(sanitize($item['description'])); ?></td>
                            <td class="px-4 py-4 text-sm text-gray-600 text-right"><?php echo $item['quantity']; ?></td>
                            <td class="px-4 py-4 text-sm text-gray-600 text-right"><?php echo $currency . number_format($item['unit_price'], 2); ?></td>
                            <td class="px-4 py-4 text-sm font-medium text-right"><?php echo $currency . number_format($item['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="flex justify-end mb-10">
                <div class="w-full md:w-80 space-y-2 border-t border-gray-200 pt-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium"><?php echo $currency . number_format($invoice['subtotal'], 2); ?></span>
                    </div>
                    <?php if ($invoice['discount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Discount</span>
                        <span class="font-medium text-red-600">-<?php echo $currency . number_format($invoice['discount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['tax_amount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax (<?php echo $invoice['tax_rate']; ?>%)</span>
                        <span class="font-medium"><?php echo $currency . number_format($invoice['tax_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['gst_amount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">GST (<?php echo $invoice['gst_rate']; ?>%)</span>
                        <span class="font-medium"><?php echo $currency . number_format($invoice['gst_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-xl font-bold pt-3 border-t border-gray-200">
                        <span>Total</span>
                        <span><?php echo $currency . number_format($invoice['total'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-gray-200 pt-6 space-y-4">
                <?php if ($invoice['notes']): ?>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-1">Notes</h4>
                    <p class="text-sm text-gray-600 whitespace-pre-line"><?php echo sanitize($invoice['notes']); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($invoice['terms']): ?>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-1">Terms & Conditions</h4>
                    <p class="text-sm text-gray-600 whitespace-pre-line"><?php echo sanitize($invoice['terms']); ?></p>
                </div>
                <?php endif; ?>
                <p class="text-center text-sm text-gray-500 mt-8">Thank you for your business!</p>
            </div>
        </div>
    </div>
</body>
</html>
