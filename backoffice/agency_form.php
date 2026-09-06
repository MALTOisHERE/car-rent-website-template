<?php
require_once __DIR__ . '/_layout.php';
requirePermission('agencies.view');

if (!canCreateAgency()) {
    http_response_code(403);
    exit(t('validation.owner_agency_create'));
}

if (requestMethod() === 'POST') {
    requireCsrfPost();
    try {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException(t('validation.agency_required_fields'));
        }
        $subdomain = generateUniqueAgencySlug($name);
        $email = normalizedEmail($_POST['email']??'');
        $phone = trim((string)($_POST['phone']??''));
        $address = trim((string)($_POST['address']??''));
        $city = trim((string)($_POST['city']??''));
        $country = strtoupper(substr(trim((string)($_POST['country_code']??'MA')),0,2));
        $currency = strtoupper(substr(trim((string)($_POST['currency']??'MAD')),0,3));
        $timezone = trim((string)($_POST['timezone']??'Africa/Casablanca'));
        // The code embeds the agency's real database id ("{id}-{NAME}"), which
        // only exists after the row is inserted -- insert with a throwaway
        // placeholder code first, then overwrite it with the id-based one,
        // both inside one transaction so the placeholder is never visible outside it.
        $code = null;
        $id = withTransaction(function () use ($name, $subdomain, $email, $phone, $address, $city, $country, $currency, $timezone, &$code) {
            dbExecute(
                "INSERT INTO agencies(name,code,subdomain,email,phone,address,city,country_code,currency,timezone,status)
                 VALUES(:name,:code,:subdomain,:email,:phone,:address,:city,:country,:currency,:timezone,'active')",
                ['name'=>$name,'code'=>'TMP-'.bin2hex(random_bytes(8)),'subdomain'=>$subdomain,'email'=>$email,'phone'=>$phone,'address'=>$address,'city'=>$city,'country'=>$country,'currency'=>$currency,'timezone'=>$timezone]
            );
            $newId = (int) db()->lastInsertId();
            $code = agencyCodeFromId($newId, $name);
            dbExecute('UPDATE agencies SET code=:code WHERE id=:id', ['code'=>$code,'id'=>$newId]);
            return $newId;
        });
        auditLog('agency.created', 'agency', $id, null, ['name'=>$name,'code'=>$code,'subdomain'=>$subdomain], $id);
        $baseDomain = appConfig('platform_base_domain');
        flash('success', $baseDomain !== ''
            ? t('message.agency_created_with_subdomain', ['url'=>$subdomain.'.'.$baseDomain])
            : t('message.agency_created_with_slug', ['slug'=>$subdomain]));
        safeRedirect('agencies.php');
    } catch (InvalidArgumentException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Agency operation failed');
        flash('danger', t('message.agency_failed'));
    }
}

backofficeHeader(t('page.agency_create.title'), 'agencies.php');
pageHeader('page.agency_create.title', 'page.agency_create.description', [
    'breadcrumbs' => [['label'=>'nav.overview','href'=>'index.php'],['label'=>'nav.agencies','href'=>'agencies.php'],['label'=>'page.agency_create.title']],
    'secondary' => ['label'=>'common.cancel','href'=>'agencies.php'],
]);
?>
<section class="card form-card">
    <form method="post">
        <?= csrfField() ?>
        <div class="grid">
            <label><?= e(t('field.name')) ?><input name="name" required></label>
            <label><?= e(t('field.email')) ?><input type="email" name="email"></label>
            <label><?= e(t('field.phone')) ?><input name="phone"></label>
            <label><?= e(t('field.address')) ?><input name="address"></label>
            <label><?= e(t('field.city')) ?><input name="city"></label>
            <label><?= e(t('field.country_code')) ?><input name="country_code" value="MA" maxlength="2"></label>
            <label><?= e(t('field.currency')) ?><input name="currency" value="MAD" maxlength="3"></label>
            <label><?= e(t('field.timezone')) ?><input name="timezone" value="Africa/Casablanca"></label>
        </div>
        <div class="form-actions">
            <button class="btn primary"><?= e(t('action.add_agency')) ?></button>
            <a class="btn secondary" href="agencies.php"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
<?php backofficeFooter();
