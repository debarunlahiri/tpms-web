<?php
require_once 'includes/functions.php';
requireLogin();
requirePermission('invoices');

$db = getDB();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request.');
}

// Build invoice data from POST
$contactId = $_POST['contact_id'] ?? '';
$issueDate = $_POST['issue_date'] ?? date('Y-m-d');
$dueDate = $_POST['due_date'] ?? '';
$status = $_POST['status'] ?? 'draft';
$taxRate = floatval($_POST['tax_rate'] ?? 0);
$gstRate = floatval($_POST['gst_rate'] ?? 0);
$discount = floatval($_POST['discount'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$terms = trim($_POST['terms'] ?? '');

$invoiceNumber = 'PREVIEW';

// Get contact details
$contact = null;
if ($contactId) {
    $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch();
}

// Build items
$items = [];
$subtotal = 0;
$descriptions = $_POST['item_description'] ?? [];
$quantities = $_POST['item_quantity'] ?? [];
$prices = $_POST['item_unit_price'] ?? [];

for ($i = 0; $i < count($descriptions); $i++) {
    $desc = trim($descriptions[$i]);
    if ($desc === '') continue;
    $qty = floatval($quantities[$i] ?? 1);
    $price = floatval($prices[$i] ?? 0);
    $amount = $qty * $price;
    $items[] = [
        'description' => $desc,
        'quantity' => $qty,
        'unit_price' => $price,
        'amount' => $amount,
    ];
    $subtotal += $amount;
}

$afterDiscount = max(0, $subtotal - $discount);
$taxAmount = $afterDiscount * ($taxRate / 100);
$gstAmount = $afterDiscount * ($gstRate / 100);
$total = $afterDiscount + $taxAmount + $gstAmount;

$companyName = setting('company_name', 'TPMS');
$companyEmail = setting('company_email', '');
$companyPhone = setting('company_phone', '');
$companyAddress = setting('company_address', '');
$companyGstin = setting('company_gstin', '');
$currency = setting('currency', '₹');

$statuses = ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Preview</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            @page { margin: 12mm; }
            .no-print { display: none !important; }
            html, body { background: white !important; margin: 0 !important; padding: 0 !important; }
            body > div { max-width: none !important; margin: 0 !important; }
            .print-container { background: white !important; box-shadow: none !important; border: 0 !important; border-radius: 0 !important; margin: 0 !important; padding: 0 !important; }
            .print-container [class*="bg-"] { background: transparent !important; }
            .print-container * { box-shadow: none !important; text-shadow: none !important; }
            .print-container table thead tr { background: transparent !important; border-bottom: 1px solid #d1d5db; }
            .print-container .rounded-full { background: transparent !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto">
        <div class="no-print flex justify-between items-center mb-4">
            <h1 class="text-xl font-bold text-secondary-900"><i class="fas fa-eye text-primary-600 mr-2"></i>Invoice Preview</h1>
            <div class="flex gap-2">
                <button onclick="window.print()" class="px-4 py-2 bg-white border border-gray-300 text-gray-900 rounded-lg hover:bg-gray-50 transition-colors"><i class="fas fa-print mr-2 text-gray-900"></i>Print</button>
                <button onclick="window.close()" class="px-4 py-2 border border-gray-300 bg-white text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">Close</button>
            </div>
        </div>

        <div class="print-container bg-white rounded-xl shadow-lg border border-gray-200 p-8 md:p-12">
            <div class="flex flex-col md:flex-row justify-between items-start mb-10">
                <div>
                    <img src="assets/images/logo.png" alt="TPMS" class="w-36 h-14 object-cover mb-3">
                    <?php if (strcasecmp(trim($companyName), 'TPMS') !== 0): ?><h1 class="text-3xl font-bold text-secondary-900"><?php echo sanitize($companyName); ?></h1><?php endif; ?>
                    <?php if ($companyAddress): ?><p class="text-sm text-gray-600 mt-1 whitespace-pre-line"><?php echo sanitize($companyAddress); ?></p><?php endif; ?>
                    <?php if ($companyEmail): ?><p class="text-sm text-gray-600"><i class="fas fa-envelope mr-1"></i><?php echo sanitize($companyEmail); ?></p><?php endif; ?>
                    <?php if ($companyPhone): ?><p class="text-sm text-gray-600"><i class="fas fa-phone mr-1"></i><?php echo sanitize($companyPhone); ?></p><?php endif; ?>
                    <?php if ($companyGstin): ?><p class="text-sm text-gray-600"><i class="fas fa-id-card mr-1"></i>GSTIN: <?php echo sanitize($companyGstin); ?></p><?php endif; ?>
                </div>
                <div class="mt-6 md:mt-0 text-left md:text-right">
                    <h2 class="no-print text-2xl font-bold text-primary-600">INVOICE</h2>
                    <p class="text-lg font-semibold text-gray-900 mt-1"><?php echo sanitize($invoiceNumber); ?></p>
                    <p class="no-print text-sm text-gray-600 mt-1">Status: <span class="px-2 py-0.5 text-xs rounded-full <?php echo statusColor($status); ?>"><?php echo $statuses[$status] ?? ucfirst($status); ?></span></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10 border-b border-gray-200 pb-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Bill To</h3>
                    <?php if ($contact): ?>
                    <p class="text-lg font-bold text-secondary-900"><?php echo sanitize(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')); ?></p>
                    <p class="text-sm text-gray-600"><?php echo sanitize($contact['company'] ?? ''); ?></p>
                    <?php if ($contact['address']): ?><p class="text-sm text-gray-600 whitespace-pre-line"><?php echo sanitize($contact['address']); ?><?php echo $contact['city'] ? ', ' . sanitize($contact['city']) : ''; ?><?php echo $contact['country'] ? ', ' . sanitize($contact['country']) : ''; ?></p><?php endif; ?>
                    <?php if ($contact['email']): ?><p class="text-sm text-gray-600"><i class="fas fa-envelope mr-1"></i><?php echo sanitize($contact['email']); ?></p><?php endif; ?>
                    <?php if ($contact['phone']): ?><p class="text-sm text-gray-600"><i class="fas fa-phone mr-1"></i><?php echo sanitize($contact['phone']); ?></p><?php endif; ?>
                    <?php else: ?>
                    <p class="text-sm text-gray-500">No contact selected</p>
                    <?php endif; ?>
                </div>
                <div class="md:text-right">
                    <div class="grid grid-cols-2 gap-4 md:justify-end">
                        <div class="text-left md:text-right">
                            <p class="text-sm text-gray-500">Issue Date</p>
                            <p class="font-medium"><?php echo $issueDate ? formatDate($issueDate) : '-'; ?></p>
                        </div>
                        <?php if (!empty($dueDate)): ?>
                        <div class="text-left md:text-right">
                            <p class="text-sm text-gray-500">Due Date</p>
                            <p class="font-medium"><?php echo formatDate($dueDate); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

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
                        <?php if (empty($items)): ?>
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No items</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td class="px-4 py-4 text-sm text-gray-600"><?php echo $index + 1; ?></td>
                                <td class="px-4 py-4 text-sm text-gray-900"><?php echo nl2br(sanitize($item['description'])); ?></td>
                                <td class="px-4 py-4 text-sm text-gray-600 text-right"><?php echo $item['quantity']; ?></td>
                                <td class="px-4 py-4 text-sm text-gray-600 text-right"><?php echo $currency . number_format($item['unit_price'], 2); ?></td>
                                <td class="px-4 py-4 text-sm font-medium text-right"><?php echo $currency . number_format($item['amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end mb-10">
                <div class="w-full md:w-80 space-y-2 border-t border-gray-200 pt-4">
                    <div class="flex justify-between text-sm"><span class="text-gray-600">Subtotal</span><span class="font-medium"><?php echo $currency . number_format($subtotal, 2); ?></span></div>
                    <?php if ($discount > 0): ?>
                    <div class="flex justify-between text-sm"><span class="text-gray-600">Discount</span><span class="font-medium text-red-600">-<?php echo $currency . number_format($discount, 2); ?></span></div>
                    <?php endif; ?>
                    <?php if ($taxAmount > 0): ?>
                    <div class="flex justify-between text-sm"><span class="text-gray-600">Tax (<?php echo $taxRate; ?>%)</span><span class="font-medium"><?php echo $currency . number_format($taxAmount, 2); ?></span></div>
                    <?php endif; ?>
                    <?php if ($gstAmount > 0): ?>
                    <div class="flex justify-between text-sm"><span class="text-gray-600">GST (<?php echo $gstRate; ?>%)</span><span class="font-medium"><?php echo $currency . number_format($gstAmount, 2); ?></span></div>
                    <?php endif; ?>
                    <div class="flex justify-between text-xl font-bold pt-3 border-t border-gray-200"><span>Total</span><span><?php echo $currency . number_format($total, 2); ?></span></div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6 space-y-4">
                <?php if ($notes): ?>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-1">Notes</h4>
                    <p class="text-sm text-gray-600 whitespace-pre-line"><?php echo sanitize($notes); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($terms): ?>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-1">Terms & Conditions</h4>
                    <p class="text-sm text-gray-600 whitespace-pre-line"><?php echo sanitize($terms); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
