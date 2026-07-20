<?php

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/validation.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/domain.php';
require_once __DIR__ . '/../app/i18n.php';

$failures = [];
$assert = function ($condition, $message) use (&$failures) {
    if (!$condition) $failures[] = $message;
};

$assert(validDateValue('2026-02-28') instanceof DateTimeImmutable, 'Valid date rejected');
$assert(validDateValue('2026-02-30') === null, 'Invalid date accepted');
$assert(passwordValidationErrors('weak') !== [], 'Weak password accepted');
$assert(passwordValidationErrors('StrongDemo!2026') === [], 'Strong password rejected');
$assert(in_array('confirmed', reservationTransitions()['pending'], true), 'Pending cannot transition to confirmed');
$assert(!in_array('active', reservationTransitions()['cancelled'], true), 'Cancelled reservation can transition');

$price = calculateRentalPrice([
    'pickup_at' => new DateTimeImmutable('2026-01-01 10:00:00'),
    'return_at' => new DateTimeImmutable('2026-01-04 10:00:00'),
    'daily_price' => '200.00',
    'options_total' => '50.00',
    'fees_total' => '0.00',
    'discount_percent' => 10,
    'tax_rate' => 20,
]);
$assert($price['days'] === 3, 'Rental day calculation failed');
$assert($price['total'] === '702.00', 'Price breakdown failed');

$protectedAreas = [
    'dashboard.view', 'agencies.view', 'users.manage', 'customers.manage',
    'vehicles.manage', 'reservations.manage', 'contracts.manage', 'payments.create',
    'payments.manage', 'invoices.manage', 'expenses.manage', 'inspections.manage',
    'maintenance.manage', 'pricing.manage', 'reports.view', 'reports.financial',
    'vehicle_documents.manage',
];

$_SESSION['role'] = ROLE_OWNER;
foreach ($protectedAreas as $permission) {
    $assert(can($permission), "Owner cannot access protected area: $permission");
}
$assert(canCreateAgency(), 'Owner cannot see the agency creation action');
$assert(canViewFinancialDashboard(), 'Owner cannot see financial dashboard metrics');
$assert(canViewFinanceHistory(), 'Owner cannot see payment history and deposits');
$assert(canViewInvoiceSections(), 'Owner cannot see invoice sections');

$_SESSION['role'] = ROLE_AGENCY_MANAGER;
$assert(can('pricing.manage'), 'Agency manager cannot manage pricing rules');
$assert(can('payments.manage'), 'Agency manager cannot manage finance');
$assert(!canCreateAgency(), 'Agency manager can see the owner-only agency creation action');
$assert(canViewFinancialDashboard(), 'Agency manager cannot see financial dashboard metrics');
$assert(canViewFinanceHistory(), 'Agency manager cannot see payment history and deposits');
$assert(canViewInvoiceSections(), 'Agency manager cannot see invoice sections');

$_SESSION['role'] = ROLE_RENTAL_AGENT;
$assert(can('payments.create'), 'Rental agent cannot create payments');
$assert(!can('payments.manage'), 'Rental agent can manage finance');
$assert(!can('pricing.manage'), 'Rental agent can manage pricing rules');
$assert(!canCreateAgency(), 'Rental agent can see the owner-only agency creation action');
$assert(!canViewFinancialDashboard(), 'Rental agent can see financial dashboard metrics');
$assert(!canViewFinanceHistory(), 'Rental agent can see payment history or deposits');
$assert(!canViewInvoiceSections(), 'Rental agent can see invoice sections');

$_SESSION['role'] = ROLE_ACCOUNTANT;
$assert(can('payments.manage'), 'Accountant cannot manage finance');
$assert(can('reports.view'), 'Accountant cannot access reports');
$assert(can('reports.financial'), 'Accountant cannot access financial reports');
$assert(!can('pricing.manage'), 'Accountant can manage pricing rules');
$assert(!canCreateAgency(), 'Accountant can see the owner-only agency creation action');
$assert(canViewFinancialDashboard(), 'Accountant cannot see financial dashboard metrics');
$assert(canViewFinanceHistory(), 'Accountant cannot see payment history and deposits');
$assert(canViewInvoiceSections(), 'Accountant cannot see invoice sections');

$_SESSION['role'] = ROLE_FLEET_AGENT;
$assert(!can('payments.create'), 'Fleet agent can create payments');
$assert(!can('payments.manage'), 'Fleet agent can manage finance');
$assert(!can('pricing.manage'), 'Fleet agent can manage pricing rules');
$assert(!canCreateAgency(), 'Fleet agent can see the owner-only agency creation action');
$assert(!canViewFinancialDashboard(), 'Fleet agent can see financial dashboard metrics');
$assert(!canViewFinanceHistory(), 'Fleet agent can see payment history or deposits');
$assert(!canViewInvoiceSections(), 'Fleet agent can see invoice sections');
unset($_SESSION['role']);

