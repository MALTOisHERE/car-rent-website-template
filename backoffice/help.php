<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/help_content.php';
requirePermission('dashboard.view');

$lang = language();
$modules = helpGlossaryModules();

backofficeHeader('nav.help', 'help.php');
pageHeader('page.help.title', 'page.help.description', ['breadcrumbs' => [['label' => 'nav.overview'], ['label' => 'nav.help']]]);
?>
<nav class="help-jump" aria-label="<?= e(t('nav.help')) ?>">
    <?php foreach ($modules as $module): ?>
    <a href="#help-<?= e($module['id']) ?>"><?= e(t($module['item'])) ?></a>
    <?php endforeach; ?>
</nav>

<?php foreach ($modules as $module): ?>
<section class="card help-module" id="help-<?= e($module['id']) ?>">
    <div class="section-card-header">
        <h2><?= navigationIcon($module['icon']) ?><?= e(t($module['item'])) ?></h2>
        <?php if ($module['group'] !== $module['item']): ?>
        <span class="help-module-tag"><?= e(t($module['group'])) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($module['statuses']): ?>
    <p class="help-subhead"><?= e(t('common.status')) ?></p>
    <div class="help-status-grid">
        <?php foreach ($module['statuses'] as $item): ?>
        <div class="help-status-item"><?= statusBadge($item['status'], $lang) ?><p><?= e($item['note'][$lang] ?? $item['note']['en']) ?></p></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($module['terms']): ?>
    <p class="help-subhead"><?= e(t('field.description')) ?></p>
    <dl class="detail-list help-terms">
        <?php foreach ($module['terms'] as $item): ?>
        <dt><?= e(t($item['label'])) ?></dt>
        <dd><?= e($item['note'][$lang] ?? $item['note']['en']) ?></dd>
        <?php endforeach; ?>
    </dl>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<?php backofficeFooter(); ?>
