<?php

function uiLabel($label)
{
    $label = (string) $label;
    $catalogue = translations();
    return array_key_exists($label, $catalogue) ? t($label) : $label;
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
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>';
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
    $labels = [
        ROLE_OWNER => 'Owner', ROLE_AGENCY_MANAGER => 'Agency manager',
        ROLE_RENTAL_AGENT => 'Rental agent', ROLE_ACCOUNTANT => 'Accountant',
        ROLE_FLEET_AGENT => 'Fleet agent', ROLE_CUSTOMER => 'Customer',
    ];
    $label = $labels[$role] ?? ucwords(strtolower(str_replace('_', ' ', $role)));
    return '<span class="role-badge role-' . e(strtolower(str_replace('_', '-', $role))) . '">' . e(uiLabel($label)) . '</span>';
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
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge badge-' . e($class) . '"><span class="badge-dot" aria-hidden="true"></span>' . e(uiLabel($label)) . '</span>';
}

function emptyState($title = 'No records found', $description = '', $action = '')
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
    if ($page > 1) $links[] = ['label'=>'Previous','page'=>$page - 1];
    if ($hasNext) $links[] = ['label'=>'Next','page'=>$page + 1];
    if (!$links) return '';
    $html = '<nav class="pagination" aria-label="Pagination">';
    foreach ($links as $link) {
        $href = $baseUrl . '?' . http_build_query(array_merge($query, ['page'=>$link['page']]));
        $html .= '<a class="btn quiet" href="' . e($href) . '">' . e(uiLabel($link['label'])) . '</a>';
    }
    return $html . '</nav>';
}

function actionMenu(array $items, $label = 'Actions')
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
            $html .= '<div class="alert alert-' . e($type) . '" role="status"><span>' . e($message) . '</span><button type="button" class="alert-close icon-button" data-dismiss-alert aria-label="Close">&times;</button></div>';
        }
    }
    return $html;
}

function responsiveTableWrapper($tableHtml, $label = 'Records')
{
    return '<div class="table-wrap" role="region" aria-label="' . e(uiLabel($label)) . '" tabindex="0">' . $tableHtml . '</div>';
}

function money($amount, $currency = null)
{
    return number_format((float) $amount, 2, '.', ' ') . ' ' . e($currency ?? appConfig('currency'));
}
