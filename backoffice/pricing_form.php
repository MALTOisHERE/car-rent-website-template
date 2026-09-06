<?php
require_once __DIR__ . '/_layout.php';
requirePermission('pricing.manage');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $agencyId = (int) ($_POST['agency_id'] ?? 0);
        requireAgencyAccess($agencyId);
        $value = positiveMoney($_POST['adjustment_value'] ?? '');
        if ($value === null) {
            throw new InvalidArgumentException(t('validation.pricing_value'));
        }
        dbExecute("INSERT INTO pricing_rules(agency_id,category_id,name,rule_type,priority,starts_at,ends_at,conditions_json,adjustment_type,adjustment_value,status,created_by)VALUES(:agency,:category,:name,:type,:priority,:starts,:ends,:conditions,:adjustment_type,:value,'active',:user)", ['agency'=>$agencyId,'category'=>($_POST['category_id']??'')?:null,'name'=>trim((string)($_POST['name']??'')),'type'=>validateChoice($_POST['rule_type']??'',['seasonal','weekend','holiday','duration','business','partner','loyalty','delivery','young_driver','mileage','late_return','other'],'other'),'priority'=>(int)($_POST['priority']??100),'starts'=>($_POST['starts_at']??'')?:null,'ends'=>($_POST['ends_at']??'')?:null,'conditions'=>json_encode(['minimum_days'=>(int)($_POST['minimum_days']??0),'maximum_days'=>(int)($_POST['maximum_days']??0)],JSON_UNESCAPED_UNICODE),'adjustment_type'=>validateChoice($_POST['adjustment_type']??'',['fixed','percentage'],'fixed'),'value'=>$value,'user'=>currentUserId()]);
        auditLog('pricing_rule.created', 'pricing_rule', db()->lastInsertId(), null, ['name'=>$_POST['name'],'value'=>$value], $agencyId);
        flash('success', t('message.pricing_created'));
        safeRedirect('pricing.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Pricing operation failed');
        flash('danger', t('message.pricing_failed'));
    }
    safeRedirect('pricing_form.php');
}

$ids = currentAgencyIds();
if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$agencies = dbFetchAll("SELECT id,name FROM agencies WHERE id IN ($ph)", $ids);
$categories = dbFetchAll("SELECT id,name FROM vehicle_categories WHERE agency_id IN ($ph) OR agency_id IS NULL", $ids);

backofficeHeader(t('page.pricing_create.title'), 'pricing.php');
pageHeader('page.pricing_create.title', 'page.pricing_create.description', [
    'breadcrumbs' => [['label'=>'nav.commercial'],['label'=>'nav.pricing','href'=>'pricing.php'],['label'=>'page.pricing_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'pricing.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <div class="grid">
            <label><?= e(t('field.agency')) ?><select name="agency_id"><?php foreach ($agencies as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.category')) ?><select name="category_id"><option value=""><?= e(t('common.all_categories')) ?></option><?php foreach ($categories as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.name')) ?><input name="name" required></label>
            <label><?= e(t('field.rule_type')) ?><select name="rule_type"><?php foreach (['seasonal','weekend','holiday','duration','business','partner','loyalty','delivery','young_driver','mileage','late_return','other'] as $type): ?><option value="<?= e($type) ?>"><?= e(t('option.' . $type)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.minimum_days')) ?><input type="number" name="minimum_days" min="0"></label>
            <label><?= e(t('field.maximum_days')) ?><input type="number" name="maximum_days" min="0"></label>
            <label><?= e(t('field.start_date')) ?><input type="datetime-local" name="starts_at"></label>
            <label><?= e(t('field.end_date')) ?><input type="datetime-local" name="ends_at"></label>
            <label><?= e(t('field.adjustment')) ?><select name="adjustment_type"><option value="fixed"><?= e(t('option.fixed')) ?></option><option value="percentage"><?= e(t('option.percentage')) ?></option></select></label>
            <label><?= e(t('field.value')) ?><input name="adjustment_value" required type="number" step="0.01" inputmode="decimal"></label>
            <label><?= e(t('field.priority')) ?><input type="number" name="priority" value="100"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.create_rule')) ?></button>
            <a class="btn secondary" href="pricing.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
