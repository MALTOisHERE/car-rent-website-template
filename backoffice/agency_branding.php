<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../app/agency_content.php';
require_once __DIR__ . '/../app/agency_content_fields.php';
requirePermission('agencies.view');

$agencyIds = currentAgencyIds();
$agencies = dbFetchAll('SELECT id,name FROM agencies WHERE archived_at IS NULL ORDER BY name');
$agencyId = (int) ($_GET['agency_id'] ?? ($agencyIds[0] ?? 0));
requireAgencyAccess($agencyId);
$agency = dbFetchOne('SELECT * FROM agencies WHERE id=:id AND archived_at IS NULL', ['id' => $agencyId]);
if (!$agency) {
    http_response_code(404);
    exit(t('validation.agency_required_fields'));
}

$sections = agencyContentSections();
$sectionKey = (string) ($_GET['section'] ?? array_key_first($sections));
if (!isset($sections[$sectionKey])) {
    $sectionKey = array_key_first($sections);
}
$contentLanguage = (string) ($_GET['content_lang'] ?? language());
if (!in_array($contentLanguage, supportedLanguages(), true)) {
    $contentLanguage = language();
}

if (requestMethod() === 'POST') {
    requireCsrfPost();
    $formAction = (string) ($_POST['form_action'] ?? '');
    try {
        if ($formAction === 'branding') {
            $updates = ['primary_color' => null, 'secondary_color' => null, 'accent_dark_color' => null];
            foreach (['primary_color', 'secondary_color', 'accent_dark_color'] as $field) {
                $value = trim((string) ($_POST[$field] ?? ''));
                $updates[$field] = isValidHexColor($value) ? $value : null;
            }
            $logoPath = $agency['logo_path'];
            if (!empty($_FILES['logo']['name'])) {
                $stored = storeValidatedImage($_FILES['logo'], 'agency_logos');
                $logoPath = $stored['path'];
            }
            dbExecute(
                'UPDATE agencies SET primary_color=:primary, secondary_color=:secondary, accent_dark_color=:dark, logo_path=:logo WHERE id=:id',
                ['primary' => $updates['primary_color'], 'secondary' => $updates['secondary_color'], 'dark' => $updates['accent_dark_color'], 'logo' => $logoPath, 'id' => $agencyId]
            );
            auditLog('agency.branding_updated', 'agency', $agencyId, null, $updates, $agencyId);
            flash('success', t('message.agency_branding_updated'));
        } elseif ($formAction === 'content') {
            $postedSection = (string) ($_POST['section'] ?? '');
            $postedLanguage = (string) ($_POST['content_lang'] ?? '');
            if (!isset($sections[$postedSection]) || !in_array($postedLanguage, supportedLanguages(), true)) {
                throw new InvalidArgumentException(t('validation.invalid_status'));
            }
            $values = [];
            foreach ($sections[$postedSection]['fields'] as $field) {
                $values[$field['key']] = trim((string) ($_POST['field'][$field['key']] ?? ''));
            }
            saveAgencyPageContent($agencyId, $postedSection, $postedLanguage, $values, currentUserId());
            auditLog('agency.content_updated', 'agency', $agencyId, null, ['section' => $postedSection, 'language' => $postedLanguage], $agencyId);
            flash('success', t('message.agency_content_updated'));
            $sectionKey = $postedSection;
            $contentLanguage = $postedLanguage;
        }
    } catch (InvalidArgumentException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        reportDatabaseError($exception, 'Agency branding operation failed');
        flash('danger', t('message.agency_failed'));
    }
    safeRedirect('agency_branding.php?' . http_build_query(['agency_id' => $agencyId, 'section' => $sectionKey, 'content_lang' => $contentLanguage]));
}

$currentContent = agencyPageContent($agencyId, $sectionKey, $contentLanguage);
$filledLanguages = array_column(
    dbFetchAll('SELECT DISTINCT language_code FROM agency_page_content WHERE agency_id=:agency AND page=:page', ['agency' => $agencyId, 'page' => $sectionKey]),
    'language_code'
);

backofficeHeader('nav.agencies', 'agency_branding.php');
pageHeader('page.agency_branding.title', 'page.agency_branding.description', [
    'breadcrumbs' => [['label' => 'nav.overview', 'href' => 'index.php'], ['label' => 'nav.agencies', 'href' => 'agencies.php'], ['label' => 'page.agency_branding.title']],
]);
?>
<?php if (count($agencies) > 1): ?>
<form class="filters"><label><?= e(t('field.agency')) ?><select name="agency_id" onchange="this.form.submit()"><?php foreach ($agencies as $a): if (currentUserRole() !== ROLE_OWNER && !in_array((int) $a['id'], $agencyIds, true)) continue; ?><option value="<?= e($a['id']) ?>" <?= $agencyId === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label><input type="hidden" name="section" value="<?= e($sectionKey) ?>"><input type="hidden" name="content_lang" value="<?= e($contentLanguage) ?>"><noscript><button class="btn primary"><?= e(t('action.apply')) ?></button></noscript></form>
<?php endif; ?>

