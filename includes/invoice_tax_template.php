<?php

/**
 * Shared tax invoice layout.
 * Expected variables: $invoiceData, $items, $currency, $companyName,
 * $companyEmail, $companyPhone, $companyAddress, $companyGstin.
 */
$billName = trim(($invoiceData['first_name'] ?? '') . ' ' . ($invoiceData['last_name'] ?? ''));
$billCompany = trim($invoiceData['contact_company'] ?? '');
$billDisplayName = $billCompany !== '' ? $billCompany : ($billName !== '' ? $billName : '—');
$billAddress = trim($invoiceData['contact_address'] ?? '');
$billLocation = implode(', ', array_filter([
    trim($invoiceData['contact_city'] ?? ''),
    trim($invoiceData['contact_country'] ?? ''),
]));
$fullBillAddress = implode(', ', array_filter([$billAddress, $billLocation]));
$shipName = trim($invoiceData['shipping_name'] ?? '') ?: $billDisplayName;
$shipAddress = trim($invoiceData['shipping_address'] ?? '') ?: $fullBillAddress;
$billState = trim($invoiceData['supply_state'] ?? '') ?: trim($invoiceData['contact_city'] ?? '');
$shipState = trim($invoiceData['shipping_state'] ?? '') ?: $billState;
$taxableValue = max(0, (float)($invoiceData['subtotal'] ?? 0) - (float)($invoiceData['discount'] ?? 0));
$igstRate = (float)($invoiceData['igst_rate'] ?? $invoiceData['tax_rate'] ?? 0);
$igstAmount = (float)($invoiceData['igst_amount'] ?? $invoiceData['tax_amount'] ?? 0);
$cgstRate = (float)($invoiceData['cgst_rate'] ?? ((float)($invoiceData['gst_rate'] ?? 0) / 2));
$cgstAmount = (float)($invoiceData['cgst_amount'] ?? ((float)($invoiceData['gst_amount'] ?? 0) / 2));
$sgstRate = (float)($invoiceData['sgst_rate'] ?? ((float)($invoiceData['gst_rate'] ?? 0) / 2));
$sgstAmount = (float)($invoiceData['sgst_amount'] ?? ((float)($invoiceData['gst_amount'] ?? 0) / 2));
$termsLines = preg_split('/\r\n|\r|\n/', trim($invoiceData['terms'] ?? ''));
$termsLines = array_values(array_filter($termsLines, static fn($line) => trim($line) !== ''));
if (!$termsLines) {
    $termsLines = [
        'All payments through DD/Cheque/Bank Transfer.',
        'All dealings subject to Delhi jurisdiction only.',
        'Goods/services once supplied will not be taken back.',
    ];
}
?>
<article class="tax-invoice">
    <header class="invoice-brand">
        <img src="assets/images/logo2.png" alt="<?php echo sanitize($companyName); ?>" class="invoice-logo">
        <h1><?php echo sanitize($companyName); ?></h1>
        <?php if ($companyAddress): ?><div class="company-address"><?php echo nl2br(sanitize($companyAddress)); ?></div><?php endif; ?>
        <div class="company-meta">
            <?php if ($companyGstin): ?><strong>GSTIN: <?php echo sanitize($companyGstin); ?></strong><?php endif; ?>
            <?php if ($companyEmail): ?><strong>Email: <?php echo sanitize($companyEmail); ?></strong><?php endif; ?>
            <?php if ($companyPhone): ?><strong>Phone: <?php echo sanitize($companyPhone); ?></strong><?php endif; ?>
            <strong>Original for Recipient</strong>
        </div>
    </header>

    <div class="section-title">Tax Invoice</div>
    <table class="invoice-info fixed-table">
        <tr>
            <td><strong>Invoice No:</strong> <span><?php echo sanitize($invoiceData['invoice_number'] ?? 'PREVIEW'); ?></span></td>
            <td><strong>Invoice date:</strong> <span><?php echo !empty($invoiceData['issue_date']) ? formatDate($invoiceData['issue_date']) : '—'; ?></span></td>
        </tr>
        <tr>
            <td><strong>Reverse Charge (Y/N):</strong> <span><?php echo sanitize($invoiceData['reverse_charge'] ?? 'N'); ?></span></td>
            <td><strong>Date of Supply:</strong> <span><?php echo !empty($invoiceData['due_date']) ? formatDate($invoiceData['due_date']) : (!empty($invoiceData['issue_date']) ? formatDate($invoiceData['issue_date']) : '—'); ?></span></td>
        </tr>
        <tr>
            <td><strong>State:</strong> <span><?php echo sanitize(strtoupper($billState)); ?> <?php echo !empty($invoiceData['state_code']) ? '/ ' . sanitize($invoiceData['state_code']) : ''; ?></span></td>
            <td><strong>Place of Supply:</strong> <span><?php echo sanitize($invoiceData['place_of_supply'] ?? $billState); ?></span></td>
        </tr>
    </table>

    <table class="party-table fixed-table">
        <tr class="blue-row">
            <th>Bill to Party</th>
            <th>Ship to Party</th>
        </tr>
        <tr>
            <td><strong>Name:</strong> <?php echo sanitize($billDisplayName); ?></td>
            <td><strong>Name:</strong> <?php echo sanitize($shipName); ?></td>
        </tr>
        <tr>
            <td><strong>Address:</strong> <?php echo sanitize($fullBillAddress ?: '—'); ?></td>
            <td><strong>Address:</strong> <?php echo sanitize($shipAddress ?: '—'); ?></td>
        </tr>
        <tr>
            <td><strong>GSTIN:</strong> <?php echo sanitize($invoiceData['billing_gstin'] ?? '—'); ?> &nbsp; <strong>State:</strong> <?php echo sanitize($billState); ?> <?php echo sanitize($invoiceData['state_code'] ?? ''); ?></td>
            <td><strong>GSTIN:</strong> <?php echo sanitize(($invoiceData['shipping_gstin'] ?? '') ?: ($invoiceData['billing_gstin'] ?? '—')); ?> &nbsp; <strong>State:</strong> <?php echo sanitize($shipState); ?> <?php echo sanitize($invoiceData['shipping_state_code'] ?? ''); ?></td>
        </tr>
    </table>

    <table class="items-table fixed-table">
        <thead>
            <tr class="blue-row">
                <th class="serial">S.<br>No.</th>
                <th>Description of Services</th>
                <th class="sac">SAC<br>Code</th>
                <th class="qty">Qty<br>(Nos)</th>
                <th class="money">Rate</th>
                <th class="money">Amount</th>
                <th class="money">Other<br>Charge</th>
                <th class="money">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$items): ?>
                <tr>
                    <td class="center">1</td>
                    <td>—</td>
                    <td></td>
                    <td class="right">0</td>
                    <td class="right"><?php echo formatCurrency(0); ?></td>
                    <td class="right">—</td>
                    <td class="right">0.00</td>
                    <td class="right"><?php echo formatCurrency(0); ?></td>
                </tr>
                <?php else: foreach ($items as $index => $item): ?>
                    <tr>
                        <td class="center"><?php echo $index + 1; ?></td>
                        <td><?php echo nl2br(sanitize($item['description'])); ?></td>
                        <td class="center"><?php echo sanitize($item['sac_code'] ?? ''); ?></td>
                        <td class="right"><?php echo number_format((float)$item['quantity'], 2); ?></td>
                        <td class="right"><?php echo formatCurrency($item['unit_price']); ?></td>
                        <td class="right"><?php echo formatCurrency($item['amount']); ?></td>
                        <td class="right"><?php echo formatCurrency($item['other_charge'] ?? 0); ?></td>
                        <td class="right"><?php echo formatCurrency((float)$item['amount'] + (float)($item['other_charge'] ?? 0)); ?></td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
        <tfoot>
            <tr class="blue-total">
                <th colspan="3">Total</th>
                <td></td>
                <td></td>
                <td class="right"><?php echo formatCurrency(array_sum(array_map(static fn($item) => (float)$item['amount'], $items))); ?></td>
                <td class="right"><?php echo formatCurrency(array_sum(array_map(static fn($item) => (float)($item['other_charge'] ?? 0), $items))); ?></td>
                <td class="right"><?php echo formatCurrency($invoiceData['subtotal'] ?? 0); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="bottom-grid">
        <div class="bottom-left">
            <section class="bank-details">
                <h3>Bank Details</h3>
                <p><strong>Bank Name &amp; Branch:</strong> <?php echo sanitize($bankNameBranch); ?></p>
                <p><strong>Account Name:</strong> <?php echo sanitize($bankAccountName); ?></p>
                <p><strong>Bank A/C:</strong> <?php echo sanitize($bankAccountNumber); ?></p>
                <p><strong>Bank IFSC:</strong> <?php echo sanitize($bankIfsc); ?></p>
            </section>
            <section class="terms">
                <h3>Terms &amp; conditions</h3>
                <ol><?php foreach ($termsLines as $line): ?><li><?php echo sanitize(trim($line)); ?></li><?php endforeach; ?></ol>
            </section>
        </div>
        <div class="bottom-right">
            <table class="totals-table fixed-table">
                <tr>
                    <th>Taxable value</th>
                    <td><?php echo formatCurrency($taxableValue); ?></td>
                </tr>
                <?php if ($igstRate > 0 || $igstAmount > 0): ?><tr>
                        <th>Add: IGST @<?php echo number_format($igstRate, 2); ?>%</th>
                        <td><?php echo formatCurrency($igstAmount); ?></td>
                    </tr><?php endif; ?>
                <?php if ($cgstRate > 0 || $cgstAmount > 0): ?><tr>
                        <th>Add: CGST @<?php echo number_format($cgstRate, 2); ?>%</th>
                        <td><?php echo formatCurrency($cgstAmount); ?></td>
                    </tr><?php endif; ?>
                <?php if ($sgstRate > 0 || $sgstAmount > 0): ?><tr>
                        <th>Add: SGST @<?php echo number_format($sgstRate, 2); ?>%</th>
                        <td><?php echo formatCurrency($sgstAmount); ?></td>
                    </tr><?php endif; ?>
                <?php if ((float)($invoiceData['discount'] ?? 0) > 0): ?><tr>
                        <th>Less: Discount</th>
                        <td>-<?php echo formatCurrency($invoiceData['discount']); ?></td>
                    </tr><?php endif; ?>
                <tr class="invoice-value">
                    <th>Invoice Value</th>
                    <td><?php echo formatCurrency($invoiceData['total'] ?? 0); ?></td>
                </tr>
            </table>
            <?php if (!empty($invoiceData['notes'])): ?><div class="invoice-notes"><strong>Notes:</strong> <?php echo nl2br(sanitize($invoiceData['notes'])); ?></div><?php endif; ?>
            <div class="certification">Certified that the particulars given above are true and correct</div>
            <div class="signature"><strong>For <?php echo sanitize(strtoupper($companyName)); ?></strong><span>Online copy - No Sign Required<br>Authorised signatory</span></div>
        </div>
    </div>
</article>
