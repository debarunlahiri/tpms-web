<?php
require_once 'includes/functions.php';
requireLogin();
requirePermission('invoices');
$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die('Invalid invoice ID.');
$stmt = $db->prepare("SELECT i.*, c.first_name, c.last_name, c.company AS contact_company, c.email AS contact_email, c.phone AS contact_phone, c.address AS contact_address, c.city AS contact_city, c.country AS contact_country FROM invoices i LEFT JOIN contacts c ON i.contact_id=c.id WHERE i.id=? " . ($viewAll ? '' : 'AND i.created_by=?'));
$stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
$invoiceData = $stmt->fetch();
if (!$invoiceData) die('Invoice not found or access denied.');
$stmt = $db->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id ASC');
$stmt->execute([$invoiceData['id']]);
$items = $stmt->fetchAll();
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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tax Invoice <?php echo sanitize($invoiceData['invoice_number']); ?></title><link rel="icon" href="assets/images/logo2.png"><link rel="stylesheet" href="assets/css/invoice-tax.css"></head>
<body class="invoice-page"><main class="invoice-shell"><div class="invoice-toolbar no-print"><button onclick="window.print()">Print</button><a href="invoices.php?action=view&amp;id=<?php echo (int)$invoiceData['id']; ?>">Back</a></div><?php require 'includes/invoice_tax_template.php'; ?></main></body></html>
