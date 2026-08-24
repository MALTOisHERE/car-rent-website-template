<?php

function uiLabel($label)
{
    $label = (string) $label;
    $legacyKeys = [
        'Dashboard'=>'page.dashboard.title','Customers'=>'page.customers.title','Customer files'=>'page.customer_detail.title',
        'Vehicles'=>'page.vehicles.title','Reservations'=>'page.reservations.title','Reservation allocation'=>'page.reservation_edit.title',
        'Contracts'=>'page.contracts.title','Inspections'=>'page.inspections.title','Checkout and return inspections'=>'page.inspections.title',
        'Payments, deposits, and invoices'=>'page.finance.title','Invoices'=>'page.invoices.title','Invoice register'=>'page.invoices.title',
        'Expenses and profitability'=>'page.expenses.title','Maintenance and vehicle documents'=>'page.maintenance.title',
        'Notifications'=>'page.notifications.title','Management reports'=>'page.reports.title','Reports'=>'page.reports.title',
        'Employees and roles'=>'page.users.title','Users'=>'nav.users','Agencies'=>'page.agencies.title','Pricing'=>'page.pricing.title',
        'Pricing rules'=>'page.pricing.title','Incidents'=>'page.incidents.title','Fines, accidents, and claims'=>'page.incidents.title',
        'Cash register'=>'page.cash.title','Daily cash register'=>'page.cash.title','Documents'=>'page.documents.title',
        'Quotes and contract documents'=>'page.documents.title','Portal requests'=>'page.requests.title','Customer portal requests'=>'page.requests.title',
        'Manage customer identity, contact, licence, and rental eligibility.'=>'page.customers.description',
        'Review customer documents, additional drivers, rentals, and payments.'=>'page.customer_detail.description',
        'Manage fleet availability, pricing, status, and specifications.'=>'page.vehicles.description',
        'Create, allocate, and progress rentals through their operational lifecycle.'=>'page.reservations.description',
        'Generate, sign, amend, and print rental agreements.'=>'page.contracts.description',
        'Capture immutable vehicle condition, photos, mileage, fuel, and damage.'=>'page.inspections.description',
        'Record and approve operating costs with fleet profitability context.'=>'page.expenses.description',
        'Schedule fleet work and monitor operational document expiry.'=>'page.maintenance.description',
        'Prepare customer messages and review delivery history.'=>'page.notifications.description',
        'Review revenue, expenses, utilization, and reservation performance.'=>'page.reports.description',
        'Manage staff access, agency assignments, and account status.'=>'page.users.description',
        'Configure authorized server-side pricing adjustments and validity periods.'=>'page.pricing.description',
        'Record fleet incidents and preserve their financial and contract context.'=>'page.incidents.description',
        'Open printable commercial documents for your assigned agencies.'=>'page.documents.description',
        'Review and resolve customer modification and cancellation requests.'=>'page.requests.description',
        'Overview'=>'nav.overview','Rentals'=>'nav.rentals','Fleet'=>'nav.fleet','Finance'=>'nav.finance','Commercial'=>'nav.commercial',
        'Analytics'=>'nav.analytics','Administration'=>'nav.administration','Add expense'=>'action.add_expense',
        'Schedule maintenance'=>'action.add_maintenance','Prepare notification'=>'common.create','Add pricing rule'=>'action.add_rule',
        'Record incident'=>'action.add_incident','Add inspection'=>'action.add_inspection','Export CSV'=>'action.export_csv',
    ];
    if (isset($legacyKeys[$label])) return t($legacyKeys[$label]);
    return hasTranslation($label) || hasTranslation($label, 'en') ? t($label) : $label;
}

function isolatedValue($value, $class = '')
{
    $class = trim('bidi-value ' . (string) $class);
    return '<bdi class="' . e($class) . '">' . e($value) . '</bdi>';
}

function formattedDate($value)
{
    $formatted = localizedDate($value);
    return $formatted === '' ? '' : isolatedValue($formatted, 'date-value');
}

function formattedDateTime($value)
{
    $formatted = localizedDateTime($value);
    return $formatted === '' ? '' : isolatedValue($formatted, 'date-value');
}

function uiAttributes(array $attributes)
{
    $html = '';
    foreach ($attributes as $name => $value) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9:_-]*$/', (string) $name) || $value === false || $value === null) {
            continue;
        }
        if ($value === true) {
            $html .= ' ' . e($name);
        } else {
            $html .= ' ' . e($name) . '="' . e($value) . '"';
        }
    }
    return $html;
}

function breadcrumb(array $items)
{
    if ($items === []) return '';
    $html = '<nav class="breadcrumbs" aria-label="' . e(t('shell.breadcrumb')) . '"><ol>';
    $last = count($items) - 1;
    foreach (array_values($items) as $index => $item) {
        $label = uiLabel($item['label'] ?? '');
        $href = (string) ($item['href'] ?? '');
        $html .= '<li>';
        if ($href !== '' && $index !== $last) {
            $html .= '<a href="' . e($href) . '">' . e($label) . '</a>';
        } else {
            $html .= '<span' . ($index === $last ? ' aria-current="page"' : '') . '>' . e($label) . '</span>';
        }
        $html .= '</li>';
    }
    return $html . '</ol></nav>';
}

function primaryAction($label, $href, array $attributes = [])
{
    $attributes['class'] = trim('btn primary ' . ($attributes['class'] ?? ''));
    return '<a href="' . e($href) . '"' . uiAttributes($attributes) . '>' . e(uiLabel($label)) . '</a>';
}

