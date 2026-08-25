<?php
require_once __DIR__ . '/_components.php';
require_once __DIR__ . '/../app/application.php';
requirePermission('invoices.manage');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
[$ids, $ph] = financeScopedPlaceholders(currentAgencyIds());
$i = dbFetchOne(
    "SELECT i.*, c.first_name, c.last_name, c.company_name, c.ice, c.tax_identifier, c.billing_address,
            a.name agency_name, a.address agency_address
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     JOIN agencies a ON a.id = i.agency_id
     WHERE i.id = ? AND i.agency_id IN ($ph) AND i.issued_at IS NOT NULL",
    array_merge([$id], $ids)
);
if (!$i) {
    printNotFound(t('validation.finance_not_found'));
}
$lang = validateChoice($i['language_code'], supportedLanguages(), 'en');
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$items = dbFetchAll('SELECT * FROM invoice_items WHERE invoice_id=:id ORDER BY sort_order,id', ['id' => $id]);
$tr = fn($key, $parameters = []) => translateInLanguage($key, $lang, $parameters);
$isCredit = $i['invoice_type'] === 'credit_note';
$balanceDue = max(0, (float) $i['total_amount'] - (float) $i['paid_amount']);
?><!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($i['invoice_number']) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>">
</head>
<body>
<div class="print-page">
<div class="document">
<button class="btn secondary" onclick="print()"><?= e($tr('common.print_pdf')) ?></button>
<header class="document-head">
<div class="document-brand">
<span class="invoice-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $i['agency_name'], 0, 1))) ?></span>
<div><strong><?= e($i['agency_name']) ?></strong><?php if ($i['agency_address']): ?><p><?= e($i['agency_address']) ?></p><?php endif; ?></div>
</div>
<div class="document-title">
<h1><?= e($tr($isCredit ? 'print.credit_note' : 'print.invoice')) ?></h1>
<p class="reference"><?= isolatedValue($i['invoice_number'], 'reference-value') ?></p>
<?= statusBadge($i['status'], $lang) ?>
</div>
</header>
<dl class="document-meta">
<div><dt><?= e($tr('print.issued_on')) ?></dt><dd><?= e(localizedDate($i['issued_at'], $lang)) ?></dd></div>
<?php if ($i['due_at']): ?><div><dt><?= e($tr('field.due_date')) ?></dt><dd><?= e(localizedDate($i['due_at'], $lang)) ?></dd></div><?php endif; ?>
<div><dt><?= e($tr('print.bill_to')) ?></dt><dd>
<?= e($i['company_name'] ?: $i['first_name'] . ' ' . $i['last_name']) ?>
<?php if ($i['ice']): ?><br>ICE: <?= e($i['ice']) ?><?php endif; ?>
<?php if ($i['tax_identifier']): ?><br><?= e($tr('print.tax_id')) ?>: <?= e($i['tax_identifier']) ?><?php endif; ?>
<?php if ($i['billing_address']): ?><br><?= e($i['billing_address']) ?><?php endif; ?>
</dd></div>
</dl>
<?php if ($i['credit_reason']): ?><p class="credit-reason"><strong><?= e($tr('field.reason')) ?>:</strong> <?= e($i['credit_reason']) ?></p><?php endif; ?>
<div class="table-wrap"><table>
<thead><tr><th><?= e($tr('field.description')) ?></th><th><?= e($tr('field.quantity')) ?></th><th><?= e($tr('field.unit_price')) ?></th><th><?= e($tr('field.total')) ?></th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr><td><?= e($item['description']) ?></td><td><?= e($item['quantity']) ?></td><td><?= e(localizedMoney($item['unit_price'], $i['currency'], $lang)) ?></td><td><?= e(localizedMoney($item['line_total'], $i['currency'], $lang)) ?></td></tr>
<?php endforeach; ?>
<tr class="totals-row"><th colspan="3"><?= e($tr('field.subtotal')) ?></th><td><?= e(localizedMoney($i['subtotal'], $i['currency'], $lang)) ?></td></tr>
<tr class="totals-row"><th colspan="3"><?= e($tr('field.tax')) ?></th><td><?= e(localizedMoney($i['tax_amount'], $i['currency'], $lang)) ?></td></tr>
<tr class="totals-row grand"><th colspan="3"><?= e($tr('field.total')) ?></th><td><?= e(localizedMoney($i['total_amount'], $i['currency'], $lang)) ?></td></tr>
<?php if ($i['invoice_type'] === 'invoice'): ?>
<tr class="totals-row"><th colspan="3"><?= e($tr('field.paid')) ?></th><td><?= e(localizedMoney($i['paid_amount'], $i['currency'], $lang)) ?></td></tr>
<tr class="totals-row<?= $balanceDue > 0 ? ' due' : '' ?>"><th colspan="3"><?= e($tr('print.balance_due')) ?></th><td><?= e(localizedMoney($balanceDue, $i['currency'], $lang)) ?></td></tr>
<?php endif; ?>
</tbody>
</table></div>
<?php if ($i['notes']): ?><p class="document-notes"><strong><?= e($tr('field.notes')) ?>:</strong> <?= e($i['notes']) ?></p><?php endif; ?>
<footer class="document-footer"><?= e($tr('print.thank_you', ['agency' => $i['agency_name']])) ?></footer>
</div>
</div>
</body>
</html>
