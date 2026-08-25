<?php

function navigationIcon($name)
{
    $paths = [
        'overview'=>'M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z',
        'rentals'=>'M4 5h16v14H4V5Zm3-3v6m10-6v6M4 10h16',
        'customers'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-12a4 4 0 0 1 0 7.75',
        'fleet'=>'M3 11l2-5h14l2 5v7h-2v2h-3v-2H8v2H5v-2H3v-7Zm3 0h12M7 15h.01M17 15h.01',
        'finance'=>'M3 6h18v12H3V6Zm3 4h4m5 4h3',
        'commercial'=>'M20 12V5H4v14h9m3-3 2 2 4-4',
        'analytics'=>'M4 20V10m6 10V4m6 16v-7m5 7H2',
        'admin'=>'M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Zm0-13 1.2 2.2 2.5.4.4 2.5 2.2 1.2-1.2 2.2 1.2 2.2-2.2 1.2-.4 2.5-2.5.4L12 22l-1.2-2.2-2.5-.4-.4-2.5-2.2-1.2 1.2-2.2-1.2-2.2 2.2-1.2.4-2.5 2.5-.4L12 2.5Z',
        'bell'=>'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 12h4',
        'document'=>'M6 2h9l5 5v15H6V2Zm9 0v6h5',
        'profile'=>'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'security'=>'M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm-6-9V6a6 6 0 0 1 12 0v2M5 8h14v13H5V8Z',
    ];
    $path = $paths[$name] ?? $paths['document'];
    return '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . e($path) . '"/></svg>';
}

function backofficeNavigation()
{
    return [
        ['label'=>'nav.overview','icon'=>'overview','items'=>[
            ['label'=>'nav.dashboard','href'=>'index.php','permission'=>'dashboard.view'],
            ['label'=>'nav.notifications','href'=>'notifications.php','permission'=>'reservations.manage'],
            ['label'=>'nav.portal_requests','href'=>'requests.php','permission'=>'reservations.manage'],
            ['label'=>'nav.help','href'=>'help.php','permission'=>'dashboard.view'],
        ]],
        ['label'=>'nav.rentals','icon'=>'rentals','items'=>[
            ['label'=>'nav.reservations','href'=>'reservations.php','permission'=>'reservations.manage'],
            ['label'=>'nav.planning','href'=>'reservation_planning.php','permission'=>'reservations.manage'],
            ['label'=>'nav.contracts','href'=>'contracts.php','permission'=>'contract.view'],
            ['label'=>'nav.inspections','href'=>'inspections.php','permission'=>'inspections.manage'],
        ]],
        ['label'=>'nav.customers','icon'=>'customers','items'=>[
            ['label'=>'nav.customers_list','href'=>'customers.php','permission'=>'customers.manage'],
        ]],
        ['label'=>'nav.fleet','icon'=>'fleet','items'=>[
            ['label'=>'nav.vehicles','href'=>'vehicles.php','permission'=>'vehicles.view'],
            ['label'=>'nav.maintenance','href'=>'maintenance.php','permission'=>'maintenance.manage'],
            ['label'=>'nav.vehicle_documents','href'=>'maintenance.php#vehicle-documents','permission'=>'maintenance.manage','active'=>'vehicle-documents'],
            ['label'=>'nav.incidents','href'=>'incidents.php','permission'=>'vehicles.manage'],
        ]],
        ['label'=>'nav.finance','icon'=>'finance','items'=>[
            ['label'=>'nav.payments','href'=>'finance.php','permission'=>'payments.create'],
            ['label'=>'nav.deposits','href'=>'finance.php#deposits','permission'=>'payments.manage','active'=>'deposits'],
            ['label'=>'nav.invoices','href'=>'invoices.php','permission'=>'invoices.manage'],
            ['label'=>'nav.expenses','href'=>'expenses.php','permission'=>'expenses.manage'],
            ['label'=>'nav.cash_register','href'=>'cash.php','permission'=>'cash.manage'],
        ]],
        ['label'=>'nav.commercial','icon'=>'commercial','items'=>[
            ['label'=>'nav.pricing','href'=>'pricing.php','permission'=>'pricing.manage'],
            ['label'=>'nav.documents','href'=>'documents.php','permission'=>'contracts.manage'],
        ]],
        ['label'=>'nav.analytics','icon'=>'analytics','items'=>[
            ['label'=>'nav.reports','href'=>'reports.php','permission'=>'reports.view'],
        ]],
        ['label'=>'nav.administration','icon'=>'admin','items'=>[
            ['label'=>'nav.agencies','href'=>'agencies.php','permission'=>'agencies.view'],
            ['label'=>'nav.users','href'=>'users.php','permission'=>'users.manage'],
        ]],
    ];
}

function renderBackofficeNavigation($active)
{
    $html = '';
    foreach (backofficeNavigation() as $group) {
        $visible = array_values(array_filter($group['items'], fn($item) => can($item['permission'])));
        if (!$visible) continue;
        $html .= '<section class="nav-group"><h2>' . navigationIcon($group['icon']) . '<span>' . e(uiLabel($group['label'])) . '</span></h2><ul>';
        foreach ($visible as $item) {
            $activeKey = $item['active'] ?? strtok($item['href'], '?#');
            $isActive = $active === $activeKey;
            $html .= '<li><a href="' . e($item['href']) . '"' . ($isActive ? ' class="active" aria-current="page"' : '') . '><span>' . e(uiLabel($item['label'])) . '</span></a></li>';
        }
        $html .= '</ul></section>';
    }
    return $html;
}