function secondaryAction($label, $href, array $attributes = [])
{
    $attributes['class'] = trim('btn secondary ' . ($attributes['class'] ?? ''));
    return '<a href="' . e($href) . '"' . uiAttributes($attributes) . '>' . e(uiLabel($label)) . '</a>';
}

function pageHeader($title, $description = '', array $options = [])
{
    $breadcrumbs = $options['breadcrumbs'] ?? [];
    $primary = $options['primary'] ?? null;
    $secondary = $options['secondary'] ?? null;
    $metadata = $options['metadata'] ?? '';
    echo '<div class="page-heading">';
    if ($breadcrumbs) echo breadcrumb($breadcrumbs);
    echo '<div class="page-heading-row"><div class="page-heading-copy"><h1>' . e(uiLabel($title)) . '</h1>';
    if ($description !== '') echo '<p>' . e(uiLabel($description)) . '</p>';
    if ($metadata !== '') echo '<div class="page-metadata">' . e($metadata) . '</div>';
    echo '</div>';
    if ($primary || $secondary) {
        echo '<div class="page-actions">';
        if (is_array($secondary)) echo secondaryAction($secondary['label'] ?? '', $secondary['href'] ?? '#', $secondary['attributes'] ?? []);
        elseif (is_string($secondary)) echo $secondary;
        if (is_array($primary)) echo primaryAction($primary['label'] ?? '', $primary['href'] ?? '#', $primary['attributes'] ?? []);
        elseif (is_string($primary)) echo $primary;
        echo '</div>';
    }
    echo '</div></div>';
}

function roleBadge($role)
{
    $role = normalizeRole($role);
    return '<span class="role-badge role-' . e(strtolower(str_replace('_', '-', $role))) . '">' . e(translatedRole($role)) . '</span>';
}

function statusBadge($status)
{
    $status = strtolower(trim((string) $status));
    $semantic = [
        'active'=>'success','available'=>'success','completed'=>'success','paid'=>'success','approved'=>'success','sent'=>'success','signed'=>'success','returned'=>'success',
        'pending'=>'warning','ready'=>'warning','scheduled'=>'warning','maintenance'=>'warning','requested'=>'warning','quote'=>'info','generated'=>'info','held'=>'info','reserved'=>'info','rented'=>'info','in_progress'=>'info','amended'=>'info',
        'cancelled'=>'danger','damaged'=>'danger','blocked'=>'danger','failed'=>'danger','rejected'=>'danger','expired'=>'danger','disputed'=>'danger','no_show'=>'danger','inactive'=>'neutral','retired'=>'neutral','sold'=>'neutral','draft'=>'neutral',
    ];
    $class = $semantic[$status] ?? 'neutral';
    return '<span class="badge badge-' . e($class) . '"><span class="badge-dot" aria-hidden="true"></span>' . e(translatedStatus($status)) . '</span>';
}

function emptyState($title = 'empty.no_records', $description = '', $action = '')
{
    $html = '<div class="empty-state"><span class="empty-state-icon" aria-hidden="true">&#9675;</span><h3>' . e(uiLabel($title)) . '</h3>';
    if ($description !== '') $html .= '<p>' . e(uiLabel($description)) . '</p>';
    if ($action !== '') $html .= '<div class="empty-state-action">' . $action . '</div>';
    return $html . '</div>';
}

function pagination($page, $hasNext, $baseUrl, array $query = [])
{
    $page = max(1, (int) $page);
    $links = [];
    if ($page > 1) $links[] = ['label'=>'common.previous','page'=>$page - 1];
    if ($hasNext) $links[] = ['label'=>'common.next','page'=>$page + 1];
    if (!$links) return '';
    $html = '<nav class="pagination" aria-label="' . e(t('shell.pagination')) . '">';
    foreach ($links as $link) {
        $href = $baseUrl . '?' . http_build_query(array_merge($query, ['page'=>$link['page']]));
        $html .= '<a class="btn quiet" href="' . e($href) . '">' . e(uiLabel($link['label'])) . '</a>';
    }
    return $html . '</nav>';
}

function actionMenu(array $items, $label = 'common.actions')
{
    static $counter = 0; $counter++;
    $id = 'action-menu-' . $counter;
    $html = '<div class="dropdown action-menu"><button class="btn icon-button" type="button" data-menu-button aria-expanded="false" aria-controls="' . e($id) . '" aria-label="' . e(uiLabel($label)) . '">&#8942;</button><div class="dropdown-menu" id="' . e($id) . '" data-menu hidden>';
    foreach ($items as $item) {
        if (isset($item['permission']) && !can($item['permission'])) continue;
        $class = !empty($item['danger']) ? ' dropdown-danger' : '';
        $html .= '<a class="dropdown-item' . $class . '" href="' . e($item['href'] ?? '#') . '">' . e(uiLabel($item['label'] ?? '')) . '</a>';
    }
    return $html . '</div></div>';
}

function flashMessages()
{
    $html = '';
    foreach (consumeFlashes() as $type => $messages) {
        foreach ($messages as $message) {
            $html .= '<div class="alert alert-' . e($type) . '" role="status"><span>' . e($message) . '</span><button type="button" class="alert-close icon-button" data-dismiss-alert aria-label="' . e(t('shell.dismiss_alert')) . '">&times;</button></div>';
        }
    }
    return $html;
}

function responsiveTableWrapper($tableHtml, $label = 'common.records')
{
    return '<div class="table-wrap" role="region" aria-label="' . e(uiLabel($label)) . '" tabindex="0">' . $tableHtml . '</div>';
}

function money($amount, $currency = null)
{
    return isolatedValue(localizedMoney($amount, $currency), 'money-value');
}
