<?php
require_once __DIR__ . '/_layout.php';
requirePermission('vehicle_damages.create');

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        if (PHP_SAPI === 'cli' && defined('VEHICLE_DAMAGE_CONTROLLER_TEST_HOOK') && VEHICLE_DAMAGE_CONTROLLER_TEST_HOOK === true && function_exists('vehicleDamageControllerTestHook')) {
            vehicleDamageControllerTestHook();
        }
        $raw = $_POST['inspection_id'] ?? null;
        if (!is_string($raw) || !preg_match('/^[1-9]\d*$/', $raw)) {
            throw new InvalidArgumentException(t('validation.vehicle_damage_not_found'));
        }
        vehicleDamageCreate(['inspection_id'=>$raw,'zone'=>$_POST['zone']??null,'damage_type'=>$_POST['damage_type']??null,'description'=>$_POST['description']??null,'severity'=>$_POST['severity']??null,'idempotency_key'=>$_POST['idempotency_key']??null]);
        flash('success', t('message.vehicle_damage_created'));
        safeRedirect('incidents.php');
    } catch (AuthorizationException) {
        flash('danger', t('validation.not_authorized'));
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('Phase 5C.1 vehicle-damage/controller failure: ' . get_class($exception));
        flash('danger', t('message.vehicle_damage_failed'));
    }
    safeRedirect('vehicle_damage_form.php');
}

$damageSources = vehicleDamageEligibleSources(null);

backofficeHeader(t('page.vehicle_damage_create.title'), 'incidents.php');
pageHeader('page.vehicle_damage_create.title', 'page.vehicle_damage_create.description', [
    'breadcrumbs' => [['label'=>'nav.fleet'],['label'=>'nav.incidents','href'=>'incidents.php'],['label'=>'page.vehicle_damage_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'incidents.php'],
]);
?>
<section class="card form-card">
    <p><?= e(t('message.vehicle_damage_source_help')) ?></p>
    <?php if ($damageSources): ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="idempotency_key" value="<?= e(contractIdempotencyToken()) ?>">
        <div class="grid">
            <label class="full"><?= e(t('field.damage_source')) ?><select name="inspection_id" required><?php foreach ($damageSources as $source): ?><option value="<?= e($source['id']) ?>"><?= isolatedValue($source['registration_number'], 'registration-value') ?> &middot; <?= isolatedValue($source['reservation_reference'], 'reference-value') ?> &middot; <?= isolatedValue($source['contract_number'], 'reference-value') ?> &middot; <?= formattedDateTime($source['completed_at']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.zone')) ?><input name="zone" maxlength="60" required></label>
            <label><?= e(t('field.damage_type')) ?><input name="damage_type" maxlength="60" required></label>
            <label class="full"><?= e(t('field.description')) ?><textarea name="description" maxlength="255" required></textarea></label>
            <label><?= e(t('field.severity')) ?><select name="severity" required><?php foreach (vehicleDamageSeverities() as $severity): ?><option value="<?= e($severity) ?>"><?= e(t('option.' . $severity)) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="form-actions">
            <button class="btn danger"><?= e(t('action.create_vehicle_damage')) ?></button>
            <a class="btn secondary" href="incidents.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
    <?php else: ?>
    <?= emptyState('empty.no_eligible_reservations') ?>
    <?php endif; ?>
</section>
<?php backofficeFooter();
