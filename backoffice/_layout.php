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
    $query = $_GET;
    $query['lang'] = $languageCode;
    return '?' . http_build_query($query);
}

function backofficeHeader($title, $active = '')
{
    $rtl = language() === 'ar';
    $title = uiLabel($title);
    $username = (string) ($_SESSION['username'] ?? '');
    $agencyContext = isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id'])
        ? 'Agency #' . (int) $_GET['agency_id'] : 'All assigned agencies';
    ?><!doctype html><html lang="<?= e(language()) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> — <?= e(appConfig('name')) ?></title>
    <link rel="stylesheet" href="assets/app.css"><script src="assets/app.js" defer></script></head><body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell">
      <div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>
      <aside class="sidebar" id="app-sidebar" aria-label="Primary navigation" data-sidebar>
        <div class="sidebar-header"><a class="product-mark" href="index.php"><span class="product-logo" aria-hidden="true">R</span><span><strong><?= e(appConfig('name')) ?></strong><small>Agency operations</small></span></a><button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">&times;</button></div>
        <nav class="sidebar-nav"><?= renderBackofficeNavigation($active) ?></nav>
        <div class="sidebar-footer"><div class="sidebar-user"><span class="avatar" aria-hidden="true"><?= e(strtoupper(substr($username, 0, 1)) ?: 'U') ?></span><span><strong><?= e($username) ?></strong><?= roleBadge(currentUserRole()) ?></span></div><form action="../account/logout.php" method="post"><?= csrfField() ?><button class="btn quiet sidebar-logout" type="submit">Sign out</button></form></div>
      </aside>
      <div class="app-content">
        <header class="topbar">
          <div class="topbar-leading"><button class="icon-button mobile-menu-button" type="button" data-sidebar-open aria-expanded="false" aria-controls="app-sidebar" aria-label="Open navigation"><span aria-hidden="true">&#9776;</span></button><div><strong><?= e($title) ?></strong><small><?= e($agencyContext) ?></small></div></div>
          <div class="topbar-actions">
            <?php if (can('reservations.manage')): ?><a class="icon-button" href="notifications.php" aria-label="Notifications"><?= navigationIcon('bell') ?></a><?php endif; ?>
            <div class="dropdown"><button class="btn quiet compact" type="button" data-menu-button aria-expanded="false" aria-controls="language-menu"><?= e(strtoupper(language())) ?><span aria-hidden="true">⌄</span></button><div class="dropdown-menu dropdown-menu-end" id="language-menu" data-menu hidden><?php foreach(supportedLanguages() as $lang):?><a class="dropdown-item" href="<?= e(backofficeLanguageUrl($lang)) ?>" hreflang="<?= e($lang) ?>" lang="<?= e($lang) ?>"><?= e(['en'=>'English','fr'=>'Français','ar'=>'العربية'][$lang]) ?></a><?php endforeach;?></div></div>
            <div class="dropdown profile-dropdown"><button class="profile-button" type="button" data-menu-button aria-expanded="false" aria-controls="profile-menu"><span class="avatar" aria-hidden="true"><?= e(strtoupper(substr($username,0,1)) ?: 'U') ?></span><span class="profile-copy"><strong><?= e($username) ?></strong><?= roleBadge(currentUserRole()) ?></span><span aria-hidden="true">⌄</span></button><div class="dropdown-menu dropdown-menu-end" id="profile-menu" data-menu hidden><div class="dropdown-meta"><?= e($_SESSION['email'] ?? '') ?></div><form action="../account/logout.php" method="post"><?= csrfField() ?><button class="dropdown-item dropdown-button" type="submit">Sign out</button></form></div></div>
          </div>
        </header>
        <main class="page-container" id="main-content" tabindex="-1"><?= flashMessages() ?>
    <?php
}

function backofficeFooter()
{
    ?></main></div></div>
    <dialog class="confirm-dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-message"><form method="dialog"><h2 id="confirm-title">Confirm action</h2><p id="confirm-message">Are you sure you want to continue?</p><div class="dialog-actions"><button class="btn secondary" value="cancel">Cancel</button><button class="btn danger" value="confirm">Confirm</button></div></form></dialog>
    <aside class="drawer" id="app-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title" hidden data-drawer><div class="drawer-header"><h2 id="drawer-title">Details</h2><button class="icon-button" type="button" data-drawer-close aria-label="Close">&times;</button></div><div class="drawer-body" data-drawer-body></div></aside><div class="drawer-backdrop" data-drawer-backdrop hidden></div>
    </body></html><?php
}
