<?php
require_once __DIR__.'/_components.php';
require_once __DIR__.'/../app/application.php';
requirePermission('contract.view');
$rawId=$_GET['id']??null;$id=is_string($rawId)&&preg_match('/^[1-9]\d*$/',$rawId)?(int)$rawId:0;$lang=validateChoice($_GET['lang']??'',supportedLanguages(),'en');
$detail=null;try{$detail=contractScopedDetail($id);}catch(Throwable$exception){}
if(!$detail||$detail['contract']['status']==='draft'){printNotFound(t('validation.contract_not_found'));}
$contract=$detail['contract'];
$version=dbFetchOne(
    'SELECT * FROM contract_versions WHERE contract_id=:contract AND agency_id=:agency AND version_number=:version AND language_code=:language',
    ['contract'=>$id,'agency'=>$contract['agency_id'],'version'=>$contract['current_version'],'language'=>$lang]
);
if(!$version){printNotFound(t('validation.contract_version_not_found'));}
$data=json_decode($version['snapshot_json'],true)?:[];
$dir=$lang==='ar'?'rtl':'ltr';
$tr=fn($key,$parameters=[])=>translateInLanguage($key,$lang,$parameters);
$currency=$data['currency']??'MAD';
?><!doctype html>
<html lang="<?=e($lang)?>" dir="<?=e($dir)?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($contract['contract_number'])?></title>
<link rel="icon" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>">
</head>
<body>
<div class="print-page">
<div class="document">
<button class="btn secondary" onclick="print()"><?=e($tr('common.print_pdf'))?></button>
<header class="document-head">
<div class="document-brand">
<span class="invoice-mark" aria-hidden="true"><?=e(mb_strtoupper(mb_substr((string)$contract['agency_name'],0,1)))?></span>
<div><strong><?=e($contract['agency_name'])?></strong></div>
</div>
<div class="document-title">
<h1><?=e($tr('print.rental_contract'))?></h1>
<p class="reference"><?=isolatedValue($contract['contract_number'],'reference-value')?></p>
<?=statusBadge($contract['status'],$lang)?>
</div>
</header>
<dl class="document-meta">
<div><dt><?=e($tr('field.version'))?></dt><dd><?=e($version['version_number'])?></dd></div>
<div><dt><?=e($tr('field.pickup'))?></dt><dd><?=e($data['period']['pickup_at']??'')?></dd></div>
<div><dt><?=e($tr('field.return'))?></dt><dd><?=e($data['period']['return_at']??'')?></dd></div>
</dl>
<div class="document-columns">
<div class="document-box"><h3><?=e($tr('field.customer'))?></h3><p>
<?=e($data['customer']['name']??'')?>
<?php if(!empty($data['customer']['identity_number'])):?><br><?=e($tr('field.identity'))?>: <?=isolatedValue($data['customer']['identity_number'],'reference-value')?><?php endif;?>
<?php if(!empty($data['customer']['licence_number'])):?><br><?=e($tr('field.licence'))?>: <?=isolatedValue($data['customer']['licence_number'],'reference-value')?><?php endif;?>
</p></div>
<div class="document-box"><h3><?=e($tr('field.vehicle'))?></h3><p>
<?=e($data['vehicle']['description']??'')?>
<?php if(!empty($data['vehicle']['registration_number'])):?><br><?=e($tr('field.registration'))?>: <?=isolatedValue($data['vehicle']['registration_number'],'registration-value')?><?php endif;?>
<?php if(!empty($data['vehicle']['vin'])):?><br>VIN: <?=isolatedValue($data['vehicle']['vin'],'reference-value')?><?php endif;?>
</p></div>
</div>
<div class="table-wrap"><table>
<tbody>
<tr class="totals-row"><th><?=e($tr('field.deposit'))?></th><td><?=e(localizedMoney($data['deposit_amount']??0,$currency,$lang))?></td></tr>
<tr class="totals-row grand"><th><?=e($tr('field.total'))?></th><td><?=e(localizedMoney($data['pricing']['total']??0,$currency,$lang))?></td></tr>
</tbody>
</table></div>
<section>
<h2><?=e($tr('print.terms'))?></h2>
<p><?=nl2br(e($version['terms_text']))?></p>
</section>
<div class="contract-signatures">
<div class="contract-signature"><?=e($tr('print.customer_signature'))?></div>
<div class="contract-signature"><?=e($tr('print.agency_signature'))?></div>
</div>
</div>
<p class="document-generated"><?=e($tr('print.generated_with'))?> <img src="assets/img/favicon.png" alt="<?=e(appConfig('name'))?>" class="document-generated-mark"></p>
</div>
</body>
</html>
