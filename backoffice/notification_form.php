<?php
require_once __DIR__ . '/_layout.php';
requirePermission('reservations.manage');

function renderMessageTemplate($template, array $values)
{
    foreach ($values as $key => $value) {
        $template = str_replace('{{' . $key . '}}', (string) $value, $template);
    }
    return $template;
}

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $reservation = dbFetchOne('SELECT r.*,c.first_name,c.last_name,c.email,c.phone,v.registration_number FROM reservations r JOIN customers c ON c.id=r.customer_id LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.id=:id', ['id' => (int) ($_POST['reservation_id'] ?? 0)]);
        if (!$reservation) {
            throw new InvalidArgumentException(t('validation.reservation_not_found'));
        }
        requireAgencyAccess($reservation['agency_id']);
        $type = validateChoice($_POST['notification_type'] ?? '', ['reservation_confirmation','pickup_reminder','return_reminder','late_return','payment_reminder','deposit_return_reminder','vehicle_ready'], 'reservation_confirmation');
        $lang = validateChoice($_POST['language_code'] ?? '', supportedLanguages(), 'fr');
        $template = dbFetchOne("SELECT * FROM notification_templates WHERE notification_type=:type AND language_code=:lang AND (agency_id=:agency OR agency_id IS NULL) AND status='active' ORDER BY agency_id DESC LIMIT 1", ['type'=>$type,'lang'=>$lang,'agency'=>$reservation['agency_id']]);
        $values = ['reference'=>$reservation['reference'],'pickup_at'=>$reservation['pickup_at'],'return_at'=>$reservation['return_at'],'vehicle'=>$reservation['registration_number'],'customer'=>$reservation['first_name'].' '.$reservation['last_name']];
        $message = $template ? renderMessageTemplate($template['message_template'], $values) : translateInLanguage('notification.default_message', $lang, $values);
        $subject = $template ? renderMessageTemplate($template['subject_template'], $values) : translateInLanguage('notification.default_subject', $lang, $values);
        $channel = validateChoice($_POST['channel'] ?? '', ['internal','email','whatsapp'], 'internal');
        $recipient = $channel === 'email' ? $reservation['email'] : $reservation['phone'];
        $status = 'ready';
        if ($channel === 'email' && $recipient) {
            $status = @mail($recipient, $subject, $message, "Content-Type: text/plain; charset=UTF-8\r\n") ? 'sent' : 'failed';
        }
        dbExecute('INSERT INTO notifications(agency_id,reservation_id,notification_type,channel,recipient,language_code,subject,message,status,sent_at,created_by)VALUES(:agency,:reservation,:type,:channel,:recipient,:lang,:subject,:message,:status,:sent_at,:user)', ['agency'=>$reservation['agency_id'],'reservation'=>$reservation['id'],'type'=>$type,'channel'=>$channel,'recipient'=>$recipient,'lang'=>$lang,'subject'=>$subject,'message'=>$message,'status'=>$status,'sent_at'=>$status==='sent'?date('Y-m-d H:i:s'):null,'user'=>currentUserId()]);
        auditLog('notification.created', 'notification', db()->lastInsertId(), null, ['type'=>$type,'channel'=>$channel,'status'=>$status], $reservation['agency_id']);
        flash($status === 'failed' ? 'danger' : 'success', t($status === 'failed' ? 'message.notification_email_failed' : 'message.notification_prepared'));
        safeRedirect('notifications.php');
    } catch (InvalidArgumentException|DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Notification operation failed');
        flash('danger', t('message.notification_failed'));
    }
    safeRedirect('notification_form.php');
}

$ids = currentAgencyIds();
if (!$ids) $ids = [0];
$ph = implode(',', array_fill(0, count($ids), '?'));
$reservations = dbFetchAll("SELECT r.id,r.reference,c.first_name,c.last_name FROM reservations r JOIN customers c ON c.id=r.customer_id WHERE r.agency_id IN ($ph) AND r.status NOT IN ('cancelled','expired') ORDER BY r.created_at DESC LIMIT 200", $ids);

backofficeHeader(t('page.notification_create.title'), 'notifications.php');
pageHeader('page.notification_create.title', 'page.notification_create.description', [
    'breadcrumbs' => [['label'=>'nav.overview'],['label'=>'nav.notifications','href'=>'notifications.php'],['label'=>'page.notification_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'notifications.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <div class="grid">
            <label><?= e(t('field.reservation')) ?><select name="reservation_id"><?php foreach ($reservations as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['reference'] . ' - ' . $r['first_name'] . ' ' . $r['last_name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.type')) ?><select name="notification_type"><?php foreach (['reservation_confirmation','pickup_reminder','return_reminder','late_return','payment_reminder','deposit_return_reminder','vehicle_ready'] as $type): ?><option value="<?= e($type) ?>"><?= e(t('option.' . $type)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.language')) ?><select name="language_code"><?php foreach (supportedLanguages() as $lang): ?><option value="<?= e($lang) ?>" <?= $lang === 'fr' ? 'selected' : '' ?>><?= e(t('language.' . $lang)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('field.channel')) ?><select name="channel"><?php foreach (['internal','email','whatsapp'] as $channel): ?><option value="<?= e($channel) ?>"><?= e(t('option.' . $channel)) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.prepare_send')) ?></button>
            <a class="btn secondary" href="notifications.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
