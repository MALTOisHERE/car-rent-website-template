<?php
require_once __DIR__ . '/_components.php';
require_once __DIR__ . '/../app/application.php';
requirePermission('contracts.manage');
$q=dbFetchOne('SELECT r.*,c.first_name,c.last_name,c.email,v.registration_number,v.brand,v.model,a.name agency_name,a.address agency_address FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN vehicles v ON v.id=r.vehicle_id JOIN agencies a ON a.id=r.agency_id WHERE r.id=:id AND r.status=\'quote\'',['id'=>(int)($_GET['id']??0)]);
if(!$q){printNotFound(t('validation.quote_not_found'));}
requireAgencyAccess($q['agency_id']);
$lang=language();
$dir=$lang==='ar'?'rtl':'ltr';
?><!doctype html>
<html lang="<?=e($lang)?>" dir="<?=e($dir)?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($q['reference'])?></title>
<link rel="icon" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>">
</head>
<body>
<div class="print-page">
<div class="document">
<button class="btn secondary" onclick="print()"><?=e(t('common.print_pdf'))?></button>
<header class="document-head">
<div class="document-brand">
<span class="invoice-mark" aria-hidden="true"><?=e(mb_strtoupper(mb_substr((string)$q['agency_name'],0,1)))?></span>
<div><strong><?=e($q['agency_name'])?></strong><?php if(!empty($q['agency_address'])):?><p><?=e($q['agency_address'])?></p><?php endif;?></div>
</div>
<div class="document-title">
<h1><?=e(t('print.quote'))?></h1>
<p class="reference"><?=isolatedValue($q['reference'],'reference-value')?></p>
</div>
</header>
<dl class="document-meta">
<div><dt><?=e(t('print.issued_on'))?></dt><dd><?=formattedDate($q['created_at'])?></dd></div>
<div><dt><?=e(t('field.customer'))?></dt><dd><?=e($q['first_name'].' '.$q['last_name'])?><br><?=e($q['email'])?></dd></div>
<div><dt><?=e(t('field.vehicle'))?></dt><dd><?=e(trim($q['brand'].' '.$q['model']))?><?php if($q['registration_number']):?><br><?=isolatedValue($q['registration_number'],'registration-value')?><?php endif;?></dd></div>
<div><dt><?=e(t('field.pickup'))?></dt><dd><?=formattedDateTime($q['pickup_at'])?></dd></div>
<div><dt><?=e(t('field.return'))?></dt><dd><?=formattedDateTime($q['return_at'])?></dd></div>
</dl>
<div class="table-wrap"><table>
<thead><tr><th><?=e(t('field.description'))?></th><th><?=e(t('field.quantity'))?></th><th><?=e(t('field.unit_price'))?></th><th><?=e(t('field.total'))?></th></tr></thead>
<tbody>
<tr><td><?=e(t('field.rental_days'))?></td><td><?=e($q['rental_days'])?></td><td><?=money($q['daily_price'],$q['currency'])?></td><td><?=money($q['total_amount'],$q['currency'])?></td></tr>
<tr class="totals-row grand"><th colspan="3"><?=e(t('field.total'))?></th><td><?=money($q['total_amount'],$q['currency'])?></td></tr>
</tbody>
</table></div>
<p class="document-notes"><?=e(t('message.quote_disclaimer'))?></p>
<footer class="document-footer">
<p class="document-generated"><?=e(t('print.generated_with'))?> <img src="assets/img/favicon.png" alt="<?=e(appConfig('name'))?>" class="document-generated-mark"></p>
</footer>
</div>
</div>
</body>
</html>
