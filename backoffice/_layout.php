<?php
require_once __DIR__ . '/../app/application.php';
requireAuthentication('../account/login.php');

if (isset($_GET['lang']) && in_array($_GET['lang'], supportedLanguages(), true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

function backofficeHeader($title, $active = '')
{
    $rtl = language() === 'ar';
    $navigation = [
        ['dashboard', 'index.php', 'dashboard.view'], ['customers', 'customers.php', 'customers.manage'],
        ['vehicles', 'vehicles.php', 'vehicles.view'], ['reservations', 'reservations.php', 'reservations.manage'],
        ['contracts', 'contracts.php', 'contracts.manage'], ['payments', 'finance.php', 'payments.create'],
        ['maintenance', 'maintenance.php', 'maintenance.manage'], ['expenses', 'expenses.php', 'expenses.manage'],
        ['reports', 'reports.php', 'reports.view'], ['Users', 'users.php', 'users.manage'],
        ['Inspections', 'inspections.php', 'inspections.manage'], ['Notifications', 'notifications.php', 'reservations.manage'],
        ['Pricing', 'pricing.php', 'reservations.manage'], ['Agencies', 'agencies.php', 'agencies.view'],
        ['Incidents', 'incidents.php', 'vehicles.manage'],
        ['Allocation editor', 'reservation_edit.php', 'reservations.manage'],
        ['Customer files', 'customer_detail.php', 'customers.manage'], ['Portal requests', 'requests.php', 'reservations.manage'],
        ['Documents', 'documents.php', 'contracts.manage'], ['Cash register', 'cash.php', 'payments.manage'],
        ['Invoices', 'invoices.php', 'invoices.manage'],
    ];
    ?><!doctype html><html lang="<?= e(language()) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> — <?= e(appConfig('name')) ?></title><link rel="stylesheet" href="assets/app.css"></head><body>
    <div class="shell"><aside class="sidebar"><div class="brand"><?= e(appConfig('name')) ?></div><nav>
    <?php foreach ($navigation as [$label, $href, $permission]): if (!can($permission)) continue; ?>
    <a class="<?= $active === $href ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e(str_contains($label, '.') ? t($label) : $label) ?></a>
    <?php endforeach; ?></nav></aside><div class="content"><header class="topbar"><div><strong><?= e($title) ?></strong><br><small><?= e($_SESSION['username'] ?? '') ?> · <?= e(currentUserRole()) ?></small></div><div>
    <a href="?lang=en">EN</a> · <a href="?lang=fr">FR</a> · <a href="?lang=ar">AR</a> &nbsp;
    <form action="../account/logout.php" method="post" style="display:inline"><?= csrfField() ?><button class="btn secondary" type="submit">Sign out</button></form>
    </div></header><main class="page">
    <?php foreach (consumeFlashes() as $type => $messages): foreach ($messages as $message): ?><div class="alert <?= e($type) ?>"><?= e($message) ?></div><?php endforeach; endforeach;
}

function backofficeFooter()
{
    ?></main></div></div></body></html><?php
}

function statusBadge($status)
{
    return '<span class="badge ' . e($status) . '">' . e(str_replace('_', ' ', $status)) . '</span>';
}

function money($amount, $currency = null)
{
    return number_format((float) $amount, 2, '.', ' ') . ' ' . e($currency ?? appConfig('currency'));
}