$requiredTranslationKeys = [
    'common.save','common.cancel','common.confirm','common.actions','shell.sign_out',
    'shell.open_navigation','nav.dashboard','nav.reservations','nav.customers',
    'nav.vehicles','nav.finance','nav.reports','role.owner','role.agency_manager',
    'role.rental_agent','role.accountant','role.fleet_agent','role.customer',
    'status.active','status.inactive','status.available','status.reserved','status.rented',
    'status.returned','status.pending','status.completed','status.cancelled','status.approved',
    'status.rejected','status.paid','status.failed','status.draft','status.generated',
    'status.sent','status.signed','status.expired','status.maintenance','status.damaged',
    'status.blocked','status.scheduled','status.in_progress','status.no_show',
];
$catalogues = [];
foreach (supportedLanguages() as $languageCode) {
    $catalogues[$languageCode] = translationCatalogue($languageCode);
    foreach ($requiredTranslationKeys as $key) {
        $assert(isset($catalogues[$languageCode][$key]) && is_string($catalogues[$languageCode][$key]) && $catalogues[$languageCode][$key] !== '', "$languageCode translation missing: $key");
    }
    foreach ($catalogues[$languageCode] as $key => $value) {
        $assert(is_string($value), "$languageCode translation is not a string: $key");
    }
}
$assert(array_keys($catalogues['en']) === array_keys($catalogues['fr']), 'French catalogue keys differ from English');
$assert(array_keys($catalogues['en']) === array_keys($catalogues['ar']), 'Arabic catalogue keys differ from English');

$_SESSION['lang'] = 'en';
$assert(t('message.contract_generated', ['id'=>42]) === 'Contract generated: #42', 'Translation placeholder interpolation failed');
$assert(t('missing.deep_key') === 'Deep key', 'Missing translation key did not produce a readable fallback');
$assert(e(t('message.contract_generated', ['id'=>'<script>alert(1)</script>'])) === 'Contract generated: #&lt;script&gt;alert(1)&lt;/script&gt;', 'Rendered translation parameter was not escaped safely');
$assert(translatedRole(ROLE_OWNER) === 'Owner', 'Owner role translation failed');
$assert(translatedStatus('in_progress') === 'In progress', 'Status translation failed');
$assert(localizedMoney('1250', 'MAD', 'en') === 'MAD 1,250.00', 'English money formatting failed');
$assert(localizedMoney('1250', 'MAD', 'fr') === '1 250,00 MAD', 'French money formatting failed');
$assert(localizedMoney('1250', 'MAD', 'ar') === '1,250.00 MAD', 'Arabic money formatting failed');
$assert(localizedDate('2026-07-17', 'en') === '17 Jul 2026', 'English date formatting failed');
$assert(localizedDate('2026-07-17', 'fr') === '17 juil. 2026', 'French date formatting failed');
$assert(localizedDate('2026-07-17', 'ar') === '17 يوليو 2026', 'Arabic date formatting failed');
$assert(localizedDateTime('2026-07-17 10:45:00', 'fr') === '17 juil. 2026 à 10:45', 'Localized date-time formatting failed');
$assert(localizedDate('not-a-date', 'en') === '' && localizedDateTime('', 'en') === '', 'Invalid date values were not handled safely');

$switch = languageSwitchUrl('ar', 'reports.php', ['page'=>'2','status'=>'paid','search'=>'Client A','date_from'=>'2026-07-01','agency_id'=>'3','_csrf'=>'secret','password'=>'secret','redirect'=>'https://example.test']);
parse_str((string) parse_url($switch, PHP_URL_QUERY), $switchQuery);
$assert(str_starts_with($switch, 'reports.php?'), 'Language switch changed the route');
$assert(($switchQuery['lang'] ?? null) === 'ar' && ($switchQuery['page'] ?? null) === '2' && ($switchQuery['agency_id'] ?? null) === '3', 'Language switch lost safe filters');
$assert(!isset($switchQuery['_csrf'], $switchQuery['password'], $switchQuery['redirect']), 'Language switch retained an unsafe value');
$unsafeSwitch = languageSwitchUrl('fr', '../evil.php', ['page'=>'-1','agency_id'=>'x','date_from'=>'invalid','status'=>'<script>']);
$assert(str_starts_with($unsafeSwitch, 'evil.php?') && !str_contains($unsafeSwitch, 'script'), 'Language switch did not reject invalid filter values');
unset($_SESSION['lang']);

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: $failure\n");
    exit(1);
}
echo "Business rule tests passed: domain rules, role permissions, visibility, translations, localization, and safe language switching.\n";
