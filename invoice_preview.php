<?php
require_once 'includes/functions.php';
requireLogin();
requirePermission('invoices');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die('Invalid request.');
$db = getDB();
$contact = null;
$contactId = $_POST['contact_id'] ?? '';
if ($contactId) {
    $stmt = $db->prepare('SELECT * FROM contacts WHERE id=?');
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch();
}
$items = [];
$subtotal = 0;
$descriptions = $_POST['item_description'] ?? [];
$sacCodes = $_POST['item_sac_code'] ?? [];
$otherCharges = $_POST['item_other_charge'] ?? [];
$quantities = $_POST['item_quantity'] ?? [];
$prices = $_POST['item_unit_price'] ?? [];
foreach ($descriptions as $index => $description) {
    $description = trim($description);
    if ($description === '') continue;
    $quantity = (float)($quantities[$index] ?? 1);
    $unitPrice = (float)($prices[$index] ?? 0);
    $amount = $quantity * $unitPrice;
    $items[] = compact('description', 'quantity', 'unitPrice', 'amount');
    $items[array_key_last($items)]['unit_price'] = $unitPrice;
    $items[array_key_last($items)]['sac_code'] = trim($sacCodes[$index] ?? '');
    $items[array_key_last($items)]['other_charge'] = (float)($otherCharges[$index] ?? 0);
    $subtotal += $amount + $items[array_key_last($items)]['other_charge'];
}
$discount = (float)($_POST['discount'] ?? 0);
$igstRate = (float)($_POST['igst_rate'] ?? 0);
$cgstRate = (float)($_POST['cgst_rate'] ?? 0);
$sgstRate = (float)($_POST['sgst_rate'] ?? 0);
$taxableValue = max(0, $subtotal - $discount);
$igstAmount = $taxableValue * $igstRate / 100;
$cgstAmount = $taxableValue * $cgstRate / 100;
$sgstAmount = $taxableValue * $sgstRate / 100;
$invoiceData = [
    'invoice_number' => 'PREVIEW',
    'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
    'due_date' => $_POST['due_date'] ?? '',
    'subtotal' => $subtotal,
    'discount' => $discount,
    'igst_rate' => $igstRate,
    'igst_amount' => $igstAmount,
    'cgst_rate' => $cgstRate,
    'cgst_amount' => $cgstAmount,
    'sgst_rate' => $sgstRate,
    'sgst_amount' => $sgstAmount,
    'total' => $taxableValue + $igstAmount + $cgstAmount + $sgstAmount,
    'notes' => trim($_POST['notes'] ?? ''),
    'terms' => trim($_POST['terms'] ?? ''),
    'reverse_charge' => ($_POST['reverse_charge'] ?? 'N') === 'Y' ? 'Y' : 'N',
    'billing_gstin' => trim($_POST['billing_gstin'] ?? ''),
    'supply_state' => trim($_POST['supply_state'] ?? ''),
    'state_code' => trim($_POST['state_code'] ?? ''),
    'place_of_supply' => trim($_POST['place_of_supply'] ?? ''),
    'shipping_name' => trim($_POST['shipping_name'] ?? ''),
    'shipping_address' => trim($_POST['shipping_address'] ?? ''),
    'shipping_gstin' => trim($_POST['shipping_gstin'] ?? ''),
    'shipping_state' => trim($_POST['shipping_state'] ?? ''),
    'shipping_state_code' => trim($_POST['shipping_state_code'] ?? ''),
    'first_name' => $contact['first_name'] ?? '',
    'last_name' => $contact['last_name'] ?? '',
    'contact_company' => $contact['company'] ?? '',
    'contact_email' => $contact['email'] ?? '',
    'contact_phone' => $contact['phone'] ?? '',
    'contact_address' => $contact['address'] ?? '',
    'contact_city' => $contact['city'] ?? '',
    'contact_country' => $contact['country'] ?? '',
];
$companyName = setting('company_name', 'TECHPRO IT SOLUTIONS');
$companyEmail = setting('company_email', 'info@techproitsolutions.in');
$companyPhone = setting('company_phone', '');
$companyAddress = setting('company_address', '220 PLOT-8, AGGARWAL TOWER, LSC-11, MANDAWALI FAZALPUR, NEW DELHI-92, INDIA');
$companyGstin = setting('company_gstin', '07AIAPB4587B1ZP');
$bankNameBranch = setting('bank_name_branch', 'Canara Bank, Patparganj');
$bankAccountName = setting('bank_account_name', 'Techpro IT Solutions');
$bankAccountNumber = setting('bank_account_number', '2756201000509');
$bankIfsc = setting('bank_ifsc', 'CNRB0002756');
$currency = setting('currency', '₹');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tax Invoice Preview</title><link rel="icon" href="assets/images/logo2.png"><link rel="stylesheet" href="assets/css/invoice-tax.css"></head>
<body class="invoice-page"><main class="invoice-shell"><div class="invoice-toolbar no-print"><button onclick="window.print()">Print</button><button onclick="window.close()">Close</button></div><?php require 'includes/invoice_tax_template.php'; ?></main></body></html>
