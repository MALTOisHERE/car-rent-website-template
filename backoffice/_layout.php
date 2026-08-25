<?php
require_once __DIR__ . '/../app/application.php';
require_once __DIR__ . '/_components.php';
require_once __DIR__ . '/_navigation.php';
requireAuthentication('../account/login.php');

if (isset($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

function backofficeLanguageUrl($languageCode)
{
    return languageSwitchUrl($languageCode, basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php')), $_GET);
}

function backofficeHeader($title, $active = '')
{
    $rtl = language() === 'ar';
    $title = uiLabel($title);
    $username = (string) ($_SESSION['username'] ?? '');
    $agencyContext = t('shell.all_assigned_agencies');
    if (isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id'])) {
        $requestedAgencyId = (int) $_GET['agency_id'];
        $requestedAgencyName = dbFetchOne('SELECT name FROM agencies WHERE id=:id', ['id'=>$requestedAgencyId])['name'] ?? null;
        $agencyContext = $requestedAgencyName ?? t('shell.agency_context', ['id'=>$requestedAgencyId]);
    }
    ?><!doctype html><html lang="<?= e(language()) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> — <?= e(appConfig('name')) ?></title>
    <link rel="icon" href="assets/img/aurevo-mark.png">
    <link rel="stylesheet" href="assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>"><script src="assets/app.js?v=<?= e(assetVersion('backoffice/assets/app.js')) ?>" defer></script>
    <script>try{if(localStorage.getItem('sidebarCollapsed')==='1')document.documentElement.classList.add('sidebar-collapsed')}catch(e){}</script>
    </head><body data-table-label="<?= e(t('shell.data_table')) ?>">
    <a class="skip-link" href="#main-content"><?= e(t('shell.skip_to_content')) ?></a>
    <div class="app-shell">
      <div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>
      <aside class="sidebar" id="app-sidebar" aria-label="<?= e(t('shell.primary_navigation')) ?>" data-sidebar>
        <div class="sidebar-header"><a class="product-mark" href="index.php"><img class="product-logo" src="assets/img/aurevo-mark.png" alt="" aria-hidden="true"><span><strong><?= e(appConfig('name')) ?></strong><small><?= e(t('shell.agency_operations')) ?></small></span></a></div>
        <nav class="sidebar-nav"><?= renderBackofficeNavigation($active) ?></nav>
      </aside>
      <div class="app-content">
        <header class="topbar">
          <div class="topbar-leading"><button class="icon-button mobile-menu-button" type="button" data-sidebar-open aria-expanded="true" aria-controls="app-sidebar" data-label-expand="<?= e(t('shell.open_navigation')) ?>" data-label-collapse="<?= e(t('shell.close_navigation')) ?>" aria-label="<?= e(t('shell.close_navigation')) ?>"><span aria-hidden="true">&#9776;</span></button><div><strong><?= e($title) ?></strong><small><?= e($agencyContext) ?></small></div></div>
          <div class="topbar-actions">
            <?php if (can('reservations.manage')): ?><a class="icon-button" href="notifications.php" aria-label="<?= e(t('shell.notifications')) ?>"><?= navigationIcon('bell') ?></a><?php endif; ?>
            <?php $chevron = '<svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'; ?>
            <div class="dropdown"><button class="btn quiet compact" type="button" data-menu-button aria-expanded="false" aria-controls="language-menu" aria-label="<?= e(t('shell.language')) ?>"><span><?= e(strtoupper(language())) ?></span><?= $chevron ?></button><div class="dropdown-menu dropdown-menu-end" id="language-menu" data-menu hidden><?php foreach(supportedLanguages() as $lang):?><a class="dropdown-item" href="<?= e(backofficeLanguageUrl($lang)) ?>" hreflang="<?= e($lang) ?>" lang="<?= e($lang) ?>"><?= e(t('language.' . $lang)) ?></a><?php endforeach;?></div></div>
            <div class="dropdown profile-dropdown"><button class="profile-button" type="button" data-menu-button aria-expanded="false" aria-controls="profile-menu" aria-label="<?= e(t('shell.profile')) ?>"><span class="avatar" aria-hidden="true"><?= e(strtoupper(substr($username,0,1)) ?: 'U') ?></span><span class="profile-copy"><strong><?= e($username) ?></strong><?= roleBadge(currentUserRole()) ?></span><?= $chevron ?></button><div class="dropdown-menu dropdown-menu-end" id="profile-menu" data-menu hidden><div class="dropdown-meta"><?= isolatedValue($_SESSION['email'] ?? '', 'email-value') ?></div><a class="dropdown-item" href="profile.php"><?= e(t('nav.profile')) ?></a><form action="../account/logout.php" method="post"><?= csrfField() ?><button class="dropdown-item dropdown-button" type="submit"><?= e(t('shell.sign_out')) ?></button></form></div></div>
          </div>
        </header>
        <div class="toast-stack" data-toast-stack aria-live="polite"><?= flashMessages() ?></div>
        <main class="page-container" id="main-content" tabindex="-1">
    <?php
}

function backofficeFooter()
{
    ?></main></div></div>
    <dialog class="confirm-dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-message"><form method="dialog"><h2 id="confirm-title"><?= e(t('shell.confirm_action')) ?></h2><p id="confirm-message"><?= e(t('shell.confirm_message')) ?></p><div class="dialog-actions"><button class="btn secondary" value="cancel"><?= e(t('common.cancel')) ?></button><button class="btn danger" value="confirm"><?= e(t('common.confirm')) ?></button></div></form></dialog>
    <aside class="drawer" id="app-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title" hidden data-drawer><div class="drawer-header"><h2 id="drawer-title"><?= e(t('shell.drawer_details')) ?></h2><button class="icon-button" type="button" data-drawer-close aria-label="<?= e(t('shell.close_details')) ?>">&times;</button></div><div class="drawer-body" data-drawer-body></div></aside><div class="drawer-backdrop" data-drawer-backdrop hidden></div>
    </body></html><?php
}