<div class="grid">
<section class="card">
    <div class="section-card-header"><h2><?= navigationIcon('admin') ?><?= e(t('section.agency_branding')) ?></h2></div>

    <div class="brand-preview" id="brandPreview" style="--preview-primary:<?= e($agency['primary_color'] ?: '#011468') ?>;--preview-secondary:<?= e($agency['secondary_color'] ?: '#011468') ?>;--preview-dark:<?= e($agency['accent_dark_color'] ?: '#00104f') ?>">
        <p class="brand-preview-heading"><?= e(t('field.preview_heading')) ?></p>
        <div class="brand-preview-row">
            <span class="brand-preview-btn"><?= e(t('field.preview_button')) ?></span>
            <span class="brand-preview-chip"><?= e(t('field.preview_badge')) ?></span>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="brandingForm">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="branding">

        <div class="logo-upload-row">
            <div class="logo-preview<?= $agency['logo_path'] ? '' : ' empty' ?>" id="logoPreview">
                <?php if ($agency['logo_path']): ?>
                <img src="../site/agency_logo.php?id=<?= e($agencyId) ?>" alt="">
                <?php else: ?>
                <span><?= e(t('field.no_logo')) ?></span>
                <?php endif; ?>
            </div>
            <label class="logo-upload-field"><?= e(t('field.logo')) ?>
                <input type="file" name="logo" id="logoInput" accept="image/jpeg,image/png,image/webp">
                <span class="field-hint"><?= e(t('field.logo_hint')) ?></span>
            </label>
        </div>

        <div class="brand-color-grid">
            <label class="brand-color-field">
                <input type="color" name="primary_color" id="primaryColor" value="<?= e($agency['primary_color'] ?: '#011468') ?>">
                <span class="brand-color-copy"><span><?= e(t('field.primary_color')) ?></span><code id="primaryColorHex"><?= e(strtoupper($agency['primary_color'] ?: '#011468')) ?></code></span>
            </label>
            <label class="brand-color-field">
                <input type="color" name="secondary_color" id="secondaryColor" value="<?= e($agency['secondary_color'] ?: '#011468') ?>">
                <span class="brand-color-copy"><span><?= e(t('field.secondary_color')) ?></span><code id="secondaryColorHex"><?= e(strtoupper($agency['secondary_color'] ?: '#011468')) ?></code></span>
            </label>
            <label class="brand-color-field">
                <input type="color" name="accent_dark_color" id="darkColor" value="<?= e($agency['accent_dark_color'] ?: '#00104f') ?>">
                <span class="brand-color-copy"><span><?= e(t('field.accent_dark_color')) ?></span><code id="darkColorHex"><?= e(strtoupper($agency['accent_dark_color'] ?: '#00104f')) ?></code></span>
            </label>
        </div>

        <button class="btn primary"><?= e(t('common.save')) ?></button>
    </form>
</section>

<section class="card">
    <div class="section-card-header"><h2><?= navigationIcon('document') ?><?= e(t('section.agency_content')) ?></h2></div>
    <form class="filters">
        <input type="hidden" name="agency_id" value="<?= e($agencyId) ?>">
        <label><?= e(t('common.status')) ?><select name="section" onchange="this.form.submit()"><?php foreach ($sections as $key => $definition): ?><option value="<?= e($key) ?>" <?= $sectionKey === $key ? 'selected' : '' ?>><?= e($definition['label']) ?></option><?php endforeach; ?></select></label>
        <label><?= e(t('field.language')) ?><select name="content_lang" onchange="this.form.submit()"><?php foreach (supportedLanguages() as $lang): ?><option value="<?= e($lang) ?>" <?= $contentLanguage === $lang ? 'selected' : '' ?>><?= e(t('language.' . $lang)) ?></option><?php endforeach; ?></select></label>
        <noscript><button class="btn primary"><?= e(t('action.apply')) ?></button></noscript>
    </form>

    <p class="help-subhead"><?= e(t('field.content_status')) ?></p>
    <div class="lang-status-row">
        <?php foreach (supportedLanguages() as $lang): $filled = in_array($lang, $filledLanguages, true); ?>
        <span class="lang-status-pill<?= $filled ? ' filled' : '' ?><?= $lang === $contentLanguage ? ' current' : '' ?>"><span class="badge-dot"></span><?= e(t('language.' . $lang)) ?> - <?= e(t($filled ? 'field.content_status_filled' : 'field.content_status_empty')) ?></span>
        <?php endforeach; ?>
    </div>

    <form method="post" class="stack-top">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="content">
        <input type="hidden" name="section" value="<?= e($sectionKey) ?>">
        <input type="hidden" name="content_lang" value="<?= e($contentLanguage) ?>">
        <?php foreach ($sections[$sectionKey]['fields'] as $field): ?>
        <label><?= e($field['label']) ?>
            <?php if ($field['type'] === 'textarea'): ?>
            <textarea name="field[<?= e($field['key']) ?>]"><?= e($currentContent[$field['key']] ?? '') ?></textarea>
            <?php else: ?>
            <input type="text" name="field[<?= e($field['key']) ?>]" value="<?= e($currentContent[$field['key']] ?? '') ?>">
            <?php endif; ?>
        </label>
        <?php endforeach; ?>
        <button class="btn primary"><?= e(t('common.save')) ?></button>
    </form>
</section>
</div>

<script>
(function () {
    var form = document.getElementById('brandingForm');
    if (!form) return;
    var swatches = [
        ['primaryColor', 'primaryColorHex', '--preview-primary'],
        ['secondaryColor', 'secondaryColorHex', '--preview-secondary'],
        ['darkColor', 'darkColorHex', '--preview-dark']
    ];
    var preview = document.getElementById('brandPreview');
    swatches.forEach(function (entry) {
        var input = document.getElementById(entry[0]);
        var hex = document.getElementById(entry[1]);
        if (!input) return;
        input.addEventListener('input', function () {
            hex.textContent = input.value.toUpperCase();
            if (preview) preview.style.setProperty(entry[2], input.value);
        });
    });
    var logoInput = document.getElementById('logoInput');
    var logoPreview = document.getElementById('logoPreview');
    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', function () {
            var file = logoInput.files && logoInput.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function () {
                logoPreview.classList.remove('empty');
                logoPreview.innerHTML = '<img src="' + reader.result + '" alt="">';
            };
            reader.readAsDataURL(file);
        });
    }
})();
</script>
<?php backofficeFooter();
